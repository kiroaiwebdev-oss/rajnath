<x-layouts.admin :title="\Illuminate\Support\Str::headline($j->offering_slug)">
    <div class="grid gap-6 xl:grid-cols-[24rem_1fr]">
        <section class="card h-fit p-5 text-sm">
            @forelse ($subjects as $i => $s)
                <div class="mb-4"><p class="font-semibold">{{ $s['name'] }}</p><p class="text-ink-600">{{ $s['birth_date'] }} {{ $s['birth_time'] }} · {{ $s['place']['label'] }}</p>
                    @if ($chart)@php $c = $chart['charts'][$i]; @endphp
                        <p class="mt-1">Lagna {{ $c['lagna']['sign_name'] }} · Moon {{ $c['moon_sign']['name'] }} · {{ $c['nakshatra']['name'] }} {{ $c['nakshatra']['pada'] }}</p>
                        <div class="mt-2">@include('public.tools._chart', ['houses' => $c['houses'], 'label' => 'D1'])</div>
                        <details class="mt-2"><summary class="cursor-pointer">Planets & dasha</summary>
                            <ul class="mt-2 space-y-0.5 text-xs">@foreach ($c['planets'] as $k => $p) @continue(! $p['classical'])<li>{{ $p['name'] }}: {{ $p['sign_name'] }} {{ $p['degree'] }} H{{ $p['house'] }}</li>@endforeach</ul>
                            <ul class="mt-2 space-y-0.5 text-xs">@foreach ($c['dasha']['periods'] as $d)<li>{{ ucfirst($d['lord']) }} {{ $d['start'] }} – {{ $d['end'] }}</li>@endforeach</ul></details>
                    @endif</div>
            @empty<p class="text-ink-600">Waiting for the customer to choose birth details.</p>@endforelse
            @if (isset($chart['matching']))<p class="font-semibold">Guna milan {{ $chart['matching']['total'] }}/36</p>@endif
            @if ($j->question)<p class="mt-3 rounded-xl bg-ivory-100 p-3"><strong>Customer asks:</strong> {{ $j->question }}</p>@endif
        </section>
        <form method="POST" action="{{ route('admin.ops.report.save', $j->ref) }}" class="card min-w-0 space-y-4 p-5">
            @csrf <h2 class="text-lg">Write the report ({{ $j->language === 'hi' ? 'Hindi' : 'English' }})</h2>
            @foreach ($defs as $k => $h)
                <div><label for="s-{{ $k }}" class="field-label">{{ $h }}</label><textarea id="s-{{ $k }}" name="sections[{{ $k }}]" rows="5" class="input" @disabled($j->status !== 'in_preparation')>{{ old("sections.$k", $sections[$k] ?? '') }}</textarea></div>
            @endforeach
            @error('sections')<p class="field-error">{{ $message }}</p>@enderror
            @if ($j->status === 'in_preparation')<div class="flex flex-col gap-3 sm:flex-row sm:justify-end"><button name="action" value="draft" class="btn-outline">Save draft</button><button name="action" value="complete" class="btn-primary">Publish to customer</button></div>@endif
        </form>
    </div>
</x-layouts.admin>
