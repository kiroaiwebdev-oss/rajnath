<x-layouts.public title="Design system" robots="noindex,nofollow" :jsonld="false">
    <div class="container-page space-y-14 py-12">
        <header>
            <p class="eyebrow">Internal reference</p>
            <h1 class="mt-2 text-3xl">Design system</h1>
            <p class="mt-2 text-ink-600">Tokens and components shared by the website, customer area, practitioner portal and admin. The Flutter app mirrors these tokens in <code>mobile/lib/theme</code>.</p>
        </header>

        <section aria-labelledby="c-h">
            <h2 id="c-h" class="text-xl">Colour</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                @foreach (['bg-ivory-50' => 'ivory-50', 'bg-ivory-100' => 'ivory-100', 'bg-ivory-200' => 'ivory-200', 'bg-ink-900' => 'ink-900', 'bg-ink-700' => 'ink-700', 'bg-ink-600' => 'ink-600', 'bg-gold-500' => 'gold-500', 'bg-gold-700' => 'gold-700'] as $cls => $n)
                    <div class="card overflow-hidden"><div class="{{ $cls }} h-16"></div><p class="p-2 text-xs">{{ $n }}</p></div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="t-h">
            <h2 id="t-h" class="text-xl">Type</h2>
            <div class="card mt-4 space-y-3 p-6">
                <p class="text-4xl font-semibold tracking-tight">Heading 1 — 36/44</p>
                <p class="text-2xl font-semibold tracking-tight">Heading 2 — 24/32</p>
                <p class="text-base">Body — 16/24. हिन्दी पाठ भी उसी परिवार में: जन्म कुंडली, पंचांग, मुहूर्त।</p>
                <p class="text-sm text-ink-600">Secondary — 14/20</p>
            </div>
        </section>

        <section aria-labelledby="b-h">
            <h2 id="b-h" class="text-xl">Buttons</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <button class="btn-primary">Primary</button><button class="btn-gold">Gold</button>
                <button class="btn-outline">Outline</button><button class="btn-ghost">Ghost</button>
                <button class="btn-danger">Danger</button><button class="btn-primary" disabled>Disabled</button>
            </div>
        </section>

        <section aria-labelledby="f-h" class="grid gap-6 md:grid-cols-2">
            <h2 id="f-h" class="sr-only">Forms</h2>
            <div class="card space-y-4 p-6">
                <h3 class="text-base">Form fields</h3>
                <x-field name="ds_name" label="Full name" hint="As it should appear on your report." />
                <div>
                    <label for="ds-err" class="field-label">Mobile number</label>
                    <input id="ds-err" class="input" aria-invalid="true" aria-describedby="ds-err-e" value="98352">
                    <p id="ds-err-e" class="field-error">Enter a 10-digit mobile number.</p>
                </div>
            </div>
            <div class="space-y-4">
                <div class="card p-6" role="status" aria-live="polite">
                    <h3 class="text-base">Loading</h3>
                    <div class="mt-3 space-y-2" aria-hidden="true"><div class="h-3 w-2/3 animate-pulse rounded bg-ivory-200"></div><div class="h-3 w-1/2 animate-pulse rounded bg-ivory-200"></div></div>
                    <span class="sr-only">Loading…</span>
                </div>
                <div class="card p-6 text-center">
                    <h3 class="text-base">Empty</h3><p class="mt-1 text-sm text-ink-600">You have no reports yet.</p>
                </div>
                <div class="card border-red-200 bg-red-50 p-6" role="alert">
                    <h3 class="text-base text-danger-700">Error</h3><p class="mt-1 text-sm">We couldn't load this. Check your connection.</p>
                    <button class="btn-outline mt-3">Try again</button>
                </div>
            </div>
        </section>

        <section aria-labelledby="bd-h">
            <h2 id="bd-h" class="text-xl">Badges</h2>
            <div class="mt-4 flex flex-wrap gap-2"><span class="badge-gold">Featured</span><span class="badge-ink">Draft</span><span class="badge-success">Paid</span><span class="badge-danger">Failed</span></div>
        </section>
    </div>
</x-layouts.public>
