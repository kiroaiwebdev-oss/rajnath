<x-layouts.admin :title="'Privacy request '.substr($c->ref, -8)">
    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <section class="card space-y-4 p-5 text-sm">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="text-ink-600">Request</dt><dd class="font-medium">{{ $c->type === 'delete' ? 'Delete account' : 'Data export' }} · {{ $c->status }}</dd></div>
                <div><dt class="text-ink-600">Case</dt><dd class="font-medium">{{ str_replace('_', ' ', $c->case_status) }}@if ($staff) · {{ $staff }}@endif</dd></div>
                <div><dt class="text-ink-600">Customer</dt><dd class="font-medium">{{ $u ? $u->name : 'Deleted person' }}@if ($u)<span class="block text-xs font-normal">{{ $u->email }} {{ $u->phone_e164 }}</span>@endif</dd></div>
                <div><dt class="text-ink-600">Received</dt><dd>{{ \Illuminate\Support\Carbon::parse($c->created_at)->tz('Asia/Kolkata')->format('d M Y, g:i A') }}</dd></div>
            </dl>
            @if ($u && $u->privacy_hold_reason)<p class="rounded-xl border border-red-200 bg-red-50 p-3"><strong>Legal hold:</strong> {{ $u->privacy_hold_reason }}</p>@endif
            @if ($u && $u->status !== 'anonymized')
                <h2 class="text-base">What blocks deletion now</h2>
                @if ($blockers)<ul class="list-disc space-y-1 pl-5">@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul>@else<p class="text-success-700">Nothing — the account can be deleted.</p>@endif
            @endif
            <h2 class="text-base">Notes</h2>
            <ul class="space-y-2">@forelse ($notes as $n)<li class="rounded-xl bg-ivory-100 p-3"><span class="text-xs text-ink-600">{{ $n['by'] }} · {{ \Illuminate\Support\Carbon::parse($n['at'])->tz('Asia/Kolkata')->format('d M, g:i A') }}</span><br>{{ $n['text'] }}</li>@empty<li class="text-ink-600">No notes yet.</li>@endforelse</ul>
            <form method="POST" action="{{ route('admin.privacy.update', $c->ref) }}" class="space-y-2">@csrf
                <label for="f-note" class="field-label">Note (what was agreed with the customer, steps taken)</label>
                <textarea id="f-note" name="note" rows="3" maxlength="1000" class="input"></textarea>@error('note')<p class="field-error">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2">
                    <button name="action" value="note" class="btn-outline">Add note</button>
                    @if ($c->case_status !== 'closed')<button name="action" value="assign" class="btn-outline">Assign to me</button><button name="action" value="close" class="btn-outline">Close with note</button>@elseif ($u)<button name="action" value="reopen" class="btn-outline">Reopen</button>@endif
                </div>
                @error('action')<p class="field-error">{{ $message }}</p>@enderror
            </form>
        </section>
        <aside class="space-y-4">
            @if ($u && $u->status !== 'anonymized')
                <div class="card space-y-2 p-5 text-sm"><h2 class="text-base">Data export</h2><p>Download the customer's data (JSON) to send it to them securely.</p><a href="{{ route('admin.privacy.export', $c->ref) }}" class="btn-outline w-full">Download export</a></div>
                @can('privacy.approve')
                    <form method="POST" action="{{ route('admin.privacy.delete', $c->ref) }}" class="card space-y-3 p-5 text-sm">@csrf
                        <h2 class="text-base">Run deletion</h2>
                        <p>Anonymises the account the same way as the customer's own deletion. Blockers are checked again.</p>
                        <x-field name="confirm" label="Type DELETE to confirm" autocomplete="off" />
                        <button class="btn-primary w-full" @disabled($blockers)>Delete this account</button>
                    </form>
                    <form method="POST" action="{{ route('admin.privacy.hold', $c->ref) }}" class="card space-y-3 p-5 text-sm">@csrf
                        <h2 class="text-base">Legal hold</h2>
                        <x-field name="reason" label="Reason (empty = clear the hold)" :value="$u->privacy_hold_reason" hint="e.g. open dispute, tax enquiry. Blocks self-service deletion." />
                        <button class="btn-outline w-full">{{ $u->privacy_hold_reason ? 'Update or clear hold' : 'Set hold' }}</button>
                    </form>
                @endcan
            @endif
        </aside>
    </div>
</x-layouts.admin>
