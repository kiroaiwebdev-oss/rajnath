<x-layouts.admin title="Reports to write">
    <nav aria-label="Status"><ul class="flex flex-wrap gap-2">
        @foreach (['in_preparation' => 'To write', 'awaiting_details' => 'Waiting for customer', 'completed' => 'Completed', 'all' => 'All'] as $k => $l)
            <li><a href="{{ route('admin.ops.reports', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k])>{{ $l }}</a></li>
        @endforeach
    </ul></nav>
    <div class="table-wrap mt-4" role="region" aria-label="Reports" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Report</th><th scope="col">Customer</th><th scope="col">Language</th><th scope="col">Waiting since</th></tr></thead>
        <tbody>@forelse ($rows as $r)<tr>
            <td><a href="{{ route('admin.ops.report', $r->ref) }}" class="font-medium">{{ \Illuminate\Support\Str::headline($r->offering_slug) }}</a></td><td>{{ $r->customer }}</td><td>{{ $r->language === 'hi' ? 'Hindi' : 'English' }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($r->updated_at)->diffForHumans() }}</td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-600">Nothing here.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.admin>
