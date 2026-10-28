<?php

namespace Tests\Concerns;

use App\Models\RechargePackage;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay sandbox stand-in for automated tests: an in-memory fake of the documented REST
 * endpoints (orders, payments, capture, refunds). Signatures use the same HMAC as Razorpay.
 */
trait FinanceHelpers
{
    protected string $keySecret = 'test_secret_abcdefghijklmnop';

    protected string $webhookSecret = 'whsec_test_123456789';

    /** @var array<string, array> fake Razorpay state */
    protected array $rz = ['orders' => [], 'payments' => [], 'refunds' => [], 'calls' => []];

    protected bool $rzDown = false;

    protected function configureRazorpay(bool $enabled = true): void
    {
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_ABCDEF123456');
        $s->set('payments.razorpay.key_secret', $this->keySecret, secret: true);
        $s->set('payments.razorpay.webhook_secret', $this->webhookSecret, secret: true);
        $s->set('payments.enabled', $enabled);
        $this->fakeRazorpay();
    }

    protected function fakeRazorpay(): void
    {
        Http::fake(function (HttpRequest $req) {
            $this->rz['calls'][] = $req->method().' '.$req->url();
            if ($this->rzDown) {
                return Http::response(['error' => ['description' => 'Service unavailable']], 503);
            }
            $path = parse_url($req->url(), PHP_URL_PATH);
            $m = $req->method();
            if ($m === 'POST' && $path === '/v1/orders') {
                $id = 'order_'.substr(md5((string) microtime(true).count($this->rz['orders'])), 0, 14);
                $this->rz['orders'][$id] = ['id' => $id, 'amount' => $req['amount'], 'currency' => $req['currency'], 'receipt' => $req['receipt'], 'status' => 'created'];

                return Http::response($this->rz['orders'][$id]);
            }
            if ($m === 'GET' && $path === '/v1/orders') {
                return Http::response(['items' => array_values($this->rz['orders']), 'count' => count($this->rz['orders'])]);
            }
            if ($m === 'GET' && preg_match('#^/v1/orders/([^/]+)/payments$#', $path, $x)) {
                return Http::response(['items' => array_values(array_filter($this->rz['payments'], fn ($p) => $p['order_id'] === $x[1]))]);
            }
            if ($m === 'GET' && preg_match('#^/v1/payments/([^/]+)$#', $path, $x)) {
                return isset($this->rz['payments'][$x[1]]) ? Http::response($this->rz['payments'][$x[1]]) : Http::response(['error' => ['description' => 'not found']], 400);
            }
            if ($m === 'POST' && preg_match('#^/v1/payments/([^/]+)/capture$#', $path, $x)) {
                $this->rz['payments'][$x[1]]['status'] = 'captured';

                return Http::response($this->rz['payments'][$x[1]]);
            }
            if ($m === 'GET' && preg_match('#^/v1/payments/([^/]+)/refunds$#', $path, $x)) {
                return Http::response(['items' => array_values(array_filter($this->rz['refunds'], fn ($r) => $r['payment_id'] === $x[1]))]);
            }
            if ($m === 'POST' && preg_match('#^/v1/payments/([^/]+)/refund$#', $path, $x)) {
                $id = 'rfnd_'.substr(md5((string) microtime(true).count($this->rz['refunds'])), 0, 14);
                $this->rz['refunds'][$id] = ['id' => $id, 'payment_id' => $x[1], 'amount' => $req['amount'], 'receipt' => $req['receipt'], 'status' => $this->rz['refund_status'] ?? 'processed'];

                return Http::response($this->rz['refunds'][$id]);
            }
            if ($m === 'GET' && preg_match('#^/v1/refunds/([^/]+)$#', $path, $x)) {
                return Http::response($this->rz['refunds'][$x[1]]);
            }

            return Http::response(['error' => ['description' => 'unexpected '.$m.' '.$path]], 404);
        });
    }

    /** Simulates the customer paying in Razorpay Checkout. */
    protected function rzPay(string $orderId, string $status = 'captured', ?int $amount = null, string $method = 'upi'): string
    {
        $id = 'pay_'.substr(md5($orderId.count($this->rz['payments']).$status), 0, 14);
        $this->rz['payments'][$id] = ['id' => $id, 'order_id' => $orderId, 'amount' => $amount ?? $this->rz['orders'][$orderId]['amount'],
            'currency' => 'INR', 'status' => $status, 'method' => $method, 'error_description' => $status === 'failed' ? 'Payment declined by bank' : null];

        return $id;
    }

    protected function checkoutSignature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret);
    }

    protected function webhook(array $body, ?string $eventId = null, ?string $secret = null)
    {
        $raw = json_encode($body);

        return $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, $secret ?? $this->webhookSecret),
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId ?? 'evt_'.uniqid(),
        ], $raw);
    }

    protected function package(int $amount = 50000, int $bonus = 0): RechargePackage
    {
        return RechargePackage::create(['name' => 'Pack '.$amount, 'amount_minor' => $amount, 'bonus_minor' => $bonus, 'active' => true]);
    }

    protected function customer(string $email = 'cust@example.com'): User
    {
        $u = new User(['name' => 'Customer '.$email, 'email' => $email]);
        $u->email_verified_at = now();
        $u->save();

        return $u;
    }
}
