{{-- Home: hero with Moon-sign form, trust bar, ways to get guidance, today's Rashifal, the full ecosystem. --}}
@php
    $st = $profile->stats();
    $rv = app(\App\Domain\Reviews\Reviews::class)->summary();
    $price = fn (string $slug) => \App\Models\Offering::public()->with('variants')->where('slug', $slug)->first()?->fromPriceMinor();
    $fmt = fn (?int $m) => $m ? \App\Support\Money::format($m) : null;
    $signs = ['मे', 'वृ', 'मि', 'क', 'सिं', 'क', 'तु', 'वृ', 'ध', 'म', 'कुं', 'मी'];
    $signSlugs = \App\Http\Controllers\Web\ContentController::signSlugs();
@endphp

<section class="relative overflow-hidden bg-ink-900 text-white">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-40" style="background: radial-gradient(60rem 30rem at 85% -10%, rgb(217 164 65 / .35), transparent 60%), radial-gradient(40rem 30rem at -10% 110%, rgb(68 77 128 / .6), transparent 60%)"></div>
    <div class="container-page relative grid items-center gap-10 py-12 md:py-16 lg:grid-cols-[1.15fr_0.85fr]">
        <div>
            <p class="eyebrow text-gold-200">{{ $profile->text('home.eyebrow') }}</p>
            <h1 class="mt-3 text-4xl leading-tight text-white sm:text-5xl">{{ __('Clear, practical guidance from :name', ['name' => $site('brand.practitioner_name')]) }}</h1>
            <p class="mt-5 max-w-xl text-lg text-white/80">{{ $profile->text('home.subtitle') }}</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-gold">{{ __('Book a consultation') }}</a>
                <a href="{{ route('services.show', 'ask-one-question') }}" class="btn border border-white/30 text-white hover:bg-white/10">{{ __('Ask a question') }}@if ($p = $fmt($price('ask-one-question'))) · {{ __('from :price', ['price' => $p]) }}@endif</a>
            </div>
            <dl class="mt-10 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (array_filter([[__('Years of practice'), $st['years']], [__('Clients guided'), $st['clients']], [__('Awards'), $st['awards']], $rv['count'] ? [__('Client rating'), number_format($rv['average'], 1).'/5'] : null]) as [$l, $n])
                    @if ($n !== '' && $n !== '0')<div class="rounded-2xl border border-white/15 bg-white/5 p-4 backdrop-blur"><dt class="text-xs text-white/70">{{ $l }}</dt><dd class="mt-1 text-2xl font-semibold text-white">{{ $n }}</dd></div>@endif
                @endforeach
            </dl>
        </div>
        <div class="relative">
            <figure class="relative mx-auto w-full max-w-sm">
                <div aria-hidden="true" class="absolute -inset-3 rounded-[2rem] bg-gold-500/20 ring-1 ring-gold-200/40"></div>
                <x-photo :src="$profile->photo('profile.photo_home')" :alt="$site('brand.practitioner_name').' at his office in Patna'" eager
                         class="relative aspect-[480/600] w-full rounded-[1.75rem] object-cover object-top shadow-2xl" />
            </figure>
            <form method="POST" action="{{ route('tools.compute', 'moon-sign') }}" class="card relative z-10 mx-auto -mt-16 w-full max-w-md space-y-3 p-5 text-ink-900 lg:-ml-10" novalidate aria-labelledby="ms-h">
                @csrf
                <p id="ms-h" class="font-semibold">{{ __('Do you know your Moon sign?') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="birth_date" :label="__('Date of birth')" type="date" min="1800-01-01" max="2399-12-31" required id="hero-dob" />
                    <x-field name="birth_time" :label="__('Time of birth')" type="time" required id="hero-tob" />
                </div>
                <div x-data="placePicker" class="relative">
                    <label for="place-hero" class="field-label">{{ __('Place of birth') }}</label>
                    <input id="place-hero" type="text" name="place_label" class="input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="list-hero" :aria-expanded="expanded"
                           x-model="query" @input="onInput" @keydown="onKey" @blur="close" placeholder="{{ __('Start typing a town or city') }}" aria-describedby="status-hero">
                    <input type="hidden" name="place_id" :value="placeId">
                    <ul id="list-hero" role="listbox" x-show="open" x-cloak class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-ink-200 bg-white py-1 shadow-[var(--shadow-card)]">
                        <template x-for="(pl, index) in results" :key="pl.id">
                            <li role="option" :data-index="index" :aria-selected="index === active ? 'true' : 'false'" class="cursor-pointer px-3 py-2.5 text-sm aria-selected:bg-ivory-100 hover:bg-ivory-50" @mousedown.prevent="choose"><span x-text="pl.label"></span></li>
                        </template>
                    </ul>
                    <p id="status-hero" class="sr-only" role="status" aria-live="polite" x-text="status"></p>
                </div>
                <button class="btn-primary w-full">{{ __('Find my Moon sign — free') }}</button>
            </form>
        </div>
    </div>
</section>

{{-- Ways to get guidance --}}
<section class="container-page py-16" aria-labelledby="ways-h">
    <p class="eyebrow">{{ __('Real astrologers. Real conversations.') }}</p>
    <h2 id="ways-h" class="mt-2 max-w-2xl text-2xl sm:text-3xl">{{ __('Choose how you want guidance') }}</h2>
    <div class="mt-8 grid gap-5 md:grid-cols-3">
        @foreach ([
            ['family-consultation', __('Talk to Acharya Ji'), __('One-to-one by audio, video or in person in Patna, for career, marriage, health and the decisions that cannot wait.'), route('services.show', 'consultation-with-acharya-ji'), $fmt($price('consultation-with-acharya-ji')), __('Book a call')],
            ['family-ask-a-question', __('Ask a question in writing'), __('Send one, two or three questions with your birth details. A written answer reaches your account in about 48 working hours.'), route('services.show', 'ask-one-question'), $fmt($price('ask-one-question')), __('Ask now')],
            ['family-reports', __('Personal reports'), __('Premium Kundli, career, finance, love, Sade Sati, Varshphal and more — prepared from your chart and delivered as a PDF.'), route('services.index', ['family' => 'reports']), $fmt($price('premium-personalized-kundli')), __('See reports')],
        ] as [$img, $t, $d, $href, $from, $cta])
            <article class="card relative flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
                <x-photo :src="'images/art/'.$img.'.jpg'" alt="" class="aspect-[16/9] w-full object-cover" />
                <div class="flex flex-1 flex-col p-6">
                    <h3 class="text-lg"><a href="{{ $href }}" class="no-underline after:absolute after:inset-0">{{ $t }}</a></h3>
                    <p class="mt-2 flex-1 text-sm text-ink-600">{{ $d }}</p>
                    <p class="mt-5 flex items-center justify-between text-sm"><span class="font-semibold">{{ $from ? __('From :price', ['price' => $from]) : '' }}</span><span class="inline-flex items-center gap-1 font-semibold text-gold-700">{{ $cta }}<svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.3 4.3a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4L11.6 10 7.3 5.7a1 1 0 010-1.4z" clip-rule="evenodd"/></svg></span></p>
                </div>
            </article>
        @endforeach
    </div>
</section>

{{-- Today's Rashifal --}}
<section class="bg-ivory-100 py-16" aria-labelledby="rf-h">
    <div class="container-page">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">{{ __('Rashifal') }}</p><h2 id="rf-h" class="mt-2 text-2xl sm:text-3xl">{{ __('Your sign today') }}</h2></div>
            <a href="{{ route('rashifal') }}" class="btn-outline">{{ __('Daily, weekly and yearly Rashifal') }}</a>
        </div>
        <ul class="mt-8 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach (\App\Domain\Astro\Vedic::SIGNS as $i => $name)
                <li><a href="{{ route('rashi', $signSlugs[$i]) }}" class="card flex flex-col items-center gap-1 p-4 text-center no-underline transition hover:-translate-y-0.5 hover:ring-2 hover:ring-gold-500">
                    <span aria-hidden="true" lang="hi" class="grid size-12 place-items-center rounded-full bg-ink-900 text-lg font-semibold text-gold-200 ring-2 ring-gold-500/40">{{ $signs[$i] }}</span>
                    <span class="mt-1 text-sm font-semibold text-ink-900">{{ __(strtok($name, ' ')) }}</span>
                    <span class="text-xs text-ink-600">{{ trim(strstr($name, ' '), ' ()') }}</span>
                </a></li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Everything in one place --}}
<section class="container-page py-16" aria-labelledby="eco-h">
    <p class="eyebrow">{{ __('Everything for your spiritual journey') }}</p>
    <h2 id="eco-h" class="mt-2 max-w-2xl text-2xl sm:text-3xl">{{ __('One place for guidance, ritual and learning') }}</h2>
    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['kundli', __('Free Kundli'), __('Birth chart, Navamsa and dasha in seconds.'), route('tools.show', 'kundli')],
            ['kundli-matching', __('Kundli matching'), __('36-guna milan with Manglik check.'), route('tools.show', 'kundli-matching')],
            ['family-puja', __('Online puja'), __('Sankalp in your name, performed on a shubh muhurat.'), route('services.index', ['family' => 'puja'])],
            ['family-chadhava', __('Chadhava'), __('Offerings at temples on your behalf.'), route('services.index', ['family' => 'chadhava'])],
            ['gems', __('Gemstones and samagri'), __('Certified stones, rudraksha, yantra and pooja kits.'), route('store.index')],
            ['panchang', __('Panchang'), __('Tithi, nakshatra, Rahu Kaal, Choghadiya and Hora.'), route('panchang')],
            ['muhurat', __('Shubh muhurat'), __('Dates for vivah, griha pravesh, mundan and more.'), route('muhurat.index')],
            ['gochar', __('Grah gochar'), __('Where the planets are now, and what it means for your sign.'), route('gochar')],
            ['festivals', __('Festivals and vrat'), __('Calendar for the year, by city.'), route('festivals')],
            ['calculators', __('26 free calculators'), __('Numerology, Lo Shu, Sade Sati, Lal Kitab and more.'), route('tools.index')],
            ['blog', __('Articles'), __('Guides to kundli, Vastu, remedies and festivals.'), route('blog')],
            ['courses', __('Courses'), __('Learn Jyotish with Acharya Ji — coming soon.'), route('courses')],
        ] as [$img, $t, $d, $href])
            <li><a href="{{ $href }}" class="card group flex items-center gap-4 p-3 no-underline transition hover:-translate-y-0.5 hover:shadow-lg">
                <x-photo :src="'images/art/'.$img.'.jpg'" alt="" class="size-20 shrink-0 rounded-xl object-cover" />
                <span><span class="block font-semibold text-ink-900 group-hover:underline">{{ $t }}</span><span class="mt-0.5 block text-sm text-ink-600">{{ $d }}</span></span>
            </a></li>
        @endforeach
    </ul>
</section>
