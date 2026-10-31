@props(['title' => null, 'description' => null, 'image' => null, 'robots' => 'index,follow', 'jsonld' => null, 'type' => 'website'])
@php
    $nav = [
        [__('Services'), route('services.index'), 'services.*'],
        [__('Consultations'), route('services.index', ['family' => 'consultations']), null],
        [__('Vastu'), route('services.index', ['family' => 'vastu']), null, false],
        [__('Puja'), route('services.index', ['family' => 'puja']), null],
        [__('Gems'), route('store.index'), 'store.*'],
        [__('Rashifal'), route('rashifal'), 'rashifal*'],
        [__('Calculators'), route('tools.index'), 'tools.*', false],
        [__('Panchang'), route('panchang'), 'panchang'],
        [__('Gochar'), route('gochar'), 'gochar*', false],
        [__('Muhurat'), route('muhurat.index'), 'muhurat.*', false],
        [__('Festivals'), route('festivals'), 'festivals', false],
        [__('About'), route('about'), 'about', false],
        [__('Awards'), route('awards'), 'awards', false],
        [__('Gallery'), route('gallery'), 'gallery*', false],
        [__('Contact'), route('contact'), 'contact'],
    ];
    $me = auth()->user();
    $accountUrl = $me ? ($me->isStaff() ? route('admin.dashboard') : route('account.dashboard')) : route('account.sign-in');
    $langs = collect(\App\Http\Middleware\SetLocale::enabled())->reject(fn ($l) => $l === app()->getLocale());
