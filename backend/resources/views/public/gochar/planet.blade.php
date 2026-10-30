@php
    $V = \App\Domain\Astro\Vedic::class; $G = \App\Domain\Astro\Gochar::class; $C = \App\Http\Controllers\Web\ContentController::class;
    $name = $V::PLANET_NAMES[$planet]; $hi = $locale === 'hi';
    $isMoon = $planet === 'moon';
    $period = $isMoon ? $from->format('F Y') : $from->format('Y');
    $prev = $isMoon ? ['month' => $from->subMonth()->format('Y-m')] : ['year' => $from->year - 1];
    $nextP = $isMoon ? ['month' => $from->addMonth()->format('Y-m')] : ['year' => $from->year + 1];
    $f = $G::FACTS[$planet];
@endphp
<x-layouts.public :title="$name.' transit '.$period.' — '.$G::HI[$planet].' गोचर'" :description="$name.' transit '.$period.': sign changes, retrograde and direct dates, and effects for each Moon sign.'" :jsonld="[\App\Support\Seo\JsonLd::webApp($name.' transit', __('Free, location-aware, from the Swiss Ephemeris.'), route('gochar.planet', $planet)), \App\Support\Seo\JsonLd::breadcrumbs([[$name.' transit', route('gochar.planet', $planet)]])]">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-600"><a href="{{ route('gochar') }}">Grah Gochar</a> / <span aria-current="page" class="text-ink-900">{{ $name }}</span></nav>
        <h1 class="mt-3 text-3xl">{{ $name }} transit {{ $period }} <span class="text-ink-600" lang="hi">· {{ $G::HI[$planet] }} गोचर</span></h1>
        @if ($now)<p class="mt-2 text-ink-600">Now in {{ $V::SIGNS[$now['sign']] }} {{ $now['deg'] }}, {{ $now['nakshatra'] }} nakshatra{{ $now['retro'] && ! in_array($planet, ['rahu', 'ketu'], true) ? ', retrograde' : '' }}.</p>@endif
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Period">
            <a class="btn-outline" href="{{ route('gochar.planet', ['planet' => $planet] + $prev + ['lang' => $locale]) }}"><span aria-hidden="true">←</span> {{ $isMoon ? $from->subMonth()->format('M Y') : $from->year - 1 }}</a>
            <a class="btn-outline" href="{{ route('gochar.planet', ['planet' => $planet] + $nextP + ['lang' => $locale]) }}">{{ $isMoon ? $from->addMonth()->format('M Y') : $from->year + 1 }} <span aria-hidden="true">→</span></a>
        </nav>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
            <section aria-labelledby="ev-h" class="min-w-0">
                <h2 id="ev-h" class="text-xl">Dates (IST)</h2>
                @if ($events === null)
                    <div class="card mt-3 border-red-200 bg-red-50 p-5" role="alert">Transit dates could not be calculated right now.</div>
                @elseif (count($events) === 0)
                    <p class="card mt-3 p-5 text-ink-600">{{ $name }} stays in one sign for the whole period.</p>
                @else
                    <div class="table-wrap mt-3" role="region" aria-label="Transit dates" tabindex="0"><table class="table text-sm">
                        <thead><tr><th scope="col">Date and time</th><th scope="col">Event</th></tr></thead>
                        <tbody>@foreach ($events as $e)<tr>
                            <td class="whitespace-nowrap">{{ $e['at']->format('D, d M Y, g:i A') }}</td>
                            <td>@if ($e['type'] === 'ingress')Enters {{ $V::SIGNS[$e['sign']] }} from {{ \Illuminate\Support\Str::before($V::SIGNS[$e['from']], ' (') }}{{ $e['retro'] && ! in_array($planet, ['rahu', 'ketu'], true) ? ' (retrograde)' : '' }}
                                @else Turns {{ $e['type'] === 'retrograde' ? 'retrograde (vakri)' : 'direct (margi)' }} at {{ \Illuminate\Support\Str::before($V::SIGNS[$e['sign']], ' (') }} {{ $e['deg'] }}@endif</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                @endif

                @if ($now)
                    <h2 class="mt-8 text-xl">Effect on each Moon sign (current position)</h2>
                    <p class="mt-1 text-sm text-ink-600">{{ $name }} in {{ \Illuminate\Support\Str::before($V::SIGNS[$now['sign']], ' (') }}, counted from your Moon sign (Chandra rashi). The verdict is the classical gochar rule; written notes are added by Acharya Ji's team.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($G::fromMoon($planet, $now['sign']) as $moon => $h)
                            <article class="card p-4">
                                <h3 class="text-base"><a href="{{ route('rashi', $C::signSlugs()[$moon]) }}">{{ $C::SIGNS_HI[$moon] }} · {{ \Illuminate\Support\Str::before($V::SIGNS[$moon], ' (') }}</a></h3>
                                <p class="mt-1 text-sm">House {{ $h['house'] }} from the Moon · <span @class(['badge-success' => $h['good'], 'badge-gold' => ! $h['good']])>{{ $h['good'] ? 'Traditionally favourable' : 'Traditionally needs care' }}</span></p>
                                @if ($notes->has($moon))<p class="mt-2 whitespace-pre-line text-sm leading-6" lang="{{ $locale }}">{{ $notes[$moon]->body }}</p>@endif
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs"><a href="{{ route('gochar.planet', ['planet' => $planet, 'lang' => $hi ? 'en' : 'hi'] + ($isMoon ? ['month' => $from->format('Y-m')] : ['year' => $from->year])) }}">{{ $hi ? 'Notes in English' : 'हिन्दी में फल' }}</a></p>
                @endif
            </section>

            <aside class="card h-fit p-5" aria-labelledby="facts-h">
                <h2 id="facts-h" class="text-lg">About {{ $name }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-ink-600">Signifies</dt><dd>{{ $f['signifies'] }}</dd></div>
                    <div><dt class="text-ink-600">Time in a sign</dt><dd>{{ $f['stay'] }}</dd></div>
                    @if ($own = $G::ownSigns($planet))<div><dt class="text-ink-600">Rules</dt><dd>{{ collect($own)->map(fn ($s) => \Illuminate\Support\Str::before($V::SIGNS[$s], ' ('))->implode(', ') }}</dd></div>@endif
                    <div><dt class="text-ink-600">Exalted / debilitated</dt><dd>{{ \Illuminate\Support\Str::before($V::SIGNS[$f['exalt'][0]], ' (') }}{{ $f['exalt'][1] ? ' '.$f['exalt'][1].'°' : '' }} / {{ \Illuminate\Support\Str::before($V::SIGNS[$f['debil']], ' (') }}</dd></div>
                    <div><dt class="text-ink-600">Nakshatras ruled</dt><dd>{{ implode(', ', $G::nakshatras($planet)) }}</dd></div>
                    <div><dt class="text-ink-600">Vimshottari dasha</dt><dd>{{ $V::DASHA_YEARS[$planet] }} years</dd></div>
                </dl>
                <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary mt-5 w-full">Ask Acharya Ji about this transit</a>
            </aside>
        </div>
        <p class="mt-8 text-xs text-ink-600">Rahu and Ketu use the mean node. Times are the moment of the event in Indian Standard Time; your local effect depends on your own chart and dasha.</p>
    </div>
</x-layouts.public>
