@php $R = \App\Domain\Astro\Festivals::RELIGIONS; @endphp
<x-layouts.public :title="'Festivals and holidays '.$year" :description="'Hindu, Islamic, Christian, Sikh, Jain and Buddhist festival dates for '.$year.' in '.$placeLabel.'.'" :jsonld="[\App\Support\Seo\JsonLd::breadcrumbs([['Festivals and holidays', route('festivals')]])]">
    <div class="container-page py-10">
        <p class="eyebrow">Festivals · त्योहार</p>
        <h1 class="mt-2 text-3xl">Festivals and holidays {{ $year }}</h1>
        <p class="mt-1 text-ink-600">{{ $placeLabel }}</p>
        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Religion">
            <a href="{{ route('festivals', array_filter(['year' => $year, 'place_id' => $place->id])) }}" @class(['btn px-4', 'bg-ink-900 text-white' => ! $religion, 'border border-ink-200 bg-white text-ink-800' => $religion]) @if (! $religion) aria-current="page" @endif>All</a>
            @foreach ($R as $k => $l)<a href="{{ route('festivals', array_filter(['year' => $year, 'religion' => $k, 'place_id' => $place->id])) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $religion === $k, 'border border-ink-200 bg-white text-ink-800' => $religion !== $k]) @if ($religion === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach
        </nav>
        <nav class="mt-3 flex flex-wrap gap-2" aria-label="Year">
            <a class="btn-outline" href="{{ route('festivals', array_filter(['year' => $year - 1, 'religion' => $religion, 'place_id' => $place->id])) }}"><span aria-hidden="true">←</span> {{ $year - 1 }}</a>
            <a class="btn-outline" href="{{ route('festivals', array_filter(['year' => $year + 1, 'religion' => $religion, 'place_id' => $place->id])) }}">{{ $year + 1 }} <span aria-hidden="true">→</span></a>
        </nav>
        @if ($list === null)
            <div class="card mt-6 border-red-200 bg-red-50 p-5" role="alert">The calendar could not be calculated right now. Please try again shortly.</div>
        @else
            @foreach ($list->groupBy(fn ($f) => substr($f['date'], 0, 7)) as $ym => $items)
                <section class="mt-8" aria-labelledby="m-{{ $ym }}">
                    <h2 id="m-{{ $ym }}" class="text-xl">{{ \Illuminate\Support\Carbon::parse($ym.'-01')->format('F Y') }}</h2>
                    <div class="table-wrap mt-3" role="region" aria-label="{{ \Illuminate\Support\Carbon::parse($ym.'-01')->format('F Y') }}" tabindex="0"><table class="table text-sm">
                        <thead><tr><th scope="col" class="w-40">Date</th><th scope="col">Festival</th><th scope="col" class="w-28">Religion</th><th scope="col">How the date is set</th></tr></thead>
                        <tbody>@foreach ($items as $f)<tr>
                            <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($f['date'])->format('D, d M') }}</td>
                            <td class="font-medium">{{ $f['name'] }}</td><td>{{ $R[$f['religion']] }}</td><td class="text-xs text-ink-600">{{ $f['basis'] }}</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                </section>
            @endforeach
            <div class="mt-8 space-y-2 text-xs text-ink-600">
                <p>Hindu, Sikh, Jain and Buddhist lunar dates are calculated for {{ $place->name }} with the Swiss Ephemeris (Lahiri ayanamsha), using amanta months and the tithi current at the time each festival needs (sunrise, midday, afternoon, evening pradosh, midnight or moonrise). Holika Dahan avoids Bhadra. Other regional rules (for example Smarta and Vaishnava Janmashtami, Bhadra for Raksha Bandhan, dashami-viddha Ekadashi) are not applied, so your tradition may observe some festivals a day earlier or later.</p>
                <p>Islamic dates follow the Umm al-Qura calendar; in India they are often one day later, after the moon is sighted. Christian dates use the Gregorian (Western) Easter.</p>
                <p>Please confirm dates for important ceremonies with Acharya Ji. Monthly vrat dates are in the <a href="{{ route('calendar') }}">vrat calendar</a>.</p>
            </div>
        @endif
    </div>
</x-layouts.public>
