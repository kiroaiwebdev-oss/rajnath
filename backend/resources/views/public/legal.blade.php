@php
    use App\Domain\Legal\LegalDocuments;
    [$defaultTitle, $path, $routeName] = LegalDocuments::KINDS[$kind];
    $title = $doc->title ?? __($defaultTitle);
    $descriptions = [
        'privacy' => __('How we collect, use, protect and delete your personal data, birth details and payment records.'),
        'terms' => __('Terms for consultations, reports, puja, gem purchases, payments, refunds and use of this website and app.'),
        'refunds' => __('When you can cancel or reschedule, and how and when refunds are paid.'),
        'shipping' => __('Where and how gemstone orders are shipped, and delivery times.'),
    ];
@endphp
<x-layouts.public :title="$title" :description="$descriptions[$kind]" :robots="$old ? 'noindex,follow' : 'index,follow'"
    :jsonld="$doc && ! $old ? [['@type' => 'WebPage', 'name' => $title, 'url' => url($path), 'dateModified' => $doc->effective_on, 'inLanguage' => $doc->locale, 'publisher' => ['@id' => \App\Support\Seo\JsonLd::id('business')]], \App\Support\Seo\JsonLd::breadcrumbs([[$title, url($path)]])] : null">
    <article class="container-page max-w-3xl py-12">
        <p class="eyebrow">{{ __('Legal') }}</p>
        <h1 class="mt-2 text-3xl sm:text-4xl">{{ $title }}</h1>
        @if ($doc)
            <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-600">
                <span>{{ __('Version :v', ['v' => $doc->version]) }}</span>
                @if ($doc->effective_on)<span aria-hidden="true">·</span><span>{{ __('In effect from :date', ['date' => \Illuminate\Support\Carbon::parse($doc->effective_on)->translatedFormat('j F Y')]) }}</span>@endif
                @if ($hasHistory)<span aria-hidden="true">·</span><a href="{{ route('legal.history', $kind) }}">{{ __('Earlier versions') }}</a>@endif
            </p>
            @if ($old)
                <p role="note" class="card mt-6 border-gold-200 bg-gold-50 p-4 text-sm">{{ __('This is an earlier version.') }} <a href="{{ url($path) }}">{{ __('Read the current version') }}</a></p>
            @endif
            <div class="prose-legal mt-8">{{ $html }}</div>
        @else
            <p class="mt-8 text-ink-700">{{ __('This page is being updated. For any question, write to :email.', ['email' => $site('contact.email')]) }}</p>
        @endif
        <nav aria-label="{{ __('Other policies') }}" class="mt-12 border-t border-ink-200/60 pt-6 text-sm">
            <ul class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach (LegalDocuments::KINDS as $k => [$t, $p])
                    @if ($k !== $kind && (in_array($k, ['privacy', 'terms'], true) || app(LegalDocuments::class)->isPublished($k)))<li><a href="{{ url($p) }}">{{ __($t) }}</a></li>@endif
                @endforeach
            </ul>
        </nav>
    </article>
</x-layouts.public>
