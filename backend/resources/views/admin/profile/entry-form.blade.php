@php $k = $e ? 'e'.$e->id : 'new-'.$section; $old = fn ($f, $v = null) => old('id') == ($e->id ?? null) && old('section') === $section ? old($f, $v) : $v; @endphp
<form method="POST" action="{{ route('admin.profile.entry') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
    @csrf
    <input type="hidden" name="section" value="{{ $section }}">
    @if ($e)<input type="hidden" name="id" value="{{ $e->id }}">@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <div><label for="{{ $k }}-t" class="field-label">Title</label><input id="{{ $k }}-t" name="title" value="{{ $old('title', $e?->title) }}" required maxlength="300" class="input"></div>
        <div><label for="{{ $k }}-th" class="field-label">Title in Hindi (optional)</label><input id="{{ $k }}-th" name="title_hi" value="{{ $old('title_hi', $e?->title_hi) }}" maxlength="300" class="input" lang="hi"></div>
        <div><label for="{{ $k }}-d" class="field-label">{{ $section === 'award' ? 'Given by / where' : 'Details' }} (optional)</label><textarea id="{{ $k }}-d" name="detail" rows="2" class="input">{{ $old('detail', $e?->detail) }}</textarea></div>
        <div><label for="{{ $k }}-dh" class="field-label">Details in Hindi (optional)</label><textarea id="{{ $k }}-dh" name="detail_hi" rows="2" class="input" lang="hi">{{ $old('detail_hi', $e?->detail_hi) }}</textarea></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label for="{{ $k }}-y" class="field-label">Year or period</label><input id="{{ $k }}-y" name="year" value="{{ $old('year', $e?->year) }}" maxlength="40" class="input" placeholder="2024 or 2011 – present"></div>
        @if ($section === 'award')
            <div><label for="{{ $k }}-g" class="field-label">Group</label>
                <select id="{{ $k }}-g" name="group" class="input">
                    <option value="">—</option>
                    @foreach (\App\Domain\Showcase\Profile::AWARD_GROUPS as $v => $l)<option value="{{ $v }}" @selected($old('group', $e?->group) === $v)>{{ $l }}</option>@endforeach
                </select></div>
        @endif
        <div><label for="{{ $k }}-s" class="field-label">Order (small first)</label><input id="{{ $k }}-s" name="sort" type="number" min="0" max="9999" value="{{ $old('sort', $e?->sort) }}" class="input"></div>
    </div>
    <div><label for="{{ $k }}-l" class="field-label">Link (optional, https://…, e.g. a news article)</label><input id="{{ $k }}-l" name="link_url" value="{{ $old('link_url', $e?->link_url) }}" class="input" inputmode="url"></div>
    <div>
        <label for="{{ $k }}-i" class="field-label">{{ $e?->image_path ? 'Replace photo' : 'Photo (optional)' }} — JPG/PNG/WebP, at least 400×400</label>
        <input id="{{ $k }}-i" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="input">
        @if ($e?->image_path)
            <div class="mt-2 flex items-center gap-3"><img src="/{{ $e->image_path }}" alt="" class="h-20 rounded-lg object-cover">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1" class="size-4 accent-ink-900"> Remove photo</label></div>
        @endif
    </div>
    @if (old('id') == ($e->id ?? null) && old('section') === $section)
        @foreach ($errors->all() as $msg)<p class="field-error">{{ $msg }}</p>@endforeach
    @endif
    <div class="flex flex-wrap items-center gap-5">
        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" class="size-4 accent-ink-900" @checked($old('published', $e?->published ?? true))> Show on the website</label>
        @if (in_array($section, ['award', 'position', 'education'], true))
            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="featured" value="1" class="size-4 accent-ink-900" @checked($old('featured', $e?->featured))> Highlight on the home page</label>
        @endif
        <button class="btn-primary">{{ $e ? 'Save' : 'Add' }}</button>
    </div>
</form>
