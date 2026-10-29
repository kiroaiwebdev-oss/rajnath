@php
    $V = \App\Domain\Astro\Vedic::class;
    $G = \App\Domain\Astro\Gochar::class;
    $T = \App\Domain\Content\HoroscopeTypes::class;
    $C = \App\Http\Controllers\Web\ContentController::class;
    $hi = $locale === 'hi';
    $elements = [['अग्नि', 'Fire'], ['पृथ्वी', 'Earth'], ['वायु', 'Air'], ['जल', 'Water']];
    $qualities = [['चर', 'Movable (chara)'], ['स्थिर', 'Fixed (sthira)'], ['द्विस्वभाव', 'Dual (dwiswabhava)']];
    $lord = $V::SIGN_LORDS[$i];
    $exalted = collect($G::FACTS)->filter(fn ($f) => $f['exalt'][0] === $i)->keys();
    $debil = collect($G::FACTS)->filter(fn ($f) => $f['debil'] === $i)->keys();
    $name = $C::SIGNS_HI[$i].' — '.$V::SIGNS[$i];
@endphp
<x-layouts.public :title="$C::SIGNS_HI[$i].' राशि — '.$V::SIGNS[$i]" :description="'Guide to '.$V::SIGNS[$i].': lord, element, nakshatras, current transits and published Rashifal.'">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-600"><a href="{{ route('rashifal', ['lang' => $locale]) }}">{{ $hi ? 'राशिफल' : 'Rashifal' }}</a> / <span aria-current="page" class="text-ink-900">{{ $name }}</span></nav>
        <h1 class="mt-3 text-3xl">{{ $name }}</h1>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="card p-5" aria-labelledby="facts-h">
                <h2 id="facts-h" class="text-lg">{{ $hi ? 'राशि परिचय' : 'About this sign' }}</h2>
                <dl class="mt-3 grid grid-cols-[10rem_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-ink-600">Symbol</dt><dd>{{ $C::SIGN_SYMBOLS[$i] }}</dd>
                    <dt class="text-ink-600">Lord (swami)</dt><dd><a href="{{ route('gochar.planet', $lord) }}">{{ $V::PLANET_NAMES[$lord] }}</a></dd>
                    <dt class="text-ink-600">Element (tattva)</dt><dd>{{ implode(' / ', $elements[$i % 4]) }}</dd>
                    <dt class="text-ink-600">Quality</dt><dd>{{ implode(' / ', $qualities[$i % 3]) }}</dd>
                    <dt class="text-ink-600">Nature</dt><dd>{{ $i % 2 === 0 ? 'Odd sign (masculine, krura)' : 'Even sign (feminine, saumya)' }}</dd>
                    <dt class="text-ink-600">Nakshatras</dt><dd>{{ collect($nakshatras)->map(fn ($p, $n) => "$n (pada $p)")->implode(', ') }}</dd>
                    @if ($exalted->isNotEmpty())<dt class="text-ink-600">Exalted here</dt><dd>{{ $exalted->map(fn ($p) => $V::PLANET_NAMES[$p])->implode(', ') }}</dd>@endif
                    @if ($debil->isNotEmpty())<dt class="text-ink-600">Debilitated here</dt><dd>{{ $debil->map(fn ($p) => $V::PLANET_NAMES[$p])->implode(', ') }}</dd>@endif
                    <dt class="text-ink-600">Lord's day</dt><dd>{{ $G::FACTS[$lord]['day'] }}</dd>
                    <dt class="text-ink-600">Lord's gemstone</dt><dd>{{ $G::FACTS[$lord]['gem'] }} <span class="text-xs text-ink-600">(wear only after a chart reading)</span></dd>
                    @if ($sunIn)<dt class="text-ink-600">Sun in this sign</dt><dd>{{ $sunIn['at']->format('d M') }} – {{ $sunOut ? $sunOut['at']->format('d M Y') : '' }} (sidereal)</dd>@endif
                </dl>
            </section>

            <section class="card p-5" aria-labelledby="gochar-h">
                <h2 id="gochar-h" class="text-lg">{{ $hi ? 'वर्तमान गोचर (चंद्र राशि से)' : 'Current transits from this Moon sign' }}</h2>
                @if ($now)
                    <div class="table-wrap mt-3" role="region" aria-label="Transits" tabindex="0"><table class="table text-sm">
                        <thead><tr><th scope="col">Planet</th><th scope="col">In sign</th><th scope="col">House from Moon</th><th scope="col">Traditional reading</th></tr></thead>
                        <tbody>@foreach ($now as $p => $pos)@php $h = $G::fromMoon($p, $pos['sign'])[$i]; @endphp
                            <tr><td><a href="{{ route('gochar.planet', $p) }}">{{ $V::PLANET_NAMES[$p] }}</a>@if ($pos['retro'] && ! in_array($p, ['rahu', 'ketu'], true)) <span class="text-xs">(R)</span>@endif</td>
                                <td>{{ \Illuminate\Support\Str::before($V::SIGNS[$pos['sign']], ' (') }}</td><td>{{ $h['house'] }}</td>
                                <td><span @class(['badge-success' => $h['good'], 'badge-gold' => ! $h['good']])>{{ $h['good'] ? 'Favourable' : 'Needs care' }}</span></td></tr>
                        @endforeach</tbody>
                    </table></div>
                    <p class="mt-2 text-xs text-ink-600">Counted from the Moon sign by the classical gochar table (Phaladeepika). Vedha and your own dasha are not considered.</p>
                @else
                    <p class="mt-3 text-sm text-ink-600">Transits could not be calculated right now.</p>
                @endif
            </section>
        </div>

        <section class="mt-8" aria-labelledby="pub-h">
            <h2 id="pub-h" class="text-xl">{{ $hi ? 'प्रकाशित राशिफल' : 'Published Rashifal' }}</h2>
            @forelse ($rows as $r)
                <article class="card mt-4 p-5"><h3 class="text-base">{{ $T::PERIODS[$r->period][$hi ? 0 : 1] ?? $r->period }}@if ($r->topic !== 'general') · {{ $T::TOPICS[$r->topic][$hi ? 0 : 1] }}@endif</h3><p class="mt-2 whitespace-pre-line leading-7" lang="{{ $locale }}">{{ $r->body }}</p></article>
            @empty
                <p class="card mt-4 p-6 text-center text-ink-600">{{ $hi ? 'अभी कोई राशिफल प्रकाशित नहीं है।' : 'Nothing published for this sign right now.' }} <a href="{{ route('rashifal', ['lang' => $locale]) }}">{{ $hi ? 'सभी राशिफल' : 'All Rashifal' }}</a></p>
            @endforelse
        </section>

        <nav class="mt-8 flex flex-wrap gap-2 text-sm" aria-label="Other signs">
            @foreach ($C::signSlugs() as $k => $slug)<a href="{{ route('rashi', ['sign' => $slug, 'lang' => $locale]) }}" @class(['btn px-3', 'bg-ink-900 text-white' => $k === $i, 'border border-ink-200 bg-white text-ink-800' => $k !== $i])>{{ $C::SIGNS_HI[$k] }}</a>@endforeach
        </nav>
    </div>
</x-layouts.public>
