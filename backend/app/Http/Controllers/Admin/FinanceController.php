<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Wallet;
use App\Domain\Payments\PaymentService;
use App\Domain\Payments\RefundService;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\RechargePackage;
use App\Models\Refund;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/** Admin → Finance: overview, payments, refunds (maker/checker), disputes, findings, recharge packages. */
class FinanceController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function overview(Ledger $ledger): View
    {
        $this->authorize('finance.view');
        $system = DB::table('ledger_accounts')->whereNull('user_id')->orderBy('code')->get();
        $customerTotals = DB::table('ledger_accounts')->whereNotNull('user_id')->groupBy('kind')->selectRaw('kind, SUM(balance_minor) total')->pluck('total', 'kind');
        $expectedClearing = (int) Payment::where('status', 'captured')->sum('amount_minor')
            - (int) Refund::where('status', 'processed')->sum('amount_minor')
            - (int) Dispute::where('status', 'lost')->sum('amount_minor');

        return view('admin.finance.overview', [
            'system' => $system, 'customerTotals' => $customerTotals,
            'expectedClearing' => $expectedClearing, 'clearing' => $ledger->balance('gateway_clearing'),
            'findings' => DB::table('reconciliation_findings')->whereNull('resolved_at')->orderByRaw("FIELD(severity,'critical','warning','info')")->orderByDesc('id')->limit(50)->get(),
            'counts' => [
                'pendingRefunds' => Refund::where('status', 'requested')->count(),
                'openDisputes' => Dispute::whereIn('status', ['open', 'under_review'])->count(),
                'ambiguous' => Payment::where('status', 'ambiguous')->count(),
            ],
            'verify' => session('ledger_verify'),
        ]);
    }

    public function verify(Ledger $ledger): RedirectResponse
    {
        $this->authorize('finance.view');
        $r = $ledger->verify();
        $this->audit->record('ledger.verified', null, ['ok' => $r['ok'], 'problems' => count($r['problems'])]);

        return redirect()->route('admin.finance.overview')->with('ledger_verify', $r);
    }

    public function resolveFinding(Request $request, int $id): RedirectResponse
    {
        $this->authorize('reconciliation.manage');
        $note = $request->validate(['note' => ['required', 'string', 'max:500']])['note'];
        $n = DB::table('reconciliation_findings')->where('id', $id)->whereNull('resolved_at')->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution_note' => $note, 'updated_at' => now()]);
        abort_unless($n === 1, 404);
        $this->audit->record('finding.resolved', null, ['finding_id' => $id]);

        return back()->with('status', 'Finding marked as resolved.');
    }

    public function payments(Request $request): View
    {
        $this->authorize('finance.view');
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));
        $list = Payment::with('user')
            ->when(isset(Payment::STATUSES[$status]), fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('ref', $q)->orWhere('provider_order_id', $q)->orWhere('provider_payment_id', $q)))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.finance.payments', ['list' => $list, 'status' => $status, 'q' => $q]);
    }

    public function payment(Payment $payment, Wallet $wallet): View
    {
        $this->authorize('finance.view');

        return view('admin.finance.payment', [
            'p' => $payment->load('user', 'refunds.requester', 'refunds.decider'),
            'bal' => $wallet->balances($payment->user_id),
            'events' => DB::table('payment_events')->where('payment_id', $payment->id)->orderBy('id')->get(),
            'disputes' => Dispute::where('payment_id', $payment->id)->get(),
        ]);
    }

    public function recheck(Payment $payment, PaymentService $svc): RedirectResponse
    {
        $this->authorize('finance.view');
        $svc->reconcile($payment);
        $this->audit->record('payment.rechecked', $payment, ['status' => $payment->refresh()->status]);

        return back()->with('status', 'Checked with Razorpay. Current status: '.(Payment::STATUSES[$payment->status] ?? $payment->status).'.');
    }

    public function requestRefund(Request $request, Payment $payment, RefundService $svc): RedirectResponse
    {
        $this->authorize('refunds.request');
        $data = $request->validate(['amount' => ['required', 'string', 'max:20'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        try {
            $amount = Money::parseRupees($data['amount']);
            $svc->request($payment, (int) $amount, $data['reason'], $request->user());
        } catch (\InvalidArgumentException|RuntimeException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Refund requested. Someone with refund approval rights must approve it.');
    }

    public function refunds(Request $request): View
    {
        $this->authorize('finance.view');
        $status = $request->query('status', 'requested');

        return view('admin.finance.refunds', [
            'list' => Refund::with('payment', 'user', 'requester', 'decider')->when(isset(Refund::STATUSES[$status]), fn ($w) => $w->where('status', $status))->latest()->paginate(30)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function decideRefund(Request $request, Refund $refund, RefundService $svc): RedirectResponse
    {
        $this->authorize('refunds.approve');
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:500', 'required_if:decision,reject']]);
        try {
            if ($data['decision'] === 'approve') {
                $r = $svc->approve($refund, $request->user(), $data['note'] ?? null);
                $msg = match ($r->status) {
                    'processed' => 'Refund approved and processed by Razorpay.',
                    'failed' => 'Razorpay rejected the refund: '.$r->failure_reason.' The money is back in the customer’s wallet.',
                    default => 'Refund approved and sent to Razorpay. It will update automatically.',
                };
            } else {
                $svc->reject($refund, $request->user(), $data['note']);
                $msg = 'Refund request rejected.';
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', $msg);
    }

    public function disputes(): View
    {
        $this->authorize('finance.view');

        return view('admin.finance.disputes', ['list' => Dispute::with('payment', 'user')->latest()->paginate(30)]);
    }

    public function packages(): View
    {
        $this->authorize('finance.view');

        return view('admin.finance.packages', ['list' => RechargePackage::orderBy('sort')->orderBy('amount_minor')->get()]);
    }

    public function savePackage(Request $request): RedirectResponse
    {
        $this->authorize('prices.manage');
        $data = $request->validate([
            'ref' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'string', 'max:20'],
            'bonus' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['nullable', 'in:1'],
        ]);
        try {
            $amount = Money::parseRupees($data['amount']);
            $bonus = Money::parseRupees($data['bonus'] ?? null) ?? 0;
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }
        if ($amount < 100) {
            throw ValidationException::withMessages(['amount' => 'The amount must be at least ₹1.']);
        }
        if ($bonus > $amount) {
            throw ValidationException::withMessages(['bonus' => 'The bonus cannot be larger than the amount paid.']);
        }
        $pk = ! empty($data['ref']) ? RechargePackage::where('ref', $data['ref'])->firstOrFail() : new RechargePackage;
        $before = $pk->exists ? $pk->only(['name', 'amount_minor', 'bonus_minor', 'active']) : null;
        $pk->fill(['name' => $data['name'], 'amount_minor' => $amount, 'bonus_minor' => $bonus, 'sort' => (int) ($data['sort'] ?? 0),
            'active' => (bool) ($data['active'] ?? false), 'updated_by' => $request->user()->id])->save();
        $this->audit->record('recharge_package.saved', $pk, ['before' => $before, 'after' => $pk->only(['name', 'amount_minor', 'bonus_minor', 'active'])]);

        return back()->with('status', 'Recharge option saved. Existing payments keep the price they were started with.');
    }
}
