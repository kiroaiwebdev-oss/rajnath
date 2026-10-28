<x-layouts.public title="Free calculators" description="Free Kundli, Kundli matching, Panchang, Moon sign, Nakshatra, Sade Sati, numerology and more.">
    <div class="container-page py-10">
        <p class="eyebrow">Free tools</p>
        <h1 class="mt-2 text-3xl">Calculators</h1>
        <p class="mt-2 max-w-2xl text-ink-600">Astronomical positions come from the Swiss Ephemeris with the Lahiri ayanamsha. Each tool is labelled: <strong>astronomical</strong> (computed), <strong>traditional</strong> (classical rules applied to computed positions), <strong>symbolic</strong> (numerology traditions) or <strong>entertainment</strong>.</p>
        <a href="{{ route('panchang') }}" class="card mt-6 flex flex-wrap items-center justify-between gap-3 p-5 no-underline">
            <span><span class="eyebrow">Daily</span><span class="mt-1 block text-lg font-semibold text-ink-900">Today's Panchang</span><span class="text-sm text-ink-600">Tithi, nakshatra, yoga, karana, sunrise, Rahu Kaal and more for your city.</span></span>
            <span class="btn-primary">Open Panchang</span>
        </a>
        @foreach (['traditional' => 'Kundli & Jyotish', 'astronomical' => 'Positions', 'symbolic' => 'Numerology & symbols', 'entertainment' => 'Just for fun'] as $kind => $heading)
            <h2 class="mt-10 text-xl">{{ $heading }}</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tools as $slug => [$title, , $k, $desc])
                    @continue($k !== $kind)
                    <li class="card relative p-5">
                        <h3 class="text-base"><a href="{{ route('tools.show', $slug) }}" class="no-underline after:absolute after:inset-0">{{ $title }}</a></h3>
                        <p class="mt-1.5 text-sm text-ink-600">{{ $desc }}</p>
                        <span class="badge-ink mt-3">{{ $k }}</span>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
</x-layouts.public>
