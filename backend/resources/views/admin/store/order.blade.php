<x-layouts.admin :title="'Gem order '.substr($o->ref, -10)">
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card p-5 text-sm">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="text-ink-600">Item</dt><dd class="font-medium">{{ $snap['product']['name'] }} × {{ $snap['quantity'] }}</dd></div>
                <div><dt class="text-ink-600">Amount</dt><dd class="font-medium">{{ \App\Support\Money::format((int) $o->subtotal_minor) }} · {{ str_replace('_', ' ', $o->status) }}</dd></div>
                <div><dt class="text-ink-600">Certificate</dt><dd>{{ $snap['product']['certification'] ?? '—' }}</dd></div>
                <div><dt class="text-ink-600">Weight</dt><dd>{{ $snap['product']['weight'] ?? '—' }}</dd></div>
            </dl>
            <h2 class="mt-6 text-base">Deliver to</h2>
            <address class="mt-2 not-italic">{{ $address['name'] }}<br>{{ $address['line1'] }}@if ($address['line2'])<br>{{ $address['line2'] }}@endif<br>{{ $address['city'] }}, {{ $address['state'] }} {{ $address['pincode'] }}<br>{{ $address['phone'] }}</address>
            <h2 class="mt-6 text-base">History</h2>
            <ul class="mt-2 space-y-1">@forelse (json_decode($s->events ?? '[]', true) as $e)<li>{{ str_replace('_', ' ', $e['status']) }} — {{ \Illuminate\Support\Carbon::parse($e['at'])->tz('Asia/Kolkata')->format('d M Y, g:i A') }}</li>@empty<li class="text-ink-600">No updates yet.</li>@endforelse</ul>
        </section>
        @if (in_array($o->status, ['paid', 'partially_refunded', 'refunded']))
        <form method="POST" action="{{ route('admin.store.shipment', $o->ref) }}" class="card h-fit space-y-4 p-5">
            @csrf @method('PUT') <h2 class="text-lg">Shipment</h2>
            <x-select name="status" label="Status" :value="$s->status" :options="['awaiting_dispatch' => 'Awaiting dispatch', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'returned' => 'Returned', 'cancelled' => 'Cancelled']" />
            <x-field name="courier" label="Courier" :value="$s->courier" />
            <x-field name="awb" label="AWB / tracking number" :value="$s->awb" />
            <x-field name="tracking_url" label="Tracking link (https)" :value="$s->tracking_url" />
            <button class="btn-primary w-full">Save</button>
            <p class="text-xs text-ink-600">Refunds for cancelled or returned items: Finance › order › Request refund.</p>
        </form>
        @else
        <p class="card h-fit p-5 text-sm">Not paid yet — nothing to dispatch.</p>
        @endif
    </div>
</x-layouts.admin>
