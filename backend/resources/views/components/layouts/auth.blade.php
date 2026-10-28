@props(['title'])
<x-layouts.base :title="$title" robots="noindex,nofollow">
    <main id="main" class="grid min-h-dvh place-items-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center justify-center gap-2.5">
                <span aria-hidden="true" class="grid size-10 place-items-center rounded-full bg-ink-900 text-sm font-bold text-gold-200">RJ</span>
                <span class="font-semibold">{{ $site('brand.name') }}</span>
            </div>
            <div class="card p-6 sm:p-8">
                <h1 class="text-xl">{{ $title }}</h1>
                @if (session('status'))<p role="status" class="mt-3 text-sm text-success-700">{{ session('status') }}</p>@endif
                <div class="mt-6">{{ $slot }}</div>
            </div>
        </div>
    </main>
</x-layouts.base>
