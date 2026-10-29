@php $V = \App\Domain\Astro\Vedic::class; $G = \App\Domain\Astro\Gochar::class; $S = fn ($i) => \Illuminate\Support\Str::before($V::SIGNS[$i], ' ('); @endphp
<x-layouts.public title="Navagraha — the nine planets" description="Guide to the nine planets of Vedic astrology: what each signifies, signs ruled, exaltation, nakshatras, dasha years, day and gemstone.">
    <div class="container-page py-10">
        <p class="eyebrow">Navagraha · नवग्रह</p>
        <h1 class="mt-2 text-3xl">The nine planets</h1>
        <p class="mt-2 max-w-3xl text-ink-600">Vedic astrology reads nine grahas: the Sun, the Moon, five planets and the two lunar nodes, Rahu and Ketu. Classical (Parashari) facts for each are below, with where it is today.</p>
        <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($G::PLANETS as $p)@php $f = $G::FACTS[$p]; @endphp
                <article class="card p-5">
                    <h2 class="text-xl"><a href="{{ route('gochar.planet', $p) }}">{{ $V::PLANET_NAMES[$p] }}</a> <span class="text-base text-ink-600" lang="hi">{{ $G::HI[$p] }}</span></h2>
                    <p class="mt-1 text-sm">{{ $f['signifies'] }}.</p>
                    <dl class="mt-3 grid grid-cols-[8.5rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                        @if ($now)<dt class="text-ink-600">Now in</dt><dd>{{ $S($now[$p]['sign']) }} {{ $now[$p]['deg'] }}{{ $now[$p]['retro'] && ! in_array($p, ['rahu', 'ketu'], true) ? ' (R)' : '' }}</dd>@endif
                        <dt class="text-ink-600">Rules</dt><dd>{{ collect($G::ownSigns($p))->map($S)->implode(', ') ?: '— (shadow planet)' }}</dd>
                        <dt class="text-ink-600">Exalted</dt><dd>{{ $S($f['exalt'][0]) }}{{ $f['exalt'][1] ? ' '.$f['exalt'][1].'°' : '' }}</dd>
                        <dt class="text-ink-600">Debilitated</dt><dd>{{ $S($f['debil']) }}</dd>
                        <dt class="text-ink-600">Nakshatras</dt><dd>{{ implode(', ', $G::nakshatras($p)) }}</dd>
                        <dt class="text-ink-600">Dasha</dt><dd>{{ $V::DASHA_YEARS[$p] }} years</dd>
                        <dt class="text-ink-600">Day</dt><dd>{{ $f['day'] }}</dd>
                        <dt class="text-ink-600">Gemstone</dt><dd>{{ $f['gem'] }}</dd>
                        <dt class="text-ink-600">Colour</dt><dd>{{ $f['colour'] }}</dd>
                        <dt class="text-ink-600">Time in a sign</dt><dd>{{ $f['stay'] }}</dd>
                    </dl>
                </article>
            @endforeach
        </div>
        <p class="mt-6 text-xs text-ink-600">Rahu's exaltation is taken as Vrishabha and Ketu's as Vrishchika; some traditions use Mithuna and Dhanu. Gemstones should be worn only after a chart reading.</p>
    </div>
</x-layouts.public>
