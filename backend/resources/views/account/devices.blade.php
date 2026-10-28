<x-layouts.account title="Devices">
    <section class="card p-5 sm:p-6" aria-labelledby="web-h">
        <h2 id="web-h" class="text-lg">Browsers</h2>
        <ul class="mt-4 divide-y divide-ink-200/60">
            @foreach ($sessions as $s)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium">{{ \Illuminate\Support\Str::limit($s->user_agent ?: 'Unknown browser', 70) }}</p>
                        <p class="text-xs text-ink-600">Last active {{ \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity)->tz(auth()->user()->display_timezone)->format('d M Y, g:i A') }} · IP {{ $s->ip_address }}</p>
                    </div>
                    @if ($s->id === $currentId)
                        <span class="badge-success">This browser</span>
                    @else
                        <form method="POST" action="{{ route('account.devices.session.revoke', $s->id) }}">@csrf @method('DELETE')<button class="btn-outline">Sign out</button></form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    <section class="card mt-6 p-5 sm:p-6" aria-labelledby="app-h">
        <h2 id="app-h" class="text-lg">Mobile app</h2>
        @forelse ($tokens as $t)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200/70 p-3 text-sm">
                <div><p class="font-medium">{{ $t->name }} <span class="text-ink-600">({{ $t->platform ?? 'app' }})</span></p>
                    <p class="text-xs text-ink-600">Signed in {{ $t->created_at->tz(auth()->user()->display_timezone)->format('d M Y') }}{{ $t->last_used_at ? ' · last used '.$t->last_used_at->diffForHumans() : '' }}</p></div>
                <form method="POST" action="{{ route('account.devices.token.revoke', $t->id) }}">@csrf @method('DELETE')<button class="btn-outline">Sign out</button></form>
            </div>
        @empty
            <p class="mt-3 text-sm text-ink-600">You are not signed in on the app.</p>
        @endforelse
    </section>
    <form method="POST" action="{{ route('account.devices.others') }}" class="mt-6">
        @csrf
        <button class="btn-danger">Sign out of all other browsers and devices</button>
    </form>
</x-layouts.account>
