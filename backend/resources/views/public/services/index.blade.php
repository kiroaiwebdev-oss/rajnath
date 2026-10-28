<x-layouts.public title="Services" description="Consultations, Vastu, remedies and puja with Acharya Dr. Rajnath Jha.">
    <div class="container-page py-10">
        <p class="eyebrow">Services</p>
        <h1 class="mt-2 text-3xl">Find the right guidance</h1>

        <form method="GET" action="{{ route('services.index') }}" class="card mt-6 grid gap-4 p-4 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end" role="search">
            <x-field name="q" label="Search" :value="$q" placeholder="e.g. marriage, vastu, gemstone" />
            <x-select name="family" label="Category" :value="$family" :options="['' => 'All categories'] + $families->pluck('name', 'slug')->all()" />
            <x-select name="mode" label="How" :value="$mode" :options="['' => 'Any', 'in_person' => 'In person', 'video' => 'Video call', 'audio' => 'Audio call', 'onsite' => 'On-site visit', 'puja' => 'Puja']" />
            <button class="btn-primary">Show results</button>
        </form>

        <p class="mt-6 text-sm text-ink-600" role="status">{{ $offerings->count() }} {{ \Illuminate\Support\Str::plural('service', $offerings->count()) }} found</p>
        @if ($offerings->isEmpty())
            <div class="card mt-4 p-8 text-center">
                <h2 class="text-base">No services match your search</h2>
                <p class="mt-2 text-sm text-ink-600">Try a different word, or <a href="{{ route('contact') }}">tell us what you need</a> and we will guide you.</p>
                <a href="{{ route('services.index') }}" class="btn-outline mt-4">Clear filters</a>
            </div>
        @else
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offerings as $o)<div class="relative"><x-service-card :o="$o" /></div>@endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
