<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coupon codes (FI-13). A coupon is checked and redeemed inside the transaction that creates the
 * order, with the coupon row locked, so usage limits hold under concurrent checkouts.
 * Uses by orders that were cancelled or failed do not count.
 * Abuse limits: total uses, uses per customer, first-order-only, minimum order, maximum discount,
 * validity window. Wallet recharges never take coupons.
 */
class Coupons
{
    /** Order statuses that count as a use of the coupon. */
    public const COUNTED = ['pending_payment', 'paid', 'partially_refunded', 'refunded'];

    /** What a coupon can apply to: service family codes plus "product" (gem store). */
    public const SCOPES = ['consultation' => 'Consultations', 'vastu' => 'Vastu', 'remedy' => 'Remedies', 'puja' => 'Puja', 'chadhava' => 'Chadhava',
        'report' => 'Reports', 'bundle' => 'Report bundles', 'written_question' => 'Ask a question', 'product' => 'Gem store'];

    public static function normalise(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    /**
     * Validates a coupon for an order and returns the discount. Locks the coupon row when called
     * inside a transaction ($lock = true).
     *
     * @return array{coupon: object, discount: int}
     */
    public function quote(User $user, string $code, string $scope, int $amountMinor, bool $lock = false): array
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['coupon' => $m]);
        $q = DB::table('coupons')->where('code', self::normalise($code));
        $c = $lock ? $q->lockForUpdate()->first() : $q->first();
        if (! $c || ! $c->active) {
            $fail('This coupon code is not valid.');
        }
        if (($c->starts_at && now()->lt($c->starts_at)) || ($c->ends_at && now()->gt($c->ends_at))) {
            $fail('This coupon is not valid today.');
        }
        $scopes = $c->applies_to ? json_decode($c->applies_to, true) : null;
        if ($scopes && ! in_array($scope, $scopes, true)) {
            $fail('This coupon cannot be used for this item.');
        }
        if ($amountMinor < (int) $c->min_order_minor) {
            $fail('This coupon needs an order of at least '.Money::format((int) $c->min_order_minor).'.');
        }
        $uses = fn () => DB::table('coupon_redemptions as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('r.coupon_id', $c->id)->whereIn('o.status', self::COUNTED);
        if ($c->usage_limit !== null && $uses()->count() >= $c->usage_limit) {
            $fail('This coupon has been fully used.');
        }
        if ($uses()->where('r.user_id', $user->id)->count() >= max(1, (int) $c->per_user_limit)) {
            $fail('You have already used this coupon.');
        }
        if ($c->first_order_only && DB::table('orders')->where('user_id', $user->id)->where('kind', '!=', 'recharge')->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->exists()) {
            $fail('This coupon is for your first order only.');
        }

        return ['coupon' => $c, 'discount' => self::discountFor($c, $amountMinor)];
    }

    /** Discount in paise. Never leaves a charge between ₹0 and ₹1 (the gateway minimum). */
    public static function discountFor(object $c, int $amountMinor): int
    {
        $d = $c->type === 'percent' ? intdiv($amountMinor * min(100, (int) $c->value), 100) : (int) $c->value;
        if ($c->max_discount_minor !== null) {
            $d = min($d, (int) $c->max_discount_minor);
        }
        $d = max(0, min($d, $amountMinor));
        $net = $amountMinor - $d;
        if ($net > 0 && $net < 100) {
            $d = $amountMinor - 100;
        }

        return $d;
    }

    /** Short human description, e.g. "20% off (up to ₹500)". */
    public static function describe(object $c): string
    {
        $s = $c->type === 'percent' ? $c->value.'% off' : Money::format((int) $c->value).' off';
        if ($c->type === 'percent' && $c->max_discount_minor !== null) {
            $s .= ' (up to '.Money::format((int) $c->max_discount_minor).')';
        }

        return $s;
    }
}
