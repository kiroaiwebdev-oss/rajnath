<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Slots;
use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Payments\Coupons;
use App\Domain\Payments\Razorpay;
use App\Domain\Practitioners\Practitioners;
use App\Domain\Store\Store;
use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use App\Models\Offering;
use App\Models\OfferingVariant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** App: wallet, buying (service, consultation slot, recharge, gem), orders and Razorpay verification. */
class CommerceController extends Controller
{
    public function __construct(private Checkout $checkout, private Razorpay $rzp) {}

    public static function order(object $o): array
    {
        $snap = json_decode($o->snapshot, true) ?: [];

        return ['ref' => $o->ref, 'kind' => $o->kind, 'status' => $o->status, 'label' => Checkout::label($o->kind, $snap),
            'subtotal_minor' => (int) $o->subtotal_minor, 'list_minor' => (int) ($o->list_minor ?? $o->subtotal_minor), 'discount_minor' => (int) ($o->discount_minor ?? 0),
            'coupon' => $snap['coupon']['code'] ?? null, 'tax_minor' => (int) ($o->tax_minor ?? 0),
            'wallet_applied_minor' => (int) $o->wallet_applied_minor, 'payable_minor' => (int) $o->payable_minor,
            'refunded_minor' => (int) $o->refunded_minor, 'created_at' => self::iso($o->created_at), 'paid_at' => self::iso($o->paid_at ?? null),
            'next' => in_array($o->status, ['paid', 'partially_refunded'], true) ? Checkout::next($o) : null,
            'invoices' => DocumentApiController::invoicesFor($o),
            // the customer's review of this order, if any: {ref, status}
            'review' => ($rv = DB::table('reviews')->where('order_id', $o->id)->first(['ref', 'status'])) ? ['ref' => $rv->ref, 'status' => $rv->status] : null];
    }

    public static function iso(?string $t): ?string
    {
        return $t ? CarbonImmutable::parse($t, 'UTC')->toIso8601ZuluString() : null;
    }

    private function payload(array $r, Request $request): array
    {
        $o = DB::table('orders')->find($r['order']->id);
        $rp = $r['razorpay'];

        return ['order' => self::order($o), 'razorpay' => $rp ? $rp + ['name' => config('brand')['brand.name'], 'description' => Checkout::label($o->kind, json_decode($o->snapshot, true)),
            'prefill' => ['email' => $request->user()->email, 'contact' => $request->user()->phone_e164]] : null];
    }

    private function unavailable(RuntimeException $e): JsonResponse
    {
        report($e);

        return AuthController::error('payments_unavailable', 'Online payment is not available right now. Please try again later.', 503);
    }

    public function wallet(Request $request, Ledger $ledger): JsonResponse
    {
        $u = $request->user();
        $acct = DB::table('ledger_accounts')->where('code', "customer_wallet:{$u->id}")->first();
        $tx = $acct ? DB::table('ledger_entries as e')->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')->where('e.account_id', $acct->id)
            ->orderByDesc('e.id')->limit(50)->get(['e.direction', 'e.amount_minor', 't.kind', 't.memo', 't.occurred_at']) : collect();

        return response()->json(['balance_minor' => $ledger->walletBalance($u->id),
            'packages' => DB::table('recharge_packages')->where('active', true)->orderBy('sort')->orderBy('amount_minor')->get(['id', 'amount_minor', 'bonus_minor'])
                ->map(fn ($p) => ['id' => $p->id, 'amount_minor' => (int) $p->amount_minor, 'bonus_minor' => (int) $p->bonus_minor]),
            'transactions' => $tx->map(fn ($t) => ['kind' => $t->kind, 'amount_minor' => (int) $t->amount_minor, 'direction' => $t->direction, 'memo' => $t->memo, 'at' => self::iso($t->occurred_at)])]);
    }

    private function variant(string $ref): OfferingVariant
    {
        $v = OfferingVariant::where('ref', $ref)->first();
        if (! $v) {
            throw ValidationException::withMessages(['variant' => 'This option is not available.']);
        }

        return $v;
    }

    public function service(Request $request): JsonResponse
    {
        $d = $request->validate(['variant' => ['required', 'string', 'size:26'], 'use_wallet' => ['nullable', 'boolean'], 'key' => ['required', 'string', 'max:64'], 'coupon' => ['nullable', 'string', 'max:32']]);
        try {
            return response()->json($this->payload($this->checkout->startServiceOrder($request->user(), $this->variant($d['variant']), (bool) ($d['use_wallet'] ?? false), $d['key'], [], $d['coupon'] ?? null), $request));
        } catch (RuntimeException $e) {
            return $this->unavailable($e);
        }
    }

