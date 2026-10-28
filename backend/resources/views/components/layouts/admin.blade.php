@props(['title' => 'Admin'])
@php
    $u = auth()->user();
    $items = array_filter([
        ['Dashboard', 'admin.dashboard', null],
        ['Enquiries', 'admin.enquiries.index', 'bookings.view'],
        ['Customers', 'admin.customers.index', 'customers.view'],
        ['Catalogue', 'admin.catalogue.index', 'catalogue.view'],
        ['Finance', 'admin.finance.index', 'finance.view'],
        ['Gem store', 'admin.store.products', 'catalogue.view'],
        ['Gem orders', 'admin.store.orders', 'puja.fulfil'],
        ['Palmistry', 'admin.palm.index', 'reports.fulfil'],
        ['Payments setup', 'admin.payments.settings', 'providers.manage'],
        ['FAQs', 'admin.faqs.index', 'content.author'],
        ['Team', 'admin.team.index', 'team.view'],
        ['Settings', 'admin.settings.edit', 'settings.view'],
        ['Email & SMS', 'admin.providers.edit', 'providers.manage'],
        ['Audit log', 'admin.audit.index', 'audit.view'],
    ], fn ($i) => $i[2] === null || $u->can($i[2]));
@endphp
<x-layouts.base :title="$title" robots="noindex,nofollow" class="bg-ivory-50">
    <div x-data="disclosure" class="min-h-dvh lg:grid lg:grid-cols-[16rem_1fr]">
        {{-- Desktop sidebar / mobile drawer --}}
        <aside id="admin-nav" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-ink-200/60 bg-white pt-[env(safe-area-inset-top)] transition-transform lg:static lg:w-auto lg:translate-x-0"
               :class="drawerClass" aria-label="Admin navigation">
            <div class="flex h-16 items-center gap-2.5 px-5">
                <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-ink-900 text-xs font-bold text-gold-200">RJ</span>
                <span class="text-sm font-semibold">Control centre</span>
            </div>
            <nav class="px-3 pb-6">
                <ul class="space-y-1">
                    @foreach ($items as [$label, $route])
                        <li>
                            <a href="{{ route($route) }}" @class([
                                'flex min-h-11 items-center rounded-xl px-3 text-sm font-medium no-underline',
                                'bg-ivory-100 text-ink-900' => request()->routeIs(str_replace('.index', '', $route).'*'),
                                'text-ink-700 hover:bg-ivory-50' => ! request()->routeIs(str_replace('.index', '', $route).'*'),
                            ]) @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>
        <div x-show="open" x-cloak @click="close" class="fixed inset-0 z-30 bg-ink-900/30 lg:hidden" aria-hidden="true"></div>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-ink-200/60 bg-ivory-50/90 px-4 backdrop-blur pt-[env(safe-area-inset-top)] sm:px-6">
                <div class="flex min-w-0 items-center gap-2">
                    <button type="button" class="btn-ghost -ml-2 px-3 lg:hidden" @click="toggle" :aria-expanded="expanded" aria-controls="admin-nav" aria-label="Open navigation">
                        <svg aria-hidden="true" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <h1 class="truncate text-lg">{{ $title }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-ink-600 sm:inline">{{ $u->name }}</span>
                    <form method="POST" action="{{ route('staff.logout') }}">@csrf<button class="btn-outline">Sign out</button></form>
                </div>
            </header>
            <main id="main" class="px-4 py-6 sm:px-6 lg:px-8 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                @if (session('status'))
                    <div role="status" class="card mb-6 border-green-200 bg-green-50 px-4 py-3 text-sm text-success-700">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="card mb-6 border-red-200 bg-red-50 px-4 py-3 text-sm text-danger-700">
                        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
