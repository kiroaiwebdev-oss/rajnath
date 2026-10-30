@php
    use App\Domain\Showcase\Profile;
    use App\Http\Controllers\Admin\ProfileController as PC;
    $canEdit = auth()->user()->can('content.publish');
@endphp
<x-layouts.admin title="About & awards">
    <p class="max-w-3xl text-sm text-ink-600">Everything here appears on the <a href="{{ route('about') }}" target="_blank" rel="noopener">About page</a>, the <a href="{{ route('awards') }}" target="_blank" rel="noopener">Awards page</a>, the home page and the app. The Hindi boxes are optional: when empty, Hindi visitors see the English text. Photos of pujas and events go in the <a href="{{ route('admin.gallery.index') }}">Gallery</a>.</p>
    @unless ($canEdit)<p class="card mt-4 max-w-3xl border-gold-200 bg-gold-50 p-4 text-sm">You can view this page. Saving needs the content publisher role.</p>@endunless

    <nav aria-label="Sections" class="mt-5 flex flex-wrap gap-2 text-sm">
        <a href="#text" class="btn-outline px-3">Text, stats &amp; photos</a>
        @foreach (Profile::SECTIONS as $key => $label)<a href="#{{ $key }}" class="btn-outline px-3">{{ $label }} <span class="ml-1 text-ink-600">{{ count($entries[$key] ?? []) }}</span></a>@endforeach
    </nav>

    <form id="text" method="POST" action="{{ route('admin.profile.text') }}" enctype="multipart/form-data" class="card mt-6 max-w-5xl scroll-mt-20 space-y-6 p-5 sm:p-6">
        @csrf @method('PUT')
        <h2 class="text-lg">Text, stats &amp; photos</h2>
        @foreach (Profile::TEXT as $key => [$label, $default, $rows])
            @php $n = PC::field($key); $nh = PC::field($key, 'hi'); $value = old($n, $values[$key] ?? $default); @endphp
            <div @class(['grid gap-4', 'lg:grid-cols-2' => PC::translatable($key)])>
                <div>
                    <label for="f-{{ $n }}" class="field-label">{{ $label }}</label>
                    @if ($rows > 1)
                        <textarea id="f-{{ $n }}" name="{{ $n }}" rows="{{ $rows }}" class="input">{{ $value }}</textarea>
                    @else
                        <input id="f-{{ $n }}" name="{{ $n }}" value="{{ $value }}" class="input">
                    @endif
                    @error($n)<p class="field-error">{{ $message }}</p>@enderror
                </div>
                @if (PC::translatable($key))
                    <div>
                        <label for="f-{{ $nh }}" class="field-label">Hindi (optional)</label>
                        @if ($rows > 1)
                            <textarea id="f-{{ $nh }}" name="{{ $nh }}" rows="{{ $rows }}" class="input" lang="hi">{{ old($nh, $values[$key.'.hi'] ?? '') }}</textarea>
                        @else
                            <input id="f-{{ $nh }}" name="{{ $nh }}" value="{{ old($nh, $values[$key.'.hi'] ?? '') }}" class="input" lang="hi">
                        @endif
                        @error($nh)<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>
        @endforeach
        <div class="grid gap-6 border-t border-ink-200/60 pt-5 sm:grid-cols-2">
            @foreach (Profile::PHOTOS as $key => [$label])
                @php $n = PC::field($key); @endphp
                <div>
                    <img src="/{{ $profile->photo($key) }}" alt="" class="mb-3 h-40 rounded-xl object-cover">
                    <label for="f-{{ $n }}" class="field-label">{{ $label }} — replace (JPG/PNG/WebP, at least 400×400)</label>
                    <input id="f-{{ $n }}" name="{{ $n }}" type="file" accept="image/jpeg,image/png,image/webp" class="input">
                    @error($n)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        @if ($canEdit)<div class="border-t border-ink-200/60 pt-5"><button class="btn-primary">Save text and photos</button></div>@endif
    </form>

    @foreach (Profile::SECTIONS as $section => $sectionLabel)
        <section id="{{ $section }}" class="mt-10 max-w-5xl scroll-mt-20" aria-labelledby="h-{{ $section }}">
            <h2 id="h-{{ $section }}" class="text-xl">{{ $sectionLabel }}</h2>
            <div class="mt-4 space-y-3">
                @forelse ($entries[$section] ?? [] as $e)
                    <details class="card p-4">
                        <summary class="flex cursor-pointer flex-wrap items-center gap-2">
                            @if ($e->image_path)<img src="/{{ $e->image_path }}" alt="" class="size-10 rounded-lg object-cover">@endif
                            <span class="font-medium">{{ $e->title }}</span>
                            @if ($e->year)<span class="text-sm text-ink-600">{{ $e->year }}</span>@endif
                            @if ($e->group)<span class="badge-ink">{{ Profile::AWARD_GROUPS[$e->group] }}</span>@endif
                            @if ($e->featured)<span class="badge-gold">On home page</span>@endif
                            @unless ($e->published)<span class="badge-danger">Hidden</span>@endunless
                        </summary>
                        @if ($canEdit)
                            @include('admin.profile.entry-form', ['e' => $e, 'section' => $section])
                            <form method="POST" action="{{ route('admin.profile.entry.destroy', $e->id) }}" class="mt-2" onsubmit="return confirm('Delete this entry?')">@csrf @method('DELETE')<button class="btn-ghost px-3 text-danger-700">Delete</button></form>
                        @else
                            <p class="mt-2 text-sm text-ink-700">{{ $e->detail }}</p>
                        @endif
                    </details>
                @empty
                    <p class="text-sm text-ink-600">Nothing here yet.</p>
                @endforelse
                @if ($canEdit)
                    <details class="card border-dashed p-4" @if ($errors->any() && old('section') === $section && ! old('id')) open @endif>
                        <summary class="cursor-pointer font-medium text-gold-700">+ Add to {{ mb_strtolower($sectionLabel) }}</summary>
                        @include('admin.profile.entry-form', ['e' => null, 'section' => $section])
                    </details>
                @endif
            </div>
        </section>
    @endforeach
</x-layouts.admin>
