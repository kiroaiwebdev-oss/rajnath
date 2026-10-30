@php $title = $offering ? __('Reviews of :service', ['service' => $offering->t()?->title]) : __('Reviews from our clients'); @endphp
<x-layouts.public :title="$title" :description="__(':count reviews from clients of :name. Average rating :avg out of 5.', ['count' => $summary['count'], 'name' => $site('brand.practitioner_name'), 'avg' => $summary['average'] ?? '—'])"
    :robots="$rating || request('page') > 1 ? 'noindex,follow' : 'index,follow'"
    :jsonld="[\App\Support\Seo\JsonLd::breadcrumbs([[__('Reviews'), route('reviews')]])]">
    <section class="container-page py-12">
        <p class="eyebrow">{{ __('Reviews') }}</p>
        <h1 class="mt-2 text-3xl sm:text-4xl">{{ $title }}</h1>
        <p class="mt-3 max-w-2xl text-ink-600">{{ __('Every review is from a real client. Reviews marked "verified purchase" were written after a paid consultation, report, puja or order.') }}</p>

        <div class="mt-8 grid gap-8 lg:grid-cols-[18rem_1fr]">
            <aside class="space-y-6">
                <div class="card p-5">
                    @if ($summary['count'])
                        <p class="text-4xl font-semibold tabular-nums">{{ number_format($summary['average'], 1) }}<span class="text-lg text-ink-600"> / 5</span></p>
                        <x-stars :rating="$summary['average']" size="size-5" class="mt-1" />
                        <p class="mt-1 text-sm text-ink-600">{{ trans_choice(':count review|:count reviews', $summary['count'], ['count' => $summary['count']]) }}</p>
                        <ul class="mt-4 space-y-1.5 text-sm">
                            @foreach ($summary['stars'] as $s => $n)
                                <li>
                                    <a href="{{ route('reviews', array_filter(['service' => $offering?->slug, 'rating' => $rating === $s ? null : $s])) }}" @class(['flex items-center gap-2 no-underline', 'font-semibold' => $rating === $s]) @if ($rating === $s) aria-current="true" @endif>
                                        <span class="w-10 tabular-nums">{{ $s }} ★</span>
                                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-ivory-200"><span class="block h-full rounded-full bg-gold-500" style="width: {{ $summary['count'] ? round($n * 100 / $summary['count']) : 0 }}%"></span></span>
                                        <span class="w-8 text-right tabular-nums text-ink-600">{{ $n }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-ink-600">{{ __('No reviews yet.') }}</p>
                    @endif
                </div>
                @if ($services->count() > 1)
                    <nav aria-label="{{ __('Filter by service') }}" class="card p-5 text-sm">
                        <p class="font-semibold">{{ __('By service') }}</p>
                        <ul class="mt-2 space-y-1">
                            <li><a href="{{ route('reviews') }}" @if (! $offering) aria-current="page" class="font-semibold" @endif>{{ __('All services') }}</a></li>
                            @foreach ($services as $s)<li><a href="{{ route('reviews', ['service' => $s->slug]) }}" @if ($offering?->id === $s->id) aria-current="page" class="font-semibold" @endif>{{ $s->t()?->title }}</a></li>@endforeach
                        </ul>
                    </nav>
                @endif
                <div class="card bg-ink-900 p-5 text-white">
                    <p class="font-semibold">{{ __('Consulted Acharya Ji?') }}</p>
                    <p class="mt-1 text-sm text-white/80">{{ __('Share your experience from your account, under Reviews.') }}</p>
                    <a href="{{ route('account.reviews.index') }}" class="btn-gold mt-4">{{ __('Write a review') }}</a>
                </div>
            </aside>
            <div>
                @if ($rows->isEmpty())
                    <p class="card p-8 text-center text-ink-600">{{ __('No reviews to show yet.') }}</p>
                @else
                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach ($rows as $r)<x-review-card :r="$r" />@endforeach
                    </div>
                    <div class="mt-6">{{ $rows->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.public>
