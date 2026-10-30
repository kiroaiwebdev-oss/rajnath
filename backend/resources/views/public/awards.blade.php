@php
    use App\Domain\Showcase\Profile;
    $awards = $p->entries('award');
    $groups = collect(Profile::AWARD_GROUPS)->map(fn ($label, $key) => [__($label), $awards->where('group', $key)->values()])
        ->put('other', [__('More honours'), $awards->whereNull('group')->values()])->filter(fn ($g) => $g[1]->isNotEmpty());
    $withPhotos = $awards->whereNotNull('image_path')->filter(fn ($e) => \App\Domain\Showcase\Gallery::fileExists($e->image_path))->values();
@endphp
<x-layouts.public :title="__('Awards and honours of :name', ['name' => $p->name()])"
    :description="__(':count awards and honours received in India and abroad by :name, Vedic astrologer, Patna.', ['count' => $awards->count(), 'name' => $p->name()])"
    :image="$withPhotos->first()?->image_path"
    :jsonld="[['@type' => 'CollectionPage', 'url' => route('awards'), 'name' => __('Awards and honours'), 'about' => ['@id' => \App\Support\Seo\JsonLd::id('founder')],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $awards->count(), 'itemListElement' => $awards->values()->map(fn ($e, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => Profile::line($e, true)])->all()]],
        \App\Support\Seo\JsonLd::breadcrumbs([[__('About'), route('about')], [__('Awards'), route('awards')]])]">
    <section class="container-page py-12">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-ink-600"><a href="{{ route('about') }}">{{ __('About') }}</a> › {{ __('Awards') }}</nav>
        <p class="eyebrow mt-4">{{ __('Recognition') }}</p>
        <h1 class="mt-2 text-3xl sm:text-4xl">{{ __('Awards and honours') }}</h1>
        <p class="mt-3 max-w-2xl text-ink-600">{{ __(':name has been honoured in India and abroad for his work in Jyotish, Vastu and Vedic education.', ['name' => $p->name()]) }}</p>

        @if ($withPhotos->isNotEmpty())
            <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($withPhotos as $e)
                    <li class="card overflow-hidden">
                        <x-photo :src="$e->image_path" :alt="Profile::line($e)" class="aspect-[4/3] w-full object-cover" />
                        <div class="p-4"><p class="font-semibold">{{ Profile::tr($e, 'title') }}</p><p class="mt-1 text-sm text-ink-600">{{ collect([Profile::tr($e, 'detail'), $e->year])->filter()->implode(' · ') }}</p></div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            @foreach ($groups as [$label, $list])
                <section aria-label="{{ $label }}">
                    <h2 class="text-xl">{{ $label }}</h2>
                    <ol class="mt-4 space-y-3 text-sm">
                        @foreach ($list as $e)
                            <li class="card flex gap-4 p-4">
                                @if ($e->year)<span class="w-12 shrink-0 font-semibold tabular-nums text-gold-700">{{ $e->year }}</span>@endif
                                <span><strong>{{ Profile::tr($e, 'title') }}</strong>@if ($d = Profile::tr($e, 'detail'))<br><span class="text-ink-600">{{ $d }}</span>@endif
                                    @if ($e->link_url)<br><a href="{{ $e->link_url }}" rel="noopener" target="_blank" class="text-xs">{{ __('Read more') }}</a>@endif</span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
    </section>

    @if ($albums->isNotEmpty())
        <section class="bg-ivory-100 py-14" aria-labelledby="alb-h">
            <div class="container-page">
                <h2 id="alb-h" class="text-2xl">{{ __('Photos from award ceremonies') }}</h2>
                <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($albums as $a)@include('public.gallery.card', ['a' => $a])@endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.public>
