@php use App\Support\Money; use App\Models\Refund; @endphp
<x-layouts.admin title="Refunds">
    @include('admin.finance._nav')
    <form method="GET" class="flex items-end gap-3"><div class="w-60"><x-select name="status" label="Status" :options="['all' => 'All'] + Refund::STATUSES" :value="$status" /></div><button class="btn-outline">Show</button></form>
    <div class="mt-5 space-y-4">
        @forelse ($list as $r)
            <article class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold tabular-nums">{{ Money::format($r->amount_minor) }} <span class="font-normal text-ink-600">to {{ $r->user->name }}</span></p>
                    <span class="{{ $r->status === 'processed' ? 'badge-success' : (in_array($r->status, ['failed', 'rejected']) ? 'badge-danger' : 'badge-gold') }}">{{ Refund::STATUSES[$r->status] }}</span>
                </div>
                <p class="mt-1 text-sm">{{ $r->reason }}</p>
                <p class="mt-1 text-xs text-ink-600">Payment <a href="{{ route('admin.finance.payment', $r->payment) }}">{{ substr($r->payment->ref, -8) }}</a> · requested by {{ $r->requester->name }} on {{ $r->created_at->tz('Asia/Kolkata')->format('d M Y, H:i') }}{{ $r->decider ? ' · decided by '.$r->decider->name : '' }}</p>
                @if ($r->failure_reason)<p class="mt-1 text-xs text-danger-700">{{ $r->failure_reason }}</p>@endif
                @if ($r->status === 'requested')
                    @can('refunds.approve')
                        <form method="POST" action="{{ route('admin.finance.refund.decide', $r) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                            @csrf
                            <div class="flex-1"><x-field name="note" :id="'n-'.$r->id" label="Note (required to reject)" /></div>
                            <button name="decision" value="approve" class="btn-primary">Approve & send</button>
                            <button name="decision" value="reject" class="btn-outline">Reject</button>
                        </form>
                    @endcan
                @endif
            </article>
        @empty
            <p class="rounded-xl bg-ivory-100 p-6 text-center text-sm text-ink-600">No refunds here.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $list->links() }}</div>
</x-layouts.admin>
