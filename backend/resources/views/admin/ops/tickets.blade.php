<x-layouts.admin title="Support">
    <nav aria-label="Status"><ul class="flex flex-wrap gap-2">
        @foreach (['open' => 'Open', 'waiting_customer' => 'Waiting for customer', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $k => $l)
            <li><a href="{{ route('admin.ops.tickets', ['status' => $k]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $status === $k, 'border border-ink-200 bg-white text-ink-800' => $status !== $k])>{{ $l }}</a></li>
        @endforeach
    </ul></nav>
    <div class="table-wrap mt-4" role="region" aria-label="Tickets" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Subject</th><th scope="col">Customer</th><th scope="col">Category</th><th scope="col">Updated</th></tr></thead>
        <tbody>@forelse ($rows as $t)<tr>
            <td><a href="{{ route('admin.ops.ticket', $t->ref) }}" class="font-medium">{{ $t->subject }}</a>@if ($t->priority === 'high')<span class="badge-danger ml-2">High</span>@endif</td><td>{{ $t->customer }}</td>
            <td>{{ \App\Domain\Support\Tickets::CATEGORIES[$t->category] ?? $t->category }}</td><td>{{ \Illuminate\Support\Carbon::parse($t->updated_at)->diffForHumans() }}</td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-600">Nothing here.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.admin>
