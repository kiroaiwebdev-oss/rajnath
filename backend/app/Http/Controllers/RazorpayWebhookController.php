<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Checkout;
use App\Domain\Payments\Razorpay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /webhooks/razorpay — events: payment.captured, payment.failed, order.paid, refund.processed, payment.dispute.*.
 * Signature (X-Razorpay-Signature) is checked against the raw body; each event id is processed
 * once (X-Razorpay-Event-Id unique); out-of-order or duplicate events are harmless.
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, Razorpay $rzp, Checkout $checkout): JsonResponse
    {
        $raw = $request->getContent();
        if (! $rzp->verifyWebhook($raw, (string) $request->header('X-Razorpay-Signature'))) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        $event = json_decode($raw, true);
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $raw));
        $inserted = DB::table('payment_events')->insertOrIgnore([
            'provider_event_id' => $eventId, 'type' => (string) ($event['event'] ?? 'unknown'), 'payload_hash' => hash('sha256', $raw),
            'payload' => $raw, 'created_at' => now(),
        ]);
        if (! $inserted) {
            return response()->json(['status' => 'duplicate']);
        }
        $pay = $event['payload']['payment']['entity'] ?? null;
        $result = 'ignored';
        if ($pay && in_array($event['event'], ['payment.captured', 'order.paid'], true) && ($pay['status'] ?? '') === 'captured') {
            $result = $checkout->applyCapture((string) $pay['order_id'], (string) $pay['id'], (int) $pay['amount'], $pay['method'] ?? null);
        } elseif ($pay && $event['event'] === 'payment.failed') {
            $checkout->markFailed((string) $pay['order_id'], (string) $pay['id'], (string) ($pay['error_description'] ?? 'failed'));
            $result = 'failed_recorded';
        } elseif (($event['event'] ?? '') === 'refund.processed') {
            $rid = $event['payload']['refund']['entity']['id'] ?? null;
            $result = $rid && DB::table('refunds')->where('provider_refund_id', $rid)->exists() ? 'refund_confirmed' : 'refund_unknown';
        }
        if (str_starts_with((string) ($event['event'] ?? ''), 'payment.dispute.')) {
            // Chargeback / dispute: finance must respond in the Razorpay dashboard before the deadline.
            $d = $event['payload']['dispute']['entity'] ?? [];
            $order = $pay ? DB::table('payments')->where('provider_payment_id', $pay['id'] ?? '')->value('order_id') : null;
            $ref = $order ? DB::table('orders')->where('id', $order)->value('ref') : null;
            DB::table('reconciliation_findings')->insert(['check' => 'dispute', 'severity' => in_array($event['event'], ['payment.dispute.lost', 'payment.dispute.created'], true) ? 'critical' : 'warning',
                'subject' => $ref ? 'order:'.$ref : 'payment:'.($pay['id'] ?? $d['payment_id'] ?? '?'),
                'detail' => mb_substr(sprintf('%s · dispute %s · ₹%s · phase %s · reason %s · respond by %s', $event['event'], $d['id'] ?? '?', number_format(((int) ($d['amount'] ?? 0)) / 100, 2),
                    $d['phase'] ?? '?', $d['reason_code'] ?? '?', isset($d['respond_by']) ? date('d M Y', (int) $d['respond_by']) : '?'), 0, 500),
                'created_at' => now(), 'updated_at' => now()]);
            $result = 'dispute_recorded';
        }
        DB::table('payment_events')->where('provider_event_id', $eventId)->update(['processed_at' => now(), 'result' => $result]);

        return response()->json(['status' => $result]);
    }
}
