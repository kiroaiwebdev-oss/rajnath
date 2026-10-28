@php use App\Support\Money; $tz = auth()->user()->display_timezone; @endphp
<x-layouts.account title="Payment details">
    @error('payment')<div role="alert" class="card mb-5 border-red-200 bg-red-50 px-4 py-3 text-sm text-danger-700">{{ $message }}</div>@enderror
    <section class="card max-w-2xl p-6" aria-labelledby="pd-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="pd-h" class="text-lg">{{ $p->snapshot['name'] ?? $p->label() }}</h2>
            <x-payment-status :status="$p->status" />
        </div>
        <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div><dt class="text-ink-600">Amount</dt><dd class="font-semibold tabular-nums">{{ Money::format($p->amount_minor) }}</dd></div>
            @if ($p->bonus_minor)<div><dt class="text-ink-600">Bonus credit</dt><dd class="tabular-nums">{{ Money::format($p->bonus_minor) }}</dd></div>@endif
            <div><dt class="text-ink-600">Started</dt><dd>{{ $p->created_at->tz($tz)->format('d M Y, g:i A') }}</dd></div>
            @if ($p->captured_at)<div><dt class="text-ink-600">Paid</dt><dd>{{ $p->captured_at->tz($tz)->format('d M Y, g:i A') }}</dd></div>@endif
            @if ($p->method)<div><dt class="text-ink-600">Method</dt><dd>{{ strtoupper($p->method) }}</dd></div>@endif
            <div><dt class="text-ink-600">Reference</dt><dd class="font-mono text-xs break-all">{{ $p->ref }}</dd></div>
            @if ($p->provider_payment_id)<div><dt class="text-ink-600">Razorpay payment ID</dt><dd class="font-mono text-xs break-all">{{ $p->provider_payment_id }}</dd></div>@endif
        </dl>

        <div class="mt-5 rounded-xl bg-ivory-100 p-4 text-sm text-ink-700">
            @switch($p->status)
                @case('captured') Payment confirmed. {{ $p->purpose === 'wallet_recharge' ? 'The amount has been added to your wallet.' : 'Your order is confirmed.' }} @break
                @case('created') Waiting for you to complete the payment. @break
                @case('pending') We are confirming this payment with Razorpay. This usually takes under a minute. @break
                @case('ambiguous') We are checking this payment with Razorpay. If money was taken, it will either be added to your wallet or returned to you. Our team has been alerted. @break
                @case('failed') The payment did not go through{{ $p->status_reason ? ': '.$p->status_reason : '.' }} If money was taken from your account, Razorpay returns it automatically, usually within 5–7 working days. @break
                @case('cancelled') This payment was not completed. @break
            @endswitch
        </div>

        @if ($p->refunds->isNotEmpty())
            <h3 class="mt-6 text-base">Refunds</h3>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($p->refunds as $r)
                    <li class="flex flex-wrap justify-between gap-2 rounded-xl border border-ink-200/70 p-3"><span>{{ Money::format($r->amount_minor) }} · {{ $r->created_at->tz($tz)->format('d M Y') }}</span><span class="{{ $r->status === 'processed' ? 'badge-success' : ($r->status === 'failed' || $r->status === 'rejected' ? 'badge-danger' : 'badge-gold') }}">{{ \App\Models\Refund::STATUSES[$r->status] }}</span></li>
                @endforeach
            </ul>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            @if (in_array($p->status, ['created', 'failed'], true) && $p->provider_order_id && $p->created_at->gt(now()->subDay()))
                <a href="{{ route('account.payments.checkout', $p) }}" class="btn-primary">{{ $p->status === 'failed' ? 'Try again' : 'Complete payment' }}</a>
            @endif
            @if (! in_array($p->status, ['captured', 'cancelled'], true))
                <form method="POST" action="{{ route('account.payments.refresh', $p) }}">@csrf<button class="btn-outline">Check status again</button></form>
            @endif
            @if ($p->status === 'captured')<a href="{{ route('account.payments.receipt', $p) }}" class="btn-outline">View receipt</a>@endif
            <a href="{{ route('account.wallet') }}" class="btn-ghost">Back to wallet</a>
        </div>
    </section>
</x-layouts.account>
