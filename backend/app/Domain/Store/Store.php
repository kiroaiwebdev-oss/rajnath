<?php

namespace App\Domain\Store;

use App\Domain\Payments\Checkout;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gem & remedy store. Stock is reserved (row lock) when the order is created, committed when the
 * payment is captured, and released if the order is cancelled or expires — so two customers can
 * never buy the last stone.
 */
class Store
{
    public const CATEGORIES = ['gemstone' => 'Gemstones', 'rudraksha' => 'Rudraksha', 'yantra' => 'Yantra', 'other' => 'Other remedies'];

    public function __construct(private Checkout $checkout, private AuditLogger $audit) {}

    public static function addressRules(): array
    {
        return [
            'ship_name' => ['required', 'string', 'max:120'],
            'ship_phone' => ['required', 'string', 'regex:/^(?:\+?91|0)?[6-9]\d{9}$/'],
            'ship_line1' => ['required', 'string', 'max:200'],
            'ship_line2' => ['nullable', 'string', 'max:200'],
            'ship_city' => ['required', 'string', 'max:100'],
            'ship_state' => ['required', 'string', 'max:100'],
            'ship_pincode' => ['required', 'regex:/^[1-9]\d{5}$/'],
        ];
    }

    public function buy(User $user, string $productRef, int $qty, array $address, string $key): array
    {
        $existing = DB::table('orders')->where('idempotency_key', 'u'.$user->id.':'.$key)->first();
        if ($existing) {
            return $this->checkout->checkoutPayload($existing);
        }

        $snapshot = DB::transaction(function () use ($productRef, $qty) {
            $p = DB::table('products')->where('ref', $productRef)->lockForUpdate()->first();
            if (! $p || ! $p->active) {
                throw ValidationException::withMessages(['product' => 'This item is not available.']);
            }
            if ($qty < 1 || $qty > $p->max_per_order) {
                throw ValidationException::withMessages(['quantity' => 'You can order up to '.$p->max_per_order.' of this item.']);
            }
            if ($p->stock < $qty) {
                throw ValidationException::withMessages(['quantity' => $p->stock ? 'Only '.$p->stock.' left in stock.' : 'Out of stock.']);
            }
            DB::table('products')->where('id', $p->id)->update(['stock' => $p->stock - $qty, 'updated_at' => now()]);

            return ['product' => ['id' => $p->id, 'ref' => $p->ref, 'name' => $p->name, 'category' => $p->category, 'weight' => $p->weight, 'certification' => $p->certification],
                'quantity' => $qty, 'unit_price_minor' => (int) $p->price_minor, 'price_minor' => (int) $p->price_minor * $qty, 'currency' => 'INR', 'shipping' => 'Included (India only)'];
        });

        try {
            $r = $this->checkout->startProductOrder($user, $snapshot, $key);
        } catch (\Throwable $e) {
            DB::table('products')->where('id', $snapshot['product']['id'])->increment('stock', $qty); // give the stock back
            throw $e;
        }
        $order = $r['order'];
        DB::table('stock_reservations')->insertOrIgnore(['product_id' => $snapshot['product']['id'], 'order_id' => $order->id, 'quantity' => $qty, 'status' => 'held', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shipments')->insertOrIgnore(['order_id' => $order->id, 'address' => Crypt::encryptString(json_encode($address)), 'status' => 'awaiting_payment', 'created_at' => now(), 'updated_at' => now()]);
        if ($order->status === 'paid') {
            $this->onPaid($order);
        }

        return $r;
    }

    public function onPaid(object $order): void
    {
        DB::table('stock_reservations')->where('order_id', $order->id)->where('status', 'held')->update(['status' => 'committed', 'updated_at' => now()]);
        DB::table('shipments')->where('order_id', $order->id)->where('status', 'awaiting_payment')->update(['status' => 'awaiting_dispatch', 'updated_at' => now()]);
    }

    /** Called when an unpaid product order is cancelled or expires. Idempotent. */
    public function release(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $r = DB::table('stock_reservations')->where('order_id', $orderId)->where('status', 'held')->lockForUpdate()->first();
            if (! $r) {
                return;
            }
            DB::table('products')->where('id', $r->product_id)->increment('stock', $r->quantity);
            DB::table('stock_reservations')->where('id', $r->id)->update(['status' => 'released', 'updated_at' => now()]);
            DB::table('shipments')->where('order_id', $orderId)->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
    }

    public function updateShipment(int $orderId, array $d, User $by): void
    {
        $s = DB::table('shipments')->where('order_id', $orderId)->first();
        $allowed = ['awaiting_dispatch' => ['dispatched', 'cancelled'], 'dispatched' => ['delivered', 'returned'], 'delivered' => [], 'returned' => [], 'cancelled' => []];
        if ($d['status'] !== $s->status && ! in_array($d['status'], $allowed[$s->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'A shipment cannot move from '.str_replace('_', ' ', $s->status).' to '.str_replace('_', ' ', $d['status']).'.']);
        }
        if ($d['status'] === 'dispatched' && (empty($d['courier']) || empty($d['awb']))) {
            throw ValidationException::withMessages(['awb' => 'Enter courier and AWB / tracking number when dispatching.']);
        }
        $events = json_decode($s->events ?? '[]', true);
        if ($d['status'] !== $s->status) {
            $events[] = ['status' => $d['status'], 'at' => now()->toIso8601ZuluString(), 'by' => $by->ref];
        }
        DB::table('shipments')->where('id', $s->id)->update(['status' => $d['status'], 'courier' => $d['courier'] ?? $s->courier, 'awb' => $d['awb'] ?? $s->awb,
            'tracking_url' => $d['tracking_url'] ?? $s->tracking_url, 'events' => json_encode($events), 'updated_at' => now()]);
        $this->audit->record('store.shipment_updated', null, ['order_id' => $orderId, 'status' => $d['status']], $by);
    }

    public static function address(object $shipment): array
    {
        return json_decode(Crypt::decryptString($shipment->address), true);
    }
}
