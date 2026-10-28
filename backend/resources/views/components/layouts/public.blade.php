@props(['title' => null, 'description' => null, 'image' => null])
@php
    $nav = [
        ['Services', route('services.index'), 'services.*'],
        ['Consultations', route('services.index', ['family' => 'consultations']), null],
        ['Vastu', route('services.index', ['family' => 'vastu']), null],
        ['Puja', route('services.index', ['family' => 'puja']), null],
        ['Gems', route('store.index'), 'store.*'],
        ['Rashifal', route('rashifal'), 'rashifal'],
        ['Calculators', route('tools.index'), 'tools.*'],
        ['Panchang', route('panchang'), 'panchang'],
        ['About', route('about'), 'about'],
        ['Contact', route('contact'), 'contact'],
    ];
    $me = auth()->user();
    $accountUrl = $me ? ($me->isStaff() ? route('admin.dashboard') : route('account.dashboard')) : route('account.sign-in');
@endphp
<x-layouts.base :title="$title" :description="$description" :image="$image">
    <header class="sticky top-0 z-40 border-b border-ink-200/60 bg-ivory-50/90 backdrop-blur pt-[env(safe-area-inset-top)]" x-data="disclosure">
        <div class="container-page flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 no-underline">
                <img src="/icons/icon-192.png" alt="" width="36" height="36" class="size-9 rounded-full">
                <span class="truncate text-base font-semibold text-ink-900">{{ $site('brand.name') }}</span>
            </a>
            <nav aria-label="Primary" class="hidden xl:block">
                <ul class="flex items-center gap-1">
                    @foreach ($nav as [$label, $href, $active])
                        <li><a href="{{ $href }}" class="btn-ghost px-3" @if ($active && request()->routeIs($active)) aria-current="page" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="flex items-center gap-2">
                <a href="{{ $accountUrl }}" class="btn-ghost hidden px-3 sm:inline-flex">{{ $me ? 'My account' : 'Sign in' }}</a>
                <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary hidden sm:inline-flex">Book consultation</a>
                <button type="button" class="btn-ghost xl:hidden" @click="toggle" :aria-expanded="expanded" aria-controls="mobile-nav" aria-label="Menu">
                    <svg aria-hidden="true" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <nav id="mobile-nav" aria-label="Mobile" class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-ink-200/60 bg-ivory-50 xl:hidden" x-show="open" x-cloak @keydown.escape.window="close">
            <ul class="container-page grid gap-1 py-3">
                @foreach ($nav as [$label, $href])
                    <li><a href="{{ $href }}" class="btn-ghost w-full justify-start">{{ $label }}</a></li>
                @endforeach
                <li><a href="{{ $accountUrl }}" class="btn-outline mt-2 w-full">{{ $me ? 'My account' : 'Sign in' }}</a></li>
                <li><a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary w-full">Book consultation</a></li>
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
                <p class="mt-3">B-Block, 1st Floor, Chanakya Plaza, Kankarbagh Main Road, Patna, Bihar 800020</p>
            </div>
            <div>
                <p class="font-semibold text-ink-900">Explore</p>
                <ul class="mt-2 space-y-1.5">
                    <li><a href="{{ route('services.index') }}">All services</a></li>
                    <li><a href="{{ route('about') }}">About Acharya Ji</a></li>
                    <li><a href="{{ route('store.index') }}">Gemstone store</a></li>
                    <li><a href="{{ route('services.show', 'palmistry-photo-report') }}">Palmistry report from photos</a></li>
                    <li><a href="{{ route('tools.index') }}">Free calculators</a></li>
                    <li><a href="{{ route('panchang') }}">Today's Panchang</a></li>
                    <li><a href="{{ route('calendar') }}">Vrat calendar</a></li>
                    <li><a href="{{ route('rashifal') }}">Rashifal</a></li>
                    <li><a href="{{ route('faq') }}">Questions & answers</a></li>
                    <li><a href="{{ route('contact') }}">Contact & timings</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-ink-900">Contact</p>
                <ul class="mt-2 space-y-1.5">
                    @if ($site('contact.phone'))<li><a href="tel:{{ $site('contact.phone') }}">{{ $site('contact.phone') }}</a></li>@endif
                    @if ($site('contact.whatsapp'))<li><a href="https://wa.me/{{ ltrim($site('contact.whatsapp'), '+') }}" rel="noopener">WhatsApp</a></li>@endif
                    @if ($site('contact.email'))<li><a href="mailto:{{ $site('contact.email') }}" class="break-all">{{ $site('contact.email') }}</a></li>@endif
                </ul>
            </div>
            <div>
                <p class="font-semibold text-ink-900">Important</p>
                <p class="mt-2">Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.</p>
                <ul class="mt-3 space-y-1.5">
                    <li><a href="{{ route('privacy') }}">Privacy policy</a></li>
                    <li><a href="{{ route('terms') }}">Terms of use</a></li>
                    <li><a href="{{ $site('source.repository_url') }}" rel="noopener">Source code (AGPL-3.0)</a></li>
                </ul>
            </div>
        </div>
        <p class="container-page border-t border-ink-200/60 py-5 text-xs text-ink-600">© {{ now()->year }} {{ $site('brand.name') }}</p>
    </footer>
</x-layouts.base>
