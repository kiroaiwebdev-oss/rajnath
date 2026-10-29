<x-layouts.admin :title="'Order '.substr($o->ref, -10)">
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card p-5 text-sm">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="text-ink-600">Customer</dt><dd class="font-medium">{{ $customer->name }}</dd></div>
                <div><dt class="text-ink-600">Status</dt><dd class="font-medium">{{ str_replace('_', ' ', $o->status) }}</dd></div>
                <div><dt class="text-ink-600">Item</dt><dd class="font-medium">{{ \App\Domain\Payments\Checkout::label($o->kind, $snap) }}</dd></div>
                <div><dt class="text-ink-600">Total / wallet / online</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format((int) $o->subtotal_minor) }} / {{ \App\Support\Money::format((int) $o->wallet_applied_minor) }} / {{ \App\Support\Money::format((int) $o->payable_minor) }}</dd></div>
                <div><dt class="text-ink-600">Refunded</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format((int) $o->refunded_minor) }}</dd></div>
                @if ((int) $o->discount_minor > 0)<div><dt class="text-ink-600">Coupon</dt><dd class="font-medium">{{ $snap['coupon']['code'] ?? '' }} · −{{ \App\Support\Money::format((int) $o->discount_minor) }} (price {{ \App\Support\Money::format((int) $o->list_minor) }})</dd></div>@endif
                @if ((int) $o->tax_minor > 0)<div><dt class="text-ink-600">GST included</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format((int) $o->tax_minor) }}</dd></div>@endif
            </dl>
            @php $invs = \Illuminate\Support\Facades\DB::table('invoices')->where('order_id', $o->id)->orderBy('id')->get(); @endphp
            @if ($invs->isNotEmpty())
                <h2 class="mt-6 text-base">Invoices</h2>
                <ul class="mt-2 space-y-1">@foreach ($invs as $inv)<li>{{ $inv->type === 'invoice' ? 'Tax invoice' : 'Credit note' }} {{ $inv->number }} · {{ \App\Support\Money::format((int) $inv->total_minor) }} · <a href="{{ route('admin.finance.invoice', $inv->ref) }}">View</a> · <a href="{{ route('admin.finance.invoice', [$inv->ref, 'pdf' => 1]) }}">PDF</a></li>@endforeach</ul>
            @endif
            <h2 class="mt-6 text-base">Payments</h2>
            <ul class="mt-2 space-y-1">@foreach ($payments as $p)<li>{{ $p->provider_order_id }} · {{ $p->provider_payment_id ?? '—' }} · {{ \App\Support\Money::format((int) $p->amount_minor) }} · <strong>{{ $p->status }}</strong> {{ $p->error }}</li>@endforeach</ul>
            <h2 class="mt-6 text-base">Refunds</h2>
            <ul class="mt-2 space-y-1">@forelse ($refunds as $r)<li>{{ \App\Support\Money::format((int) $r->amount_minor) }} to {{ $r->destination }} · {{ $r->status }} · {{ $r->reason }}</li>@empty<li class="text-ink-600">None</li>@endforelse</ul>
            <details class="mt-6"><summary class="cursor-pointer text-ink-600">Purchase snapshot</summary><pre class="mt-2 overflow-x-auto rounded-xl bg-ivory-100 p-3 text-xs">{{ json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
        </section>
        @can('refunds.request')
        @if (in_array($o->status, ['paid', 'partially_refunded']))
        <form method="POST" action="{{ route('admin.finance.refund.request', $o->ref) }}" class="card h-fit space-y-4 p-5">
            @csrf <h2 class="text-lg">Request refund</h2>
            <x-field name="amount" label="Amount (₹)" inputmode="decimal" required />
            <x-select name="destination" label="Refund to" :options="['source' => 'Original payment (Razorpay)', 'wallet' => 'Customer wallet']" />
            <x-field name="reason" label="Reason" required />
            <p class="text-xs text-ink-600">A different person with approval rights must approve.</p>
            <button class="btn-primary w-full">Submit request</button>
        </form>
        @endif
        @endcan
    </div>
</x-layouts.admin>
