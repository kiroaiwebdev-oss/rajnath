@php [$title, $path] = \App\Domain\Legal\LegalDocuments::KINDS[$kind]; @endphp
<x-layouts.public :title="__(':title — earlier versions', ['title' => __($title)])" robots="noindex,follow" :jsonld="false">
    <section class="container-page max-w-3xl py-12">
        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-ink-600"><a href="{{ url($path) }}">{{ __($title) }}</a> › {{ __('Earlier versions') }}</nav>
        <h1 class="mt-4 text-3xl">{{ __('Earlier versions') }}</h1>
        <p class="mt-2 text-ink-600">{{ __('Every published version is kept, so you can see the text that applied on any date.') }}</p>
        <ol class="mt-8 space-y-3">
            @foreach ($versions as $v)
                <li class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <p class="font-semibold">{{ __('Version :v', ['v' => $v->version]) }} @if ($v->status === 'published')<span class="badge-success ml-1">{{ __('Current') }}</span>@endif</p>
                        <p class="text-sm text-ink-600">{{ __('In effect from :date', ['date' => \Illuminate\Support\Carbon::parse($v->effective_on ?? $v->published_at)->translatedFormat('j F Y')]) }}@if ($v->change_note) · {{ $v->change_note }}@endif</p>
                    </div>
                    <a href="{{ url($path).($v->status === 'published' ? '' : '?version='.urlencode($v->version)) }}" class="btn-outline">{{ __('Read') }}</a>
                </li>
            @endforeach
        </ol>
    </section>
</x-layouts.public>
