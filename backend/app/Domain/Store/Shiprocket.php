<?php

namespace App\Domain\Store;

use App\Domain\Notify\Notifier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Shiprocket courier booking for gem-store orders (replaces typing the AWB by hand; the manual
 * form still works). Flow: create order → assign AWB (Shiprocket picks the courier) → request
 * pickup → shipment "dispatched". Status then comes from Shiprocket's tracking webhook
 * (header x-api-key = our token) and an hourly poll: delivered / RTO → returned.
 * API: https://apiv2.shiprocket.in/v1/external (token from the API user's login, valid 10 days).
 */
class Shiprocket
{
    public const API = 'https://apiv2.shiprocket.in/v1/external';

    public const TOKEN_CACHE = 'shiprocket:token';

    public function __construct(private SettingsRepository $settings, private Store $store, private AuditLogger $audit) {}

    public function configured(): bool
    {
        return (bool) ($this->settings->get('courier.shiprocket.email') && $this->settings->get('courier.shiprocket.password') && $this->settings->get('courier.shiprocket.pickup_location'));
    }

    private function http(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('Shiprocket is not set up (Admin › Payments setup › Courier).');
        }
        $token = Cache::remember(self::TOKEN_CACHE, now()->addDays(9), function () {
            $r = Http::asJson()->acceptJson()->timeout(15)->post(self::API.'/auth/login', ['email' => $this->settings->get('courier.shiprocket.email'), 'password' => $this->settings->get('courier.shiprocket.password')]);
            if (! $r->successful() || ! $r->json('token')) {
                throw new RuntimeException('Shiprocket sign-in failed ('.$r->status().').');
            }

            return $r->json('token');
        });

