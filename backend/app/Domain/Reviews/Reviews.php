<?php

namespace App\Domain\Reviews;

use App\Domain\Notify\Notifier;
use App\Models\Offering;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer reviews. A signed-in customer can review each paid order once ("verified"); staff can
 * also add a review received offline when the person has given permission (recorded in
 * permission_note). Nothing is public until a moderator approves it. Customers see the reason if a
 * review is not published, can edit it while pending, and can delete it at any time; deleting the
 * account deletes the reviews.
 */
class Reviews
{
    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Published', 'rejected' => 'Not published', 'hidden' => 'Hidden'];

    public const PAID = ['paid', 'partially_refunded'];

    private const CACHE = 'reviews.summary.v1';

    public function __construct(private AuditLogger $audit, private Notifier $notifier) {}

    /** @return array{rating: int, title: ?string, body: string, display_name: string, city: ?string} */
    public static function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'display_name' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** Shown name: "Asha D." from "Asha Devi" (customers can change it before sending). */
    public static function suggestedName(User $u): string
    {
        $parts = preg_split('/\s+/', trim((string) $u->name)) ?: [];
        $first = $parts[0] ?? 'Customer';

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $first;
    }

    /** Paid orders of the customer that have no review yet. */
    public function reviewableOrders(User $u): Collection
    {
        return DB::table('orders as o')->leftJoin('reviews as r', 'r.order_id', '=', 'o.id')->where('o.user_id', $u->id)
            ->whereIn('o.kind', ['service', 'product'])->whereIn('o.status', self::PAID)->whereNull('r.id')
            ->orderByDesc('o.id')->limit(50)->get(['o.*']);
    }

    public function reviewForOrder(int $orderId): ?object
    {
        return DB::table('reviews')->where('order_id', $orderId)->first();
    }

