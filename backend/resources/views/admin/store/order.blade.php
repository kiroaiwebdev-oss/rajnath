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
        <div class="space-y-4">
        <section class="card space-y-3 p-5 text-sm" aria-labelledby="cr-h">
            <h2 id="cr-h" class="text-lg">Courier (Shiprocket)</h2>
            @error('courier')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @if ($s->provider_error)<p class="text-danger-700">{{ $s->provider_error }}</p>@endif
            @if ($s->provider === 'shiprocket' && $s->awb)
                <p>{{ $s->courier }} · AWB {{ $s->awb }}<br>Courier status: {{ $s->provider_status ?? '—' }}@if ($s->synced_at) <span class="text-xs text-ink-600">(updated {{ \Illuminate\Support\Carbon::parse($s->synced_at)->tz('Asia/Kolkata')->format('d M, g:i A') }})</span>@endif</p>
                @if ($s->label_url)<p><a href="{{ $s->label_url }}" rel="noopener" target="_blank">Print shipping label</a></p>@endif
            @elseif ($s->status === 'awaiting_dispatch' && $courierReady)
                <form method="POST" action="{{ route('admin.store.courier', $o->ref) }}">@csrf<button class="btn-primary w-full">Book courier and pickup</button></form>
                <p class="text-xs text-ink-600">Creates the Shiprocket order, gets the AWB, requests pickup from your pickup address and marks the order dispatched. Delivery updates arrive automatically.</p>
            @elseif (! $courierReady)
                <p class="text-ink-600">Shiprocket is not set up. Add it in Admin › Payments setup, or enter the courier and AWB below.</p>
            @endif
        </section>
        <form method="POST" action="{{ route('admin.store.shipment', $o->ref) }}" class="card h-fit space-y-4 p-5">
            @csrf @method('PUT') <h2 class="text-lg">Shipment</h2>
            <x-select name="status" label="Status" :value="$s->status" :options="['awaiting_dispatch' => 'Awaiting dispatch', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'returned' => 'Returned', 'cancelled' => 'Cancelled']" />
            <x-field name="courier" label="Courier" :value="$s->courier" />
            <x-field name="awb" label="AWB / tracking number" :value="$s->awb" />
            <x-field name="tracking_url" label="Tracking link (https)" :value="$s->tracking_url" />
            <button class="btn-primary w-full">Save</button>
            <p class="text-xs text-ink-600">Manual update (any courier). Refunds for cancelled or returned items: Finance › order › Request refund.</p>
        </form>
        </div>
        @else
        <p class="card h-fit p-5 text-sm">Not paid yet — nothing to dispatch.</p>
        @endif
    </div>
</x-layouts.admin>
