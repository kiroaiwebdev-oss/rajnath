<?php

namespace App\Domain\Puja;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Uploads\SafeImage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Puja & Chadhava booking and fulfilment.
 *
 *   awaiting_details ─(customer gives sankalp)→ requested ─(staff assigns authorised priest/temple
 *   and time)→ scheduled ─(performed, photo/video proof uploaded)→ performed ─(verified)→ completed
 *   any open state ─→ cancelled (refund via Finance)
 *
 * Only suppliers with a verified, unexpired authorisation and linked to the offering can be
 * assigned. Sankalp details are encrypted; proof files are private and shown only to the customer
 * and fulfilment staff. No outcome is promised.
 */
class PujaBookings
{
    public const DISK = 'local';
    public const SELF_PERFORMED = 'puja-rituals'; // performed personally by Acharya Ji

    public function __construct(private AuditLogger $audit) {}

    public static function supplierOk(object $s): bool
    {
        return $s->active && $s->verified_at && $s->authorization_doc && (! $s->authorized_until || $s->authorized_until >= now()->toDateString());
    }

    /** True when an offering may be published (has at least one valid supplier, or is self-performed). */
    public static function canPublish(int $offeringId, string $slug): bool
    {
        if ($slug === self::SELF_PERFORMED) {
            return true;
        }

        return DB::table('suppliers')->join('offering_supplier', 'offering_supplier.supplier_id', '=', 'suppliers.id')
            ->where('offering_supplier.offering_id', $offeringId)->get()->contains(fn ($s) => self::supplierOk($s));
    }

