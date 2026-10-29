@php $t = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format(\Illuminate\Support\Carbon::parse($iso)->format('Y-m-d') === $date ? 'g:i A' : 'g:i A, d M') : '—'; @endphp
<x-layouts.public title="Panchang" description="Daily Panchang: tithi, nakshatra, yoga, karana, sunrise, sunset, Rahu Kaal for your city.">
    <div class="container-page py-10">
        <p class="eyebrow">Daily Panchang</p>
        <h1 class="mt-2 text-3xl">{{ \Illuminate\Support\Carbon::parse($date)->format('l, d F Y') }}</h1>
        <p class="mt-1 text-ink-600">{{ $placeLabel }}@if ($p) · UTC{{ $p['place']['utc_offset'] }}@endif</p>

        <form method="GET" action="{{ route('panchang') }}" class="card mt-6 grid gap-4 p-4 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
            <x-field name="date" label="Date" type="date" :value="$date" min="1800-01-01" max="2399-12-31" />
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

        @if (! $p)
            <div class="card mt-6 border-red-200 bg-red-50 p-5" role="alert">The Panchang could not be calculated right now. Please try again shortly.</div>
        @else
            @if ($p['polar'])<div class="card mt-6 border-gold-200 bg-gold-50 p-4 text-sm" role="note">{{ $p['polar_note'] }}</div>@endif
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['Sunrise' => $p['sunrise'], 'Sunset' => $p['sunset'], 'Moonrise' => $p['moonrise'], 'Moonset' => $p['moonset']] as $k => $v)
                    <div class="card p-4"><p class="text-xs text-ink-600">{{ $k }}</p><p class="mt-1 text-xl font-semibold">{{ $v ? $t($v) : 'No event' }}</p></div>
                @endforeach
            </div>
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <section class="card p-5" aria-labelledby="ang-h">
                    <h2 id="ang-h" class="text-lg">Panchang</h2>
                    <dl class="mt-3 divide-y divide-ink-200/60 text-sm">
                        <div class="flex justify-between gap-3 py-2"><dt class="text-ink-600">Vara</dt><dd class="font-medium">{{ $p['vara'] }}</dd></div>
                        @foreach (['tithi' => 'Tithi', 'nakshatra' => 'Nakshatra', 'yoga' => 'Yoga', 'karana' => 'Karana'] as $k => $l)
                            <div class="py-2"><dt class="text-ink-600">{{ $l }}</dt>
                                <dd class="mt-1 space-y-0.5">@foreach ($p['angas'][$k] as $a)<p><span class="font-medium">{{ $a['name'] }}</span>{{ $a['ends'] ? ' until '.$t($a['ends']) : ' (to next sunrise)' }}</p>@endforeach</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-3 py-2"><dt class="text-ink-600">Moon sign</dt><dd class="font-medium">{{ $p['moon_sign'] }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-ink-600">Sun sign (sidereal)</dt><dd class="font-medium">{{ $p['sun_sign'] }}</dd></div>
                    </dl>
                </section>
                @if ($p['intervals'])
                <section class="card p-5" aria-labelledby="int-h">
                    <h2 id="int-h" class="text-lg">Timings</h2>
                    <dl class="mt-3 divide-y divide-ink-200/60 text-sm">
                        @foreach (['abhijit' => 'Abhijit muhurta', 'brahma_muhurta' => 'Brahma muhurta', 'rahu_kaal' => 'Rahu Kaal', 'yamaganda' => 'Yamaganda', 'gulika' => 'Gulika Kaal'] as $k => $l)
                            <div class="flex justify-between gap-3 py-2"><dt class="text-ink-600">{{ $l }}</dt><dd class="text-right font-medium">{{ $t($p['intervals'][$k]['start']) }} – {{ $t($p['intervals'][$k]['end']) }}@if (! empty($p['intervals'][$k]['note']))<span class="block text-xs font-normal text-ink-600">{{ $p['intervals'][$k]['note'] }}</span>@endif</dd></div>
                        @endforeach
                    </dl>
                </section>
                @endif
            </div>
            @if ($p['choghadiya'])
                @php $now = now(); $isNow = fn ($r) => $now->between(\Illuminate\Support\Carbon::parse($r['start']), \Illuminate\Support\Carbon::parse($r['end'])); $V = \App\Domain\Astro\Vedic::PLANET_NAMES; @endphp
                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <section class="card p-5" aria-labelledby="chog-h">
                        <h2 id="chog-h" class="text-lg">Choghadiya</h2>
                        @foreach (['day' => 'Day (sunrise to sunset)', 'night' => 'Night (sunset to next sunrise)'] as $per => $label)
                            <h3 class="mt-3 text-sm font-semibold text-ink-700">{{ $label }}</h3>
                            <ul class="mt-1 divide-y divide-ink-200/60 text-sm">
                                @foreach (collect($p['choghadiya'])->where('period', $per) as $c)
                                    <li @class(['flex justify-between gap-3 py-1.5', 'bg-gold-50 font-semibold' => $isNow($c)])>
                                        <span><span @class(['badge-success' => $c['quality'] === 'good', 'badge-gold' => $c['quality'] === 'neutral', 'badge-danger' => $c['quality'] === 'bad'])>{{ $c['name'] }}</span>@if ($isNow($c)) <span class="text-xs">now</span>@endif</span>
                                        <span>{{ $t($c['start']) }} – {{ $t($c['end']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                        <p class="mt-2 text-xs text-ink-600">Amrit, Shubh and Labh are auspicious; Char is good for travel; Udveg, Rog and Kaal are avoided for new work.</p>
                    </section>
                    <section class="card p-5" aria-labelledby="hora-h">
                        <h2 id="hora-h" class="text-lg">Hora (planetary hours)</h2>
                        @foreach (['day' => 'Day', 'night' => 'Night'] as $per => $label)
                            <h3 class="mt-3 text-sm font-semibold text-ink-700">{{ $label }}</h3>
                            <ul class="mt-1 grid grid-cols-1 divide-y divide-ink-200/60 text-sm">
                                @foreach (collect($p['hora'])->where('period', $per) as $h)
                                    <li @class(['flex justify-between gap-3 py-1.5', 'bg-gold-50 font-semibold' => $isNow($h)])><span>{{ $V[$h['lord']] }}@if ($isNow($h)) <span class="text-xs">now</span>@endif</span><span>{{ $t($h['start']) }} – {{ $t($h['end']) }}</span></li>
                                @endforeach
                            </ul>
                        @endforeach
                        <p class="mt-2 text-xs text-ink-600">Each hora is 1/12 of the day or night; the first day hora belongs to the lord of the weekday (Chaldean order).</p>
                    </section>
                </div>
            @endif
            <p class="mt-4 text-xs text-ink-600">{{ $p['method'] }} Regional festival rules and Muhurat selection need an astrologer's review; this page shows the astronomical Panchang.</p>
        @endif
    </div>
</x-layouts.public>
