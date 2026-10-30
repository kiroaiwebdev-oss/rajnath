@php use App\Domain\Showcase\Gallery; @endphp
<x-layouts.admin title="Gallery">
    <p class="max-w-3xl text-sm text-ink-600">Albums of pujas, anushthans, awards, events and media coverage. They appear on the <a href="{{ route('gallery') }}" target="_blank" rel="noopener">Gallery page</a> and in the app once published. Photos are resized and their location data is removed on upload. Do not upload photos of customers without their written permission.</p>
    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="min-w-0" aria-label="Albums">
            <nav aria-label="Filter" class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.gallery.index') }}" class="btn-outline px-3" @if (! $category) aria-current="page" @endif>All</a>
                @foreach (Gallery::CATEGORIES as $k => $l)<a href="{{ route('admin.gallery.index', ['category' => $k]) }}" class="btn-outline px-3" @if ($category === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach
            </nav>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($albums as $a)
                    <li class="card relative overflow-hidden">
                        @if ($url = Gallery::coverUrl($a))<img src="{{ $url }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">@else<div class="aspect-[4/3] w-full bg-ivory-100"></div>@endif
                        <div class="p-4">
                            <h2 class="text-base"><a href="{{ route('admin.gallery.edit', $a->ref) }}" class="no-underline after:absolute after:inset-0">{{ $a->title }}</a></h2>
                            <p class="mt-1 text-xs text-ink-600">{{ Gallery::CATEGORIES[$a->category] ?? $a->category }} · {{ $a->photo_count }} photos · {{ $a->video_count }} videos @if ($a->event_date)· {{ \Illuminate\Support\Carbon::parse($a->event_date)->format('j M Y') }}@endif</p>
                            <p class="mt-2 flex flex-wrap gap-1">@if ($a->published)<span class="badge-success">Live</span>@else<span class="badge-ink">Draft</span>@endif @if ($a->featured)<span class="badge-gold">Home page</span>@endif</p>
                        </div>
                    </li>
                @empty
                    <li class="card p-6 text-sm text-ink-600 sm:col-span-2 lg:col-span-3">No albums yet. Create the first one on the right.</li>
                @endforelse
            </ul>
        </section>
        <form method="POST" action="{{ route('admin.gallery.store') }}" class="card h-fit space-y-4 p-5">
            @csrf
            <h2 class="text-lg">New album</h2>
            <x-field name="title" label="Title" required placeholder="Maha Rudrabhishek, Shravan 2026" />
            <x-select name="category" label="Category" :options="Gallery::CATEGORIES" value="puja" />
            <x-field name="event_date" label="Date (optional)" type="date" />
            <x-field name="location" label="Place (optional)" placeholder="Mahavir Mandir, Patna" />
            <div><label for="new-desc" class="field-label">Description (optional)</label><textarea id="new-desc" name="description" rows="3" class="input">{{ old('description') }}</textarea></div>
            @can('content.publish')<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" class="size-4 accent-ink-900"> Publish now</label>@endcan
            <button class="btn-primary w-full">Create album</button>
        </form>
    </div>
</x-layouts.admin>
