<x-layouts.admin title="Practitioners">
    <p class="max-w-3xl text-sm text-ink-600">Team astrologers sign in with their own staff account (password + authenticator). They appear for booking only after their profile is approved and a commission is approved by a second person. Team-consultation services can be published only when at least one astrologer of that tier is bookable.</p>
    @if (session('invite_link'))
        <div class="card mt-4 border-gold-500 p-4 text-sm" role="status">One-time invitation link (valid 48 hours). Send it to the astrologer privately:
            <code class="mt-2 block break-all rounded-lg bg-white p-2 text-xs">{{ session('invite_link') }}</code></div>
    @endif
    <p class="mt-3 text-sm">Waiting for a decision: {{ $pendingCommission }} commission {{ \Illuminate\Support\Str::plural('proposal', $pendingCommission) }}, {{ $pendingPayouts }} {{ \Illuminate\Support\Str::plural('payout', $pendingPayouts) }}.</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="card min-w-0 overflow-x-auto p-0" aria-labelledby="pr-h" tabindex="0">
            <h2 id="pr-h" class="sr-only">Astrologers</h2>
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-ink-600"><tr><th class="p-3">Name</th><th class="p-3">Tier</th><th class="p-3">Profile</th><th class="p-3">Share</th><th class="p-3">Bookable</th></tr></thead>
                <tbody class="divide-y divide-ink-200/60">
                    @forelse ($rows as $p)
                        <tr>
                            <td class="p-3"><a href="{{ route('admin.practitioners.show', $p->ref) }}">{{ $p->display_name }}</a><br><span class="text-xs text-ink-600">{{ $p->email }}</span></td>
                            <td class="p-3">{{ \App\Domain\Practitioners\Practitioners::TIERS[$p->tier] }}</td>
                            <td class="p-3"><span class="{{ $p->status === 'approved' ? 'badge-success' : ($p->status === 'submitted' ? 'badge-gold' : 'badge-ink') }}">{{ ucfirst($p->status) }}</span></td>
                            <td class="p-3">{{ $p->share !== null ? ($p->share / 100).'%' : '—' }}</td>
                            <td class="p-3">{{ $p->bookable ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-ink-600">No astrologers yet. Invite one with the form.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <aside class="space-y-6">
            @can('practitioners.manage')
                <form method="POST" action="{{ route('admin.practitioners.invite') }}" class="card space-y-4 p-5">
                    @csrf
                    <h2 class="text-base">Invite an astrologer</h2>
                    <x-field name="name" label="Full name" />
                    <x-field name="email" label="Email" type="email" />
                    <button class="btn-primary w-full">Create invitation</button>
                    <p class="text-xs text-ink-600">Only invite people you have a signed agreement with.</p>
                </form>
            @endcan
            @if ($invited->isNotEmpty())
                <div class="card p-5 text-sm"><h2 class="text-base">Invitations not yet accepted</h2>
                    <ul class="mt-2 space-y-1">@foreach ($invited as $i)<li>{{ $i->name }} · {{ $i->email }}</li>@endforeach</ul></div>
            @endif
        </aside>
    </div>
</x-layouts.admin>
