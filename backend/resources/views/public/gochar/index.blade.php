@php $V = \App\Domain\Astro\Vedic::class; $G = \App\Domain\Astro\Gochar::class; @endphp
<x-layouts.public title="Grah Gochar — planetary transits" description="Where the nine planets are now in the sidereal zodiac, their next sign change, and transit dates for Sun, Moon, Mars, Mercury, Jupiter, Venus, Saturn, Rahu and Ketu." :jsonld="[\App\Support\Seo\JsonLd::webApp('Grah Gochar', __('Free, location-aware, from the Swiss Ephemeris.'), route('gochar')), \App\Support\Seo\JsonLd::breadcrumbs([['Grah Gochar', route('gochar')]])]">
    <div class="container-page py-10">
        <p class="eyebrow">Grah Gochar · ग्रह गोचर</p>
        <h1 class="mt-2 text-3xl">Planetary transits today</h1>
        <p class="mt-2 max-w-3xl text-ink-600">Sidereal positions (Lahiri ayanamsha) calculated with the Swiss Ephemeris. Open a planet for its sign changes, retrograde periods and sign-by-sign effects for the year.</p>
        @if (! $now)
            <div class="card mt-6 border-red-200 bg-red-50 p-5" role="alert">Transits could not be calculated right now. Please try again shortly.</div>
        @else
            <div class="table-wrap mt-6" role="region" aria-label="Current planetary positions" tabindex="0"><table class="table">
                <thead><tr><th scope="col">Planet</th><th scope="col">Sign</th><th scope="col">Degree</th><th scope="col">Nakshatra</th><th scope="col">Motion</th><th scope="col">Next sign change (IST)</th></tr></thead>
                <tbody>@foreach ($now as $p => $pos)<tr>
                    <th scope="row" class="font-medium"><a href="{{ route('gochar.planet', $p) }}">{{ $V::PLANET_NAMES[$p] }}</a> <span class="text-ink-600" lang="hi">{{ $G::HI[$p] }}</span></th>
                    <td>{{ $V::SIGNS[$pos['sign']] }}</td><td>{{ $pos['deg'] }}</td><td>{{ $pos['nakshatra'] }}</td>
                    <td>{{ $pos['retro'] ? 'Retrograde' : 'Direct' }}</td>
                    <td>@if ($next[$p] ?? null){{ $next[$p]['at']->format('d M Y, g:i A') }} → {{ \Illuminate\Support\Str::before($V::SIGNS[$next[$p]['sign']], ' (') }}@else — @endif</td>
                </tr>@endforeach</tbody>
            </table></div>
        @endif
        <div class="mt-8 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($G::PLANETS as $p)<a href="{{ route('gochar.planet', $p) }}" class="card p-4 no-underline hover:border-gold-500"><span class="font-semibold">{{ $V::PLANET_NAMES[$p] }}</span><span class="block text-sm text-ink-600" lang="hi">{{ $G::HI[$p] }} गोचर</span></a>@endforeach
        </div>
        <p class="mt-6 text-sm"><a href="{{ route('navagraha') }}">The nine planets (Navagraha) — a short guide</a></p>
    </div>
</x-layouts.public>
