<x-layouts.public title="Vrat calendar" description="Monthly vrat and festival calendar: Ekadashi, Pradosh, Purnima, Amavasya, Chaturthi and Sankranti for your city.">
    <div class="container-page py-10">
        <p class="eyebrow">Calendar</p>
        <h1 class="mt-2 text-3xl">{{ $month->format('F Y') }} — vrat dates</h1>
        <p class="mt-1 text-ink-600">{{ $placeLabel }}</p>
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Month">
            <a class="btn-outline" href="{{ route('calendar', array_filter(['month' => $month->subMonth()->format('Y-m'), 'place_id' => $place->id])) }}">← {{ $month->subMonth()->format('M') }}</a>
            <a class="btn-outline" href="{{ route('calendar', array_filter(['month' => $month->addMonth()->format('Y-m'), 'place_id' => $place->id])) }}">{{ $month->addMonth()->format('M') }} →</a>
        </nav>
        @if ($days === null)
            <div class="card mt-6 border-red-200 bg-red-50 p-5" role="alert">The calendar could not be calculated right now. Please try again shortly.</div>
        @else
            <ul class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($days as $date => $d)
                    <li @class(['card p-4', 'border-gold-500 bg-gold-50' => count($d['events']) > 0])>
                        <p class="text-sm font-semibold">{{ \Illuminate\Support\Carbon::parse($date)->format('D, d M') }}</p>
                        <p class="text-xs text-ink-600">{{ $d['tithi'] }} · {{ $d['nakshatra'] }}</p>
                        @foreach ($d['events'] as $e)<p class="mt-1 text-sm font-medium text-gold-700">{{ $e }}</p>@endforeach
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-xs text-ink-600">Dates use the tithi at local sunrise (udaya tithi) with Swiss Ephemeris and Lahiri ayanamsha. Regional rules (e.g. Ekadashi when Dashami overlaps, Pradosh at dusk) are not applied, so observance in your tradition may differ by a day. Major festival dates should be confirmed with Acharya Ji.</p>
        @endif
    </div>
</x-layouts.public>
