<x-layouts.admin title="Enquiries">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Filter by status"><ul class="flex flex-wrap gap-2">
            @foreach (['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed', 'spam' => 'Spam', 'all' => 'All'] as $k => $l)
                <li><a href="{{ route('admin.enquiries.index', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k]) @if ($status === $k) aria-current="page" @endif>{{ $l }}@if ($k !== 'all') ({{ $counts[$k] ?? 0 }})@endif</a></li>
            @endforeach
        </ul></nav>
        @can('bookings.manage')<a href="{{ route('admin.enquiries.export', ['status' => $status]) }}" class="btn-outline">Export CSV</a>@endcan
    </div>
    <div class="table-wrap mt-5" role="region" aria-label="Enquiries" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">Received (IST)</th><th scope="col">Name</th><th scope="col">Service</th><th scope="col">Phone</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($list as $e)
                <tr>
                    <td class="whitespace-nowrap">{{ $e->created_at->tz('Asia/Kolkata')->format('d M, g:i A') }}</td>
                    <td><a href="{{ route('admin.enquiries.show', $e) }}" class="font-medium">{{ $e->name }}</a></td>
                    <td>{{ $e->offering?->t()?->title ?? 'General' }}</td>
                    <td class="whitespace-nowrap">{{ \App\Support\Logging\Redactor::mask($e->phone_e164) }}</td>
                    <td><span class="badge-ink">{{ \App\Models\Enquiry::STATUSES[$e->status] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-ink-600">No enquiries here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $list->links() }}</div>
</x-layouts.admin>
