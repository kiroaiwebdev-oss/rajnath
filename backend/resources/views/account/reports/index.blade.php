<x-layouts.account title="Reports">
    @if ($rows->isEmpty())
        <div class="card p-8 text-center"><p class="text-ink-600">No reports yet.</p><a href="{{ route('services.index', ['family' => 'reports']) }}" class="btn-primary mt-4">See reports</a></div>
    @else
        <ul class="space-y-3">
            @foreach ($rows as $r)
                <li class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div><p class="font-medium">{{ $r->title }}</p><p class="text-xs text-ink-600">Ordered {{ \Illuminate\Support\Carbon::parse($r->created_at)->tz('Asia/Kolkata')->format('d M Y') }}</p></div>
                    <div class="flex items-center gap-3">
                        <span @class(['badge-gold' => $r->status === 'awaiting_details', 'badge-ink' => $r->status === 'in_preparation', 'badge-success' => $r->status === 'completed'])>{{ ['awaiting_details' => 'Details needed', 'in_preparation' => 'Being prepared', 'completed' => 'Ready'][$r->status] }}</span>
                        <a href="{{ route('account.reports.show', $r->ref) }}" class="btn-outline">{{ $r->status === 'awaiting_details' ? 'Add details' : 'Open' }}</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.account>
