@php use App\Domain\Blog\Blog; @endphp
<x-layouts.admin title="Articles">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Filter" class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.blog.index') }}" class="btn-outline px-3" @if (! $status) aria-current="page" @endif>All</a>
            <a href="{{ route('admin.blog.index', ['status' => 'draft']) }}" class="btn-outline px-3" @if ($status === 'draft') aria-current="page" @endif>Drafts <span class="text-ink-600">{{ (int) ($counts['draft'] ?? 0) }}</span></a>
            <a href="{{ route('admin.blog.index', ['status' => 'published']) }}" class="btn-outline px-3" @if ($status === 'published') aria-current="page" @endif>Published <span class="text-ink-600">{{ (int) ($counts['published'] ?? 0) }}</span></a>
        </nav>
        <a href="{{ route('admin.blog.create') }}" class="btn-primary">New article</a>
    </div>
    <p class="mt-3 max-w-3xl text-sm text-ink-600">Read each draft before publishing. Tick several articles to publish them together.</p>

    <form method="POST" action="{{ route('admin.blog.action') }}" class="mt-4">
        @csrf
        <div class="table-wrap" role="region" aria-label="Articles" tabindex="0"><table class="table">
            <thead><tr><th scope="col"><span class="sr-only">Select</span></th><th scope="col">Article</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Updated</th></tr></thead>
            <tbody>
                @forelse ($posts as $p)
                    <tr>
                        <td><input type="checkbox" name="refs[]" value="{{ $p->ref }}" class="size-4 accent-ink-900" aria-label="Select {{ $p->title }}"></td>
                        <td><a href="{{ route('admin.blog.edit', $p->ref) }}" class="font-medium">{{ $p->title }}</a><br><span class="text-xs text-ink-600">{{ $p->reading_minutes }} min read · /blog/{{ $p->slug }}</span></td>
                        <td>{{ Blog::CATEGORIES[$p->category] ?? $p->category }}</td>
                        <td>@if ($p->status === 'published')<span class="badge-success">Published</span>@else<span class="badge-gold">Draft</span>@endif @if ($p->featured)<span class="badge-ink">Featured</span>@endif</td>
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($p->updated_at)->tz('Asia/Kolkata')->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-600">No articles yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @can('content.publish')
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <label for="bulk" class="text-sm font-medium">With the ticked articles:</label>
                <select id="bulk" name="action" class="input w-auto">
                    <option value="publish">Publish</option><option value="unpublish">Move back to draft</option><option value="feature">Feature on blog home</option><option value="unfeature">Stop featuring</option><option value="delete">Delete</option>
                </select>
                <button class="btn-primary" onclick="return this.form.action.value !== 'delete' || confirm('Delete the ticked articles?')">Apply</button>
            </div>
            @error('refs')<p class="field-error">Tick at least one article.</p>@enderror
        @endcan
    </form>
    <div class="mt-4">{{ $posts->links() }}</div>
</x-layouts.admin>
