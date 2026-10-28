<x-layouts.admin :title="$p->display_name">
    @php $M = fn ($v) => \App\Support\Money::format((int) $v); @endphp
    <p class="text-sm text-ink-600">{{ $user->email }} · {{ \App\Domain\Practitioners\Practitioners::TIERS[$p->tier] }} · profile <strong>{{ $p->status }}</strong> · current share {{ $share !== null ? ($share / 100).'%' : 'none approved' }} · payout to {{ $payout }}</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card min-w-0 p-5" aria-labelledby="pf-h">
            <h2 id="pf-h" class="text-lg">Public profile</h2>
            @if ($p->photo_path)<img src="{{ Storage::disk('uploads')->url($p->photo_path) }}" alt="" class="mt-3 size-24 rounded-full object-cover">@endif
            <dl class="mt-3 space-y-2 text-sm">
                <div><dt class="font-semibold">Bio</dt><dd class="whitespace-pre-line">{{ $p->bio ?: '—' }}</dd></div>
                <div><dt class="font-semibold">Languages</dt><dd>{{ $p->languages ?: '—' }}</dd></div>
                <div><dt class="font-semibold">Specialties</dt><dd>{{ $p->specialties ?: '—' }}</dd></div>
                <div><dt class="font-semibold">Experience</dt><dd>{{ $p->experience_years !== null ? $p->experience_years.' years' : '—' }}</dd></div>
            </dl>
            @can('practitioners.review')
                <form method="POST" action="{{ route('admin.practitioners.review', $p->ref) }}" class="mt-5 grid gap-3 border-t border-ink-200/60 pt-4 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <x-select name="tier" label="Tier" :options="\App\Domain\Practitioners\Practitioners::TIERS" :value="$p->tier" />
                    <x-select name="decision" label="Decision" :options="['approve' => 'Approve', 'return' => 'Return for changes', 'suspend' => 'Suspend']" />
                    <button class="btn-primary">Save decision</button>
                    @error('decision')<p class="field-error sm:col-span-3" role="alert">{{ $message }}</p>@enderror
                </form>
            @endcan
        </section>

        <section class="card min-w-0 p-5" aria-labelledby="cm-h">
            <h2 id="cm-h" class="text-lg">Commission</h2>
            <p class="text-sm text-ink-600">The astrologer's share of the consultation price (after any refund). A second person must approve.</p>
            <ul class="mt-3 divide-y divide-ink-200/60 text-sm">
                @forelse ($rules as $r)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>{{ $r->share_bps / 100 }}% from {{ $r->effective_from }} · <strong>{{ $r->status }}</strong>@if ($r->note) · {{ $r->note }}@endif</span>
                        @if ($r->status === 'proposed')
                            @can('commission.approve')
                                <form method="POST" action="{{ route('admin.practitioners.commission.decide', $r->id) }}" class="flex gap-2">@csrf
                                    <button name="decision" value="approve" class="btn-primary">Approve</button><button name="decision" value="reject" class="btn-outline">Reject</button></form>
                            @endcan
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-ink-600">No commission set.</li>
                @endforelse
            </ul>
            @can('commission.propose')
                <form method="POST" action="{{ route('admin.practitioners.commission', $p->ref) }}" class="mt-4 grid gap-3 border-t border-ink-200/60 pt-4 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <x-field name="share_percent" label="Share %" type="number" />
                    <x-field name="effective_from" label="From" type="date" :value="now('Asia/Kolkata')->toDateString()" />
                    <x-field name="note" label="Note (optional)" />
                    <button class="btn-outline sm:col-span-3">Propose commission</button>
                </form>
            @endcan
        </section>

        <section class="card min-w-0 p-5 xl:col-span-2" aria-labelledby="po-h">
            <h2 id="po-h" class="text-lg">Earnings and payouts</h2>
            <p class="text-sm">Pending (refund window): {{ $M($balances['pending']) }} · Available: <strong>{{ $M($balances['available']) }}</strong> · In payout: {{ $M($balances['in_payout']) }} · Paid: {{ $M($balances['paid']) }}</p>
            @error('payout')<p class="field-error mt-2" role="alert">{{ $message }}</p>@enderror
            @error('utr')<p class="field-error mt-2" role="alert">{{ $message }}</p>@enderror
            @can('payouts.propose')
                <form method="POST" action="{{ route('admin.practitioners.payout', $p->ref) }}" class="mt-3">@csrf<button class="btn-outline" @disabled($balances['available'] <= 0)>Propose payout of available earnings</button></form>
            @endcan
            <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="Payouts table">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-ink-600"><tr><th class="p-2">Payout</th><th class="p-2">Amount</th><th class="p-2">Status</th><th class="p-2">To</th><th class="p-2">Action</th></tr></thead>
                    <tbody class="divide-y divide-ink-200/60">
                        @forelse ($payoutRows as $po)
                            <tr>
                                <td class="p-2 font-mono text-xs">{{ $po->ref }}</td>
                                <td class="p-2">{{ $M($po->amount_minor) }}</td>
                                <td class="p-2">{{ $po->status }}@if ($po->utr) · UTR {{ $po->utr }}@endif</td>
                                <td class="p-2">{{ \App\Domain\Practitioners\Practitioners::maskedPayout(\App\Domain\Practitioners\Payouts::method($po)) }}</td>
                                <td class="p-2">
                                    @if ($po->status === 'proposed')
                                        @can('payouts.approve')<form method="POST" action="{{ route('admin.payouts.action', $po->ref) }}" class="inline">@csrf<button name="action" value="approve" class="btn-primary">Approve</button></form>@endcan
                                    @elseif ($po->status === 'approved')
                                        @can('payouts.propose')
                                            <form method="POST" action="{{ route('admin.payouts.action', $po->ref) }}" class="flex flex-wrap items-end gap-2">@csrf
                                                <input type="hidden" name="action" value="paid"><label class="sr-only" for="utr-{{ $po->id }}">UTR</label>
                                                <input id="utr-{{ $po->id }}" name="utr" class="input w-40" placeholder="Bank UTR"><button class="btn-primary">Mark paid</button></form>
                                        @endcan
                                    @endif
                                    @if (in_array($po->status, ['proposed', 'approved'], true))
                                        @can('payouts.propose')<form method="POST" action="{{ route('admin.payouts.action', $po->ref) }}" class="mt-1 inline">@csrf<button name="action" value="cancel" class="btn-ghost">Cancel</button></form>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-3 text-ink-600">No payouts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-ink-600">Money is sent from your bank account (NEFT/IMPS/UPI) outside this system; then record the UTR here. Automatic bank payouts (RazorpayX) are not connected.</p>
        </section>
    </div>
</x-layouts.admin>
