<?php

namespace App\Domain\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Razorpay REST client (https://razorpay.com/docs/api/). Uses HTTP basic auth with the
 * key id/secret saved in admin. All amounts are integer paise, as Razorpay expects.
 */
class RazorpayClient
{
    public const BASE = 'https://api.razorpay.com/v1';

    public function __construct(private RazorpayConfig $cfg) {}

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::BASE)->withBasicAuth((string) $this->cfg->keyId(), (string) $this->cfg->keySecret())
            ->acceptJson()->asJson()->timeout(15)->connectTimeout(5);
    }

    /** @throws RazorpayException */
    public function createOrder(int $amountMinor, string $receipt, array $notes = []): array
    {
        return $this->call('post', '/orders', ['amount' => $amountMinor, 'currency' => 'INR', 'receipt' => mb_substr($receipt, 0, 40), 'notes' => $notes, 'payment_capture' => 1]);
    }

    public function fetchOrderPayments(string $orderId): array
    {
        return $this->call('get', '/orders/'.rawurlencode($orderId).'/payments')['items'] ?? [];
    }

    public function fetchPayment(string $paymentId): array
    {
        return $this->call('get', '/payments/'.rawurlencode($paymentId));
    }

    public function capture(string $paymentId, int $amountMinor): array
    {
        return $this->call('post', '/payments/'.rawurlencode($paymentId).'/capture', ['amount' => $amountMinor, 'currency' => 'INR']);
    }

    /** Idempotent on Razorpay's side through the receipt; we also never call it twice for one refund row. */
    public function refund(string $paymentId, int $amountMinor, string $receipt, array $notes = []): array
    {
        return $this->call('post', '/payments/'.rawurlencode($paymentId).'/refund', ['amount' => $amountMinor, 'speed' => 'normal', 'receipt' => mb_substr($receipt, 0, 40), 'notes' => $notes]);
    }

    public function fetchPaymentRefunds(string $paymentId): array
    {
        return $this->call('get', '/payments/'.rawurlencode($paymentId).'/refunds')['items'] ?? [];
    }

    public function fetchRefund(string $refundId): array
    {
        return $this->call('get', '/refunds/'.rawurlencode($refundId));
    }

    /** Lightweight authenticated call used by the admin "Test connection" button. */
    public function ping(): array
    {
        return $this->call('get', '/orders', ['count' => 1]);
    }

    private function call(string $method, string $path, array $body = []): array
    {
        try {
            $res = $method === 'get' ? $this->http()->get($path, $body) : $this->http()->post($path, $body);
        } catch (ConnectionException $e) {
            throw new RazorpayException('Could not reach Razorpay: '.mb_substr($e->getMessage(), 0, 200), transient: true);
        }
        if ($res->successful()) {
            return $res->json() ?? [];
        }
        $desc = $res->json('error.description') ?? $res->reason();
        throw new RazorpayException("Razorpay error {$res->status()}: {$desc}", transient: $res->serverError() || $res->status() === 429, status: $res->status());
    }

    /** Checkout signature: HMAC-SHA256(order_id|payment_id, key_secret). */
    public function checkoutSignatureValid(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->cfg->keySecret();

        return $secret !== null && hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $secret), $signature);
    }

    /** Webhook signature: HMAC-SHA256(raw body, webhook_secret). */
    public function webhookSignatureValid(string $rawBody, string $signature): bool
    {
        $secret = $this->cfg->webhookSecret();

        return $secret !== null && $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
