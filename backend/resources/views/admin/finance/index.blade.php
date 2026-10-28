<x-layouts.admin title="Finance">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($totals as $l => $v)<div class="card p-4"><p class="text-xs text-ink-600">{{ $l }}</p><p class="mt-1 text-xl font-semibold tabular-nums">{{ \App\Support\Money::format($v) }}</p></div>@endforeach
    </div>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Order status"><ul class="flex flex-wrap gap-2">
            @foreach (['' => 'All', 'pending_payment' => 'Pending', 'paid' => 'Paid', 'partially_refunded' => 'Part refunded', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'] as $k => $l)
                <li><a href="{{ route('admin.finance.index', array_filter(['status' => $k])) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k])>{{ $l }}</a></li>
            @endforeach
        </ul></nav>
        @can('reconciliation.manage')<form method="POST" action="{{ route('admin.finance.reconcile') }}">@csrf<button class="btn-outline">Run reconciliation now</button></form>@endcan
    </div>
    @if ($findings->isNotEmpty())
        <section class="card mt-5 border-red-200 p-4" aria-labelledby="fd-h"><h2 id="fd-h" class="text-base text-danger-700">Open findings</h2>
            <ul class="mt-2 space-y-2 text-sm">@foreach ($findings as $f)<li class="flex flex-wrap items-center justify-between gap-2"><span><span class="badge-danger">{{ $f->severity }}</span> {{ $f->check }} — {{ $f->detail }}</span>
                @can('reconciliation.manage')<form method="POST" action="{{ route('admin.finance.finding.resolve', $f->id) }}">@csrf<button class="btn-ghost px-3">Resolve</button></form>@endcan</li>@endforeach</ul></section>
    @endif
    <div class="table-wrap mt-5" role="region" aria-label="Orders" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Date (IST)</th><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Kind</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
        <tbody>@forelse ($orders as $o)<tr>
            <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($o->created_at)->tz('Asia/Kolkata')->format('d M, g:i A') }}</td>
            <td><a href="{{ route('admin.finance.order', $o->ref) }}" class="font-mono text-xs">{{ substr($o->ref, -10) }}</a></td><td>{{ $o->customer }}</td><td>{{ $o->kind }}</td>
            <td class="whitespace-nowrap tabular-nums">{{ \App\Support\Money::format((int) $o->subtotal_minor) }}</td><td><span class="badge-ink">{{ str_replace('_', ' ', $o->status) }}</span></td></tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-ink-600">No orders.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $orders->links() }}</div>
    <h2 class="mt-8 text-base">Recent refunds</h2>
    <div class="table-wrap mt-3" role="region" aria-label="Refunds" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Order</th><th scope="col">Amount</th><th scope="col">To</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Decision</span></th></tr></thead>
        <tbody>@forelse ($refunds as $r)<tr>
            <td class="font-mono text-xs">{{ substr($r->order_ref, -10) }}</td><td class="tabular-nums">{{ \App\Support\Money::format((int) $r->amount_minor) }}</td><td>{{ $r->destination }}</td><td>{{ $r->reason }}</td>
            <td><span class="badge-ink">{{ $r->status }}</span>@if ($r->error)<br><span class="text-xs text-danger-700">{{ $r->error }}</span>@endif</td>
            <td>@if ($r->status === 'requested')@can('refunds.approve')<form method="POST" action="{{ route('admin.finance.refund.decide', $r->ref) }}" class="flex gap-2">@csrf
                <button name="decision" value="approve" class="btn-primary px-3">Approve</button><button name="decision" value="reject" class="btn-outline px-3">Reject</button></form>@endcan @endif</td></tr>
        @empty<tr><td colspan="6" class="py-6 text-center text-ink-600">No refunds.</td></tr>@endforelse</tbody>
    </table></div>
</x-layouts.admin>
