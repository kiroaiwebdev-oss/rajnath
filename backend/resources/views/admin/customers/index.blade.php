<x-layouts.admin title="Customers">
    <form method="GET" class="flex max-w-xl items-end gap-2" role="search">
        <div class="flex-1"><x-field name="q" label="Search name, email or mobile" :value="$q" /></div>
        <button class="btn-outline">Search</button>
    </form>
    <div class="table-wrap mt-5" role="region" aria-label="Customers" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Mobile</th><th scope="col">Joined</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($list as $c)
                <tr>
                    <td><a href="{{ route('admin.customers.show', $c) }}" class="font-medium">{{ $c->name }}</a></td>
                    <td>{{ $c->email ? \App\Support\Logging\Redactor::mask($c->email) : '—' }}</td>
                    <td>{{ $c->phone_e164 ? \App\Support\Logging\Redactor::mask($c->phone_e164) : '—' }}</td>
                    <td class="whitespace-nowrap">{{ $c->created_at->tz('Asia/Kolkata')->format('d M Y') }}</td>
                    <td>@if ($c->isActive())<span class="badge-success">Active</span>@else<span class="badge-danger">{{ ucfirst($c->status) }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-ink-600">No customers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $list->links() }}</div>
</x-layouts.admin>
