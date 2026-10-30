<?php

namespace App\Http\Controllers\Web;

use App\Domain\Payments\Checkout;
use App\Domain\Reviews\Reviews;
use App\Http\Controllers\Controller;
use App\Models\Offering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Public reviews page and the customer's own reviews (write, edit while waiting, delete). */
class ReviewController extends Controller
{
    public function __construct(private Reviews $reviews) {}

    public function index(Request $request): View
    {
        $offering = is_string($s = $request->query('service')) ? Offering::public()->where('slug', $s)->first() : null;
        $rating = in_array((int) $request->query('rating'), [1, 2, 3, 4, 5], true) ? (int) $request->query('rating') : null;

        return view('public.reviews', ['rows' => $this->reviews->published($offering?->id, $rating), 'summary' => $this->reviews->summary($offering?->id),
            'offering' => $offering, 'rating' => $rating,
            'services' => Offering::public()->with('translations')->whereIn('id', DB::table('reviews')->where('status', 'approved')->whereNotNull('offering_id')->distinct()->pluck('offering_id'))->get()]);
    }

    public function mine(Request $request): View
    {
        $u = $request->user();

        return view('account.reviews.index', ['reviews' => DB::table('reviews')->where('user_id', $u->id)->orderByDesc('id')->get(),
            'orders' => $this->reviews->reviewableOrders($u)->map(function ($o) {
                $o->label = Checkout::label($o->kind, json_decode($o->snapshot, true) ?: []);

                return $o;
            })]);
    }

    public function create(Request $request, string $orderRef): View
    {
        $o = DB::table('orders')->where('ref', $orderRef)->where('user_id', $request->user()->id)->first();
        abort_unless($o, 404);
        if ($existing = $this->reviews->reviewForOrder($o->id)) {
            return $this->edit($request, $existing->ref);
        }

        return view('account.reviews.form', ['order' => $o, 'label' => Checkout::label($o->kind, json_decode($o->snapshot, true) ?: []), 'review' => null,
            'suggested' => Reviews::suggestedName($request->user())]);
    }

    public function store(Request $request, string $orderRef): RedirectResponse
    {
        $d = $request->validate(Reviews::rules() + ['consent' => ['accepted']], ['consent.accepted' => 'Please agree that we may publish your review.']);
        $this->reviews->submit($request->user(), $orderRef, $d);

        return redirect()->route('account.reviews.index')->with('status', 'Thank you! Your review will appear once our team has checked it.');
    }

    private function own(Request $request, string $ref): object
    {
        $r = DB::table('reviews')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($r, 404);

        return $r;
    }

    public function edit(Request $request, string $ref): View
    {
        $r = $this->own($request, $ref);
        $o = $r->order_id ? DB::table('orders')->find($r->order_id) : null;

        return view('account.reviews.form', ['order' => $o, 'label' => $o ? Checkout::label($o->kind, json_decode($o->snapshot, true) ?: []) : $r->subject, 'review' => $r,
            'suggested' => $r->display_name]);
    }

    public function update(Request $request, string $ref): RedirectResponse
    {
        $this->reviews->edit($request->user(), $this->own($request, $ref), $request->validate(Reviews::rules()));

        return redirect()->route('account.reviews.index')->with('status', 'Updated. It will appear once our team has checked it.');
    }

    public function destroy(Request $request, string $ref): RedirectResponse
    {
        $this->reviews->deleteOwn($request->user(), $this->own($request, $ref));

        return redirect()->route('account.reviews.index')->with('status', 'Review deleted.');
    }
}
