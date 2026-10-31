@php
    use App\Domain\Blog\Blog;
    use App\Domain\Showcase\Profile;
    $title = Profile::tr($p, 'title');
    $excerpt = Profile::tr($p, 'excerpt');
    $cover = Blog::cover($p);
    $J = \App\Support\Seo\JsonLd::class;
    $ld = [['@type' => 'BlogPosting', '@id' => route('blog.show', $p->slug).'#article', 'headline' => $title, 'description' => $excerpt, 'image' => asset($cover),
        'datePublished' => \Illuminate\Support\Carbon::parse($p->published_at)->toIso8601String(), 'dateModified' => \Illuminate\Support\Carbon::parse($p->updated_at)->toIso8601String(),
        'author' => ['@id' => $J::id('founder')], 'publisher' => ['@id' => $J::id('business')], 'mainEntityOfPage' => route('blog.show', $p->slug),
        'articleSection' => Blog::CATEGORIES[$p->category] ?? null, 'keywords' => implode(', ', json_decode($p->tags ?? '[]', true)), 'inLanguage' => app()->getLocale()],
        $J::breadcrumbs([[__('Articles'), route('blog')], [__(Blog::CATEGORIES[$p->category] ?? ''), route('blog', ['category' => $p->category])], [$title, route('blog.show', $p->slug)]])];
    if ($faqs) { $ld[] = $J::faq($faqs); }
@endphp
<x-layouts.public :title="$title" :description="$excerpt" :image="$cover" type="article" :jsonld="$ld">
    <article>
        <header class="bg-ink-900 text-white">
            <div class="container-page grid items-center gap-8 py-12 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-white/70"><a href="{{ route('blog') }}" class="text-white/80">{{ __('Articles') }}</a> › <a href="{{ route('blog', ['category' => $p->category]) }}" class="text-white/80">{{ __(Blog::CATEGORIES[$p->category] ?? '') }}</a></nav>
                    <h1 class="mt-4 text-3xl leading-tight text-white sm:text-4xl">{{ $title }}</h1>
                    <p class="mt-4 text-lg text-white/80">{{ $excerpt }}</p>
                    <p class="mt-5 flex flex-wrap items-center gap-3 text-sm text-white/70">
                        <img src="/{{ app(Profile::class)->photo('profile.photo_about') }}" alt="" class="size-9 rounded-full object-cover object-top" width="36" height="36">
                        <span>{{ app(Profile::class)->name() }}</span><span aria-hidden="true">·</span>
                        <span>{{ \Illuminate\Support\Carbon::parse($p->published_at)->translatedFormat('j F Y') }}</span><span aria-hidden="true">·</span>
                        <span>{{ __(':n min read', ['n' => $p->reading_minutes]) }}</span>
                    </p>
                </div>
                <x-photo :src="$cover" alt="" eager class="w-full rounded-[var(--radius-card)] shadow-2xl" />
            </div>
        </header>
        <div class="container-page grid gap-10 py-12 lg:grid-cols-[16rem_minmax(0,1fr)_18rem]">
            <aside class="hidden lg:block">
                @if (count($toc) > 2)
                    <nav aria-label="{{ __('On this page') }}" class="sticky top-24 text-sm">
                        <p class="font-semibold">{{ __('On this page') }}</p>
                        <ol class="mt-3 space-y-2 border-l border-ink-200 pl-4">@foreach ($toc as [$id, $h])<li><a href="#{{ $id }}" class="no-underline text-ink-700 hover:text-ink-900">{{ $h }}</a></li>@endforeach</ol>
                    </nav>
                @endif
            </aside>
            <div class="prose-legal min-w-0 max-w-3xl text-[1.0625rem]">{{ $html }}
                @if ($tags = json_decode($p->tags ?? '[]', true))
                    <p class="mt-10 flex flex-wrap gap-2 text-xs">@foreach ($tags as $t)<span class="rounded-full bg-ivory-100 px-3 py-1 text-ink-700">#{{ $t }}</span>@endforeach</p>
                @endif
                <p class="mt-8 rounded-xl bg-ivory-100 p-4 text-sm text-ink-700">{{ __('Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.') }}</p>
            </div>
            <aside class="space-y-5">
                <div class="card bg-ink-900 p-5 text-white lg:sticky lg:top-24">
                    <p class="font-semibold">{{ __('Want this read for your own chart?') }}</p>
                    <p class="mt-1 text-sm text-white/80">{{ __('Speak with Acharya Ji, or ask one question in writing.') }}</p>
                    <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-gold mt-4 w-full">{{ __('Book consultation') }}</a>
                    <a href="{{ route('services.show', 'ask-one-question') }}" class="btn mt-2 w-full border border-white/30 text-white hover:bg-white/10">{{ __('Ask a question — from ₹699') }}</a>
                </div>
                @foreach ($services as $o)<div class="relative"><x-service-card :o="$o" /></div>@endforeach
            </aside>
        </div>
    </article>
    @if ($related->isNotEmpty())
        <section class="bg-ivory-100 py-14" aria-labelledby="rel-h">
            <div class="container-page">
                <h2 id="rel-h" class="text-2xl">{{ __('Keep reading') }}</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach ($related as $r)@include('public.blog.card', ['p' => $r])@endforeach</div>
            </div>
        </section>
    @endif
</x-layouts.public>
