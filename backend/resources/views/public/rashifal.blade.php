@php $names = $locale === 'hi' ? \App\Http\Controllers\Web\ContentController::SIGNS_HI : \App\Domain\Astro\Vedic::SIGNS; $pl = ['daily' => ['आज का राशिफल', 'Today'], 'weekly' => ['साप्ताहिक', 'This week'], 'monthly' => ['मासिक', 'This month'], 'yearly' => ['वार्षिक', 'This year']]; @endphp
<x-layouts.public :title="$locale === 'hi' ? 'राशिफल' : 'Rashifal'" description="Daily, weekly, monthly and yearly Rashifal reviewed by Acharya Dr. Rajnath Jha's team.">
    <div class="container-page py-10" lang="{{ $locale }}">
        <p class="eyebrow">{{ $locale === 'hi' ? 'राशिफल' : 'Rashifal' }}</p>
        <h1 class="mt-2 text-3xl">{{ $pl[$period][$locale === 'hi' ? 0 : 1] }}</h1>
        <nav aria-label="Period" class="mt-5 flex flex-wrap gap-2">
            @foreach ($pl as $k => $l)<a href="{{ route('rashifal', ['period' => $k, 'lang' => $locale]) }}" @class(['btn px-4', 'bg-ink-900 text-white' => $period === $k, 'border border-ink-200 bg-white text-ink-800' => $period !== $k]) @if ($period === $k) aria-current="page" @endif>{{ $l[$locale === 'hi' ? 0 : 1] }}</a>@endforeach
            <a href="{{ route('rashifal', ['period' => $period, 'lang' => $locale === 'hi' ? 'en' : 'hi']) }}" class="btn-ghost px-3" lang="{{ $locale === 'hi' ? 'en' : 'hi' }}">{{ $locale === 'hi' ? 'English' : 'हिन्दी' }}</a>
        </nav>
        @if ($rows->isEmpty())
            <p class="card mt-6 p-8 text-center text-ink-600">{{ $locale === 'hi' ? 'इस अवधि का राशिफल अभी प्रकाशित नहीं हुआ है।' : 'Rashifal for this period has not been published yet.' }}</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (range(0, 11) as $s)
                    <article class="card p-5">
                        <h2 class="text-lg">{{ $names[$s] }}</h2>
                        @if ($rows->has($s))<p class="mt-2 whitespace-pre-line leading-7">{{ $rows[$s]->body }}</p>@else<p class="mt-2 text-sm text-ink-600">{{ $locale === 'hi' ? 'जल्द आ रहा है' : 'Coming soon' }}</p>@endif
                    </article>
                @endforeach
            </div>
        @endif
        <p class="mt-6 text-xs text-ink-600">{{ $locale === 'hi' ? 'राशिफल चंद्र राशि पर आधारित सामान्य मार्गदर्शन है। व्यक्तिगत फल के लिए कुंडली देखें।' : 'Rashifal is general guidance by Moon sign. For personal guidance, consult your own chart.' }}</p>
    </div>
</x-layouts.public>
