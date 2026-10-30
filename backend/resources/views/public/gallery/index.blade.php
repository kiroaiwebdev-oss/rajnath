@php use App\Domain\Showcase\Gallery; use App\Domain\Showcase\Profile; @endphp
<x-layouts.public :title="$category ? __(Gallery::CATEGORIES[$category]).' — '.__('Gallery') : __('Gallery: pujas, events and awards')"
    :description="__('Photos and videos of pujas, anushthans, award ceremonies, seminars and media appearances of :name.', ['name' => $site('brand.practitioner_name')])"
    :image="($c = $albums->first()?->cover) && $c->kind === 'photo' ? $c->path : null"
    :jsonld="[['@type' => 'CollectionPage', 'url' => route('gallery'), 'name' => __('Gallery'), 'about' => ['@id' => \App\Support\Seo\JsonLd::id('founder')],
        'hasPart' => $albums->map(fn ($a) => ['@type' => 'ImageGallery', 'name' => Profile::tr($a, 'title'), 'url' => route('gallery.show', $a->slug), 'thumbnailUrl' => Gallery::coverUrl($a)])->all()],
        \App\Support\Seo\JsonLd::breadcrumbs([[__('Gallery'), route('gallery')]])]">
    <section class="container-page py-12">
        <p class="eyebrow">{{ __('Gallery') }}</p>
        <h1 class="mt-2 text-3xl sm:text-4xl">{{ __('Pujas, events and moments') }}</h1>
        <p class="mt-3 max-w-2xl text-ink-600">{{ __('Pujas and anushthans performed by Acharya Ji, award ceremonies, seminars and media appearances.') }}</p>

        @if ($categories->count() > 1)
            <nav aria-label="{{ __('Filter by category') }}" class="mt-6 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('gallery') }}" class="btn-outline px-3" @if (! $category) aria-current="page" @endif>{{ __('All') }}</a>
                @foreach ($categories as $k => $l)<a href="{{ route('gallery', ['category' => $k]) }}" class="btn-outline px-3" @if ($category === $k) aria-current="page" @endif>{{ __($l) }}</a>@endforeach
            </nav>
        @endif

        @if ($albums->isEmpty())
            <p class="card mt-8 p-8 text-center text-ink-600">{{ __('Photos will appear here soon.') }}</p>
        @else
            <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $a)@include('public.gallery.card', ['a' => $a])@endforeach
            </ul>
        @endif
    </section>
</x-layouts.public>
