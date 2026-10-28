<x-layouts.account title="Notifications">
    <section class="card p-5" aria-labelledby="nt-h">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="nt-h" class="text-lg">Updates for you</h2>
            <p class="text-sm text-ink-600">Choose which updates we send in <a href="{{ route('account.profile') }}">Profile &amp; contact</a>.</p>
        </div>
        <ul class="mt-4 divide-y divide-ink-200/60">
            @forelse ($rows as $n)
                <li class="py-3">
                    <p class="font-medium">@if (! $n->read_at)<span class="badge-gold mr-1">New</span>@endif{{ $n->title }}</p>
                    <p class="text-sm text-ink-700">{{ $n->body }}</p>
                    <p class="mt-1 text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($n->created_at)->timezone('Asia/Kolkata')->format('d M Y, g:i A') }}
                        @if ($n->link) · <a href="{{ $n->link }}">Open</a>@endif</p>
                </li>
            @empty
                <li class="py-3 text-ink-600">No notifications yet.</li>
            @endforelse
        </ul>
        {{ $rows->links() }}
    </section>
</x-layouts.account>
