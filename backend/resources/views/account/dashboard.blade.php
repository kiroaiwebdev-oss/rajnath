<x-layouts.account :title="'Namaste, '.auth()->user()->name">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2" aria-labelledby="req-h">
            <div class="flex items-center justify-between gap-3">
                <h2 id="req-h" class="text-lg">My requests</h2>
                <a href="{{ route('services.index') }}" class="btn-outline">New request</a>
            </div>
            @forelse ($enquiries as $e)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-ink-200/70 p-3 text-sm">
                    <div><p class="font-medium">{{ $e->offering?->t()?->title ?? 'General enquiry' }}</p>
                        <p class="text-xs text-ink-600">Sent {{ $e->created_at->tz(auth()->user()->display_timezone)->format('d M Y, g:i A') }} · Ref {{ substr($e->ref, -8) }}</p></div>
                    <span @class(['badge-gold' => $e->status === 'new', 'badge-success' => $e->status === 'contacted', 'badge-ink' => ! in_array($e->status, ['new', 'contacted'])])>{{ $e->status === 'new' ? 'Received' : \App\Models\Enquiry::STATUSES[$e->status] }}</span>
                </div>
            @empty
                <p class="mt-4 rounded-xl bg-ivory-100 p-6 text-center text-sm text-ink-600">No requests yet. Choose a service to send your first request.</p>
            @endforelse
            <p class="mt-4 text-xs text-ink-600">Online bookings and reports will appear here as those features open.</p>
        </section>
        <div class="space-y-6">
        <section class="card p-5" aria-labelledby="wl-h">
            <div class="flex items-center justify-between gap-3">
                <h2 id="wl-h" class="text-lg">Wallet</h2>
                <a href="{{ route('account.wallet') }}" class="btn-ghost px-3">Open</a>
            </div>
            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ \App\Support\Money::format($wallet['available']) }}</p>
            <p class="text-xs text-ink-600">Available balance</p>
        </section>
        <section class="card p-5" aria-labelledby="bp-h">
            <div class="flex items-center justify-between gap-3">
                <h2 id="bp-h" class="text-lg">Birth profiles</h2>
                <a href="{{ route('account.birth-profiles.create') }}" class="btn-ghost px-3">Add</a>
            </div>
            @forelse ($profiles as $p)
                <a href="{{ route('account.birth-profiles.edit', $p) }}" class="mt-3 block rounded-xl border border-ink-200/70 p-3 text-sm no-underline hover:border-ink-400">
                    <span class="font-medium">{{ $p->name }}</span> @if ($p->is_primary)<span class="badge-gold ml-1">Primary</span>@endif
                    <span class="block text-xs text-ink-600">{{ \App\Models\BirthProfile::LABELS[$p->label] }} · {{ $p->birth_date->format('d M Y') }} · {{ \Illuminate\Support\Str::before($p->place_label, ',') }}</span>
                </a>
            @empty
                <p class="mt-3 text-sm text-ink-600">Save birth details once and reuse them for consultations and reports.</p>
                <a href="{{ route('account.birth-profiles.create') }}" class="btn-primary mt-4 w-full">Add birth details</a>
            @endforelse
        </section>
    </div>
    </div>
</x-layouts.account>
