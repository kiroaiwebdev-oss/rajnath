@php use App\Domain\Blog\Blog; use App\Domain\Showcase\Profile; @endphp
<article class="card relative flex h-full flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
    <x-photo :src="Blog::cover($p)" alt="" class="aspect-[16/10] w-full object-cover" />
    <div class="flex flex-1 flex-col p-5">
        <p class="eyebrow">{{ __(Blog::CATEGORIES[$p->category] ?? $p->category) }}</p>
        <h3 class="mt-1.5 text-base leading-snug"><a href="{{ route('blog.show', $p->slug) }}" class="no-underline after:absolute after:inset-0 hover:underline">{{ Profile::tr($p, 'title') }}</a></h3>
        <p class="mt-2 line-clamp-3 flex-1 text-sm text-ink-600">{{ Profile::tr($p, 'excerpt') }}</p>
        <p class="mt-4 text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($p->published_at)->translatedFormat('j M Y') }} · {{ __(':n min read', ['n' => $p->reading_minutes]) }}</p>
    </div>
</article>
