<x-layouts.admin title="Gem orders">
    <nav aria-label="Shipment status"><ul class="flex flex-wrap gap-2">
        @foreach (['awaiting_dispatch' => 'To dispatch', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'returned' => 'Returned', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
            <li><a href="{{ route('admin.store.orders', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k])>{{ $l }}</a></li>
        @endforeach
    </ul></nav>
    <div class="table-wrap mt-4" role="region" aria-label="Orders" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Item</th><th scope="col">Payment</th><th scope="col">Shipment</th></tr></thead>
        <tbody>@forelse ($rows as $r) @php $sn = json_decode($r->snapshot, true); @endphp<tr>
            <td><a href="{{ route('admin.store.order', $r->order_ref) }}" class="font-mono text-xs">{{ substr($r->order_ref, -10) }}</a></td><td>{{ $r->customer }}</td>
            <td>{{ $sn['product']['name'] }} × {{ $sn['quantity'] }}</td><td>{{ str_replace('_', ' ', $r->order_status) }}</td><td><span class="badge-ink">{{ str_replace('_', ' ', $r->status) }}</span></td></tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-ink-600">No orders here.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.admin>
