<x-layouts.admin title="Puja bookings">
    <nav aria-label="Status"><ul class="flex flex-wrap gap-2">
        @foreach (['requested' => 'To arrange', 'awaiting_details' => 'Waiting for sankalp', 'scheduled' => 'Scheduled', 'performed' => 'To verify', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
            <li><a href="{{ route('admin.ops.pujas', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k])>{{ $l }}</a></li>
        @endforeach
    </ul></nav>
    <div class="table-wrap mt-4" role="region" aria-label="Puja bookings" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Puja</th><th scope="col">Customer</th><th scope="col">Preferred / scheduled</th><th scope="col">Status</th></tr></thead>
        <tbody>@forelse ($rows as $r)<tr>
            <td><a href="{{ route('admin.ops.puja', $r->ref) }}" class="font-medium">{{ $r->title }}</a></td><td>{{ $r->customer }}</td>
            <td>{{ $r->scheduled_at ? \Illuminate\Support\Carbon::parse($r->scheduled_at, 'UTC')->tz('Asia/Kolkata')->format('d M, g:i A') : ($r->preferred_date ? \Illuminate\Support\Carbon::parse($r->preferred_date)->format('d M').' (preferred)' : '—') }}</td>
            <td>@include('account.puja._status', ['s' => $r->status])</td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-600">Nothing here.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.admin>
