<?php

namespace App\Http\Controllers\Web;

use App\Domain\Ledger\Wallet;
use App\Domain\Payments\PaymentService;
use App\Domain\Payments\PaymentUnavailable;
use App\Domain\Payments\RazorpayConfig;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RechargePackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Customer wallet, recharge checkout, payment status, receipts and statement. */
class WalletController extends Controller
{
    public function __construct(private Wallet $wallet, private PaymentService $payments) {}

    public function show(Request $request, RazorpayConfig $cfg): View
    {
        $u = $request->user();

        return view('account.wallet.index', [
            'bal' => $this->wallet->balances($u),
            'packages' => RechargePackage::where('active', true)->orderBy('sort')->orderBy('amount_minor')->get(),
            'entries' => $this->wallet->statement($u->id, 30),
            'payments' => Payment::where('user_id', $u->id)->latest()->limit(10)->get(),
            'paymentsReady' => $cfg->ready(),
            'testMode' => $cfg->mode() === 'test',
        ]);
    }

    public function recharge(Request $request): RedirectResponse
    {
        $data = $request->validate(['package' => ['required', 'string', 'size:26'], 'idem' => ['required', 'string', 'max:64']]);
        $package = RechargePackage::where('ref', $data['package'])->where('active', true)->first();
        if (! $package) {
            return back()->withErrors(['package' => 'Please choose a recharge option.']);
        }
        try {
            $p = $this->payments->startRecharge($request->user(), $package, 'web:'.$data['idem']);
        } catch (PaymentUnavailable $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        return $p->status === 'created'
            ? redirect()->route('account.payments.checkout', $p)
            : redirect()->route('account.payments.show', $p);
    }

    public function checkout(Request $request, Payment $payment): View|RedirectResponse
    {
        $this->own($request, $payment);
        if (! in_array($payment->status, ['created', 'failed'], true) || ! $payment->provider_order_id) {
            return redirect()->route('account.payments.show', $payment);
        }

        return view('account.wallet.checkout', ['p' => $payment, 'options' => $this->payments->checkoutOptions($payment, $request->user())]);
    }

    public function confirm(Request $request, Payment $payment): RedirectResponse
    {
        $this->own($request, $payment);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64', 'regex:/^pay_[A-Za-z0-9]+$/'],
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]+$/'],
        ]);
        try {
            $this->payments->confirmFromCheckout($payment, $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature']);
        } catch (RuntimeException) {
            return redirect()->route('account.payments.show', $payment)->withErrors(['payment' => 'We could not verify this payment confirmation. If money was taken, it will be confirmed automatically or returned by Razorpay.']);
        }

        return redirect()->route('account.payments.show', $payment);
    }

    public function status(Request $request, Payment $payment): View
    {
        $this->own($request, $payment);

        return view('account.wallet.payment', ['p' => $payment->load('refunds'), 'bal' => $this->wallet->balances($request->user())]);
    }

    public function refresh(Request $request, Payment $payment): RedirectResponse
    {
        $this->own($request, $payment);
        $this->payments->reconcile($payment);

        return redirect()->route('account.payments.show', $payment)->with('status', 'Payment status checked with Razorpay.');
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $this->own($request, $payment);
        abort_unless($payment->status === 'captured', 404);

        return view('account.wallet.receipt', ['p' => $payment->load('refunds'), 'u' => $request->user()]);
    }

    public function statementCsv(Request $request): StreamedResponse
    {
        $u = $request->user();
        $rows = $this->wallet->statement($u->id, 5000);

        return response()->streamDownload(function () use ($rows, $u) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date (local)', 'Reference', 'Description', 'Balance type', 'Credit (INR)', 'Debit (INR)', 'Balance after (INR)']);
            foreach ($rows as $e) {
                $amt = number_format($e->amount_minor / 100, 2, '.', '');
                fputcsv($out, [
                    Carbon::parse($e->created_at, 'UTC')->tz($u->display_timezone)->format('Y-m-d H:i'),
                    $e->tx_ref, self::csvSafe((string) $e->memo), self::KINDS[$e->kind] ?? $e->kind,
                    $e->direction === 'credit' ? $amt : '', $e->direction === 'debit' ? $amt : '',
                    number_format($e->balance_after_minor / 100, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'wallet-statement-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public const KINDS = ['customer_cash' => 'Paid balance', 'customer_bonus' => 'Bonus balance', 'customer_held' => 'On hold'];

    private static function csvSafe(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    private function own(Request $request, Payment $payment): void
    {
        abort_unless($payment->user_id === $request->user()->id, 404);
    }

    public static function newIdem(): string
    {
        return (string) Str::ulid();
    }
}
