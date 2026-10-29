<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Coupons;
use App\Domain\Payments\Invoices;
use App\Domain\Payments\Razorpay;
use App\Domain\Payments\Reconciler;
use App\Domain\Payments\Refunds;
use App\Domain\Store\Shiprocket;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Pdf\Documents;
use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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
            'packages' => DB::table('recharge_packages')->orderBy('sort')->orderBy('amount_minor')->get(),
            'tax' => app(Invoices::class)->config() + ['enabled' => (bool) $s->get('tax.gst_enabled')],
            'rzx' => ['account' => $s->get('payouts.razorpayx.account_number'), 'mode' => $s->get('payouts.razorpayx.mode', 'IMPS'), 'webhook' => $s->masked('payouts.razorpayx.webhook_secret')],
            'courier' => ['email' => $s->get('courier.shiprocket.email'), 'password' => $s->masked('courier.shiprocket.password'), 'pickup' => $s->get('courier.shiprocket.pickup_location'),
                'webhook' => $s->masked('courier.shiprocket.webhook_token')]]);
    }

    /** GST invoicing (FI-08). */
    public function saveTax(Request $request, SettingsRepository $s): RedirectResponse
    {
        $this->authorize('providers.manage');
        $d = $request->validate([
            'gst_enabled' => ['nullable', 'boolean'],
            'gstin' => ['required_if:gst_enabled,1', 'nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'legal_name' => ['required_if:gst_enabled,1', 'nullable', 'string', 'max:160'], 'address' => ['required_if:gst_enabled,1', 'nullable', 'string', 'max:300'],
            'service_rate' => ['required', 'numeric', 'between:0,28'], 'product_rate' => ['required', 'numeric', 'between:0,28'],
            'service_sac' => ['required', 'regex:/^\d{4,8}$/'], 'product_hsn' => ['required', 'regex:/^\d{4,8}$/'], 'invoice_prefix' => ['required', 'regex:/^[A-Z0-9]{1,3}$/'],
        ], ['gstin.regex' => 'Enter the 15-character GSTIN in capitals.', 'invoice_prefix.regex' => 'Use 1–3 capital letters or digits (invoice numbers may have at most 16 characters).']);
        if (! empty($d['gstin']) && ! array_key_exists(substr($d['gstin'], 0, 2), Invoices::STATES)) {
            throw ValidationException::withMessages(['gstin' => 'The first two digits are not a valid state code.']);
        }
        foreach (['gstin', 'legal_name', 'address', 'service_sac', 'product_hsn', 'invoice_prefix'] as $k) {
            $s->set("tax.$k", $d[$k] ?? null);
        }
        $s->set('tax.service_rate_bps', (int) round($d['service_rate'] * 100));
        $s->set('tax.product_rate_bps', (int) round($d['product_rate'] * 100));
        $s->set('tax.gst_enabled', (bool) ($d['gst_enabled'] ?? false));

        return back()->with('status', 'GST settings saved.');
    }

    /** RazorpayX payouts: uses the Razorpay API keys above plus the RazorpayX account number. */
    public function savePayouts(Request $request, SettingsRepository $s): RedirectResponse
    {
        $this->authorize('providers.manage');
        $d = $request->validate(['account_number' => ['nullable', 'regex:/^[0-9A-Za-z]{6,40}$/'], 'mode' => ['required', Rule::in(['IMPS', 'NEFT', 'RTGS'])],
            'webhook_secret' => ['nullable', 'string', 'max:100']]);
        $s->set('payouts.razorpayx.account_number', $d['account_number'] ?? null);
        $s->set('payouts.razorpayx.mode', $d['mode']);
        if (! empty($d['webhook_secret'])) {
            $s->set('payouts.razorpayx.webhook_secret', $d['webhook_secret'], secret: true);
        }

        return back()->with('status', 'RazorpayX settings saved.');
    }

    /** Shiprocket courier (gem store). */
    public function saveCourier(Request $request, SettingsRepository $s): RedirectResponse
    {
        $this->authorize('providers.manage');
        $d = $request->validate(['email' => ['nullable', 'email', 'max:190'], 'password' => ['nullable', 'string', 'max:120'],
            'pickup_location' => ['nullable', 'string', 'max:60'], 'webhook_token' => ['nullable', 'string', 'min:16', 'max:120']]);
        $s->set('courier.shiprocket.email', $d['email'] ?? null);
        $s->set('courier.shiprocket.pickup_location', $d['pickup_location'] ?? null);
        foreach (['password', 'webhook_token'] as $k) {
            if (! empty($d[$k])) {
                $s->set("courier.shiprocket.$k", $d[$k], secret: true);
            }
        }
        Cache::forget(Shiprocket::TOKEN_CACHE);

        return back()->with('status', 'Courier settings saved.');
    }

    // ---- Invoices (staff view) ----

    public function invoice(string $ref, Documents $docs, Request $request): Response
    {
        $this->authorize('finance.view');
        $inv = DB::table('invoices')->where('ref', $ref)->first();
        abort_unless($inv, 404);

        return $request->boolean('pdf') ? $docs->download($docs->invoiceHtml($inv), str_replace('/', '-', $inv->number).'.pdf')
            : response($docs->invoiceHtml($inv))->header('Cache-Control', 'private, no-store');
    }

    // ---- Coupons (FI-13) ----

    public function coupons(): View
    {
        $this->authorize('prices.manage');
        $uses = DB::table('coupon_redemptions as r')->join('orders as o', 'o.id', '=', 'r.order_id')->whereIn('o.status', Coupons::COUNTED)
            ->groupBy('r.coupon_id')->selectRaw('r.coupon_id, count(*) as n, sum(r.discount_minor) as total')->get()->keyBy('coupon_id');

        return view('admin.finance.coupons', ['coupons' => DB::table('coupons')->orderByDesc('active')->orderByDesc('id')->get(), 'uses' => $uses]);
    }

    public function saveCoupon(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('prices.manage');
        $d = $request->validate([
            'id' => ['nullable', 'integer', 'exists:coupons,id'],
            'code' => ['required', 'regex:/^[A-Za-z0-9_-]{3,32}$/', Rule::unique('coupons', 'code')->ignore($request->input('id'))],
            'description' => ['nullable', 'string', 'max:200'], 'type' => ['required', Rule::in(['percent', 'flat'])],
            'value' => ['required', 'string', 'max:12'], 'max_discount' => ['nullable', 'string', 'max:12'], 'min_order' => ['nullable', 'string', 'max:12'],
            'applies_to' => ['nullable', 'array'], 'applies_to.*' => [Rule::in(array_keys(Coupons::SCOPES))],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'per_user_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'first_order_only' => ['nullable', 'boolean'], 'active' => ['nullable', 'boolean'],
        ], ['code.regex' => 'Use 3–32 letters, digits, - or _.']);
        try {
            $value = $d['type'] === 'percent' ? (int) $d['value'] : Money::parseRupees($d['value']);
            $row = ['code' => strtoupper($d['code']), 'description' => $d['description'] ?? null, 'type' => $d['type'], 'value' => $value,
                'max_discount_minor' => Money::parseRupees($d['max_discount'] ?? null), 'min_order_minor' => Money::parseRupees($d['min_order'] ?? null) ?? 0,
                'applies_to' => ! empty($d['applies_to']) ? json_encode(array_values($d['applies_to'])) : null,
                'starts_at' => ! empty($d['starts_at']) ? CarbonImmutable::parse($d['starts_at'], 'Asia/Kolkata')->utc() : null,
                'ends_at' => ! empty($d['ends_at']) ? CarbonImmutable::parse($d['ends_at'], 'Asia/Kolkata')->utc() : null,
                'usage_limit' => $d['usage_limit'] ?? null, 'per_user_limit' => (int) $d['per_user_limit'], 'first_order_only' => (bool) ($d['first_order_only'] ?? false),
                'active' => (bool) ($d['active'] ?? false), 'updated_at' => now()];
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['value' => $e->getMessage()]);
        }
        if ($d['type'] === 'percent' && ($value < 1 || $value > 100)) {
            throw ValidationException::withMessages(['value' => 'A percentage must be between 1 and 100.']);
        }
        if ($d['type'] === 'flat' && $value < 100) {
            throw ValidationException::withMessages(['value' => 'Minimum ₹1.']);
        }
        if (! empty($d['id'])) {
            DB::table('coupons')->where('id', $d['id'])->update($row);
        } else {
            DB::table('coupons')->insert($row + ['created_by' => $request->user()->id, 'created_at' => now()]);
        }
        $audit->record('finance.coupon_saved', null, ['code' => $row['code'], 'type' => $row['type'], 'value' => $row['value'], 'active' => $row['active']]);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon '.$row['code'].' saved.');
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