    public function slots(Request $request, Slots $slots): JsonResponse
    {
        $d = $request->validate(['variant' => ['required', 'string', 'size:26'], 'practitioner' => ['nullable', 'string', 'size:26']]);
        $v = $this->variant($d['variant']);
        abort_unless(Slots::bookable($v) && $v->active, 404);
        $p = Practitioners::forBooking($v, $d['practitioner'] ?? null);

        return response()->json(['data' => (object) $slots->available((int) $v->duration_min, 21, (int) ($p->id ?? 0))]);
    }

    public function booking(Request $request, Slots $slots): JsonResponse
    {
        $d = $request->validate(['variant' => ['required', 'string', 'size:26'], 'start' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}Z$/'], 'practitioner' => ['nullable', 'string', 'size:26'],
            'birth_profile' => ['nullable', 'string', 'size:26'], 'topic' => ['nullable', 'string', 'max:1000'], 'use_wallet' => ['nullable', 'boolean'], 'key' => ['required', 'string', 'max:64'], 'coupon' => ['nullable', 'string', 'max:32']]);
        $v = $this->variant($d['variant']);
        $o = DB::table('offerings')->find($v->offering_id);
        if (! $o || $o->status !== 'active' || ! $v->active) {
            throw ValidationException::withMessages(['variant' => 'This option is not available.']);
        }
        $bp = ! empty($d['birth_profile']) ? BirthProfile::where('user_id', $request->user()->id)->where('ref', $d['birth_profile'])->value('id') : null;
        try {
            [$b, $r] = $slots->hold($request->user(), $v, $d['start'], $bp, $d['topic'] ?? null, $d['key'], (bool) ($d['use_wallet'] ?? false), $d['practitioner'] ?? null, $d['coupon'] ?? null);
        } catch (RuntimeException $e) {
            return $this->unavailable($e);
        }
        $b = DB::table('bookings')->find($b->id);

