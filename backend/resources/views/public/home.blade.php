@php
    $homeFaqs = [
        [__('How do I book a consultation with Acharya Ji?'), __('Open Consultations, choose audio, video or in person, pick a time and pay online. You can also call or WhatsApp the office.')],
        [__('What do I need for a kundli reading?'), __('Your date, exact time and place of birth. If the time is not known, palmistry and Prashna kundli can still help.')],
        [__('How soon do reports and written answers arrive?'), __('Written answers in about 48 working hours, reports in 3–5 working days, in your account and by email.')],
        [__('Can I consult from outside India?'), __('Yes. Consultations are held by video or audio call, and payment is online.')],
    ];
@endphp
<x-layouts.public :description="__('Vedic astrology consultations, Kundli, Vastu and remedies with Acharya Dr. Rajnath Jha, Ph.D. in Astrology, Director of Jyotirveda Vigyan Sansthan, Patna.')" :jsonld="[\App\Support\Seo\JsonLd::faq($homeFaqs), ['@type' => 'ItemList', 'name' => __('How Acharya Ji can help'), 'itemListElement' => $featured->values()->map(fn ($o, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => route('services.show', $o->slug), 'name' => $o->t()?->title])->all()]]">
    @include('public.partials.home-top')

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

    {{-- Reviews (Admin › Reviews; featured ones first) --}}
    @php $homeReviews = app(\App\Domain\Reviews\Reviews::class)->featured(3); $rs = app(\App\Domain\Reviews\Reviews::class)->summary(); @endphp
    @if ($homeReviews->isNotEmpty())
    <section class="container-page pt-16" aria-labelledby="rev-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">{{ __('Reviews') }}</p>
                <h2 id="rev-h" class="mt-2 text-2xl">{{ __('What clients say') }}</h2>
                <p class="mt-1 flex items-center gap-2 text-sm text-ink-600"><x-stars :rating="$rs['average']" /> {{ number_format($rs['average'], 1) }} / 5 · {{ trans_choice(':count review|:count reviews', $rs['count'], ['count' => $rs['count']]) }}</p>
            </div>
            <a href="{{ route('reviews') }}" class="btn-outline">{{ __('All reviews') }}</a>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach ($homeReviews as $r)<x-review-card :r="$r" clamp />@endforeach
        </div>
    </section>
    @endif

    {{-- In the spotlight: videos from published gallery albums --}}
    @php $videos = \Illuminate\Support\Facades\DB::table('gallery_items as i')->join('gallery_albums as a', 'a.id', '=', 'i.album_id')->where('a.published', true)->where('i.kind', 'video')->orderByDesc('i.id')->limit(3)->get(['i.video_id', 'i.caption', 'a.title', 'a.slug']); @endphp
    @if ($videos->isNotEmpty())
    <section class="container-page pt-16" aria-labelledby="vid-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">{{ __('In the spotlight') }}</p><h2 id="vid-h" class="mt-2 text-2xl">{{ __('Watch Acharya Ji') }}</h2></div>
            <a href="{{ route('gallery') }}" class="btn-outline">{{ __('All videos and photos') }}</a>
        </div>
        <ul class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach ($videos as $v)
                <li class="card relative overflow-hidden">
                    <img src="{{ \App\Domain\Showcase\Gallery::videoThumb($v->video_id) }}" alt="" width="480" height="360" loading="lazy" decoding="async" class="aspect-video w-full object-cover">
                    <span aria-hidden="true" class="absolute inset-x-0 top-0 grid aspect-video place-items-center"><span class="grid size-14 place-items-center rounded-full bg-ink-900/75 text-white"><svg class="ml-1 size-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span>
                    <p class="p-4 text-sm font-semibold"><a href="{{ route('gallery.show', $v->slug) }}" class="no-underline after:absolute after:inset-0">{{ $v->caption ?: $v->title }}</a></p>
                </li>
            @endforeach
        </ul>
    </section>
    @endif

    {{-- Latest articles --}}
    @php $latestPosts = app(\App\Domain\Blog\Blog::class)->latest(3); @endphp
    @if ($latestPosts->isNotEmpty())
    <section class="container-page pt-16" aria-labelledby="art-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="eyebrow">{{ __('Articles') }}</p><h2 id="art-h" class="mt-2 text-2xl">{{ __('Learn from the blog') }}</h2></div>
            <a href="{{ route('blog') }}" class="btn-outline">{{ __('All articles') }}</a>
        </div>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach ($latestPosts as $lp)@include('public.blog.card', ['p' => $lp])@endforeach</div>
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

    {{-- Why online, and common questions (also FAQPage data) --}}
    <section class="bg-ivory-100 py-16" aria-labelledby="why-h">
        <div class="container-page grid gap-10 lg:grid-cols-2">
            <div>
                <p class="eyebrow">{{ __('Why Astro Rajnath Jha') }}</p>
                <h2 id="why-h" class="mt-2 text-2xl sm:text-3xl">{{ __('Traditional Jyotish, explained plainly') }}</h2>
                <div class="mt-5 space-y-4 leading-7 text-ink-800">
                    <p>{{ __('Every reading starts from your exact birth details and the classical Parashari method. Acharya Ji explains what the chart shows, what is likely to help, and what is simply a matter of time — without fear and without expensive remedies.') }}</p>
                    <p>{{ __('You can meet him in Patna, speak by audio or video from anywhere in the world, or send a question and receive a written answer. Pujas are performed with full vidhi on a shubh muhurat, with photos in your account.') }}</p>
                </div>
                <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach ([__('Ph.D. in Astrology, 30+ years of practice'), __('Personal attention to every chart'), __('Simple, affordable remedies'), __('Hindi, Maithili, Sanskrit and English'), __('Private and confidential'), __('Secure payments, GST invoices')] as $pt)
                        <li class="flex items-start gap-2 text-sm"><svg aria-hidden="true" class="mt-0.5 size-5 shrink-0 text-gold-700" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>{{ $pt }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="text-xl">{{ __('Questions people ask') }}</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($homeFaqs as [$q, $a])
                        <details class="card p-4"><summary class="cursor-pointer font-semibold">{{ $q }}</summary><p class="mt-2 text-sm leading-6 text-ink-700">{{ $a }}</p></details>
                    @endforeach
                </div>
                <a href="{{ route('faq') }}" class="btn-outline mt-5">{{ __('All questions') }}</a>
            </div>
        </div>
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
