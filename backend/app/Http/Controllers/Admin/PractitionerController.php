<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\StaffInvitations;
use App\Domain\Practitioners\Payouts;
use App\Domain\Practitioners\Practitioners;
use App\Domain\Practitioners\RazorpayX;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin › Practitioners: invite, review profiles, commission (maker/checker), earnings and payouts. */
class PractitionerController extends Controller
{
    public function __construct(private Practitioners $practitioners, private Payouts $payouts) {}

    private function find(string $ref): object
    {
        $p = DB::table('practitioners')->where('ref', $ref)->first();
        abort_unless($p, 404);

        return $p;
    }

    public function index(): View
    {
        $this->authorize('practitioners.view');

        return view('admin.practitioners.index', [
            'rows' => DB::table('practitioners as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', 'u.email', 'u.status as account_status')->orderBy('p.display_name')->get()
                ->each(function ($p) {
                    $p->share = Practitioners::currentShareBps($p->id);
                    $p->bookable = Practitioners::bookable($p);
                }),
            'invited' => DB::table('staff_invitations')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->where('roles', 'like', '%practitioner%')->get(),
            'pendingCommission' => DB::table('commission_rules')->where('status', 'proposed')->count(),
            'pendingPayouts' => DB::table('payouts')->whereIn('status', ['proposed', 'approved'])->count(),
        ]);
    }

    public function invite(Request $request, StaffInvitations $inv): RedirectResponse
    {
        $this->authorize('practitioners.manage');
        $d = $request->validate(['email' => ['required', 'email', 'max:190', 'unique:users,email'], 'name' => ['required', 'string', 'max:120']], ['email.unique' => 'This email already has an account.']);
        [, $token] = $inv->create($d['email'], $d['name'], ['practitioner'], $request->user());

        return back()->with('invite_link', url('/staff/invitation/'.$token));
    }

    public function show(string $ref): View
    {
        $this->authorize('practitioners.view');
        $p = $this->find($ref);

        return view('admin.practitioners.show', [
            'p' => $p, 'user' => DB::table('users')->find($p->user_id), 'share' => Practitioners::currentShareBps($p->id),
            'payout' => Practitioners::maskedPayout(Practitioners::payoutDetails($p)), 'rzxReady' => app(RazorpayX::class)->configured(),
            'rules' => DB::table('commission_rules')->where('practitioner_id', $p->id)->orderByDesc('id')->get(),
            'balances' => $this->payouts->balances($p->id),
            'payoutRows' => DB::table('payouts')->where('practitioner_id', $p->id)->orderByDesc('id')->limit(20)->get(),
            'bookings' => DB::table('bookings')->where('practitioner_id', $p->id)->orderByDesc('start_utc')->limit(20)->get(),
            'hours' => DB::table('availability_rules')->where('resource_key', $p->id)->orderBy('weekday')->get(),
        ]);
    }

    public function review(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('practitioners.review');
        $d = $request->validate(['decision' => ['required', Rule::in(['approve', 'return', 'suspend'])], 'tier' => ['required', Rule::in(array_keys(Practitioners::TIERS))]]);
        $this->practitioners->review($this->find($ref), $d['decision'], $d['tier'], $request->user());

        return back()->with('status', ['approve' => 'Profile approved.', 'return' => 'Profile returned to the astrologer for changes.', 'suspend' => 'Profile suspended. They no longer appear for booking.'][$d['decision']]);
    }

    public function proposeCommission(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('commission.propose');
        $d = $request->validate(['share_percent' => ['required', 'numeric', 'between:10,90'], 'effective_from' => ['required', 'date_format:Y-m-d'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->practitioners->proposeCommission($this->find($ref), (int) round($d['share_percent'] * 100), $d['effective_from'], $d['note'] ?? null, $request->user());

        return back()->with('status', 'Commission proposed. A different person with approval rights must approve it.');
    }

    public function decideCommission(Request $request, int $id): RedirectResponse
    {
        $this->authorize('commission.approve');
        $d = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $this->practitioners->decideCommission($id, $d['decision'] === 'approve', $request->user());

        return back()->with('status', $d['decision'] === 'approve' ? 'Commission approved.' : 'Commission rejected.');
    }

    public function proposePayout(Request $request, string $ref): RedirectResponse
    {
        $this->authorize('payouts.propose');
        $po = $this->payouts->propose($this->find($ref), $request->user());

        return back()->with('status', 'Payout of '.Money::format((int) $po->amount_minor).' proposed. A different person must approve it before you transfer the money.');
    }

    public function payoutAction(Request $request, string $payoutRef): RedirectResponse
    {
        $po = DB::table('payouts')->where('ref', $payoutRef)->first();
        abort_unless($po, 404);
        $a = $request->validate(['action' => ['required', Rule::in(['approve', 'paid', 'cancel', 'send'])]])['action'];
        if ($a === 'send') {
            $this->authorize('payouts.propose');
            $sent = app(RazorpayX::class)->send($po, $request->user());

            return back()->with('status', $sent->status === 'paid' ? 'Payout sent and confirmed by RazorpayX (UTR '.$sent->utr.').' : 'Payout sent to RazorpayX ('.$sent->provider_status.'). It is marked paid automatically when the bank confirms.');
        }
        if ($a === 'approve') {
            $this->authorize('payouts.approve');
            $this->payouts->approve($po, $request->user());
        } elseif ($a === 'paid') {
            $this->authorize('payouts.propose');
            $utr = $request->validate(['utr' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6,30}$/']], ['utr.regex' => 'Enter the bank reference (UTR) of the transfer, 6–30 letters/digits.'])['utr'];
            $this->payouts->markPaid($po, strtoupper($utr), $request->user());
        } else {
            $this->authorize('payouts.propose');
            $this->payouts->cancel($po, $request->user());
        }

        return back()->with('status', ['approve' => 'Payout approved. Transfer the money from the bank, then record the UTR.', 'paid' => 'Payout recorded as paid.', 'cancel' => 'Payout cancelled; the earnings are available again.'][$a]);
    }
}