        return response()->json($this->payload($r, $request) + ['booking' => ['ref' => $b->ref, 'status' => $b->status]]);
    }

    public function recharge(Request $request): JsonResponse
    {
        $d = $request->validate(['package' => ['required', 'integer'], 'key' => ['required', 'string', 'max:64']]);
        $pkg = DB::table('recharge_packages')->where('id', $d['package'])->where('active', true)->first();
        if (! $pkg) {
            throw ValidationException::withMessages(['package' => 'Choose a recharge amount.']);
        }
        try {
            return response()->json($this->payload($this->checkout->startRecharge($request->user(), $pkg, $d['key']), $request));
        } catch (RuntimeException $e) {
            return $this->unavailable($e);
        }
    }

    public function product(Request $request, Store $store): JsonResponse
    {
        $d = $request->validate(['product' => ['required', 'string', 'size:26'], 'quantity' => ['required', 'integer', 'min:1', 'max:10'], 'key' => ['required', 'string', 'max:64'],
            'address' => ['required', 'array'], 'address.name' => ['required', 'string', 'max:120'], 'address.phone' => ['required', 'regex:/^(?:\+?91)?[6-9]\d{9}$/'],
            'address.line1' => ['required', 'string', 'max:200'], 'address.line2' => ['nullable', 'string', 'max:200'], 'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'max:100'], 'address.pincode' => ['required', 'regex:/^[1-9]\d{5}$/'], 'coupon' => ['nullable', 'string', 'max:32']],
            ['address.phone.regex' => 'Enter a 10-digit Indian mobile number.', 'address.pincode.regex' => 'Enter a 6-digit PIN code.']);
        $a = $d['address'];
        $address = ['name' => $a['name'], 'phone' => $a['phone'], 'line1' => $a['line1'], 'line2' => $a['line2'] ?? null, 'city' => $a['city'], 'state' => $a['state'], 'pincode' => $a['pincode']];
        try {
            return response()->json($this->payload($store->buy($request->user(), $d['product'], (int) $d['quantity'], $address, $d['key'], $d['coupon'] ?? null), $request));
        } catch (RuntimeException $e) {
            return $this->unavailable($e);
        }
    }

    /** Preview a coupon before paying. The order applies it again (and locks it) at checkout. */
    public function checkCoupon(Request $request, Coupons $coupons): JsonResponse
    {
        $d = $request->validate(['code' => ['required', 'string', 'max:32'], 'variant' => ['required_without:product', 'nullable', 'string', 'size:26'],
            'product' => ['required_without:variant', 'nullable', 'string', 'size:26'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:10']]);
        if (! empty($d['variant'])) {
            $v = $this->variant($d['variant']);
            $o = Offering::with('family')->find($v->offering_id);
            if (! $o || $o->status !== 'active' || ! $v->active || ! $v->price_minor) {
                throw ValidationException::withMessages(['variant' => 'This option is not available.']);
            }
            [$scope, $amount] = [$o->family->code, (int) $v->price_minor];
        } else {
            $p = DB::table('products')->where('ref', $d['product'])->where('active', true)->first();
            if (! $p) {
                throw ValidationException::withMessages(['product' => 'This item is not available.']);
            }
            [$scope, $amount] = ['product', (int) $p->price_minor * (int) ($d['quantity'] ?? 1)];
        }
        $q = $coupons->quote($request->user(), $d['code'], $scope, $amount);
        $discount = $scope === 'product' ? min($q['discount'], $amount - 100) : $q['discount'];

        return response()->json(['data' => ['code' => $q['coupon']->code, 'description' => Coupons::describe($q['coupon']),
            'list_minor' => $amount, 'discount_minor' => $discount, 'total_minor' => $amount - $discount]]);
    }

    private function mine(Request $request, string $ref): object
    {
        $o = DB::table('orders')->where('ref', $ref)->where('user_id', $request->user()->id)->first();
        abort_unless($o, 404);

        return $o;
    }

    public function orders(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('orders')->where('user_id', $request->user()->id)->orderByDesc('id')->limit(100)->get()->map(fn ($o) => self::order($o))]);
    }

    public function show(Request $request, string $ref): JsonResponse
    {
        return response()->json(['data' => self::order($this->mine($request, $ref))]);
    }

    /** Same checks as the website: signature, then the captured amount is confirmed with Razorpay. */
    public function verify(Request $request, string $ref): JsonResponse
    {
        $o = $this->mine($request, $ref);
        $d = $request->validate(['razorpay_payment_id' => ['required', 'string', 'max:60'], 'razorpay_order_id' => ['required', 'string', 'max:60'], 'razorpay_signature' => ['required', 'string', 'max:128']]);
        $p = DB::table('payments')->where('order_id', $o->id)->where('provider_order_id', $d['razorpay_order_id'])->first();
        if (! $p || ! $this->rzp->verifyCheckoutSignature($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) {
            return AuthController::error('payment_unverified', 'We could not verify this payment. If money was deducted, it will be confirmed automatically or refunded.', 422);
        }
        try {
            $cap = collect($this->rzp->orderPayments($d['razorpay_order_id']))->firstWhere('id', $d['razorpay_payment_id']);
            if ($cap && $cap['status'] === 'captured') {
                $this->checkout->applyCapture($d['razorpay_order_id'], $cap['id'], (int) $cap['amount'], $cap['method'] ?? null);
            } else {
                DB::table('payments')->where('id', $p->id)->where('status', '!=', 'captured')->update(['status' => 'authorized', 'provider_payment_id' => $d['razorpay_payment_id'], 'updated_at' => now()]);
            }
        } catch (RuntimeException $e) {
            report($e);
            DB::table('payments')->where('id', $p->id)->where('status', '!=', 'captured')->update(['status' => 'ambiguous', 'provider_payment_id' => $d['razorpay_payment_id'], 'updated_at' => now()]);
        }

        return response()->json(['data' => self::order(DB::table('orders')->find($o->id))]);
    }

    public function cancel(Request $request, string $ref): JsonResponse
    {
        $o = $this->mine($request, $ref);
        $this->checkout->cancel($o);

        return response()->json(['data' => self::order(DB::table('orders')->find($o->id))]);
    }

    // ---------- public ----------
    public static function productToApi(object $p): array
    {
        return ['ref' => $p->ref, 'slug' => $p->slug, 'name' => $p->name, 'category' => $p->category, 'summary' => $p->summary, 'description' => $p->description,
            'weight' => $p->weight, 'origin' => $p->origin, 'certification' => $p->certification, 'price_minor' => (int) $p->price_minor,
            'in_stock' => $p->stock > 0, 'max_per_order' => min((int) $p->max_per_order, max(1, (int) $p->stock)), 'image_url' => $p->image_path ? url('/'.ltrim($p->image_path, '/')) : null];
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('products')->where('active', true)->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('sort')->orderBy('name')->get()->map(fn ($p) => self::productToApi($p))]);
    }

    public function productShow(string $slug): JsonResponse
    {
        $p = DB::table('products')->where('slug', $slug)->where('active', true)->first();

        return $p ? response()->json(['data' => self::productToApi($p)]) : AuthController::error('not_found', 'Item not found.', 404);
    }

    public function practitioners(Request $request): JsonResponse
    {
        $tier = Practitioners::TIER_OFFERINGS[(string) $request->query('offering')] ?? null;

        return response()->json(['data' => $tier ? Practitioners::forTier($tier)->map(fn ($p) => Practitioners::toApi($p)) : []]);
    }
}
