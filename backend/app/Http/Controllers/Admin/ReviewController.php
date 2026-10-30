<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reviews\Reviews;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin › Reviews: moderation queue, public replies, home-page picks, and reviews received offline. */
class ReviewController extends Controller
{
    public function __construct(private Reviews $reviews) {}

    public function index(Request $request): View
    {
        $this->authorize('content.review');
        $status = (string) $request->query('status', 'pending');
        $status = isset(Reviews::STATUSES[$status]) || $status === 'all' ? $status : 'pending';
        $rows = DB::table('reviews as r')->leftJoin('users as u', 'u.id', '=', 'r.user_id')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
            ->when($status !== 'all', fn ($q) => $q->where('r.status', $status))
            ->select('r.*', 'u.name as account_name', 'o.ref as order_ref')->orderByDesc('r.id')->paginate(25)->withQueryString();

        return view('admin.reviews.index', ['rows' => $rows, 'status' => $status, 'summary' => $this->reviews->summary(),
            'counts' => DB::table('reviews')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'offerings' => Offering::with('translations')->orderBy('sort')->get()->mapWithKeys(fn ($o) => [$o->id => $o->t('en')?->title ?? $o->slug])->all()]);
    }

    public function moderate(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('content.review');
        $r = DB::table('reviews')->where('ref', $ref)->first();
        abort_unless($r, 404);
        $d = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'hide', 'feature', 'unfeature', 'reply'])],
            'reason' => ['nullable', 'string', 'max:300'], 'reply' => ['nullable', 'string', 'max:1500']]);
        if (in_array($d['action'], ['feature', 'unfeature'], true)) {
            $this->authorize('content.publish');
        }
        $this->reviews->moderate($r, $request->user(), $d['action'], $d['reason'] ?? null, $request->has('reply') ? (string) ($d['reply'] ?? '') : null);

        return back()->with('status', match ($d['action']) {
            'approve' => 'Published.', 'reject' => 'Not published. The customer sees your reason.', 'hide' => 'Hidden from the website.',
            'feature' => 'Shown on the home page.', 'unfeature' => 'Removed from the home page.', default => 'Reply saved.',
        });
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('content.publish');
        $d = $request->validate(Reviews::rules() + ['offering_id' => ['nullable', 'exists:offerings,id'], 'permission_note' => ['required', 'string', 'max:300']],
            ['permission_note.required' => 'Write how the person agreed to publish (e.g. "WhatsApp message, 2 Nov 2026").']);
        $this->reviews->addByStaff($request->user(), $d);

        return redirect()->route('admin.reviews.index', ['status' => 'pending'])->with('status', 'Added to the queue. Approve it to publish.');
    }
}
