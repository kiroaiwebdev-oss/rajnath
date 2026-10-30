@php use App\Domain\Reviews\Reviews; @endphp
<x-layouts.admin title="Reviews">
    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="card p-4"><p class="text-sm text-ink-600">Average rating</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ $summary['average'] ?? '—' }}<span class="text-base text-ink-600"> / 5</span></p></div>
        @foreach (Reviews::STATUSES as $k => $l)
            <a href="{{ route('admin.reviews.index', ['status' => $k]) }}" @class(['card p-4 no-underline', 'ring-2 ring-gold-500' => $status === $k])><p class="text-sm text-ink-600">{{ $l }}</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ (int) ($counts[$k] ?? 0) }}</p></a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_24rem]">
        <section class="min-w-0 space-y-4" aria-label="Reviews">
            <p class="text-sm"><a href="{{ route('admin.reviews.index', ['status' => 'all']) }}">Show all</a></p>
            @forelse ($rows as $r)
                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="stars" aria-label="{{ $r->rating }} out of 5">@for ($i = 1; $i <= 5; $i++)<svg aria-hidden="true" class="size-4 {{ $i <= $r->rating ? '' : 'text-ink-200' }}" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9z"/></svg>@endfor</p>
                            <h2 class="mt-1 text-base">{{ $r->title ?: \Illuminate\Support\Str::limit($r->body, 60) }}</h2>
                            <p class="text-xs text-ink-600">{{ $r->display_name }}{{ $r->city ? ', '.$r->city : '' }} · {{ $r->subject ?? 'General' }} · {{ \Illuminate\Support\Carbon::parse($r->created_at)->tz('Asia/Kolkata')->format('j M Y') }}
                                @if ($r->source === 'staff') · added by staff ({{ $r->permission_note }}) @elseif ($r->account_name) · account: {{ $r->account_name }} @endif
                                @if ($r->order_ref) · order @can('finance.view')<a href="{{ route('admin.finance.order', $r->order_ref) }}">{{ substr($r->order_ref, -8) }}</a>@else{{ substr($r->order_ref, -8) }}@endcan @endif</p>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <span @class(['badge-gold' => $r->status === 'pending', 'badge-success' => $r->status === 'approved', 'badge-danger' => $r->status === 'rejected', 'badge-ink' => $r->status === 'hidden'])>{{ Reviews::STATUSES[$r->status] }}</span>
                            @if ($r->verified)<span class="badge-ink">Verified purchase</span>@endif
                            @if ($r->featured)<span class="badge-gold">Home page</span>@endif
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $r->body }}</p>
                    @if ($r->reject_reason)<p class="mt-2 text-sm text-danger-700">Reason given: {{ $r->reject_reason }}</p>@endif

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-ink-200/60 pt-4">
                        @if ($r->status !== 'approved')
                            <form method="POST" action="{{ route('admin.reviews.moderate', $r->ref) }}">@csrf<input type="hidden" name="action" value="approve"><button class="btn-primary">Publish</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.reviews.moderate', $r->ref) }}">@csrf<input type="hidden" name="action" value="hide"><button class="btn-outline">Hide</button></form>
                            @can('content.publish')
                                <form method="POST" action="{{ route('admin.reviews.moderate', $r->ref) }}">@csrf<input type="hidden" name="action" value="{{ $r->featured ? 'unfeature' : 'feature' }}"><button class="btn-outline">{{ $r->featured ? 'Remove from home page' : 'Show on home page' }}</button></form>
                            @endcan
                        @endif
                        @if ($r->status !== 'rejected')
                            <details class="w-full sm:w-auto">
                                <summary class="btn-ghost cursor-pointer text-danger-700">Don't publish…</summary>
                                <form method="POST" action="{{ route('admin.reviews.moderate', $r->ref) }}" class="mt-2 flex flex-wrap gap-2">@csrf<input type="hidden" name="action" value="reject">
                                    <input name="reason" class="input max-w-md" required maxlength="300" aria-label="Reason (the customer sees this)" placeholder="Reason (the customer sees this)">
                                    <button class="btn-outline">Confirm</button></form>
                            </details>
                        @endif
                    </div>
                    <details class="mt-3" @if ($r->reply) open @endif>
                        <summary class="cursor-pointer text-sm font-medium">{{ $r->reply ? 'Public reply' : 'Reply publicly' }}</summary>
                        <form method="POST" action="{{ route('admin.reviews.moderate', $r->ref) }}" class="mt-2 space-y-2">@csrf<input type="hidden" name="action" value="reply">
                            <textarea name="reply" rows="3" maxlength="1500" class="input" aria-label="Reply">{{ $r->reply }}</textarea>
                            <button class="btn-outline">Save reply</button></form>
                    </details>
                </article>
            @empty
                <p class="card p-8 text-center text-ink-600">Nothing here.</p>
            @endforelse
            {{ $rows->links() }}
        </section>

        @can('content.publish')
        <form method="POST" action="{{ route('admin.reviews.store') }}" class="card h-fit space-y-4 p-5">
            @csrf
            <h2 class="text-lg">Add a review received offline</h2>
            <p class="text-xs text-ink-600">Only with the person's permission to publish. It is not marked "verified purchase".</p>
            <x-select name="rating" label="Rating" :options="[5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star']" value="5" />
            <x-select name="offering_id" label="Service (optional)" :options="['' => '—'] + $offerings" />
            <x-field name="title" label="Title (optional)" />
            <div><label for="f-body" class="field-label">Review</label><textarea id="f-body" name="body" rows="5" class="input" required minlength="20">{{ old('body') }}</textarea>@error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
            <x-field name="display_name" label="Name to show" required placeholder="Ravi K." />
            <x-field name="city" label="City (optional)" />
            <x-field name="permission_note" label="How they gave permission" required placeholder="WhatsApp message, 2 Nov 2026" />
            <button class="btn-primary w-full">Add</button>
        </form>
        @endcan
    </div>
</x-layouts.admin>
