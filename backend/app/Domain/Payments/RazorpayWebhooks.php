<?php

namespace App\Domain\Payments;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verified Razorpay webhook handling. Signature is checked over the raw body; each event id is
 * stored once (replays are acknowledged and ignored). A webhook that claims a capture is not
 * trusted on its own: the payment is re-fetched from the API before money is credited.
 */
class RazorpayWebhooks
{
    public function __construct(
        private RazorpayClient $rzp,
        private PaymentService $payments,
        private RefundService $refunds,
        private DisputeService $disputes,
    ) {}

    /** @return array{0: int, 1: string} HTTP status and short result */
    public function handle(string $raw, string $signature, ?string $eventId): array
    {
        if (! $this->rzp->webhookSignatureValid($raw, $signature)) {
            Log::warning('razorpay.webhook_bad_signature');

            return [400, 'invalid_signature'];
        }
        $body = json_decode($raw, true);
        if (! is_array($body) || empty($body['event'])) {
            return [400, 'invalid_payload'];
        }
        $eventId = $eventId ?: 'sha:'.hash('sha256', $raw);
        $inserted = DB::table('payment_events')->insertOrIgnore([
            'provider' => 'razorpay', 'provider_event_id' => mb_substr($eventId, 0, 100), 'type' => mb_substr($body['event'], 0, 60),
            'payload_sha256' => hash('sha256', $raw), 'received_at' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        if (! $inserted) {
            return [200, 'duplicate'];
        }
        $row = DB::table('payment_events')->where('provider', 'razorpay')->where('provider_event_id', mb_substr($eventId, 0, 100));
        try {
            [$result, $paymentId] = $this->dispatch($body['event'], $body['payload'] ?? []);
            $row->update(['result' => $result, 'payment_id' => $paymentId, 'processed_at' => now('UTC')->format('Y-m-d H:i:s.u')]);
        } catch (Throwable $e) {
            // Let Razorpay retry: remove the claim so the retried delivery is processed.
            DB::table('payment_events')->where('provider', 'razorpay')->where('provider_event_id', mb_substr($eventId, 0, 100))->delete();
            Log::error('razorpay.webhook_failed', ['event' => $body['event'], 'error' => $e->getMessage()]);

            return [500, 'error'];
        }

        return [200, $result];
    }

    /** @return array{0: string, 1: ?int} */
    private function dispatch(string $event, array $payload): array
    {
        $pay = $payload['payment']['entity'] ?? null;
        switch (true) {
            case in_array($event, ['payment.captured', 'payment.authorized', 'order.paid', 'payment.failed'], true):
                $p = $pay ? Payment::where('provider_order_id', $pay['order_id'] ?? '')->first() : null;
                if (! $p) {
                    Findings::raise('webhook.unknown_payment', 'warning', null, ['event' => $event, 'order_id' => $pay['order_id'] ?? null, 'payment_id' => $pay['id'] ?? null], 'webhook.unknown:'.($pay['id'] ?? 'none'));

                    return ['unknown_payment', null];
                }
                if ($event === 'payment.failed') {
                    $this->payments->settle($p, $pay);
                } else {
                    // Re-fetch: do not credit money on the webhook body alone.
                    $this->payments->settle($p, $this->rzp->fetchPayment($pay['id']));
                }

                return ['applied', $p->id];

            case str_starts_with($event, 'refund.'):
                $rf = $payload['refund']['entity'] ?? [];
                $r = Refund::where('provider_refund_id', $rf['id'] ?? '')->first()
                    ?? (isset($rf['receipt']) ? Refund::where('ref', $rf['receipt'])->first() : null);
                if (! $r) {
                    Findings::raise('webhook.unknown_refund', 'warning', null, ['event' => $event, 'refund_id' => $rf['id'] ?? null], 'webhook.unknown_refund:'.($rf['id'] ?? 'none'));

                    return ['unknown_refund', null];
                }
                if (! $r->provider_refund_id && isset($rf['id'])) {
                    $r->update(['provider_refund_id' => $rf['id']]);
                }
                $this->refunds->applyProvider($r, $event === 'refund.processed' ? $this->rzp->fetchRefund($rf['id']) : $rf);

                return ['applied', $r->payment_id];

            case str_starts_with($event, 'payment.dispute.'):
                $p = $pay ? Payment::where('provider_payment_id', $pay['id'] ?? '')->first() : null;
                $d = $this->disputes->apply($event, $payload['dispute']['entity'] ?? [], $p);

                return [$d ? 'applied' : 'unknown_payment', $p?->id];
        }

        return ['ignored', null];
    }
}
