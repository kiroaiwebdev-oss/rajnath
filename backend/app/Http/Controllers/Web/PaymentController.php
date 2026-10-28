<?php

namespace App\Http\Controllers\Web;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Payments\Razorpay;
use App\Http\Controllers\Controller;
use App\Models\OfferingVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private Checkout $checkout, private Razorpay $rzp, private Ledger $ledger) {}

    public function wallet(Request $request): View
    {
        $u = $request->user();
        $acct = DB::table('ledger_accounts')->where('code', "customer_wallet:{$u->id}")->first();
        $entries = $acct ? DB::table('ledger_entries as e')->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('e.account_id', $acct->id)->orderByDesc('e.id')->limit(50)->get(['e.direction', 'e.amount_minor', 't.kind', 't.memo', 't.occurred_at']) : collect();

        return view('account.wallet', [
            'balance' => $acct ? (int) $acct->balance_minor : 0, 'entries' => $entries,
            'packages' => DB::table('recharge_packages')->where('active', true)->orderBy('sort')->orderBy('amount_minor')->get(),
            'payable' => $this->rzp->configured(),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('account.orders', ['orders' => DB::table('orders')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20)]);
    }

    public function receipt(Request $request, string $ref): View
    {
        $o = DB::table('orders')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($o, 404);

        return view('account.receipt', ['o' => $o, 'snap' => json_decode($o->snapshot, true), 'payment' => DB::table('payments')->where('order_id', $o->id)->where('status', 'captured')->first(),
            'refunds' => DB::table('refunds')->where('order_id', $o->id)->where('status', 'processed')->get()]);
    }

    public function buy(Request $request): RedirectResponse
    {
        $d = $request->validate(['variant' => ['required', 'string', 'size:26'], 'use_wallet' => ['nullable', 'boolean'], 'key' => ['required', 'string', 'max:64']]);
        $v = OfferingVariant::where('ref', $d['variant'])->firstOrFail();
        try {
            $r = $this->checkout->startServiceOrder($request->user(), $v, (bool) ($d['use_wallet'] ?? false), $d['key']);
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors(['variant' => 'Online payment is not available right now. Please send a request instead.']);
        }

        return redirect()->route('checkout.show', $r['order']->ref);
    }

    public function recharge(Request $request): RedirectResponse
    {
        $d = $request->validate(['package' => ['required', 'integer'], 'key' => ['required', 'string', 'max:64']]);
        $pkg = DB::table('recharge_packages')->where('id', $d['package'])->where('active', true)->first();
        abort_unless($pkg, 404);
        try {
            $r = $this->checkout->startRecharge($request->user(), $pkg, $d['key']);
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors(['package' => 'Online payment is not available right now.']);
        }

        return redirect()->route('checkout.show', $r['order']->ref);
    }

    public function show(Request $request, string $ref): View|RedirectResponse
    {
        $o = DB::table('orders')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($o, 404);
        if ($o->status !== 'pending_payment') {
            return redirect()->route('account.receipt', $o->ref);
        }

        return view('account.checkout', ['o' => $o, 'snap' => json_decode($o->snapshot, true), 'rzp' => $this->checkout->checkoutPayload($o)['razorpay'], 'user' => $request->user()]);
    }

    /** Browser callback after Razorpay Checkout. Signature verified server-side; amount checked on capture. */
    public function verify(Request $request, string $ref): RedirectResponse
    {
        $o = DB::table('orders')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($o, 404);
        $d = $request->validate(['razorpay_payment_id' => ['required', 'string', 'max:60'], 'razorpay_order_id' => ['required', 'string', 'max:60'], 'razorpay_signature' => ['required', 'string', 'max:128']]);
        $p = DB::table('payments')->where('order_id', $o->id)->where('provider_order_id', $d['razorpay_order_id'])->first();
        if (! $p || ! $this->rzp->verifyCheckoutSignature($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) {
            return redirect()->route('checkout.show', $o->ref)->withErrors(['payment' => 'We could not verify this payment. If money was deducted, it will be confirmed automatically within a few minutes or refunded.']);
        }
        // Confirm the captured amount with Razorpay rather than trusting the browser.
        try {
            $items = $this->rzp->orderPayments($d['razorpay_order_id']);
            $cap = collect($items)->firstWhere('id', $d['razorpay_payment_id']);
            if ($cap && $cap['status'] === 'captured') {
                $this->checkout->applyCapture($d['razorpay_order_id'], $cap['id'], (int) $cap['amount'], $cap['method'] ?? null);
            } else {
                DB::table('payments')->where('id', $p->id)->update(['status' => 'authorized', 'provider_payment_id' => $d['razorpay_payment_id'], 'updated_at' => now()]);
            }
        } catch (RuntimeException $e) {
            report($e);
            DB::table('payments')->where('id', $p->id)->update(['status' => 'ambiguous', 'provider_payment_id' => $d['razorpay_payment_id'], 'updated_at' => now()]);
        }

        return redirect()->route('account.receipt', $o->ref);
    }

    public function cancel(Request $request, string $ref): RedirectResponse
    {
        $o = DB::table('orders')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($o, 404);
        $this->checkout->cancel($o);

        return redirect()->route('account.orders')->with('status', 'Order cancelled.');
    }

    public static function newKey(): string
    {
        return (string) Str::uuid();
    }
}
