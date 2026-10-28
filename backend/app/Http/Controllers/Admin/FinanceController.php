<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Razorpay;
use App\Domain\Payments\Reconciler;
use App\Domain\Payments\Refunds;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, Ledger $ledger): View
    {
        $this->authorize('finance.view');
        $status = (string) $request->query('status', '');

        return view('admin.finance.index', [
            'orders' => DB::table('orders')->join('users', 'users.id', '=', 'orders.user_id')->select('orders.*', 'users.name as customer')
                ->when($status !== '', fn ($q) => $q->where('orders.status', $status))->orderByDesc('orders.id')->paginate(30)->withQueryString(),
            'status' => $status,
            'totals' => [
                'Revenue (net)' => (int) (DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor') ?? 0),
                'Customer wallets (owed)' => (int) DB::table('ledger_accounts')->where('code', 'like', 'customer_wallet:%')->sum('balance_minor'),
                'Gateway clearing' => (int) (DB::table('ledger_accounts')->where('code', 'gateway_clearing')->value('balance_minor') ?? 0),
                'Refunds in flight' => (int) (DB::table('ledger_accounts')->where('code', 'refunds_payable')->value('balance_minor') ?? 0),
            ],
            'refunds' => DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id')->select('refunds.*', 'orders.ref as order_ref')->orderByDesc('refunds.id')->limit(20)->get(),
            'findings' => DB::table('reconciliation_findings')->whereNull('resolved_at')->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function order(string $ref): View
    {
        $this->authorize('finance.view');
        $o = DB::table('orders')->where('ref', $ref)->first();
        abort_unless($o, 404);

        return view('admin.finance.order', ['o' => $o, 'snap' => json_decode($o->snapshot, true), 'payments' => DB::table('payments')->where('order_id', $o->id)->get(),
            'refunds' => DB::table('refunds')->where('order_id', $o->id)->get(), 'customer' => DB::table('users')->find($o->user_id)]);
    }

    public function requestRefund(Request $request, string $ref, Refunds $refunds): RedirectResponse
    {
        $this->authorize('refunds.request');
        $o = DB::table('orders')->where('ref', $ref)->first();
        abort_unless($o, 404);
        $d = $request->validate(['amount' => ['required', 'string', 'max:20'], 'destination' => ['required', Rule::in(['source', 'wallet'])], 'reason' => ['required', 'string', 'max:300']]);
        try {
            $amt = Money::parseRupees($d['amount']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }
        $refunds->request($o, (int) $amt, $d['destination'], $d['reason'], $request->user());

        return back()->with('status', 'Refund requested. A different finance approver must approve it.');
    }

    public function decideRefund(Request $request, string $ref, Refunds $refunds): RedirectResponse
    {
        $this->authorize('refunds.approve');
        $approve = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]])['decision'] === 'approve';
        $r = $refunds->decide($ref, $approve, $request->user());

        return back()->with('status', 'Refund '.$r->status.($r->error ? ': '.$r->error : '').'.');
    }

    public function reconcile(Reconciler $rec, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('reconciliation.manage');
        $s = $rec->run();
        $audit->record('finance.reconcile_run', null, $s);

        return back()->with('status', "Reconciliation: checked {$s['checked']}, applied {$s['applied']}, cancelled {$s['cancelled']}, ledger problems {$s['ledger_problems']}.");
    }

    public function resolveFinding(int $id, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('reconciliation.manage');
        DB::table('reconciliation_findings')->where('id', $id)->update(['resolved_at' => now(), 'updated_at' => now()]);
        $audit->record('finance.finding_resolved', null, ['finding' => $id]);

        return back()->with('status', 'Marked resolved.');
    }

    // ---- Payment provider + recharge packages (owner-level, step-up) ----

    public function settings(SettingsRepository $s, Razorpay $rzp): View
    {
        $this->authorize('providers.manage');

        return view('admin.finance.settings', ['keyId' => $s->get('payments.razorpay.key_id'), 'secretMasked' => $s->masked('payments.razorpay.key_secret'),
            'webhookMasked' => $s->masked('payments.razorpay.webhook_secret'), 'live' => (bool) $s->get('payments.live_enabled'), 'mode' => $rzp->mode(), 'configured' => $rzp->configured(),
            'packages' => DB::table('recharge_packages')->orderBy('sort')->orderBy('amount_minor')->get()]);
    }

    public function saveSettings(Request $request, SettingsRepository $s): RedirectResponse
    {
        $this->authorize('providers.manage');
        $d = $request->validate([
            'key_id' => ['nullable', 'regex:/^rzp_(test|live)_[A-Za-z0-9]{8,32}$/'],
            'key_secret' => ['nullable', 'string', 'max:100'], 'webhook_secret' => ['nullable', 'string', 'max:100'],
            'live_enabled' => ['nullable', 'boolean'],
        ], ['key_id.regex' => 'Key ID must look like rzp_test_… or rzp_live_…']);
        if (($d['key_id'] ?? null) !== $s->get('payments.razorpay.key_id')) {
            $s->set('payments.razorpay.key_id', $d['key_id'] ?? null);
        }
        foreach (['key_secret', 'webhook_secret'] as $k) {
            if (! empty($d[$k])) {
                $s->set("payments.razorpay.$k", $d[$k], secret: true);
            }
        }
        $s->set('payments.live_enabled', (bool) ($d['live_enabled'] ?? false));

        return back()->with('status', 'Payment settings saved.');
    }

    public function savePackage(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('prices.manage');
        $d = $request->validate(['id' => ['nullable', 'integer', 'exists:recharge_packages,id'], 'amount' => ['required', 'string', 'max:12'], 'bonus' => ['nullable', 'string', 'max:12'], 'active' => ['nullable', 'boolean']]);
        try {
            $row = ['amount_minor' => Money::parseRupees($d['amount']), 'bonus_minor' => Money::parseRupees($d['bonus'] ?? null) ?? 0, 'active' => (bool) ($d['active'] ?? false), 'updated_at' => now()];
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }
        if ($row['amount_minor'] < 100) {
            throw ValidationException::withMessages(['amount' => 'Minimum ₹1.']);
        }
        $d['id'] ?? null ? DB::table('recharge_packages')->where('id', $d['id'])->update($row) : DB::table('recharge_packages')->insert($row + ['created_at' => now()]);
        $audit->record('finance.package_saved', null, $row);

        return back()->with('status', 'Recharge package saved.');
    }
}
