@php [$title, $input, $kind, $desc] = $tool; $r = $result; @endphp
<x-layouts.public :title="$title" :description="$desc.' '.__('Free, instant, by Acharya Dr. Rajnath Jha.')" :jsonld="[\App\Support\Seo\JsonLd::webApp($title, $desc, route('tools.show', $slug)), \App\Support\Seo\JsonLd::breadcrumbs([[__('Free calculators'), route('tools.index')], [$title, route('tools.show', $slug)]])]">
    <div class="container-page py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-600"><a href="{{ route('tools.index') }}">Calculators</a> / <span class="text-ink-900">{{ $title }}</span></nav>
        <div class="mt-4 flex flex-wrap items-center gap-3"><h1 class="text-3xl">{{ $title }}</h1><span class="badge-ink">{{ $kind }}</span></div>
        <p class="mt-2 max-w-2xl text-ink-600">{{ $desc }}</p>
        @if ($kind === 'entertainment')<p class="mt-3 max-w-2xl rounded-xl border border-gold-200 bg-gold-50 p-3 text-sm">This is a game for fun. It does not predict anything about a real relationship.</p>@endif
        @if ($kind === 'symbolic')<p class="mt-3 max-w-2xl rounded-xl bg-ivory-100 p-3 text-sm">Numerology and colour associations are traditional symbolic systems, not scientific measurements.</p>@endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[24rem_1fr]">
            <form method="POST" action="{{ route('tools.compute', $slug) }}" class="card h-fit space-y-4 p-5" novalidate>
                @csrf
                @if ($input === 'birth')
                    @include('public.tools._person', ['p' => '', 'legend' => null])
                @elseif ($input === 'couple')
                    @include('public.tools._person', ['p' => 'a_', 'legend' => 'Boy / groom'])
                    @include('public.tools._person', ['p' => 'b_', 'legend' => 'Girl / bride'])
                @else
                    @if (in_array($input, ['name', 'name_dob', 'two_names']))<x-field name="name" label="{{ $input === 'two_names' ? 'Your name' : 'Full name' }}" required />@endif
                    @if ($input === 'two_names')<x-field name="partner" label="Their name" required />@endif
                    @if (in_array($input, ['dob', 'dob_month', 'name_dob', 'vehicle']))<x-field name="birth_date" label="Date of birth" type="date" required />@endif
                    @if ($slug === 'personal-year')<x-field name="year" label="Year" type="number" :value="now('Asia/Kolkata')->year" />@endif
                    @if ($input === 'dob_month')<x-field name="month" label="Month" type="month" :value="now('Asia/Kolkata')->format('Y-m')" required />@endif
                    @if ($input === 'vehicle')<x-field name="plate" label="Vehicle number" placeholder="BR01AB1234" required />@endif
                @endif
                <button class="btn-primary w-full">Calculate</button>
                <p class="text-xs text-ink-600">Details are used only to calculate this result and are not stored.</p>
            </form>

            <section aria-live="polite" aria-labelledby="res-h" class="min-w-0">
                <h2 id="res-h" class="sr-only">Result</h2>
                @if (! empty($engineError))
                    <div class="card border-red-200 bg-red-50 p-5" role="alert">The calculation service is unavailable right now. Please try again in a few minutes.</div>
                @elseif (! $r)
                    <div class="card p-8 text-center text-ink-600">Enter the details and press Calculate.</div>
                @else
                    @include('public.tools._result', ['r' => $r])
                    <div class="card mt-6 flex flex-wrap items-center justify-between gap-3 p-5">
                        <p class="text-sm">Want Acharya Ji to read this in detail?</p>
                        <a href="{{ route('services.show', 'janma-kundali-reading') }}" class="btn-primary">Book a reading</a>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.public>
