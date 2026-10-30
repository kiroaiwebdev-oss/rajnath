@php use App\Domain\Reviews\Reviews; @endphp
<x-layouts.account title="Reviews">
    @if ($orders->isNotEmpty())
        <section aria-labelledby="todo-h">
            <h2 id="todo-h" class="text-lg">Waiting for your review</h2>
            <ul class="mt-3 grid gap-3 md:grid-cols-2">
                @foreach ($orders as $o)
                    <li class="card flex flex-wrap items-center justify-between gap-3 p-4">
                        <div><p class="font-medium">{{ $o->label }}</p><p class="text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($o->paid_at ?? $o->created_at)->tz(auth()->user()->display_timezone)->format('d M Y') }}</p></div>
                        <a href="{{ route('account.reviews.create', $o->ref) }}" class="btn-primary">Write a review</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="mine-h">
        <h2 id="mine-h" class="text-lg">Your reviews</h2>
        @forelse ($reviews as $r)
            <article class="card mt-3 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3"><x-stars :rating="$r->rating" /><span class="text-sm text-ink-600">{{ $r->subject }}</span></div>
                    <span @class(['badge-gold' => $r->status === 'pending', 'badge-success' => $r->status === 'approved', 'badge-danger' => $r->status === 'rejected', 'badge-ink' => $r->status === 'hidden'])>{{ Reviews::STATUSES[$r->status] }}</span>
                </div>
                @if ($r->title)<p class="mt-3 font-semibold">{{ $r->title }}</p>@endif
                <p class="mt-1 whitespace-pre-line text-sm leading-6">{{ $r->body }}</p>
                @if ($r->status === 'rejected' && $r->reject_reason)<p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-danger-700">Why it was not published: {{ $r->reject_reason }}</p>@endif
                @if ($r->reply)<p class="mt-3 rounded-xl bg-ivory-100 p-3 text-sm"><strong>Reply from our team:</strong> {{ $r->reply }}</p>@endif
                <div class="mt-4 flex flex-wrap gap-2">
                    @if (in_array($r->status, ['pending', 'rejected'], true))<a href="{{ route('account.reviews.edit', $r->ref) }}" class="btn-outline">Edit</a>@endif
                    <form method="POST" action="{{ route('account.reviews.destroy', $r->ref) }}" onsubmit="return confirm('Delete this review?')">@csrf @method('DELETE')<button class="btn-ghost text-danger-700">Delete</button></form>
                </div>
            </article>
        @empty
            <p class="card mt-3 p-6 text-sm text-ink-600">You have not written a review yet. After a paid consultation, report, puja or order, you can share your experience here.</p>
        @endforelse
    </section>
</x-layouts.account>
