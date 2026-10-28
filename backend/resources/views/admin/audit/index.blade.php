<x-layouts.admin title="Audit log">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <form method="GET" class="flex w-full max-w-md items-end gap-2" role="search">
            <div class="flex-1"><x-field name="action" label="Filter by action prefix" :value="$action" placeholder="e.g. staff." /></div>
            <button class="btn-outline">Filter</button>
        </form>
        <a href="{{ route('admin.audit.verify') }}" class="btn-outline">Verify integrity</a>
    </div>
    <div class="table-wrap mt-5" role="region" aria-label="Audit events" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">#</th><th scope="col">When (IST)</th><th scope="col">Action</th><th scope="col">Actor</th><th scope="col">Subject</th><th scope="col">Details</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td class="tabular-nums">{{ $l->id }}</td>
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($l->created_at, 'UTC')->tz('Asia/Kolkata')->format('d M Y, H:i:s') }}</td>
                    <td><code>{{ $l->action }}</code></td>
                    <td>{{ $l->actor_label }}</td>
                    <td>{{ $l->subject_type }} {{ $l->subject_ref }}</td>
                    <td class="max-w-sm"><code class="block break-all text-xs text-ink-600">{{ $l->context }}</code></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-ink-600">No matching events.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.admin>
