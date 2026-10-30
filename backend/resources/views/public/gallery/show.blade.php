@php
    use App\Domain\Showcase\Gallery;
    use App\Domain\Showcase\Profile;
    $title = Profile::tr($album, 'title');
    $desc = Profile::tr($album, 'description');
    $first = $items->firstWhere('kind', 'photo');
@endphp
<x-layouts.public :title="$title.' — '.__('Gallery')" :description="\Illuminate\Support\Str::limit($desc ?: __(':title: photos and videos from :name.', ['title' => $title, 'name' => $site('brand.name')]), 158)"
    :image="$first?->path"
    :jsonld="[\App\Support\Seo\JsonLd::gallery($album, $items), \App\Support\Seo\JsonLd::breadcrumbs([[__('Gallery'), route('gallery')], [$title, route('gallery.show', $album->slug)]])]">
    <section class="container-page py-12" x-data="lightbox" @keydown.window="key">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-ink-600"><a href="{{ route('gallery') }}">{{ __('Gallery') }}</a> › <a href="{{ route('gallery', ['category' => $album->category]) }}">{{ __(Gallery::CATEGORIES[$album->category] ?? $album->category) }}</a></nav>
        <h1 class="mt-4 text-3xl sm:text-4xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-ink-600">{{ collect([$album->event_date ? \Illuminate\Support\Carbon::parse($album->event_date)->translatedFormat('j F Y') : null, $album->location])->filter()->implode(' · ') }}</p>
        @if ($desc)<div class="mt-4 max-w-3xl space-y-3 leading-7">@foreach (preg_split('/\R\s*\R/', $desc) as $para)<p>{{ $para }}</p>@endforeach</div>@endif

        @if ($items->isEmpty())
            <p class="card mt-8 p-8 text-center text-ink-600">{{ __('Photos will appear here soon.') }}</p>
        @else
            <ul class="mt-8 columns-2 gap-4 sm:columns-3 lg:columns-4" aria-label="{{ __('Photos and videos') }}">
                @foreach ($items as $n => $i)
                    @php $cap = Profile::tr($i, 'caption'); @endphp
                    <li class="mb-4 break-inside-avoid">
                        <figure>
                            <button type="button" class="group relative block w-full overflow-hidden rounded-xl focus-visible:outline-2" @click="openAt"
                                    data-lb data-index="{{ $n }}" data-kind="{{ $i->kind }}" data-caption="{{ $cap }}"
                                    data-src="{{ $i->kind === 'photo' ? '/'.$i->path : Gallery::videoThumb($i->video_id) }}" data-video="{{ $i->video_id }}"
                                    aria-label="{{ $i->kind === 'video' ? __('Play video') : __('Open photo') }}{{ $cap ? ': '.$cap : '' }}">
                                @if ($i->kind === 'photo')
                                    <x-photo :src="$i->thumb_path ?: $i->path" :alt="$cap ?: $title" class="w-full transition group-hover:scale-[1.02]" />
                                @else
                                    <img src="{{ Gallery::videoThumb($i->video_id) }}" alt="{{ $cap ?: $title }}" width="480" height="360" loading="lazy" decoding="async" class="w-full">
                                    <span aria-hidden="true" class="absolute inset-0 grid place-items-center"><span class="grid size-14 place-items-center rounded-full bg-ink-900/75 text-white"><svg class="ml-1 size-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span>
                                @endif
                            </button>
                            @if ($cap)<figcaption class="mt-1.5 text-xs text-ink-600">{{ $cap }}</figcaption>@endif
                        </figure>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Lightbox --}}
        <div x-show="isOpen" x-cloak class="fixed inset-0 z-50 flex flex-col bg-ink-900/95 text-white" role="dialog" aria-modal="true" aria-label="{{ __('Photo viewer') }}"
             @touchstart="touchStart" @touchend="touchEnd">
            <div class="flex items-center justify-between gap-3 p-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                <p class="text-sm tabular-nums" x-text="counter"></p>
                <button type="button" x-ref="close" class="btn-ghost text-white" @click="close" aria-label="{{ __('Close') }}">
                    <svg aria-hidden="true" class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center px-2">
                <button type="button" class="btn-ghost absolute left-1 z-10 text-white" @click="prev" aria-label="{{ __('Previous') }}"><svg aria-hidden="true" class="size-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 6l-6 6 6 6"/></svg></button>
                <img x-show="isPhoto" :src="src" :alt="caption" class="max-h-full max-w-full object-contain">
                <div x-show="isVideo" class="aspect-video w-full max-w-4xl">
                    <button type="button" x-show="showPlay" class="relative block h-full w-full" @click="play" aria-label="{{ __('Play video') }}">
                        <img :src="src" alt="" class="h-full w-full object-cover">
                        <span class="absolute inset-0 grid place-items-center"><span class="grid size-16 place-items-center rounded-full bg-white/90 text-ink-900"><svg aria-hidden="true" class="ml-1 size-7" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span>
                    </button>
                    <template x-if="showFrame"><iframe :src="embed" class="h-full w-full" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="{{ __('Video') }}"></iframe></template>
                </div>
                <button type="button" class="btn-ghost absolute right-1 z-10 text-white" @click="next" aria-label="{{ __('Next') }}"><svg aria-hidden="true" class="size-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 6l6 6-6 6"/></svg></button>
            </div>
            <p class="min-h-12 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] text-center text-sm" x-text="caption"></p>
        </div>
    </section>

    @if ($more->isNotEmpty())
        <section class="bg-ivory-100 py-14" aria-labelledby="more-h">
            <div class="container-page">
                <h2 id="more-h" class="text-2xl">{{ __('More albums') }}</h2>
                <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($more as $a)@include('public.gallery.card', ['a' => $a])@endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.public>
