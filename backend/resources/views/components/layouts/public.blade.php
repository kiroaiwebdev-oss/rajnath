@props(['title' => null, 'description' => null])
@php
    $nav = [
        ['Consultations', '#'], ['Reports', '#'], ['Puja', '#'], ['Horoscope', '#'],
        ['Panchang', '#'], ['Calculators', '#'], ['About', '#about'],
    ];
@endphp
<x-layouts.base :title="$title" :description="$description">
    <header class="sticky top-0 z-40 border-b border-ink-200/60 bg-ivory-50/90 backdrop-blur pt-[env(safe-area-inset-top)]" x-data="disclosure">
        <div class="container-page flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline">
                <span aria-hidden="true" class="grid size-9 place-items-center rounded-full bg-ink-900 text-sm font-bold text-gold-200">RJ</span>
                <span class="text-base font-semibold text-ink-900">{{ $site('brand.name') }}</span>
            </a>
            <nav aria-label="Primary" class="hidden lg:block">
                <ul class="flex items-center gap-1">
                    @foreach ($nav as [$label, $href])
                        <li><a href="{{ $href }}" class="btn-ghost px-3">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="flex items-center gap-2">
                <a href="#" class="btn-primary hidden sm:inline-flex">Book consultation</a>
                <button type="button" class="btn-ghost lg:hidden" @click="toggle" :aria-expanded="expanded" aria-controls="mobile-nav" aria-label="Menu">
                    <svg aria-hidden="true" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <nav id="mobile-nav" aria-label="Mobile" class="border-t border-ink-200/60 bg-ivory-50 lg:hidden" x-show="open" x-cloak @keydown.escape.window="close">
            <ul class="container-page grid gap-1 py-3">
                @foreach ($nav as [$label, $href])
                    <li><a href="{{ $href }}" class="btn-ghost w-full justify-start">{{ $label }}</a></li>
                @endforeach
                <li><a href="#" class="btn-primary mt-2 w-full">Book consultation</a></li>
            </ul>
        </nav>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="mt-24 border-t border-ink-200/60 bg-ivory-100 pb-[env(safe-area-inset-bottom)]">
        <div class="container-page grid gap-8 py-12 text-sm text-ink-600 md:grid-cols-3">
            <div>
                <p class="font-semibold text-ink-900">{{ $site('brand.name') }}</p>
                <p class="mt-2">{{ $site('brand.tagline') }}</p>
            </div>
            <div>
                <p class="font-semibold text-ink-900">Contact</p>
                <ul class="mt-2 space-y-1">
                    @if ($site('contact.phone'))<li><a href="tel:{{ $site('contact.phone') }}">{{ $site('contact.phone') }}</a></li>@endif
                    @if ($site('contact.email'))<li><a href="mailto:{{ $site('contact.email') }}">{{ $site('contact.email') }}</a></li>@endif
                    @if ($site('contact.address'))<li>{{ $site('contact.address') }}</li>@endif
                </ul>
            </div>
            <div>
                <p class="font-semibold text-ink-900">Important</p>
                <p class="mt-2">Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.</p>
                <p class="mt-3"><a href="{{ $site('source.repository_url') }}" rel="noopener">Source code (AGPL-3.0)</a></p>
            </div>
        </div>
        <p class="container-page border-t border-ink-200/60 py-5 text-xs text-ink-600">© {{ now()->year }} {{ $site('brand.name') }}</p>
    </footer>
</x-layouts.base>
