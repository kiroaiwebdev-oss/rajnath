@php use App\Support\Money; @endphp
<x-layouts.account title="Secure payment">
    <section class="card mx-auto max-w-lg p-6" x-data="rzpCheckout" data-autoopen="1" aria-labelledby="co-h">
        <h2 id="co-h" class="text-lg">{{ $p->snapshot['name'] ?? $p->label() }}</h2>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-ink-600">Amount to pay</dt><dd class="font-semibold tabular-nums">{{ Money::format($p->amount_minor) }}</dd></div>
            @if ($p->bonus_minor)<div class="flex justify-between"><dt class="text-ink-600">Bonus credit</dt><dd class="tabular-nums">+ {{ Money::format($p->bonus_minor) }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-ink-600">Reference</dt><dd class="font-mono text-xs">{{ $p->ref }}</dd></div>
        </dl>
        @if ($p->provider_mode === 'test')<p class="mt-3"><span class="badge-gold">Test mode — no real money is charged</span></p>@endif
        <p class="mt-4 min-h-6 text-sm text-ink-700" role="status" aria-live="polite" x-text="message"></p>
        <button type="button" class="btn-primary mt-2 w-full" @click="pay" :disabled="busy">Pay {{ Money::format($p->amount_minor) }}</button>
        <a href="{{ route('account.payments.show', $p) }}" class="btn-ghost mt-2 w-full">I have already paid — check status</a>
        <form x-ref="form" method="POST" action="{{ route('account.payments.confirm', $p) }}" class="hidden">
            @csrf
            <input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_signature">
        </form>
        <script type="application/json" id="rzp-options">@json($options)</script>
        <noscript><p class="mt-3 text-sm text-danger-700">JavaScript is needed for the payment window.</p></noscript>
    </section>
</x-layouts.account>
