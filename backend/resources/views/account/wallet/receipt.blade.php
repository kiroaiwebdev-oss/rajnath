@php use App\Support\Money; $tz = $u->display_timezone; $gstin = $site('finance.gstin'); @endphp
<x-layouts.base title="Payment receipt" robots="noindex,nofollow" class="bg-white">
    <main id="main" class="mx-auto max-w-2xl px-6 py-10 print:py-0">
        <div class="flex flex-col-reverse gap-4 border-b border-ink-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-lg font-semibold">{{ $site('finance.legal_name') ?? $site('brand.name') }}</p>
                <p class="text-sm text-ink-600">{{ $site('contact.address') }}</p>
                <p class="text-sm text-ink-600">{{ $site('contact.email') }}</p>
                @if ($gstin)<p class="text-sm text-ink-600">GSTIN: {{ $gstin }}</p>@endif
            </div>
            <div class="min-w-0 sm:text-right">
                <h1 class="text-xl">Payment receipt</h1>
                <p class="break-all font-mono text-xs text-ink-600">{{ $p->ref }}</p>
            </div>
        </div>
        <dl class="mt-6 grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-x-4 gap-y-3 text-sm [&_dd]:break-all">
            <dt class="text-ink-600">Received from</dt><dd>{{ $u->name }}</dd>
            <dt class="text-ink-600">Date</dt><dd>{{ $p->captured_at->tz($tz)->format('d M Y, g:i A T') }}</dd>
            <dt class="text-ink-600">For</dt><dd>{{ $p->snapshot['name'] ?? $p->label() }}</dd>
            <dt class="text-ink-600">Method</dt><dd>{{ $p->method ? strtoupper($p->method) : 'Online' }} via Razorpay</dd>
            <dt class="text-ink-600">Razorpay payment ID</dt><dd class="font-mono text-xs">{{ $p->provider_payment_id }}</dd>
            <dt class="text-ink-600 font-semibold">Amount paid</dt><dd class="font-semibold tabular-nums">{{ Money::format($p->amount_minor) }}</dd>
            @if ($p->bonus_minor)<dt class="text-ink-600">Promotional bonus credit</dt><dd class="tabular-nums">{{ Money::format($p->bonus_minor) }} (not charged)</dd>@endif
            @if ($p->refunded_minor)<dt class="text-ink-600">Refunded</dt><dd class="tabular-nums">{{ Money::format($p->refunded_minor) }}</dd>@endif
        </dl>
        <p class="mt-8 text-xs text-ink-600">{{ $gstin ? 'Tax invoices for services are issued when the service is delivered.' : 'This is a payment receipt, not a tax invoice.' }} Wallet money is used for consultations, reports and other services on this website.</p>
        <div class="mt-8 flex gap-3 print:hidden">
            <a href="{{ route('account.payments.show', $p) }}" class="btn-outline">Back</a>
        </div>
    </main>
</x-layouts.base>
