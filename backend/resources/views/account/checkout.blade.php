<x-layouts.account title="Checkout">
    <div class="card max-w-xl p-6" x-data="razorpayCheckout"
         data-key="{{ $rzp['key'] ?? '' }}" data-order="{{ $rzp['order_id'] ?? '' }}" data-amount="{{ $rzp['amount'] ?? 0 }}"
         data-name="{{ $site('brand.name') }}" data-email="{{ $user->email }}" data-contact="{{ $user->phone_e164 }}" data-customer="{{ $user->name }}">
        <h2 class="text-lg">{{ \App\Domain\Payments\Checkout::label($o->kind, $snap) }}</h2>
        
        <dl class="mt-4 space-y-2 text-sm">
            @if ((int) $o->discount_minor > 0)
                <div class="flex justify-between"><dt>Price</dt><dd>{{ \App\Support\Money::format((int) $o->list_minor) }}</dd></div>
                <div class="flex justify-between text-success-700"><dt>Coupon {{ $snap['coupon']['code'] ?? '' }}</dt><dd>−{{ \App\Support\Money::format((int) $o->discount_minor) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt>Total</dt><dd>{{ \App\Support\Money::format((int) $o->subtotal_minor) }}</dd></div>
            @if ($o->wallet_applied_minor)<div class="flex justify-between"><dt>From wallet</dt><dd>−{{ \App\Support\Money::format((int) $o->wallet_applied_minor) }}</dd></div>@endif
            <div class="flex justify-between border-t border-ink-200 pt-2 text-base font-semibold"><dt>To pay now</dt><dd>{{ \App\Support\Money::format((int) $o->payable_minor) }}</dd></div>
        </dl>
        @if (! empty($snap['disclaimer']))<p class="mt-4 rounded-xl bg-ivory-100 p-3 text-xs">{{ $snap['disclaimer'] }}</p>@endif
        @error('payment')<p class="field-error mt-3" role="alert">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('checkout.verify', $o->ref) }}" x-ref="verify">
            @csrf
            <input type="hidden" name="razorpay_payment_id" x-ref="pid"><input type="hidden" name="razorpay_order_id" x-ref="oid"><input type="hidden" name="razorpay_signature" x-ref="sig">
        </form>
        <p class="mt-3 text-sm text-danger-700" role="alert" x-text="error"></p>
        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
            <form method="POST" action="{{ route('checkout.cancel', $o->ref) }}">@csrf<button class="btn-outline w-full">Cancel order</button></form>
            <button type="button" class="btn-primary" @click="pay" :disabled="busy">Pay {{ \App\Support\Money::format((int) $o->payable_minor) }} securely</button>
        </div>
        <p class="mt-4 text-xs text-ink-600">Payments are processed by Razorpay (UPI, cards, net banking, wallets). We never see your card details.</p>
    </div>
    <script src="https://checkout.razorpay.com/v1/checkout.js" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" defer></script>
</x-layouts.account>
