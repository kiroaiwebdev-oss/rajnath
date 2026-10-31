@php use App\Domain\Blog\Blog; @endphp
<x-layouts.public :title="$category ? __(Blog::CATEGORIES[$category]).' — '.__('Articles') : __('Astrology articles and guides')"
    :description="__('Clear, practical articles on kundli, grah gochar, marriage matching, Vastu, numerology, vrat and remedies, from the desk of Acharya Dr. Rajnath Jha.')"
    image="images/art/blog.jpg" :robots="$q || request('page') > 1 ? 'noindex,follow' : 'index,follow'"
    :jsonld="[['@type' => 'Blog', 'name' => __('Articles'), 'url' => route('blog'), 'publisher' => ['@id' => \App\Support\Seo\JsonLd::id('business')]], \App\Support\Seo\JsonLd::breadcrumbs([[__('Articles'), route('blog')]])]">
    <section class="bg-ink-900 text-white">
        <div class="container-page py-14">
            <p class="eyebrow text-gold-200">{{ __('Articles') }}</p>
            <h1 class="mt-2 max-w-3xl text-3xl text-white sm:text-4xl">{{ __('Learn Jyotish, Vastu and numerology the clear way') }}</h1>
            <p class="mt-3 max-w-2xl text-white/80">{{ __('Guides written in plain language, based on the classical Parashari tradition.') }}</p>
            <form method="GET" action="{{ route('blog') }}" role="search" class="mt-6 flex max-w-xl gap-2">
                @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                <label for="blog-q" class="sr-only">{{ __('Search articles') }}</label>
                <input id="blog-q" name="q" value="{{ $q }}" class="input" placeholder="{{ __('Search articles, e.g. Sade Sati') }}">
                <button class="btn-gold">{{ __('Search') }}</button>
            </form>
        </div>
    </section>
    <section class="container-page py-10">
        <nav aria-label="{{ __('Filter by category') }}" class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('blog') }}" class="btn-outline px-3" @if (! $category) aria-current="page" @endif>{{ __('All') }}</a>
            @foreach (Blog::CATEGORIES as $k => $l)<a href="{{ route('blog', ['category' => $k]) }}" class="btn-outline px-3" @if ($category === $k) aria-current="page" @endif>{{ __($l) }}</a>@endforeach
        </nav>
        @if ($posts->isEmpty())
            <p class="card mt-8 p-8 text-center text-ink-600">{{ $q ? __('No articles match your search.') : __('Articles will appear here soon.') }}</p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $p)@include('public.blog.card', ['p' => $p])@endforeach
            </div>
            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.public>
