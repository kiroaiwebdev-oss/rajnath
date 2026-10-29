<x-layouts.admin title="Operational health">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="min-w-0">
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($checks as $c)
                    <li @class(['card p-4', 'border-red-200 bg-red-50' => $c['state'] === 'fail', 'border-gold-200 bg-gold-50' => $c['state'] === 'warn'])>
                        <p class="flex items-center justify-between gap-2"><span class="font-semibold">{{ $c['name'] }}</span>
                            <span @class(['badge-success' => $c['state'] === 'ok', 'badge-gold' => $c['state'] === 'warn', 'badge-danger' => $c['state'] === 'fail'])>{{ ['ok' => 'OK', 'warn' => 'Check', 'fail' => 'Problem'][$c['state']] }}</span></p>
                        <p class="mt-1 text-sm text-ink-700">{{ $c['detail'] }}</p>
                    </li>
                @endforeach
            </ul>
            <h2 class="mt-8 text-lg">Recent failed jobs</h2>
            <div class="table-wrap mt-3" role="region" aria-label="Failed jobs" tabindex="0"><table class="table text-sm">
                <thead><tr><th scope="col">When (IST)</th><th scope="col">Queue</th><th scope="col">Error</th></tr></thead>
                <tbody>@forelse ($failed as $f)<tr><td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($f->failed_at)->tz('Asia/Kolkata')->format('d M, g:i A') }}</td><td>{{ $f->queue }}</td><td class="text-xs">{{ \Illuminate\Support\Str::limit(strtok($f->exception, "\n"), 220) }}</td></tr>
                @empty<tr><td colspan="3" class="py-6 text-center text-ink-600">None.</td></tr>@endforelse</tbody>
            </table></div>
            <p class="mt-2 text-xs text-ink-600">Retry with <code>php artisan queue:retry all</code> on the server after fixing the cause.</p>
        </section>
        <aside class="space-y-4">
            <section class="card p-5 text-sm" aria-labelledby="pv-h"><h2 id="pv-h" class="text-base">Providers</h2>
                <ul class="mt-2 divide-y divide-ink-200/60">@foreach ($providers as $name => $v)<li class="flex justify-between gap-2 py-1.5"><span>{{ $name }}</span><span @class(['badge-success' => $v, 'badge-ink' => ! $v])>{{ is_string($v) ? $v : ($v ? 'Set up' : 'Not set up') }}</span></li>@endforeach</ul></section>
            <section class="card p-5 text-sm" aria-labelledby="ap-h"><h2 id="ap-h" class="text-base">Application</h2>
                <dl class="mt-2 space-y-1">@foreach ($app as $k => $v)<div class="flex justify-between gap-2"><dt class="text-ink-600">{{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach</dl></section>
            <p class="text-xs text-ink-600">Public probes for monitoring: <code>/up</code> and <code>{{ route('ready') }}</code>.</p>
        </aside>
    </div>
</x-layouts.admin>
