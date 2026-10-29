<x-layouts.admin title="Privacy requests">
    <nav class="mb-4 flex gap-2" aria-label="Filter">
        <a href="{{ route('admin.privacy.index') }}" @class(['btn px-4', 'bg-ink-900 text-white' => $show === 'open', 'border border-ink-200 bg-white text-ink-800' => $show !== 'open']) @if ($show === 'open') aria-current="page" @endif>Open ({{ $counts['open'] }})</a>
        <a href="{{ route('admin.privacy.index', ['show' => 'closed']) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $show === 'closed', 'border border-ink-200 bg-white text-ink-800' => $show !== 'closed']) @if ($show === 'closed') aria-current="page" @endif>Closed and completed</a>
    </nav>
    <div class="table-wrap" role="region" aria-label="Privacy requests" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Received</th><th scope="col">Type</th><th scope="col">Customer</th><th scope="col">Case</th><th scope="col">Why blocked</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
        <tbody>@forelse ($rows as $r)<tr>
            <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->created_at)->tz('Asia/Kolkata')->format('d M Y, g:i A') }}</td>
            <td>{{ $r->type === 'delete' ? 'Delete account' : 'Data export' }}<span class="block text-xs text-ink-600">{{ $r->status }}</span></td>
            <td>{{ $r->customer ?? 'Deleted person' }}@if ($r->email)<span class="block text-xs text-ink-600">{{ $r->email }}</span>@endif</td>
            <td><span @class(['badge-gold' => $r->case_status === 'open', 'badge-ink' => $r->case_status !== 'open'])>{{ str_replace('_', ' ', $r->case_status) }}</span>@if ($r->assignee)<span class="block text-xs text-ink-600">{{ $r->assignee }}</span>@endif</td>
            <td class="max-w-md text-xs">{{ $r->blocked_reason }}</td>
            <td><a href="{{ route('admin.privacy.show', $r->ref) }}">Open</a></td></tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-ink-600">{{ $show === 'open' ? 'No open requests.' : 'Nothing yet.' }}</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
    <p class="mt-4 text-xs text-ink-600">Customers export and delete their own data from their account. A deletion that cannot run yet (unpaid order, upcoming booking, wallet balance, legal hold…) opens a case here so the team can help and then run it.</p>
</x-layouts.admin>
