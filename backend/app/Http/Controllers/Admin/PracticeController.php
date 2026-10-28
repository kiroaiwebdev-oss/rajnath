<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Slots;
use App\Domain\Practitioners\Payouts;
use App\Domain\Practitioners\Practitioners;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** "My practice" for a team astrologer: own profile, hours, bookings, earnings and payouts only. */
class PracticeController extends Controller
{
    public function __construct(private Practitioners $practitioners) {}

    private function me(Request $r): object
    {
        abort_unless($r->user()->hasRole('practitioner'), 403);

        return $this->practitioners->ensureProfile($r->user());
    }

    public function show(Request $r, Payouts $payouts): View
    {
        $p = $this->me($r);

        return view('admin.practice.show', [
            'p' => $p, 'share' => Practitioners::currentShareBps($p->id), 'payout' => Practitioners::maskedPayout(Practitioners::payoutDetails($p)),
            'hours' => DB::table('availability_rules')->where('resource_key', $p->id)->orderBy('weekday')->orderBy('start_local')->get(),
            'leave' => DB::table('availability_exceptions')->where('resource_key', $p->id)->where('date', '>=', now('Asia/Kolkata')->toDateString())->orderBy('date')->get(),
            'bookings' => DB::table('bookings as b')->join('users as u', 'u.id', '=', 'b.user_id')->where('b.practitioner_id', $p->id)->whereIn('b.status', ['confirmed', 'completed', 'no_show'])
                ->where('b.start_utc', '>=', now()->subDays(14))->orderBy('b.start_utc')->select('b.*', 'u.name as customer')->limit(60)->get(),
            'balances' => $payouts->balances($p->id),
            'payoutRows' => DB::table('payouts')->where('practitioner_id', $p->id)->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function saveProfile(Request $r): RedirectResponse
    {
        $p = $this->me($r);
        $d = $r->validate(['display_name' => ['required', 'string', 'max:120'], 'bio' => ['required', 'string', 'min:60', 'max:2000'], 'languages' => ['nullable', 'string', 'max:160'],
            'specialties' => ['nullable', 'string', 'max:255'], 'experience_years' => ['nullable', 'integer', 'between:0,70'], 'photo' => ['nullable', 'file', 'max:6144'], 'submit' => ['nullable', 'boolean']]);
        $this->practitioners->saveProfile($p, $r->user(), $d, $r->file('photo'), (bool) ($d['submit'] ?? false));

        return back()->with('status', ($d['submit'] ?? false) ? 'Profile sent for review. You appear for booking after approval.' : 'Profile saved.');
    }

    public function savePayout(Request $r): RedirectResponse
    {
        $p = $this->me($r);
        $d = $r->validate(['method' => ['required', Rule::in(['upi', 'bank'])], 'upi' => ['required_if:method,upi', 'nullable', 'regex:/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/'],
            'account_name' => ['required_if:method,bank', 'nullable', 'string', 'max:120'], 'account_number' => ['required_if:method,bank', 'nullable', 'regex:/^\d{9,18}$/'],
            'ifsc' => ['required_if:method,bank', 'nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/']],
            ['upi.regex' => 'Enter a UPI ID like name@bank.', 'account_number.regex' => 'Account number: 9–18 digits.', 'ifsc.regex' => 'IFSC looks like SBIN0001234.']);
        $this->practitioners->savePayoutDetails($p, $r->user(), $d);

        return back()->with('status', 'Payout details saved. Finance will see the change on your next payout.');
    }

    public function saveHours(Request $r): RedirectResponse
    {
        $p = $this->me($r);
        $d = $r->validate(['weekday' => ['required', 'integer', 'between:0,6'], 'start_local' => ['required', 'date_format:H:i'], 'end_local' => ['required', 'date_format:H:i', 'after:start_local'], 'slot_minutes' => ['required', Rule::in([15, 30, 45, 60])]]);
        DB::table('availability_rules')->insert($d + ['resource_key' => $p->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Working hours added.');
    }

    public function deleteHours(Request $r, int $id): RedirectResponse
    {
        $p = $this->me($r);
        abort_unless(DB::table('availability_rules')->where('id', $id)->where('resource_key', $p->id)->delete(), 404);

        return back()->with('status', 'Removed. Existing bookings are not affected.');
    }

    public function addLeave(Request $r): RedirectResponse
    {
        $p = $this->me($r);
        $d = $r->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', Rule::unique('availability_exceptions')->where('resource_key', $p->id)], 'reason' => ['nullable', 'string', 'max:120']]);
        DB::table('availability_exceptions')->insert($d + ['resource_key' => $p->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Leave day added.');
    }

    public function updateBooking(Request $r, string $ref, Slots $slots): RedirectResponse
    {
        $p = $this->me($r);
        $b = DB::table('bookings')->where('ref', $ref)->where('practitioner_id', $p->id)->first();
        abort_unless($b, 404);
        $d = $r->validate(['status' => ['nullable', Rule::in(['completed', 'no_show'])], 'meeting_link' => ['nullable', 'url:https', 'max:255'], 'provider' => ['nullable', Rule::in(['livekit', 'zoom', 'google_meet'])]]);
        if (! empty($d['provider'])) {
            return \App\Http\Controllers\Admin\OperationsController::switchProvider($b, $d['provider'], $r->user());
        }
        if (($d['status'] ?? null) && \Carbon\CarbonImmutable::parse($b->start_utc, 'UTC')->isFuture()) {
            return back()->withErrors(['status' => 'You can close a session only after its start time.']);
        }
        $slots->setOutcome($b, $d['status'] ?? 'link', $d['meeting_link'] ?? null, $r->user());

        return back()->with('status', 'Booking updated.');
    }

    public function statement(Request $r): StreamedResponse
    {
        $p = $this->me($r);
        $rows = DB::table('practitioner_earnings as e')->join('bookings as b', 'b.id', '=', 'e.booking_id')->leftJoin('payouts as po', 'po.id', '=', 'e.payout_id')
            ->where('e.practitioner_id', $p->id)->orderBy('e.id')->get(['b.ref', 'b.start_utc', 'e.gross_minor', 'e.share_bps', 'e.amount_minor', 'e.status', 'po.ref as payout', 'po.utr']);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['booking', 'session_utc', 'gross_inr', 'share_percent', 'earning_inr', 'status', 'payout', 'utr']);
            foreach ($rows as $x) {
                fputcsv($out, [$x->ref, $x->start_utc, number_format($x->gross_minor / 100, 2, '.', ''), $x->share_bps / 100, number_format($x->amount_minor / 100, 2, '.', ''), $x->status, $x->payout, $x->utr]);
            }
            fclose($out);
        }, 'earnings-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store']);
    }
}
