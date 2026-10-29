@php
    $T = \App\Domain\Content\HoroscopeTypes::class;
    $hi = $locale === 'hi';
    $L = fn (array $pair) => $pair[$hi ? 0 : 1];
    $url = fn (array $over = []) => $kind === 'rashi'
        ? route('rashifal', array_filter(array_merge(['period' => $period, 'topic' => $topic !== 'general' ? $topic : null, 'lang' => $locale, 'year' => $year], $over)))
        : route('rashifal.kind', array_filter(array_merge(['kind' => $kind, 'period' => $period, 'lang' => $locale, 'year' => $year], $over)));
    $heading = $L($T::KINDS[$kind]).' — '.$L($T::PERIODS[$period]).($topic !== 'general' ? ' · '.$L($T::TOPICS[$topic]) : '').($year ? ' '.$year : '');
    $slugs = \App\Http\Controllers\Web\ContentController::signSlugs();
@endphp
<x-layouts.public :title="$hi ? 'राशिफल' : 'Horoscope'" description="Daily, weekly, monthly, half-yearly and yearly Rashifal; love, career, finance, health and education; nakshatra, Chinese, numerology and tarot — written and reviewed by Acharya Dr. Rajnath Jha's team.">
    <div class="container-page py-10" lang="{{ $locale }}">
        <p class="eyebrow">{{ $hi ? 'राशिफल' : 'Horoscope' }}</p>
        <h1 class="mt-2 text-3xl">{{ $heading }}</h1>

        <nav aria-label="{{ $hi ? 'प्रकार' : 'Type' }}" class="mt-5 flex flex-wrap gap-2">
            @foreach ($T::PUBLIC_KINDS as $k)
                <a href="{{ $k === 'rashi' ? route('rashifal', ['lang' => $locale]) : route('rashifal.kind', ['kind' => $k, 'lang' => $locale]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $kind === $k, 'border border-ink-200 bg-white text-ink-800' => $kind !== $k]) @if ($kind === $k) aria-current="page" @endif>{{ $L($T::KINDS[$k]) }}</a>
            @endforeach
            <a href="{{ $url(['lang' => $hi ? 'en' : 'hi']) }}" class="btn-ghost px-3" lang="{{ $hi ? 'en' : 'hi' }}">{{ $hi ? 'English' : 'हिन्दी' }}</a>
        </nav>
        <nav aria-label="{{ $hi ? 'अवधि' : 'Period' }}" class="mt-3 flex flex-wrap gap-2">
            @foreach ($T::PERIODS as $k => $l)<a href="{{ $url(['period' => $k, 'year' => null]) }}" @class(['btn px-4', 'bg-gold-700 text-white' => $period === $k, 'border border-ink-200 bg-white text-ink-800' => $period !== $k]) @if ($period === $k) aria-current="page" @endif>{{ $L($l) }}</a>@endforeach
        </nav>
        @if ($kind === 'rashi')
            <nav aria-label="{{ $hi ? 'विषय' : 'Topic' }}" class="mt-3 flex flex-wrap gap-2">
                @foreach ($T::TOPICS as $k => $l)<a href="{{ $url(['topic' => $k === 'general' ? null : $k]) }}" @class(['btn px-3 text-sm', 'bg-ink-900 text-white' => $topic === $k, 'border border-ink-200 bg-white text-ink-800' => $topic !== $k]) @if ($topic === $k) aria-current="page" @endif>{{ $L($l) }}</a>@endforeach
            </nav>
        @endif
        @if (in_array($period, ['yearly', 'halfyearly'], true))
            <nav aria-label="{{ $hi ? 'वर्ष' : 'Year' }}" class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $url(['year' => null]) }}" @class(['btn px-3 text-sm', 'bg-ink-900 text-white' => ! $year, 'border border-ink-200 bg-white text-ink-800' => $year]) @if (! $year) aria-current="page" @endif>{{ $hi ? 'वर्तमान' : 'Current' }}</a>
                @foreach ($years as $y)<a href="{{ $url(['year' => $y]) }}" @class(['btn px-3 text-sm', 'bg-ink-900 text-white' => $year === $y, 'border border-ink-200 bg-white text-ink-800' => $year !== $y]) @if ($year === $y) aria-current="page" @endif>{{ $period === 'yearly' && $hi ? 'वार्षिक राशिफल '.$y : $y }}</a>@endforeach
            </nav>
        @endif

        @if ($rows->isEmpty())
            <p class="card mt-6 p-8 text-center text-ink-600">{{ $hi ? 'इस अवधि का राशिफल अभी प्रकाशित नहीं हुआ है।' : 'This horoscope has not been published yet.' }}</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($names as $s => $name)
                    <article class="card p-5">
                        <h2 class="text-lg">@if ($kind === 'rashi')<a href="{{ route('rashi', ['sign' => $slugs[$s], 'lang' => $locale]) }}">{{ $name }}</a>@else{{ $name }}@endif</h2>
                        @forelse ($rows->get($s, collect()) as $r)
                            @if ($rows->get($s)->count() > 1)<p class="mt-3 text-xs font-semibold text-ink-600">{{ \Illuminate\Support\Carbon::parse($r->starts_on)->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($r->ends_on)->format('d M Y') }}</p>@endif
                            <p class="mt-2 whitespace-pre-line leading-7">{{ $r->body }}</p>
                        @empty
                            <p class="mt-2 text-sm text-ink-600">{{ $hi ? 'जल्द आ रहा है' : 'Coming soon' }}</p>
                        @endforelse
                    </article>
                @endforeach
            </div>
        @endif
        <p class="mt-6 text-xs text-ink-600">
            @if ($kind === 'chinese'){{ $hi ? 'चीनी राशि जन्म वर्ष से (चीनी नव वर्ष जनवरी–फरवरी में बदलता है)।' : 'Chinese sign by birth year (the Chinese year changes in late January or February).' }}
            @elseif ($kind === 'numerology'){{ $hi ? 'मूलांक = जन्म तारीख के अंकों का योग (1–9)।' : 'Mulank = digits of your birth date added to a single number (1–9).' }}
            @elseif ($kind === 'tarot'){{ $hi ? 'टैरो पठन मनोरंजन और आत्म-चिंतन के लिए है।' : 'Tarot readings are for reflection and entertainment.' }}
            @else{{ $hi ? 'राशिफल चंद्र राशि पर आधारित सामान्य मार्गदर्शन है। व्यक्तिगत फल के लिए कुंडली देखें।' : 'Rashifal is general guidance by Moon sign. For personal guidance, consult your own chart.' }}@endif
        </p>
    </div>
</x-layouts.public>
