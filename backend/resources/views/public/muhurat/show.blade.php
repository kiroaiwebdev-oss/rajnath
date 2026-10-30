@php
    $M = \App\Domain\Astro\Muhurat::class; $V = \App\Domain\Astro\Vedic::class;
    $tz = $place->tz_name;
    $fmt = function (string $iso, string $date) { $c = \Illuminate\Support\Carbon::parse($iso); return $c->format('Y-m-d') === $date ? $c->format('g:i A') : $c->format('g:i A, d M'); };
    $paksha = fn (array $nums, string $p) => $nums ? $p.' '.implode(', ', $nums) : null;
@endphp
<x-layouts.public :title="$rule['name'].' muhurat — '.$month->format('F Y')" :description="$rule['name'].' muhurat dates for '.$month->format('F Y').' in '.$placeLabel.', from the Panchang.'" :jsonld="[\App\Support\Seo\JsonLd::webApp($rule['name'].' muhurat', __('Free, location-aware, from the Swiss Ephemeris.'), route('muhurat.show', $event)), \App\Support\Seo\JsonLd::breadcrumbs([[$rule['name'].' muhurat', route('muhurat.show', $event)]])]">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-600"><a href="{{ route('muhurat.index') }}">Shubh Muhurat</a> / <span aria-current="page" class="text-ink-900">{{ $rule['name'] }}</span></nav>
        <h1 class="mt-3 text-3xl">{{ $rule['name'] }} muhurat — {{ $month->format('F Y') }}</h1>
        <p class="mt-1 text-ink-600"><span lang="hi">{{ $rule['hi'] }}</span> · {{ $placeLabel }}</p>

        <form method="GET" action="{{ route('muhurat.show', $event) }}" class="card mt-6 grid gap-4 p-4 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
            <x-field name="month" label="Month" type="month" :value="$month->format('Y-m')" min="1900-01" max="2100-12" />
            <div x-data="placePicker" data-label="{{ $placeLabel }}" data-id="{{ $place->id }}" class="relative">
                <label for="pp-place" class="field-label">City</label>
                <input id="pp-place" type="text" class="input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="pp-list" :aria-expanded="expanded" x-model="query" @input="onInput" @keydown="onKey" @blur="close" aria-describedby="pp-status">
                <input type="hidden" name="place_id" :value="placeId">
                <ul id="pp-list" role="listbox" x-show="open" x-cloak class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-ink-200 bg-white py-1 shadow-[var(--shadow-card)]">
                    <template x-for="(pl, index) in results" :key="pl.id"><li role="option" :data-index="index" :aria-selected="index === active ? 'true' : 'false'" class="cursor-pointer px-3 py-2.5 text-sm aria-selected:bg-ivory-100" @mousedown.prevent="choose"><span x-text="pl.label"></span></li></template>
                </ul>
                <p id="pp-status" class="sr-only" role="status" aria-live="polite" x-text="status"></p>
            </div>
            <button class="btn-primary">Show</button>
        </form>
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Month">
            <a class="btn-outline" href="{{ route('muhurat.show', array_filter(['event' => $event, 'month' => $month->subMonth()->format('Y-m'), 'place_id' => $place->id])) }}"><span aria-hidden="true">←</span> {{ $month->subMonth()->format('M Y') }}</a>
            <a class="btn-outline" href="{{ route('muhurat.show', array_filter(['event' => $event, 'month' => $month->addMonth()->format('Y-m'), 'place_id' => $place->id])) }}">{{ $month->addMonth()->format('M Y') }} <span aria-hidden="true">→</span></a>
        </nav>

        @if ($dates === null)
            <div class="card mt-6 border-red-200 bg-red-50 p-5" role="alert">The muhurat could not be calculated right now. Please try again shortly.</div>
        @elseif (count($dates) === 0)
            <p class="card mt-6 p-6 text-ink-600">No suitable dates in {{ $month->format('F Y') }} under these rules{{ $rule['avoid'] ? ' (this month may fall in '.collect($rule['avoid'])->map(fn ($a) => $M::AVOID_LABELS[$a])->implode(', ').')' : '' }}. Try another month.</p>
        @else
            <ul class="mt-6 grid gap-3 md:grid-cols-2">
                @foreach ($dates as $c)@php $f = $c['facts']; @endphp
                    <li class="card p-4">
                        <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($c['date'])->format('l, d F Y') }}</p>
                        <ul class="mt-1 space-y-0.5 text-sm text-ink-700">@foreach ($c['windows'] as $w)<li>{{ $fmt($w[0], $c['date']) }} – {{ $fmt($w[1], $c['date']) }} <span class="text-xs text-ink-600">({{ $w[2] }}, {{ $w[3] }})</span></li>@endforeach</ul>
                        <p class="mt-1 text-xs text-ink-600">{{ $f['masa'] }} (amanta)@if (($f['masa_purnimanta'] ?? null) && $f['masa_purnimanta'] !== $f['masa']) / {{ $f['masa_purnimanta'] }} (purnimanta)@endif · {{ \Illuminate\Support\Str::before($V::VARAS[$f['weekday']], ' (') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        <details class="card mt-8 p-5 text-sm">
            <summary class="cursor-pointer font-semibold">Rules used for {{ $rule['name'] }}</summary>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                <li>Nakshatra: {{ collect($rule['nakshatras'])->map(fn ($n) => $V::NAKSHATRAS[$n])->implode(', ') }}</li>
                <li>Weekday: {{ collect($rule['varas'])->map(fn ($v) => \Illuminate\Support\Str::after(\Illuminate\Support\Str::before($V::VARAS[$v], ')'), '('))->implode(', ') }}</li>
                <li>Tithi: {{ implode('; ', array_filter([$paksha($rule['shukla'], 'Shukla'), $paksha($rule['krishna'], 'Krishna')])) }} (numbers within the paksha)</li>
                <li>Always avoided: Vyatipata and Vaidhriti yoga, Vishti (Bhadra) karana, Amavasya.</li>
                @if ($rule['avoid'])<li>Also avoided: {{ collect($rule['avoid'])->map(fn ($a) => $M::AVOID_LABELS[$a])->implode('; ') }}.</li>@endif
                <li>A window is a stretch of at least 48 minutes, between sunrise and the next sunrise, in which all the above hold together. Times after midnight show the next date.</li>
            </ul>
        </details>
        <p class="mt-4 rounded-2xl border border-gold-200 bg-gold-50 p-4 text-sm"><strong>Please note:</strong> these dates are worked out from the Panchang by rule. The final muhurat also depends on lagna shuddhi, the charts of the people involved and family tradition, so please confirm with Acharya Ji before fixing a ceremony. <a href="{{ route('services.index', ['family' => 'consultations']) }}">Book a consultation</a>.</p>
    </div>
</x-layouts.public>
