<x-layouts.account title="Receipt">
    <article class="card max-w-2xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><p class="text-sm text-ink-600">{{ $site('brand.name') }}</p><h2 class="text-xl">Order {{ substr($o->ref, -10) }}</h2></div>
            <span @class(['badge-success' => $o->status === 'paid', 'badge-gold' => $o->status === 'pending_payment', 'badge-ink' => ! in_array($o->status, ['paid', 'pending_payment'])])>{{ ucwords(str_replace('_', ' ', $o->status)) }}</span>
        </div>
        @if ($o->status === 'pending_payment')
            <p class="mt-4 rounded-xl bg-gold-50 p-3 text-sm" role="status">We are confirming your payment with the bank. This page updates when it is confirmed (usually within a few minutes). If money was deducted and the payment is not confirmed, it is refunded automatically by your bank or by us.</p>
        @endif
        <dl class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Item</dt><dd class="text-right font-medium">{{ $o->kind === 'recharge' ? 'Wallet recharge' : $snap['offering']['title'].' — '.$snap['variant']['name'] }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Date</dt><dd>{{ \Illuminate\Support\Carbon::parse($o->created_at)->tz(auth()->user()->display_timezone)->format('d M Y, g:i A') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Amount</dt><dd>{{ \App\Support\Money::format((int) $o->subtotal_minor) }}</dd></div>
            @if ($o->wallet_applied_minor)<div class="flex justify-between gap-3"><dt class="text-ink-600">Paid from wallet</dt><dd>{{ \App\Support\Money::format((int) $o->wallet_applied_minor) }}</dd></div>@endif
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Paid online</dt><dd>{{ \App\Support\Money::format((int) $o->payable_minor) }}{{ $payment ? ' ('.($payment->method ?? 'Razorpay').')' : '' }}</dd></div>
            @if (($snap['bonus_minor'] ?? 0) > 0)<div class="flex justify-between gap-3"><dt class="text-ink-600">Bonus credit</dt><dd>{{ \App\Support\Money::format((int) $snap['bonus_minor']) }}</dd></div>@endif
            @foreach ($refunds as $r)<div class="flex justify-between gap-3 text-success-700"><dt>Refunded ({{ $r->destination === 'wallet' ? 'to wallet' : 'to original payment' }})</dt><dd>−{{ \App\Support\Money::format((int) $r->amount_minor) }}</dd></div>@endforeach
        </dl>
        @if ($payment)<p class="mt-4 text-xs text-ink-600">Payment reference: {{ $payment->provider_payment_id }}</p>@endif
        @if ($o->kind === 'service' && $o->status === 'paid')<p class="mt-4 text-sm">Our team will contact you to schedule this service. You can also call {{ $site('contact.phone') }}.</p>@endif
    </article>
</x-layouts.account>
