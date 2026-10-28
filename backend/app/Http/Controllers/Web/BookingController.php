<?php

namespace App\Http\Controllers\Web;

use App\Domain\Booking\Slots;
use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use App\Models\OfferingVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class BookingController extends Controller
{
    public function __construct(private Slots $slots) {}

    public function pick(Request $request, string $variantRef): View
    {
        $v = OfferingVariant::where('ref', $variantRef)->where('active', true)->firstOrFail();
        $o = $v->offering()->with('translations')->first();
        abort_unless($o && $o->status === 'active' && Slots::bookable($v) && $v->price_minor, 404);

        return view('account.booking.pick', ['v' => $v, 'o' => $o, 'slots' => $this->slots->available((int) $v->duration_min),
            'profiles' => BirthProfile::where('user_id', $request->user()->id)->orderByDesc('is_primary')->get(), 'reschedule' => null,
            'wallet' => app(\App\Domain\Ledger\Ledger::class)->walletBalance($request->user()->id)]);
    }

    public function hold(Request $request, string $variantRef): RedirectResponse
    {
        $v = OfferingVariant::where('ref', $variantRef)->where('active', true)->firstOrFail();
        $d = $request->validate(['start' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}Z$/'], 'birth_profile' => ['nullable', 'string', 'size:26'], 'topic' => ['nullable', 'string', 'max:1000'], 'use_wallet' => ['nullable', 'boolean'], 'key' => ['required', 'string', 'max:64']]);
        $bp = ! empty($d['birth_profile']) ? BirthProfile::where('user_id', $request->user()->id)->where('ref', $d['birth_profile'])->value('id') : null;
        try {
            [, $r] = $this->slots->hold($request->user(), $v, $d['start'], $bp, $d['topic'] ?? null, $d['key'], (bool) ($d['use_wallet'] ?? false));
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors(['start' => 'Online payment is not available right now. Please try again later or call us.']);
        }

        return redirect()->route('checkout.show', $r['order']->ref);
    }

    public function index(Request $request): View
    {
        return view('account.booking.index', ['bookings' => DB::table('bookings as b')->join('offering_variants as v', 'v.id', '=', 'b.variant_id')->join('offering_translations as t', fn ($j) => $j->on('t.offering_id', '=', 'v.offering_id')->where('t.locale', 'en'))
            ->where('b.user_id', $request->user()->id)->whereIn('b.status', ['confirmed', 'completed', 'cancelled', 'no_show'])->select('b.*', 'v.name as variant', 't.title')->orderByDesc('b.start_utc')->get()]);
    }

    public function cancel(Request $request, string $ref): RedirectResponse
    {
        $b = DB::table('bookings')->where('ref', $ref)->first();
        abort_unless($b && $b->user_id === $request->user()->id, 404);
        $this->slots->cancelByCustomer($b, $request->user());

        return redirect()->route('account.bookings')->with('status', 'Booking cancelled. The full amount has been added to your wallet.');
    }

    public function rescheduleForm(Request $request, string $ref): View
    {
        $b = DB::table('bookings')->where('ref', $ref)->first();
        abort_unless($b && $b->user_id === $request->user()->id, 404);
        $v = OfferingVariant::findOrFail($b->variant_id);

        return view('account.booking.pick', ['v' => $v, 'o' => $v->offering()->with('translations')->first(), 'slots' => $this->slots->available((int) $v->duration_min), 'profiles' => collect(), 'reschedule' => $b]);
    }

    public function reschedule(Request $request, string $ref): RedirectResponse
    {
        $b = DB::table('bookings')->where('ref', $ref)->first();
        abort_unless($b && $b->user_id === $request->user()->id, 404);
        $d = $request->validate(['start' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}Z$/']]);
        $this->slots->reschedule($b, $request->user(), $d['start']);

        return redirect()->route('account.bookings')->with('status', 'Booking moved to the new time.');
    }
}
