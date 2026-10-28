<x-layouts.admin title="FAQs">
    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="min-w-0 space-y-3" aria-label="Existing questions">
            @foreach ($faqs as $f)
                <details class="card p-4">
                    <summary class="cursor-pointer font-medium">{{ $f->question }} @unless ($f->published)<span class="badge-ink ml-1">Hidden</span>@endunless</summary>
                    @can('content.publish')
                    <form method="POST" action="{{ route('admin.faqs.save') }}" class="mt-3 space-y-3">
                        @csrf <input type="hidden" name="id" value="{{ $f->id }}">
                        <x-field name="question" label="Question" :value="$f->question" :id="'q-'.$f->id" />
                        <div><label for="a-{{ $f->id }}" class="field-label">Answer</label><textarea id="a-{{ $f->id }}" name="answer" rows="3" class="input">{{ $f->answer }}</textarea></div>
                        <div class="flex flex-wrap items-center gap-4">
                            <x-field name="sort" label="Order" type="number" :value="$f->sort" :id="'s-'.$f->id" class="input w-24" />
                            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" class="size-4 accent-ink-900" @checked($f->published)> Published</label>
                        </div>
                        <button class="btn-outline">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.faqs.destroy', $f->id) }}" class="mt-2">@csrf @method('DELETE')<button class="btn-ghost px-3 text-danger-700">Delete</button></form>
                    @else
                    <p class="mt-2 text-sm text-ink-700">{{ $f->answer }}</p>
                    @endcan
                </details>
            @endforeach
        </section>
        @can('content.publish')
        <form method="POST" action="{{ route('admin.faqs.save') }}" class="card h-fit space-y-4 p-5">
            @csrf
            <h2 class="text-lg">Add a question</h2>
            <x-field name="question" label="Question" id="new-q" />
            <div><label for="new-a" class="field-label">Answer</label><textarea id="new-a" name="answer" rows="4" class="input"></textarea></div>
            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" checked class="size-4 accent-ink-900"> Publish now</label>
            <button class="btn-primary w-full">Add</button>
        </form>
        @endcan
    </div>
</x-layouts.admin>
