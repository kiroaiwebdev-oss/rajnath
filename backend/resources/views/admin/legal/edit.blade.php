@php use App\Domain\Legal\LegalDocuments; [$label, $path] = LegalDocuments::KINDS[$kind]; @endphp
<x-layouts.admin :title="$label.' — '.LegalDocuments::LOCALES[$locale]">
    <p class="text-sm"><a href="{{ route('admin.legal.index') }}">← All legal pages</a>
        @if ($current) · Live version {{ $current->version }}, in effect from {{ \Illuminate\Support\Carbon::parse($current->effective_on)->format('j M Y') }} · <a href="{{ url($path) }}" target="_blank" rel="noopener">View</a>@else · Not published yet @endif</p>
    @error('unpublish')<p class="field-error mt-2">{{ $message }}</p>@enderror

    <div class="mt-4 grid gap-6 2xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.legal.save', [$kind, $locale]) }}" class="card min-w-0 space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="text-lg">Draft</h2>
            <x-field name="title" label="Page title" :value="$draft->title" required />
            <div>
                <label for="f-body" class="field-label">Text</label>
                <textarea id="f-body" name="body" rows="28" class="input font-mono text-sm leading-6" spellcheck="true" @if ($locale === 'hi') lang="hi" @endif>{{ old('body', $draft->body) }}</textarea>
                @error('body')<p class="field-error">{{ $message }}</p>@enderror
                <details class="field-hint mt-2">
                    <summary class="cursor-pointer">Formatting help</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li><code>## Heading</code> for a section title, <code>**bold**</code>, <code>- item</code> for a list, a blank line between paragraphs.</li>
                        <li><code>> text</code> for a highlighted note. Links: <code>[text](https://…)</code>; web and email addresses become links automatically.</li>
                        <li>These are filled in from Settings when shown: @foreach (LegalDocuments::PLACEHOLDERS as $p)<code>{{ $p }}</code> @endforeach</li>
                    </ul>
                </details>
            </div>
            <x-field name="change_note" label="What changed (shown in the public history)" :value="$draft->change_note" placeholder="Added the gemstone return window" />
            <div class="flex flex-wrap gap-3">
                <button class="btn-primary">Save draft and preview</button>
            </div>
        </form>

        <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="prev-h">
            <h2 id="prev-h" class="text-lg">Preview</h2>
            <p class="text-xs text-ink-600">Of the saved draft, as visitors will see it.</p>
            <div class="prose-legal mt-4 max-h-[48rem] overflow-y-auto rounded-xl border border-ink-200/60 bg-ivory-50 p-5">
                <h1 class="text-2xl font-semibold">{{ $draft->title }}</h1>
                {{ $preview }}
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.legal.publish', [$kind, $locale]) }}" class="card space-y-4 p-5">
            @csrf
            <h2 class="text-lg">Publish</h2>
            <p class="text-sm text-ink-600">Publishing freezes the saved draft as a new dated version. You will be asked for your password and authenticator code.</p>
            <x-field name="effective_on" label="In effect from" type="date" :value="now('Asia/Kolkata')->toDateString()" />
            <label class="flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" class="mt-0.5 size-4 accent-ink-900"> I have checked this text (with a lawyer or CA where needed).</label>
            @error('confirm')<p class="field-error">{{ $message }}</p>@enderror
            @error('effective_on')<p class="field-error">{{ $message }}</p>@enderror
            <button class="btn-primary">Publish new version</button>
        </form>
        <div class="card space-y-4 p-5">
            <h2 class="text-lg">History</h2>
            <ol class="space-y-2 text-sm">
                @forelse ($history as $v)
                    <li class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ $v->version }}</span> @if ($v->status === 'published')<span class="badge-success">Live</span>@endif
                        <span class="text-ink-600">{{ \Illuminate\Support\Carbon::parse($v->published_at)->tz('Asia/Kolkata')->format('j M Y, H:i') }}{{ $v->change_note ? ' · '.$v->change_note : '' }}</span>
                        <a href="{{ url($path).'?version='.urlencode($v->version).($locale !== 'en' ? '&lang='.$locale : '') }}" target="_blank" rel="noopener">Read</a></li>
                @empty
                    <li class="text-ink-600">No published versions yet.</li>
                @endforelse
            </ol>
            <div class="flex flex-wrap gap-3 border-t border-ink-200/60 pt-4">
                <form method="POST" action="{{ route('admin.legal.discard', [$kind, $locale]) }}" onsubmit="return confirm('Throw away the draft?')">@csrf @method('DELETE')<button class="btn-ghost text-danger-700">Discard draft</button></form>
                @if ($current && ! ($locale === 'en' && in_array($kind, ['privacy', 'terms'], true)))
                    <form method="POST" action="{{ route('admin.legal.unpublish', [$kind, $locale]) }}" onsubmit="return confirm('Take this page off the website?')">@csrf<button class="btn-ghost text-danger-700">Take page down</button></form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
