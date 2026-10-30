@php
    use App\Domain\Showcase\Gallery;
    use App\Domain\Showcase\Profile;
    $stats = $p->stats();
    $awards = $p->entries('award');
    $name = $p->name();
@endphp
<x-layouts.public :title="__('About :name', ['name' => $name])" :description="\Illuminate\Support\Str::limit($p->text('profile.summary'), 158)" :image="$p->photo('profile.photo_about')" type="profile"
    :jsonld="[['@type' => 'ProfilePage', 'url' => route('about'), 'mainEntity' => ['@id' => \App\Support\Seo\JsonLd::id('founder')], 'dateModified' => \App\Support\Seo\JsonLd::profileModified()], \App\Support\Seo\JsonLd::breadcrumbs([[__('About'), route('about')]])]">
    <section class="container-page grid gap-10 py-12 lg:grid-cols-[0.8fr_1.2fr]">
        <figure class="mx-auto w-full max-w-sm">
            <x-photo :src="$p->photo('profile.photo_about')" :alt="$name" eager class="aspect-[3/4] w-full rounded-[var(--radius-card)] object-cover object-top shadow-[var(--shadow-card)]" />
        </figure>
        <div>
            <p class="eyebrow">{{ __('About the Acharya') }}</p>
            <h1 class="mt-2 text-3xl sm:text-4xl">{{ $name }}</h1>
            <p class="mt-2 text-ink-600">{{ $p->text('profile.headline') }}</p>
            <div class="mt-6 space-y-4 leading-7">
                @foreach ($p->paragraphs() as $para)<p>{{ $para }}</p>@endforeach
            </div>
            <dl class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([[__('Years of practice'), $stats['years']], [__('Clients guided'), $stats['clients']], [__('Awards'), $stats['awards']], [__('Languages'), $stats['languages']]] as [$l, $n])
                    @if ($n !== '' && $n !== '0')<div class="card p-4"><dt class="text-xs text-ink-600">{{ $l }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $n }}</dd></div>@endif
                @endforeach
            </dl>
            @if ($p->entries('expertise')->isNotEmpty())
                <h2 class="mt-8 text-lg">{{ __('Areas of expertise') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                    @foreach ($p->entries('expertise') as $e)<li class="rounded-full border border-gold-200 bg-gold-50 px-3 py-1">{{ Profile::tr($e, 'title') }}</li>@endforeach
                </ul>
            @endif
        </div>
    </section>

    @if ($p->entries('education')->isNotEmpty() || $p->entries('position')->isNotEmpty())
    <section class="bg-ivory-100 py-14" aria-labelledby="edu-h">
        <div class="container-page grid gap-10 lg:grid-cols-2">
            <div>
                <h2 id="edu-h" class="text-2xl">{{ __('Education') }}</h2>
                <ol class="mt-5 space-y-3 text-sm">
                    @foreach ($p->entries('education') as $e)
                        <li class="card p-4"><p class="font-semibold">{{ Profile::tr($e, 'title') }}@if ($e->year) <span class="font-normal text-ink-600">— {{ $e->year }}</span>@endif</p>@if ($d = Profile::tr($e, 'detail'))<p class="text-ink-600">{{ $d }}</p>@endif</li>
                    @endforeach
                </ol>
            </div>
            <div>
                <h2 class="text-2xl">{{ __('Positions') }}</h2>
                <ul class="mt-5 space-y-3 text-sm">
                    @foreach ($p->entries('position') as $e)
                        <li class="card p-4"><p class="font-semibold">{{ Profile::tr($e, 'title') }}</p>@if ($e->year || Profile::tr($e, 'detail'))<p class="text-ink-600">{{ collect([$e->year, Profile::tr($e, 'detail')])->filter()->implode('. ') }}</p>@endif</li>
                    @endforeach
                </ul>
                <h3 class="mt-8 text-lg">{{ __('Languages') }}</h3>
                <p class="mt-2 text-sm text-ink-700">{{ implode(', ', $p->languages()) }}</p>
            </div>
        </div>
    </section>
    @endif

    @if ($awards->isNotEmpty())
    <section class="container-page py-14" aria-labelledby="aw-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="aw-h" class="text-2xl">{{ __('Awards') }}</h2>
            <a href="{{ route('awards') }}" class="btn-outline">{{ __('All awards and photos') }}</a>
        </div>
        <div @class(['mt-6 grid gap-8', 'lg:grid-cols-[1fr_20rem]' => $awardPhotos->isNotEmpty()])>
            <ul class="grid gap-3 text-sm sm:grid-cols-2">
                @foreach ($awards->take(10) as $e)
                    <li class="card p-3"><strong>{{ Profile::tr($e, 'title') }}</strong>{{ ($d = Profile::tr($e, 'detail')) ? ' — '.$d : '' }}@if ($e->year && ! str_contains((string) $d.$e->title, (string) $e->year)), {{ $e->year }}@endif</li>
                @endforeach
            </ul>
            @if ($awardPhotos->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($awardPhotos as $ph)<x-photo :src="$ph['src']" :alt="$ph['alt']" class="w-full rounded-[var(--radius-card)] object-cover" />@endforeach
                </div>
            @endif
        </div>
    </section>
    @endif

    @php $cols = collect(['research' => __('Research papers'), 'book' => __('Books'), 'media' => __('Media')])->filter(fn ($l, $k) => $p->entries($k)->isNotEmpty()); @endphp
    @if ($cols->isNotEmpty())
    <section class="bg-ivory-100 py-14" aria-label="{{ __('Research, books and media') }}">
        <div class="container-page grid gap-10 lg:grid-cols-3">
            @foreach ($cols as $key => $label)
                <div>
                    <h2 class="text-xl">{{ $label }}</h2>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-ink-700">
                        @foreach ($p->entries($key) as $e)
                            <li>@if ($e->link_url)<a href="{{ $e->link_url }}" rel="noopener" target="_blank">{{ Profile::line($e) }}</a>@else{{ Profile::line($e) }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    @if ($albums->isNotEmpty())
    <section class="container-page py-14" aria-labelledby="gal-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="gal-h" class="text-2xl">{{ __('Pujas, events and moments') }}</h2>
            <a href="{{ route('gallery') }}" class="btn-outline">{{ __('Open the gallery') }}</a>
        </div>
        <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($albums as $a)@include('public.gallery.card', ['a' => $a])@endforeach
        </ul>
    </section>
    @endif

    <section class="container-page py-14 text-center">
        <h2 class="text-2xl">{{ __('Speak with Acharya Ji') }}</h2>
        <p class="mx-auto mt-2 max-w-xl text-ink-600">{{ __('In person in Patna, or by video and audio call.') }}</p>
        <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary mt-6">{{ __('See consultation options') }}</a>
    </section>
</x-layouts.public>
