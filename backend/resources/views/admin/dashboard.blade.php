<x-layouts.admin title="Dashboard">
    @if ($attention)
    <section aria-labelledby="att-h" class="mb-8">
        <h2 id="att-h" class="text-base">Needs attention</h2>
        <ul class="mt-3 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($attention as [$label, $n, $route])
                <li><a href="{{ route($route) }}" @class(['card block p-5 no-underline transition hover:-translate-y-0.5', 'ring-2 ring-gold-500' => $n > 0])>
                    <p class="text-sm text-ink-600">{{ $label }}</p>
                    <p @class(['mt-2 text-3xl font-semibold tabular-nums', 'text-gold-700' => $n > 0, 'text-ink-400' => $n === 0])>{{ number_format($n) }}</p>
                </a></li>
            @endforeach
        </ul>
    </section>
    @endif
    <section aria-labelledby="stats" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <h2 id="stats" class="sr-only">Overview</h2>
        @foreach ($stats as $label => $value)
            <div class="card p-5">
                <p class="text-sm text-ink-600">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </section>

    <section class="mt-8" aria-labelledby="recent">
        <div class="flex items-center justify-between">
            <h2 id="recent" class="text-base">Recent activity</h2>
            @can('audit.view')<a href="{{ route('admin.audit.index') }}" class="text-sm">View audit log</a>@endcan
        </div>
        <div class="table-wrap mt-3" role="region" aria-label="Recent activity" tabindex="0">
            <table class="table">
                <thead><tr><th scope="col">When (IST)</th><th scope="col">Action</th><th scope="col">Actor</th></tr></thead>
                <tbody>
                    @forelse ($recent as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->created_at, 'UTC')->tz('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                            <td><code>{{ $r->action }}</code></td>
                            <td>{{ $r->actor_label }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-ink-600">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
