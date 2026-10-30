<x-layouts.public :description="__('Vedic astrology consultations, Kundli, Vastu and remedies with Acharya Dr. Rajnath Jha, Ph.D. in Astrology, Director of Jyotirveda Vigyan Sansthan, Patna.')" :jsonld="[['@type' => 'ItemList', 'name' => __('How Acharya Ji can help'), 'itemListElement' => $featured->values()->map(fn ($o, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => route('services.show', $o->slug), 'name' => $o->t()?->title])->all()]]">
    {{-- Hero --}}
    <section class="container-page grid items-center gap-10 pt-10 pb-16 md:grid-cols-[1.1fr_0.9fr] md:pt-16">
        <div>
            <p class="eyebrow">{{ $profile->text('home.eyebrow') }}</p>
            <h1 class="mt-3 text-4xl leading-tight sm:text-5xl">{{ __('Clear, practical guidance from :name', ['name' => $site('brand.practitioner_name')]) }}</h1>
            <p class="mt-5 max-w-xl text-lg text-ink-600">{{ $profile->text('home.subtitle') }}</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary">{{ __('Book a consultation') }}</a>
                <a href="{{ route('services.index') }}" class="btn-outline">{{ __('Browse all services') }}</a>
            </div>
            @php $st = $profile->stats(); @endphp
            <dl class="mt-10 grid max-w-lg grid-cols-3 gap-3 sm:gap-4">
                @foreach ([[__('Years of practice'), $st['years']], [__('Clients guided'), $st['clients']], [__('Awards'), $st['awards']]] as [$l, $n])
                    @if ($n !== '' && $n !== '0')<div class="card p-4"><dt class="text-xs text-ink-600">{{ $l }}</dt><dd class="mt-1 text-xl font-semibold sm:text-2xl">{{ $n }}</dd></div>@endif
                @endforeach
            </dl>
        </div>
        <figure class="relative mx-auto w-full max-w-sm">
            <div aria-hidden="true" class="absolute -inset-3 rounded-[2rem] bg-gold-50 ring-1 ring-gold-200"></div>
            <x-photo :src="$profile->photo('profile.photo_home')" :alt="$site('brand.practitioner_name').' at his office in Patna'" eager
                     class="relative aspect-[480/699] w-full rounded-[1.75rem] object-cover shadow-[var(--shadow-card)]" />
        </figure>
    </section>

    {{-- Featured services from the live catalogue --}}
    @if ($featured->isNotEmpty())
    <section class="container-page pb-16" aria-labelledby="featured-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">{{ __('Services') }}</p>
                <h2 id="featured-h" class="mt-2 text-2xl">{{ __('How Acharya Ji can help') }}</h2>
            </div>
            <a href="{{ route('services.index') }}" class="btn-outline">{{ __('All services') }}</a>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($featured as $o)<div class="relative"><x-service-card :o="$o" /></div>@endforeach
        </div>
    </section>
    @endif

    {{-- Recognition (Admin › About & awards) --}}
    @if ($highlights->isNotEmpty())
    <section class="bg-ivory-100 py-16" aria-labelledby="rec-h">
        <div @class(['container-page grid items-center gap-10', 'lg:grid-cols-2' => $awardPhotos->isNotEmpty()])>
            <div>
                <p class="eyebrow">{{ __('Recognition') }}</p>
                <h2 id="rec-h" class="mt-2 text-2xl">{{ __('Honoured in India and abroad') }}</h2>
                <ul class="mt-6 space-y-3 text-sm">
                    @foreach ($highlights as $e)
                        <li class="card p-4"><strong>{{ \App\Domain\Showcase\Profile::tr($e, 'title') }}</strong>@if ($d = \App\Domain\Showcase\Profile::tr($e, 'detail')) — {{ $d }}@endif @if ($e->year && ! str_contains((string) $d.$e->title, (string) $e->year))({{ $e->year }})@endif</li>
                    @endforeach
                </ul>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('awards') }}" class="btn-outline">{{ __('All awards') }}</a>
                    <a href="{{ route('about') }}" class="btn-ghost">{{ __('Full biography & awards') }}</a>
                </div>
            </div>
            @if ($awardPhotos->isNotEmpty())
            <div @class(['grid gap-4', 'grid-cols-[1fr_0.75fr]' => $awardPhotos->count() > 1])>
                @foreach ($awardPhotos as $ph)<x-photo :src="$ph['src']" :alt="$ph['alt']" class="h-full w-full rounded-[var(--radius-card)] object-cover" />@endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    {{-- Gallery (Admin › Gallery, albums marked "home page") --}}
    @if ($albums->isNotEmpty())
    <section class="container-page pt-16" aria-labelledby="gal-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">{{ __('Gallery') }}</p>
                <h2 id="gal-h" class="mt-2 text-2xl">{{ __('Pujas, events and moments') }}</h2>
            </div>
            <a href="{{ route('gallery') }}" class="btn-outline">{{ __('Open the gallery') }}</a>
        </div>
        <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($albums as $a)@include('public.gallery.card', ['a' => $a])@endforeach
        </ul>
    </section>
    @endif

    {{-- Families --}}
    <section class="container-page py-16" aria-labelledby="fam-h">
        <p class="eyebrow">{{ __('Find what you need') }}</p>
        <h2 id="fam-h" class="mt-2 text-2xl">{{ __('Browse by category') }}</h2>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($families as $f)
                <li class="card relative p-5">
                    <h3 class="text-base"><a href="{{ route('services.index', ['family' => $f->slug]) }}" class="no-underline after:absolute after:inset-0">{{ $f->label() }}</a></h3>
                    <p class="mt-2 text-sm text-ink-600">{{ $f->blurb() }}</p>
                    <p class="mt-3 text-xs font-semibold text-gold-700">{{ trans_choice(':count service|:count services', $f->offerings_count, ['count' => $f->offerings_count]) }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Visit --}}
    <section class="container-page" aria-labelledby="visit-h">
        <div class="card grid overflow-hidden md:grid-cols-2">
            <x-photo src="images/owner/office-wide-960.jpg" alt="Acharya Ji at work in his Kankarbagh office" class="h-full min-h-56 w-full object-cover" />
            <div class="p-6 sm:p-8">
                <p class="eyebrow">{{ __('Visit or call') }}</p>
                @php $biz = app(\App\Support\Business::class); @endphp
                <h2 id="visit-h" class="mt-2 text-2xl">{{ __($biz->address()['locality']) }}</h2>
                <p class="mt-3 text-ink-600">{{ $biz->addressLine() }}</p>
                <p class="mt-3 text-sm text-ink-600">{{ $biz->hoursLine() }}<br>{{ __('Online consultations by video or audio call.') }}</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a href="tel:{{ $site('contact.phone') }}" class="btn-primary">{{ __('Call :phone', ['phone' => $site('contact.phone')]) }}</a>
                    <a href="{{ route('contact') }}" class="btn-outline">{{ __('Directions & contact') }}</a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