        return Http::asJson()->acceptJson()->timeout(20)->connectTimeout(5)->withToken($token);
    }

    private function call(string $method, string $path, array $body = []): array
    {
        $r = $this->http()->{$method}(self::API.$path, $body);
        if ($r->status() === 401) {
            Cache::forget(self::TOKEN_CACHE);
            $r = $this->http()->{$method}(self::API.$path, $body);
        }
        if (! $r->successful()) {
            throw new RuntimeException('Shiprocket '.$path.' failed ('.$r->status().'): '.mb_substr((string) ($r->json('message') ?? $r->body()), 0, 200));
        }

        return $r->json() ?? [];
    }

    /** Books the courier for a paid order awaiting dispatch. */
    public function book(object $order, User $by): object
    {
        $s = DB::table('shipments')->where('order_id', $order->id)->first();
        if (! $s || $s->status !== 'awaiting_dispatch') {
            throw ValidationException::withMessages(['courier' => 'Only paid orders awaiting dispatch can be booked with the courier.']);
        }
        $snap = json_decode($order->snapshot, true);
        $a = Store::address($s);
        $p = DB::table('products')->find($snap['product']['id']);
        $qty = (int) $snap['quantity'];
        try {
            if (! $s->provider_shipment_id) {
                $created = $this->call('post', '/orders/create/adhoc', [
                    'order_id' => $order->ref, 'order_date' => Carbon::parse($order->paid_at ?? $order->created_at)->tz('Asia/Kolkata')->format('Y-m-d H:i'),
                    'pickup_location' => $this->settings->get('courier.shiprocket.pickup_location'),
                    'billing_customer_name' => $a['name'], 'billing_last_name' => '', 'billing_address' => $a['line1'], 'billing_address_2' => $a['line2'] ?? '',
                    'billing_city' => $a['city'], 'billing_pincode' => $a['pincode'], 'billing_state' => $a['state'], 'billing_country' => 'India',
                    'billing_email' => DB::table('users')->where('id', $order->user_id)->value('email') ?? '', 'billing_phone' => substr(preg_replace('/\D/', '', $a['phone']), -10),
                    'shipping_is_billing' => true,
                    'order_items' => [['name' => $snap['product']['name'], 'sku' => $snap['product']['ref'], 'units' => $qty, 'selling_price' => round($snap['unit_price_minor'] / 100, 2), 'discount' => round(((int) $order->discount_minor) / 100 / max(1, $qty), 2), 'tax' => 0]],
                    'payment_method' => 'Prepaid', 'sub_total' => round($order->subtotal_minor / 100, 2),
                    'length' => (int) ($p->ship_length_cm ?? 12), 'breadth' => (int) ($p->ship_breadth_cm ?? 10), 'height' => (int) ($p->ship_height_cm ?? 6),
                    'weight' => round(max(1, (int) ($p->ship_weight_g ?? 200)) * $qty / 1000, 3),
                ]);
                if (empty($created['shipment_id'])) {
                    throw new RuntimeException('Shiprocket did not return a shipment id.');
                }
                DB::table('shipments')->where('id', $s->id)->update(['provider' => 'shiprocket', 'provider_order_id' => (string) $created['order_id'], 'provider_shipment_id' => (string) $created['shipment_id'],
                    'provider_status' => $created['status'] ?? 'NEW', 'provider_error' => null, 'updated_at' => now()]);
                $s = DB::table('shipments')->find($s->id);
            }
            $awb = $this->call('post', '/courier/assign/awb', ['shipment_id' => (int) $s->provider_shipment_id]);
            $data = $awb['response']['data'] ?? [];
            if (empty($data['awb_code'])) {
                throw new RuntimeException('No courier could take this parcel: '.mb_substr((string) ($awb['message'] ?? 'AWB not assigned'), 0, 150));
            }
            $pickup = $this->call('post', '/courier/generate/pickup', ['shipment_id' => [(int) $s->provider_shipment_id]]);
            $label = rescue(fn () => $this->call('post', '/courier/generate/label', ['shipment_id' => [(int) $s->provider_shipment_id]]), [], false);
        } catch (RuntimeException $e) {
            DB::table('shipments')->where('id', $s->id)->update(['provider_error' => mb_substr($e->getMessage(), 0, 300), 'updated_at' => now()]);
            $this->audit->record('store.courier_failed', null, ['order' => $order->ref], $by);
            throw ValidationException::withMessages(['courier' => $e->getMessage()]);
        }
        $this->store->updateShipment($order->id, ['status' => 'dispatched', 'courier' => $data['courier_name'] ?? 'Shiprocket', 'awb' => $data['awb_code'],
            'tracking_url' => 'https://shiprocket.co/tracking/'.rawurlencode($data['awb_code'])], $by);
        DB::table('shipments')->where('id', $s->id)->update(['provider_status' => ($pickup['pickup_status'] ?? 0) ? 'PICKUP SCHEDULED' : 'AWB ASSIGNED',
            'label_url' => isset($label['label_url']) && str_starts_with((string) $label['label_url'], 'https://') ? $label['label_url'] : null, 'synced_at' => now(), 'updated_at' => now()]);
        $this->audit->record('store.courier_booked', null, ['order' => $order->ref, 'awb' => $data['awb_code']], $by);
        app(Notifier::class)->send((int) $order->user_id, 'order', 'Your order is on its way',
            'Your '.$snap['product']['name'].' has been handed to '.($data['courier_name'] ?? 'the courier').'. Tracking number: '.$data['awb_code'].'.', $order->ref, route('account.receipt', $order->ref));

        return DB::table('shipments')->find($s->id);
    }

    /**
     * Applies a Shiprocket tracking status (from the webhook or polling) to the shipment.
     * Returns the shipment status after the update, or null when the AWB is unknown.
     */
    public function applyStatus(string $awb, string $status): ?string
    {
        $s = DB::table('shipments')->where('awb', $awb)->where('provider', 'shiprocket')->first();
        if (! $s) {
            return null;
        }
        $st = strtoupper(trim($status));
        DB::table('shipments')->where('id', $s->id)->update(['provider_status' => mb_substr($st, 0, 60), 'synced_at' => now(), 'updated_at' => now()]);
        $target = match (true) {
            str_starts_with($st, 'RTO DELIVERED'), $st === 'RTO_DELIVERED' => 'returned',
            $st === 'DELIVERED' => 'delivered',
            default => null,
        };
        if ($target && $s->status === 'dispatched') {
            $events = json_decode($s->events ?? '[]', true);
            $events[] = ['status' => $target, 'at' => now()->toIso8601ZuluString(), 'by' => 'shiprocket'];
            DB::table('shipments')->where('id', $s->id)->update(['status' => $target, 'events' => json_encode($events), 'updated_at' => now()]);
            $this->audit->record('store.shipment_updated', null, ['order_id' => $s->order_id, 'status' => $target, 'source' => 'shiprocket'], null, 'system');
            if ($target === 'delivered') {
                $order = DB::table('orders')->find($s->order_id);
                app(Notifier::class)->send((int) $order->user_id, 'order', 'Delivered', 'Your gem-store order has been delivered.', $order->ref, route('account.receipt', $order->ref));
            }

            return $target;
        }

        return $s->status;
    }

    /** Hourly: refresh tracking for dispatched Shiprocket shipments (webhook fallback). */
    public function sync(): int
    {
        if (! $this->configured()) {
            return 0;
        }
        $n = 0;
        foreach (DB::table('shipments')->where('provider', 'shiprocket')->where('status', 'dispatched')->whereNotNull('awb')
            ->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', now()->subMinutes(50)))->limit(50)->get() as $s) {
            try {
                $t = $this->call('get', '/courier/track/awb/'.rawurlencode($s->awb));
                $status = $t['tracking_data']['shipment_track'][0]['current_status'] ?? null;
                if ($status) {
                    $this->applyStatus($s->awb, $status);
                    $n++;
                }
            } catch (RuntimeException $e) {
                report($e);
            }
        }

        return $n;
    }
}