@endphp
<x-layouts.base :title="$title" :description="$description" :image="$image" :robots="$robots" :jsonld="$jsonld" :type="$type">
    <header class="sticky top-0 z-40 border-b border-ink-200/60 bg-ivory-50/90 backdrop-blur pt-[env(safe-area-inset-top)]" x-data="disclosure">
        <div class="container-page flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 no-underline">
                <img src="/icons/icon-192.png" alt="" width="36" height="36" class="size-9 rounded-full">
                <span class="truncate text-base font-semibold text-ink-900">{{ $site('brand.name') }}</span>
            </a>
            <nav aria-label="Primary" class="hidden xl:block">
                <ul class="flex items-center gap-1">
                    {{-- desktop shows the main sections; Vastu, Calculators and About stay in the footer and mobile menu --}}
                    @foreach (array_filter($nav, fn ($n) => $n[3] ?? true) as [$label, $href, $active])
                        <li><a href="{{ $href }}" class="btn-ghost whitespace-nowrap px-3" @if ($active && request()->routeIs($active)) aria-current="page" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="flex items-center gap-2">
                @foreach ($langs as $l)<a href="{{ request()->fullUrlWithQuery(['lang' => $l]) }}" lang="{{ $l }}" hreflang="{{ $l }}" class="btn-ghost px-2 text-sm" aria-label="{{ config('locales.available')[$l] }}">{{ config('locales.available')[$l] }}</a>@endforeach
                <a href="{{ $accountUrl }}" class="btn-ghost hidden whitespace-nowrap px-3 sm:inline-flex">{{ $me ? __('My account') : __('Sign in') }}</a>
                <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary hidden whitespace-nowrap sm:inline-flex">{{ __('Book consultation') }}</a>
                <button type="button" class="btn-ghost xl:hidden" @click="toggle" :aria-expanded="expanded" aria-controls="mobile-nav" aria-label="{{ __('Menu') }}">
                    <svg aria-hidden="true" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <nav id="mobile-nav" aria-label="Mobile" class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-ink-200/60 bg-ivory-50 xl:hidden" x-show="open" x-cloak @keydown.escape.window="close">
            <ul class="container-page grid gap-1 py-3">
                @foreach ($nav as [$label, $href])
                    <li><a href="{{ $href }}" class="btn-ghost w-full justify-start">{{ $label }}</a></li>
                @endforeach
                <li><a href="{{ $accountUrl }}" class="btn-outline mt-2 w-full">{{ $me ? __('My account') : __('Sign in') }}</a></li>
                <li><a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary w-full">{{ __('Book consultation') }}</a></li>
            </ul>
        </nav>
    </header>

    <main id="main">
        @if (session('status'))
            <div class="container-page pt-4"><div role="status" class="card border-green-200 bg-green-50 px-4 py-3 text-sm text-success-700">{{ session('status') }}</div></div>
        @endif
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t border-ink-200/60 bg-ivory-100 pb-[env(safe-area-inset-bottom)]">
        <div class="container-page grid gap-8 py-12 text-sm text-ink-600 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="font-semibold text-ink-900">{{ $site('brand.name') }}</p>
                <p class="mt-2">{{ $site('brand.tagline') }}</p>
                <address class="mt-3 not-italic">{{ app(\App\Support\Business::class)->addressLine() }}</address>
            </div>
            <div>
                <p class="font-semibold text-ink-900">{{ __('Explore') }}</p>
                <ul class="mt-2 space-y-1.5">
                    <li><a href="{{ route('services.index') }}">{{ __('All services') }}</a></li>
                    <li><a href="{{ route('about') }}">{{ __('About Acharya Ji') }}</a></li>
                    <li><a href="{{ route('awards') }}">{{ __('Awards and honours') }}</a></li>
                    <li><a href="{{ route('gallery') }}">{{ __('Gallery: pujas and events') }}</a></li>
                    <li><a href="{{ route('reviews') }}">{{ __('Client reviews') }}</a></li>
                    <li><a href="{{ route('courses') }}">{{ __('Astrology courses (coming soon)') }}</a></li>
                    <li><a href="{{ route('store.index') }}">{{ __('Gemstone store') }}</a></li>
                    <li><a href="{{ route('services.show', 'palmistry-photo-report') }}">{{ __('Palmistry report from photos') }}</a></li>
                    <li><a href="{{ route('tools.index') }}">{{ __('Free calculators') }}</a></li>
                    <li><a href="{{ route('panchang') }}">{{ __('Today\'s Panchang') }}</a></li>
                    <li><a href="{{ route('calendar') }}">{{ __('Vrat calendar') }}</a></li>
                    <li><a href="{{ route('rashifal') }}">{{ __('Rashifal') }}</a></li>
                    <li><a href="{{ route('gochar') }}">{{ __('Grah gochar (transits)') }}</a></li>
                    <li><a href="{{ route('navagraha') }}">{{ __('The nine planets') }}</a></li>
                    <li><a href="{{ route('muhurat.index') }}">{{ __('Shubh muhurat') }}</a></li>
                    <li><a href="{{ route('festivals') }}">{{ __('Festivals and holidays') }}</a></li>
                    <li><a href="{{ route('assistant') }}">{{ __('Ask our assistant') }}</a></li>
                    <li><a href="{{ route('faq') }}">{{ __('Questions & answers') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('Contact & timings') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-ink-900">{{ __('Contact') }}</p>
                <ul class="mt-2 space-y-1.5">
                    @if ($site('contact.phone'))<li><a href="tel:{{ $site('contact.phone') }}">{{ $site('contact.phone') }}</a></li>@endif
                    @if ($site('contact.whatsapp'))<li><a href="https://wa.me/{{ ltrim($site('contact.whatsapp'), '+') }}" rel="noopener">WhatsApp</a></li>@endif
                    @if ($site('contact.email'))<li><a href="mailto:{{ $site('contact.email') }}" class="break-all">{{ $site('contact.email') }}</a></li>@endif
                </ul>
            </div>
            <div>
                <p class="font-semibold text-ink-900">{{ __('Important') }}</p>
                <p class="mt-2">{{ __('Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.') }}</p>
                <ul class="mt-3 space-y-1.5">
                    <li><a href="{{ route('privacy') }}">{{ __('Privacy policy') }}</a></li>
                    <li><a href="{{ route('terms') }}">{{ __('Terms of use') }}</a></li>
                    @php $legalDocs = app(\App\Domain\Legal\LegalDocuments::class); @endphp
                    @if ($legalDocs->isPublished('refunds'))<li><a href="{{ route('legal.refunds') }}">{{ __('Cancellation and refund policy') }}</a></li>@endif
                    @if ($legalDocs->isPublished('shipping'))<li><a href="{{ route('legal.shipping') }}">{{ __('Shipping and delivery policy') }}</a></li>@endif
                    <li><a href="{{ $site('source.repository_url') }}" rel="noopener">{{ __('Source code (AGPL-3.0)') }}</a></li>
                </ul>
            </div>
        </div>
        <p class="container-page border-t border-ink-200/60 py-5 text-xs text-ink-600">© {{ now()->year }} {{ $site('brand.name') }}</p>
    </footer>
</x-layouts.base>