    /** @param array{rating: int, title?: ?string, body: string, display_name: string, city?: ?string} $d */
    public function submit(User $u, string $orderRef, array $d): object
    {
        $o = DB::table('orders')->where('ref', $orderRef)->where('user_id', $u->id)->first();
        if (! $o || ! in_array($o->status, self::PAID, true) || ! in_array($o->kind, ['service', 'product'], true)) {
            throw ValidationException::withMessages(['order' => 'You can review a service or product after paying for it.']);
        }
        if ($this->reviewForOrder($o->id)) {
            throw ValidationException::withMessages(['order' => 'You have already reviewed this order.']);
        }
        $snap = json_decode($o->snapshot, true) ?: [];
        $offeringId = $o->kind === 'service' ? Offering::where('slug', $snap['offering']['slug'] ?? '')->value('id') : null;
        $productId = $o->kind === 'product' ? DB::table('products')->where('id', (int) ($snap['product']['id'] ?? 0))->value('id') : null;
        $subject = $o->kind === 'service' ? ($snap['offering']['title'] ?? null) : ($snap['product']['name'] ?? null);
        $id = DB::table('reviews')->insertGetId(['ref' => (string) Str::ulid(), 'user_id' => $u->id, 'order_id' => $o->id, 'offering_id' => $offeringId, 'product_id' => $productId,
            'subject' => $subject ? mb_substr($subject, 0, 160) : null] + $this->clean($d) + ['locale' => app()->getLocale(), 'status' => 'pending', 'source' => 'customer', 'verified' => true,
                'consented_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('review.submitted', $u, ['review' => $id, 'order' => $o->ref, 'rating' => (int) $d['rating']], $u);

        return DB::table('reviews')->find($id);
    }

    private function clean(array $d): array
    {
        $t = fn (?string $v) => ($v = trim(strip_tags((string) $v))) === '' ? null : $v;

        return ['rating' => (int) $d['rating'], 'title' => $t($d['title'] ?? null), 'body' => (string) $t($d['body']), 'display_name' => (string) $t($d['display_name']), 'city' => $t($d['city'] ?? null)];
    }

    /** The customer edits a review that is still waiting (or was not published); it goes back to the queue. */
    public function edit(User $u, object $r, array $d): void
    {
        if ((int) $r->user_id !== $u->id || ! in_array($r->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages(['body' => 'This review can no longer be changed. You can delete it and write a new one.']);
        }
        DB::table('reviews')->where('id', $r->id)->update($this->clean($d) + ['status' => 'pending', 'reject_reason' => null, 'updated_at' => now()]);
        self::forget();
    }

    public function deleteOwn(User $u, object $r): void
    {
        abort_unless((int) $r->user_id === $u->id, 404);
        DB::table('reviews')->where('id', $r->id)->delete();
        self::forget();
        $this->audit->record('review.deleted_by_customer', $u, ['review' => $r->ref], $u);
    }

    /** Staff add a review received by phone, letter or WhatsApp, with the person's permission. */
    public function addByStaff(User $staff, array $d): object
    {
        $id = DB::table('reviews')->insertGetId(['ref' => (string) Str::ulid(), 'offering_id' => $d['offering_id'] ?? null, 'subject' => isset($d['offering_id']) ? Offering::find($d['offering_id'])?->t('en')?->title : null]
            + $this->clean($d) + ['locale' => 'en', 'status' => 'pending', 'source' => 'staff', 'verified' => false, 'permission_note' => $d['permission_note'],
                'consented_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('review.added_by_staff', null, ['review' => $id, 'permission' => $d['permission_note']]);

        return DB::table('reviews')->find($id);
    }

    /** approve | reject | hide | feature | unfeature, with an optional public reply. */
    public function moderate(object $r, User $staff, string $action, ?string $reason = null, ?string $reply = null): void
    {
        $row = ['moderated_by' => $staff->id, 'moderated_at' => now(), 'updated_at' => now()];
        $row += match ($action) {
            'approve' => ['status' => 'approved', 'reject_reason' => null, 'published_at' => $r->published_at ?? now()],
            'reject' => ['status' => 'rejected', 'reject_reason' => $reason, 'featured' => false],
            'hide' => ['status' => 'hidden', 'featured' => false],
            'feature' => ['featured' => true],
            'unfeature' => ['featured' => false],
            'reply' => [],
        };
        if ($action === 'feature' && $r->status !== 'approved') {
            throw ValidationException::withMessages(['action' => 'Publish the review before showing it on the home page.']);
        }
        if ($action === 'reject' && ! $reason) {
            throw ValidationException::withMessages(['reason' => 'Tell the customer why (they will see this).']);
        }
        if ($reply !== null) {
            $reply = trim($reply);
            $row += ['reply' => $reply === '' ? null : $reply, 'replied_at' => $reply === '' ? null : now()];
        }
        DB::table('reviews')->where('id', $r->id)->update($row);
        self::forget();
        $this->audit->record('review.'.$action, null, ['review' => $r->ref, 'reason' => $reason]);
        if ($r->user_id && in_array($action, ['approve', 'reject'], true) && $r->status !== $row['status']) {
            $this->notifier->send((int) $r->user_id, 'review', $action === 'approve' ? 'Your review is published' : 'Your review was not published',
                $action === 'approve' ? 'Thank you for sharing your experience.' : 'Reason: '.$reason.' You can edit it and send it again.', $r->ref, route('account.reviews.index'));
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE);
    }

    /** Published reviews, newest first. */
    public function published(?int $offeringId = null, ?int $rating = null, int $perPage = 12): LengthAwarePaginator
    {
        return DB::table('reviews')->where('status', 'approved')->when($offeringId, fn ($q) => $q->where('offering_id', $offeringId))
            ->when($rating, fn ($q) => $q->where('rating', $rating))->orderByDesc('featured')->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function featured(int $limit = 3): Collection
    {
        $f = DB::table('reviews')->where('status', 'approved')->where('featured', true)->orderByDesc('published_at')->limit($limit)->get();

        return $f->isNotEmpty() ? $f : DB::table('reviews')->where('status', 'approved')->where('rating', '>=', 4)->orderByDesc('published_at')->limit($limit)->get();
    }

    public function forOffering(int $offeringId, int $limit = 6): Collection
    {
        return DB::table('reviews')->where('status', 'approved')->where('offering_id', $offeringId)->orderByDesc('featured')->orderByDesc('published_at')->limit($limit)->get();
    }

    /** @return array{count: int, average: ?float, stars: array<int, int>} */
    public function summary(?int $offeringId = null): array
    {
        $rows = Cache::remember(self::CACHE, 600, fn () => DB::table('reviews')->where('status', 'approved')
            ->selectRaw('offering_id, rating, count(*) as n')->groupBy('offering_id', 'rating')->get()->map(fn ($r) => (array) $r)->all());
        $rows = collect($rows)->when($offeringId, fn ($c) => $c->where('offering_id', $offeringId));
        $stars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $r) {
            $stars[(int) $r['rating']] += (int) $r['n'];
        }
        $count = array_sum($stars);

        return ['count' => $count, 'average' => $count ? round(array_sum(array_map(fn ($s, $n) => $s * $n, array_keys($stars), $stars)) / $count, 1) : null, 'stars' => $stars];
    }

    public static function toApi(object $r): array
    {
        return ['ref' => $r->ref, 'rating' => (int) $r->rating, 'title' => $r->title, 'body' => $r->body, 'name' => $r->display_name, 'city' => $r->city,
            'subject' => $r->subject, 'verified' => (bool) $r->verified, 'reply' => $r->reply,
            'date' => substr((string) ($r->published_at ?? $r->created_at), 0, 10)];
    }

    /** The customer's own view of a review (includes status and the reason if it was not published). */
    public static function toOwnerApi(object $r): array
    {
        return self::toApi($r) + ['status' => $r->status, 'status_label' => self::STATUSES[$r->status] ?? $r->status, 'reject_reason' => $r->reject_reason,
            'editable' => in_array($r->status, ['pending', 'rejected'], true)];
    }
}
