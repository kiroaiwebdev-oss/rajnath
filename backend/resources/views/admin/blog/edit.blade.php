@php use App\Domain\Blog\Blog; @endphp
<x-layouts.admin :title="$p ? 'Edit article' : 'New article'">
    <p class="text-sm"><a href="{{ route('admin.blog.index') }}">← All articles</a>
        @if ($p?->status === 'published') · <a href="{{ route('blog.show', $p->slug) }}" target="_blank" rel="noopener">View on the website</a>@endif</p>
    <div class="mt-4 grid gap-6 2xl:grid-cols-2">
        <form method="POST" action="{{ $p ? route('admin.blog.update', $p->ref) : route('admin.blog.store') }}" enctype="multipart/form-data" class="card min-w-0 space-y-4 p-5 sm:p-6">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-field name="title" label="Title" :value="$p?->title" required /></div>
                <div class="sm:col-span-2"><x-field name="title_hi" label="Title in Hindi (optional)" :value="$p?->title_hi" lang="hi" /></div>
                <x-select name="category" label="Category" :options="Blog::CATEGORIES" :value="$p?->category" />
                <x-field name="slug" label="Web address" :value="$p?->slug" hint="Leave empty to make it from the title." />
            </div>
            <div><label for="f-excerpt" class="field-label">Short summary (shown in lists and Google)</label><textarea id="f-excerpt" name="excerpt" rows="2" maxlength="400" class="input" required>{{ old('excerpt', $p?->excerpt) }}</textarea>@error('excerpt')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="f-excerpt-hi" class="field-label">Short summary in Hindi (optional)</label><textarea id="f-excerpt-hi" name="excerpt_hi" rows="2" maxlength="400" class="input" lang="hi">{{ old('excerpt_hi', $p?->excerpt_hi) }}</textarea></div>
            <div><label for="f-body" class="field-label">Article (## for headings, - for lists, ### Question? under "## Frequently asked questions")</label>
                <textarea id="f-body" name="body" rows="24" class="input font-mono text-sm leading-6" required>{{ old('body', $p?->body) }}</textarea>@error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="f-body-hi" class="field-label">Article in Hindi (optional)</label><textarea id="f-body-hi" name="body_hi" rows="10" class="input font-mono text-sm leading-6" lang="hi">{{ old('body_hi', $p?->body_hi) }}</textarea></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="tags" label="Tags (comma separated)" :value="implode(', ', json_decode($p?->tags ?? '[]', true))" />
                <x-field name="services" label="Related services (web addresses, comma separated)" :value="implode(', ', json_decode($p?->services ?? '[]', true))" />
            </div>
            <div><label for="f-cover" class="field-label">Cover image (optional, JPG/PNG/WebP)</label><input id="f-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" class="input">
                @if ($p)<img src="/{{ Blog::cover($p) }}" alt="" class="mt-3 h-28 rounded-xl object-cover">@endif</div>
            <button class="btn-primary">{{ $p ? 'Save' : 'Create draft' }}</button>
        </form>
        @if ($p)
            <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="pv-h">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="pv-h" class="text-lg">Preview</h2>
                    @can('content.publish')
                        <form method="POST" action="{{ route('admin.blog.action') }}" class="flex gap-2">@csrf<input type="hidden" name="refs[]" value="{{ $p->ref }}">
                            @if ($p->status === 'draft')<button name="action" value="publish" class="btn-primary">Publish</button>@else<button name="action" value="unpublish" class="btn-outline">Move back to draft</button>@endif
                        </form>
                    @endcan
                </div>
                <div class="prose-legal mt-4 max-h-[60rem] overflow-y-auto rounded-xl border border-ink-200/60 bg-ivory-50 p-5"><h1 class="text-2xl font-semibold">{{ $p->title }}</h1>{{ $preview }}</div>
            </section>
        @endif
    </div>
</x-layouts.admin>
