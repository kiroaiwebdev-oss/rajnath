<x-layouts.admin title="Palmistry readings">
    <nav aria-label="Status"><ul class="flex flex-wrap gap-2">
        @foreach (['open' => 'To prepare', 'awaiting_photos' => 'Awaiting photos', 'needs_new_photos' => 'New photos asked', 'completed' => 'Completed', 'all' => 'All'] as $k => $l)
            <li><a href="{{ route('admin.palm.index', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k])>{{ $l }}</a></li>
        @endforeach
    </ul></nav>
    <div class="table-wrap mt-4" role="region" aria-label="Readings" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Submitted (IST)</th><th scope="col">Status</th></tr></thead>
        <tbody>@forelse ($rows as $r)<tr>
            <td><a href="{{ route('admin.palm.show', $r->ref) }}" class="font-mono text-xs">{{ substr($r->ref, -8) }}</a></td><td>{{ $r->customer }}</td>
            <td>{{ $r->submitted_at ? \Illuminate\Support\Carbon::parse($r->submitted_at)->tz('Asia/Kolkata')->format('d M, g:i A') : '—' }}</td><td><span class="badge-ink">{{ str_replace('_', ' ', $r->status) }}</span></td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-600">Nothing here.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.admin>
