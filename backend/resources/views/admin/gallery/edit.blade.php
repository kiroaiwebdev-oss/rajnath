@php use App\Domain\Showcase\Gallery; $canPublish = auth()->user()->can('content.publish'); @endphp
<x-layouts.admin :title="'Album: '.$album->title">
    <p class="text-sm"><a href="{{ route('admin.gallery.index') }}">← All albums</a>
        @if ($album->published) · <a href="{{ route('gallery.show', $album->slug) }}" target="_blank" rel="noopener">View on the website</a>@endif</p>

    <div class="mt-4 grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="min-w-0 space-y-6" aria-label="Photos and videos">
            <div class="grid gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.gallery.upload', $album->ref) }}" enctype="multipart/form-data" class="card space-y-3 p-5">
                    @csrf
                    <h2 class="text-lg">Add photos</h2>
                    <label for="f-photos" class="field-label">Choose up to {{ Gallery::MAX_UPLOAD }} photos (JPG/PNG/WebP, under 8 MB each)</label>
                    <input id="f-photos" name="photos[]" type="file" multiple required accept="image/jpeg,image/png,image/webp" class="input">
                    @error('photos')<p class="field-error">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="field-error">{{ $message }}</p>@enderror
                    <button class="btn-primary">Upload</button>
                </form>
                <form method="POST" action="{{ route('admin.gallery.video', $album->ref) }}" class="card space-y-3 p-5">
                    @csrf
                    <h2 class="text-lg">Add a YouTube video</h2>
                    <x-field name="video_url" label="YouTube link" placeholder="https://www.youtube.com/watch?v=…" inputmode="url" />
                    <x-field name="caption" label="Caption (optional)" />
                    <button class="btn-primary">Add video</button>
                </form>
            </div>

            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($items as $i)
                    <li class="card overflow-hidden">
                        <div class="relative">
                            <img src="{{ $i->kind === 'photo' ? '/'.($i->thumb_path ?: $i->path) : Gallery::videoThumb($i->video_id) }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">
                            @if ($i->kind === 'video')<span class="badge-ink absolute left-2 top-2">Video</span>@endif
                            @if ((int) $album->cover_item_id === (int) $i->id)<span class="badge-gold absolute right-2 top-2">Cover</span>@endif
                        </div>
                        <form method="POST" action="{{ route('admin.gallery.item', [$album->ref, $i->id]) }}" class="space-y-2 p-3">
                            @csrf @method('PUT')
                            <input name="caption" value="{{ $i->caption }}" maxlength="300" class="input" aria-label="Caption" placeholder="Caption">
                            <input name="caption_hi" value="{{ $i->caption_hi }}" maxlength="300" class="input" aria-label="Caption in Hindi" placeholder="Caption in Hindi (optional)" lang="hi">
                            <div class="flex items-center gap-3">
                                <input name="sort" type="number" min="0" max="9999" value="{{ $i->sort }}" class="input w-24" aria-label="Order">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="cover" value="1" class="size-4 accent-ink-900"> Cover</label>
                                <button class="btn-outline ml-auto px-3">Save</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.gallery.item.destroy', [$album->ref, $i->id]) }}" class="px-3 pb-3" onsubmit="return confirm('Remove this from the album?')">
                            @csrf @method('DELETE')<button class="btn-ghost px-2 text-sm text-danger-700">Remove</button>
                        </form>
                    </li>
                @empty
                    <li class="card p-6 text-sm text-ink-600 sm:col-span-2 lg:col-span-3">No photos or videos yet.</li>
                @endforelse
            </ul>
        </section>

        <aside class="space-y-4">
            <form method="POST" action="{{ route('admin.gallery.update', $album->ref) }}" class="card space-y-4 p-5">
                @csrf @method('PUT')
                <h2 class="text-lg">Album details</h2>
                <x-field name="title" label="Title" :value="$album->title" required />
                <x-field name="title_hi" label="Title in Hindi (optional)" :value="$album->title_hi" lang="hi" />
                <x-field name="slug" label="Web address" :value="$album->slug" hint="Lower-case letters, numbers and dashes." />
                <x-select name="category" label="Category" :options="Gallery::CATEGORIES" :value="$album->category" />
                <x-field name="event_date" label="Date" type="date" :value="$album->event_date" />
                <x-field name="location" label="Place" :value="$album->location" />
                <div><label for="f-desc" class="field-label">Description</label><textarea id="f-desc" name="description" rows="4" class="input">{{ old('description', $album->description) }}</textarea></div>
                <div><label for="f-desc-hi" class="field-label">Description in Hindi (optional)</label><textarea id="f-desc-hi" name="description_hi" rows="3" class="input" lang="hi">{{ old('description_hi', $album->description_hi) }}</textarea></div>
                <x-field name="sort" label="Order on the gallery page (small first)" type="number" :value="$album->sort" />
                @if ($canPublish)
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" class="size-4 accent-ink-900" @checked($album->published)> Published (visible to everyone)</label>
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="featured" value="1" class="size-4 accent-ink-900" @checked($album->featured)> Show on the home page</label>
                @else
                    <p class="text-sm text-ink-600">Status: {{ $album->published ? 'Live' : 'Draft' }}. A content publisher makes it live.</p>
                @endif
                <button class="btn-primary w-full">Save album</button>
            </form>
            @can('content.publish')
                <form method="POST" action="{{ route('admin.gallery.destroy', $album->ref) }}" class="card p-5" onsubmit="return confirm('Delete this album and all its photos? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button class="btn-ghost w-full text-danger-700">Delete album</button>
                </form>
            @endcan
        </aside>
    </div>
</x-layouts.admin>
