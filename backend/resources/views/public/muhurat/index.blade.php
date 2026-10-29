<x-layouts.public title="Shubh Muhurat" description="Auspicious dates for marriage (vivah), namkaran, annaprashan, mundan, karnavedha, vidyarambh and upanayan, calculated from the Panchang for your city.">
    <div class="container-page py-10">
        <p class="eyebrow">Shubh Muhurat · शुभ मुहूर्त</p>
        <h1 class="mt-2 text-3xl">Muhurat finder</h1>
        <p class="mt-2 max-w-3xl text-ink-600">Choose a ceremony to see suitable dates and time windows in a month, calculated from the Panchang for your city. Each list follows the classical tithi, vara and nakshatra rules; Acharya Ji finalises the exact muhurat with lagna and your family's charts.</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (\App\Domain\Astro\Muhurat::EVENTS as $k => $e)
                <a href="{{ route('muhurat.show', $k) }}" class="card p-5 no-underline hover:border-gold-500">
                    <span class="text-lg font-semibold text-ink-900">{{ $e['name'] }}</span>
                    <span class="mt-1 block text-ink-600" lang="hi">{{ $e['hi'] }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <p class="card p-5 text-sm">Need the final muhurat for your family? <a href="{{ route('services.show', 'baby-muhurat-consultation') }}">Baby Muhurat consultation</a> covers namkaran, annaprashan, mundan, karnavedha and vidyarambh. For marriage, book a <a href="{{ route('services.show', 'love-marriage-compatibility') }}">compatibility consultation</a>.</p>
            <p class="card border-gold-200 bg-gold-50 p-5 text-sm" role="note"><strong>Childbirth:</strong> we do not give dates or times for delivery. The timing and method of childbirth, including a caesarean section, are medical decisions for your doctors and must never be changed for astrological reasons.</p>
        </div>
        <p class="mt-6 text-sm">Also see: <a href="{{ route('panchang') }}">daily Panchang</a> · <a href="{{ route('calendar') }}">vrat calendar</a> · <a href="{{ route('festivals') }}">festival and holiday calendar</a></p>
    </div>
</x-layouts.public>
