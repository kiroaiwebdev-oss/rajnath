<?php

namespace App\Domain\Payments;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay REST client (Orders, Payments, Refunds) and signature checks.
 * Keys come from Admin → Payments (encrypted settings). Test keys start with rzp_test_.
 */
class Razorpay
{
    public const API = 'https://api.razorpay.com/v1';

    public function __construct(private SettingsRepository $settings) {}

    public function keyId(): ?string
    {
        return $this->settings->get('payments.razorpay.key_id') ?: null;
    }

    public function configured(): bool
    {
        return (bool) ($this->keyId() && $this->settings->get('payments.razorpay.key_secret'));
    }

    public function mode(): string
    {
        return str_starts_with((string) $this->keyId(), 'rzp_live_') ? 'live' : 'test';
    }

    public function liveAllowed(): bool
    {
        return (bool) $this->settings->get('payments.live_enabled');
    }

    private function http()
    {
        if (! $this->configured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }
        if ($this->mode() === 'live' && ! $this->liveAllowed()) {
            throw new RuntimeException('Live payments are not enabled by the owner.');
        }

        return Http::asJson()->acceptJson()->timeout(15)->connectTimeout(5)
            ->withBasicAuth($this->keyId(), $this->settings->get('payments.razorpay.key_secret'));
    }

    public function createOrder(int $amountMinor, string $receipt, array $notes = []): array
    {
        $r = $this->http()->post(self::API.'/orders', ['amount' => $amountMinor, 'currency' => 'INR', 'receipt' => mb_substr($receipt, 0, 40), 'notes' => $notes]);
        if (! $r->successful() || empty($r['id'])) {
            throw new RuntimeException('Razorpay order failed: '.($r['error']['description'] ?? $r->status()));
        }

        return $r->json();
    }

    public function orderPayments(string $orderId): array
    {
        $r = $this->http()->get(self::API."/orders/$orderId/payments");
        if (! $r->successful()) {
            throw new RuntimeException('Razorpay lookup failed: '.$r->status());
        }

        return $r->json('items') ?? [];
    }

    public function refund(string $paymentId, int $amountMinor, string $receipt): array
    {
        $r = $this->http()->withHeaders(['X-Razorpay-Idempotency-Key' => $receipt])
            ->post(self::API."/payments/$paymentId/refund", ['amount' => $amountMinor, 'receipt' => mb_substr($receipt, 0, 40), 'speed' => 'normal']);
        if (! $r->successful() || empty($r['id'])) {
            throw new RuntimeException('Razorpay refund failed: '.($r['error']['description'] ?? $r->status()));
        }

        return $r->json();
    }

    /** Checkout handler signature: HMAC_SHA256(order_id|payment_id, key_secret). */
    public function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = (string) $this->settings->get('payments.razorpay.key_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $secret), $signature);
    }

    /** Webhook signature: HMAC_SHA256(raw body, webhook secret). */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) $this->settings->get('payments.razorpay.webhook_secret');

        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
