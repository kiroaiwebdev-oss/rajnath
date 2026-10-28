<x-layouts.account :title="$t->subject">
    <p class="text-sm text-ink-600">{{ \App\Domain\Support\Tickets::CATEGORIES[$t->category] ?? '' }} · {{ \App\Domain\Support\Tickets::STATUSES[$t->status] }}</p>
    <ol class="mt-5 max-w-3xl space-y-3">
        @foreach ($messages as $m)
            <li @class(['card p-4', 'border-gold-200 bg-gold-50' => $m->kind === 'staff'])>
                <p class="text-xs text-ink-600">{{ $m->kind === 'staff' ? 'Support team' : 'You' }} · {{ \Illuminate\Support\Carbon::parse($m->created_at)->tz('Asia/Kolkata')->format('d M Y, g:i A') }}</p>
                <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
            </li>
        @endforeach
    </ol>
    @if ($t->status !== 'closed')
        <form method="POST" action="{{ route('account.support.reply', $t->ref) }}" class="card mt-5 max-w-3xl space-y-3 p-5">
            @csrf <label for="f-body" class="field-label">Reply</label><textarea id="f-body" name="body" rows="4" class="input" required></textarea>
            <div class="flex flex-wrap justify-between gap-3"><button class="btn-primary">Send reply</button></div>
        </form>
        <form method="POST" action="{{ route('account.support.close', $t->ref) }}" class="mt-3">@csrf<button class="btn-ghost px-3">Close this request</button></form>
    @else
        <p class="mt-5 text-sm">This request is closed. <a href="{{ route('account.support.index') }}">Open a new one</a> if you need more help.</p>
    @endif
</x-layouts.account>
