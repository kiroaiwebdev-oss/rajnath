<?php

namespace App\Http\Controllers;

use App\Domain\Practitioners\RazorpayX;
use App\Domain\Store\Shiprocket;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Signed provider callbacks (CSRF-exempt):
 * - POST /webhooks/razorpayx — payout.processed / failed / reversed / rejected, HMAC-SHA256 of the
 *   raw body with the RazorpayX webhook secret (X-Razorpay-Signature).
 * - POST /webhooks/courier-tracking — Shiprocket tracking updates, authenticated by the token
 *   set in Shiprocket (header x-api-key). Shiprocket rejects URLs containing its own name.
 * Each event is stored once in payment_events; replays are harmless.
 */
class ProviderWebhookController extends Controller
{
    public function razorpayx(Request $request, RazorpayX $rzx): JsonResponse
    {
        $raw = $request->getContent();
        if (! $rzx->verifyWebhook($raw, (string) $request->header('X-Razorpay-Signature'))) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        $event = json_decode($raw, true) ?: [];
        $id = 'rzx:'.($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $raw));
        if (! $this->once($id, 'razorpayx.'.($event['event'] ?? 'unknown'), $raw)) {
            return response()->json(['status' => 'duplicate']);
        }
        $e = $event['payload']['payout']['entity'] ?? null;
        $result = 'ignored';
        if ($e && str_starts_with((string) ($event['event'] ?? ''), 'payout.')) {
            $p = DB::table('payouts')->where('provider_payout_id', $e['id'] ?? '')->first() ?? DB::table('payouts')->where('ref', $e['reference_id'] ?? '')->where('provider', 'razorpayx')->first();
            $result = $p ? $rzx->apply($p, (string) ($e['status'] ?? ''), $e['utr'] ?? null, $e['status_details']['description'] ?? null) : 'unknown_payout';
        }
        DB::table('payment_events')->where('provider_event_id', $id)->update(['processed_at' => now(), 'result' => $result]);

        return response()->json(['status' => $result]);
    }

    public function courier(Request $request, SettingsRepository $settings, Shiprocket $shiprocket): JsonResponse
    {
        $token = (string) $settings->get('courier.shiprocket.webhook_token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('x-api-key'))) {
            return response()->json(['error' => 'unauthorised'], 401);
        }
        $raw = $request->getContent();
        $d = json_decode($raw, true) ?: [];
        $awb = (string) ($d['awb'] ?? '');
        $status = (string) ($d['current_status'] ?? $d['shipment_status'] ?? '');
        if (! $this->once('sr:'.hash('sha256', $raw), 'courier.tracking', $raw)) {
            return response()->json(['status' => 'duplicate']);
        }
        $result = $awb !== '' && $status !== '' ? ($shiprocket->applyStatus($awb, $status) ?? 'unknown_awb') : 'ignored';
        DB::table('payment_events')->where('provider_event_id', 'sr:'.hash('sha256', $raw))->update(['processed_at' => now(), 'result' => $result]);

        // Shiprocket expects 200 for every accepted call.
        return response()->json(['status' => $result]);
    }

    private function once(string $id, string $type, string $raw): bool
    {
        return (bool) DB::table('payment_events')->insertOrIgnore(['provider_event_id' => mb_substr($id, 0, 80), 'type' => mb_substr($type, 0, 40), 'payload_hash' => hash('sha256', $raw),
            'payload' => json_validate($raw) ? $raw : '{}', 'created_at' => now()]);
    }
}
