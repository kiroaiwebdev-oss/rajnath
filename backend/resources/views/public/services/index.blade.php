<x-layouts.public :title="__('Services')" :description="__('Consultations, Vastu, remedies and puja with Acharya Dr. Rajnath Jha.')">
    <div class="container-page py-10">
        <p class="eyebrow">{{ __('Services') }}</p>
        <h1 class="mt-2 text-3xl">{{ __('Find the right guidance') }}</h1>

        <form method="GET" action="{{ route('services.index') }}" class="card mt-6 grid gap-4 p-4 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end" role="search">
            <x-field name="q" :label="__('Search')" :value="$q" :placeholder="__('e.g. marriage, vastu, gemstone')" />
            <x-select name="family" :label="__('Category')" :value="$family" :options="['' => __('All categories')] + $families->mapWithKeys(fn ($f) => [$f->slug => $f->label()])->all()" />
            <x-select name="mode" :label="__('How')" :value="$mode" :options="['' => __('Any'), 'in_person' => __('In person'), 'video' => __('Video call'), 'audio' => __('Audio call'), 'onsite' => __('On-site visit'), 'puja' => __('Puja')]" />
            <button class="btn-primary">{{ __('Show results') }}</button>
        </form>

        <p class="mt-6 text-sm text-ink-600" role="status">{{ trans_choice(':count service found|:count services found', $offerings->count(), ['count' => $offerings->count()]) }}</p>
        @if ($offerings->isEmpty())
            <div class="card mt-4 p-8 text-center">
                <h2 class="text-base">{{ __('No services match your search') }}</h2>
                <p class="mt-2 text-sm text-ink-600">{{ __('Try a different word, or') }} <a href="{{ route('contact') }}">{{ __('tell us what you need') }}</a> {{ __('and we will guide you.') }}</p>
                <a href="{{ route('services.index') }}" class="btn-outline mt-4">{{ __('Clear filters') }}</a>
            </div>
        @else
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offerings as $o)<div class="relative"><x-service-card :o="$o" /></div>@endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