    public function onOrderPaid(object $order, array $snap): void
    {
        DB::table('puja_bookings')->insertOrIgnore([
            'ref' => (string) Str::ulid(), 'user_id' => $order->user_id, 'order_id' => $order->id,
            'offering_id' => DB::table('offerings')->where('ref', $snap['offering']['ref'])->value('id'),
            'status' => 'awaiting_details', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function sankalpRules(): array
    {
        return [
            'devotees' => ['required', 'array', 'min:1', 'max:8'],
            'devotees.*.name' => ['required', 'string', 'max:120'],
            'devotees.*.relation' => ['nullable', 'string', 'max:40'],
            'gotra' => ['nullable', 'string', 'max:80'],
            'wish' => ['nullable', 'string', 'max:500'],
            'location' => ['required', 'in:online,home,temple'],
            'preferred_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'address' => ['required_if:location,home', 'nullable', 'string', 'max:400'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?91|0)?[6-9]\d{9}$|^\+[1-9]\d{7,14}$/'],
        ];
    }

    public function submitSankalp(object $b, User $u, array $d): void
    {
        abort_unless($b->user_id === $u->id, 404);
        if (! in_array($b->status, ['awaiting_details', 'requested'], true)) {
            throw ValidationException::withMessages(['devotees' => 'Details can no longer be changed. Please contact support.']);
        }
        $sankalp = ['devotees' => array_values(array_map(fn ($x) => ['name' => trim($x['name']), 'relation' => $x['relation'] ?? null], $d['devotees'])),
            'gotra' => $d['gotra'] ?? null, 'wish' => $d['wish'] ?? null, 'address' => $d['address'] ?? null, 'phone' => $d['phone']];
        DB::table('puja_bookings')->where('id', $b->id)->update([
            'sankalp' => Crypt::encryptString(json_encode($sankalp, JSON_UNESCAPED_UNICODE)), 'location' => $d['location'],
            'preferred_date' => $d['preferred_date'] ?? null, 'status' => 'requested', 'updated_at' => now(),
        ]);
        $this->audit->record('puja.sankalp_submitted', null, ['booking' => $b->ref], $u);
    }

    public static function sankalp(object $b): ?array
    {
        return $b->sankalp ? json_decode(Crypt::decryptString($b->sankalp), true) : null;
    }

    public function schedule(object $b, int $supplierId, string $localDateTime, ?string $liveLink, ?string $note, User $staff): void
    {
        if (! in_array($b->status, ['requested', 'scheduled'], true)) {
            throw ValidationException::withMessages(['scheduled_at' => 'This booking cannot be scheduled now.']);
        }
        $s = DB::table('suppliers')->find($supplierId);
        $linked = DB::table('offering_supplier')->where('offering_id', $b->offering_id)->where('supplier_id', $supplierId)->exists();
        if (! $s || ! $linked || ! self::supplierOk($s)) {
            throw ValidationException::withMessages(['supplier' => 'Choose a priest/temple that is authorised and linked to this puja.']);
        }
        $at = CarbonImmutable::parse($localDateTime, 'Asia/Kolkata');
        if ($at->isPast()) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a future date and time.']);
        }
        DB::table('puja_bookings')->where('id', $b->id)->update(['supplier_id' => $s->id, 'scheduled_at' => $at->utc(), 'live_link' => $liveLink, 'customer_note' => $note, 'status' => 'scheduled', 'updated_at' => now()]);
        $this->audit->record('puja.scheduled', null, ['booking' => $b->ref, 'supplier' => $s->ref], $staff);
        $this->notify($b, 'Your puja is scheduled', 'Your puja is scheduled for '.$at->format('d M Y, g:i A').' IST.'.($liveLink ? ' Join online: '.$liveLink : ''));
    }

    /** @param UploadedFile[] $photos */
    public function markPerformed(object $b, array $photos, ?string $videoUrl, ?string $note, User $staff): void
    {
        if ($b->status !== 'scheduled') {
            throw ValidationException::withMessages(['photos' => 'Only scheduled pujas can be marked performed.']);
        }
        if (! $photos) {
            throw ValidationException::withMessages(['photos' => 'Upload at least one photo from the puja as proof.']);
        }
        $paths = [];
        foreach (array_slice($photos, 0, 10) as $i => $f) {
            $paths[] = SafeImage::store($f, self::DISK, 'puja/'.$b->ref, 'photos');
        }
        DB::table('puja_bookings')->where('id', $b->id)->update(['evidence' => json_encode(['photos' => $paths, 'video_url' => $videoUrl]),
            'customer_note' => $note ?? $b->customer_note, 'status' => 'performed', 'updated_at' => now()]);
        $this->audit->record('puja.performed', null, ['booking' => $b->ref, 'photos' => count($paths)], $staff);
    }

    public function complete(object $b, User $staff): void
    {
        if ($b->status !== 'performed') {
            throw ValidationException::withMessages(['status' => 'Mark the puja performed with proof first.']);
        }
        DB::table('puja_bookings')->where('id', $b->id)->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
        $this->audit->record('puja.completed', null, ['booking' => $b->ref], $staff);
        $this->notify($b, 'Your puja has been performed', 'Your puja has been performed. Photos are available in your account.');
    }

    public function cancel(object $b, string $reason, User $staff): void
    {
        if (in_array($b->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['reason' => 'Already closed.']);
        }
        DB::table('puja_bookings')->where('id', $b->id)->update(['status' => 'cancelled', 'customer_note' => $reason, 'updated_at' => now()]);
        $this->audit->record('puja.cancelled', null, ['booking' => $b->ref], $staff);
    }

    private function notify(object $b, string $subject, string $body): void
    {
        $u = User::find($b->user_id);
        if (! $u?->email) {
            return;
        }
        try {
            Mail::raw($body."\n\n".route('account.puja.show', $b->ref), fn ($m) => $m->to($u->email)->subject($subject));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function storeAuthorization(UploadedFile $f): string
    {
        $ext = strtolower($f->getClientOriginalExtension());
        $mime = $f->getMimeType();
        if ($f->getSize() > 10 * 1024 * 1024 || ! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true) || ! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw ValidationException::withMessages(['authorization' => 'Upload the signed authorisation as PDF, JPG or PNG (max 10 MB).']);
        }
        if ($mime === 'application/pdf') {
            if (! str_starts_with((string) file_get_contents($f->getRealPath(), false, null, 0, 5), '%PDF-')) {
                throw ValidationException::withMessages(['authorization' => 'This is not a valid PDF.']);
            }
            $path = 'suppliers/'.Str::ulid().'.pdf';
            Storage::disk(self::DISK)->put($path, file_get_contents($f->getRealPath()));

            return $path;
        }

        return SafeImage::store($f, self::DISK, 'suppliers', 'authorization');
    }
}
