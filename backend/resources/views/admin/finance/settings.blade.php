<x-layouts.admin title="Payments setup">
    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.payments.save') }}" class="card space-y-5 p-5 sm:p-6">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg">Razorpay</h2>
                @if (! $configured)<span class="badge-danger">Not configured</span>@elseif ($mode === 'live' && ! $live)<span class="badge-danger">Live key saved, live payments off</span>@else<span class="badge-success">{{ ucfirst($mode) }} mode</span>@endif</div>
            <x-field name="key_id" label="Key ID" :value="$keyId" hint="Razorpay Dashboard → Settings → API Keys. Start with test keys (rzp_test_…)." />
            <x-field name="key_secret" label="Key secret" type="password" autocomplete="new-password" :hint="($secretMasked ? 'Saved: '.$secretMasked.'. ' : 'Not saved. ').'Leave blank to keep.'" />
            <x-field name="webhook_secret" label="Webhook secret" type="password" autocomplete="new-password" :hint="($webhookMasked ? 'Saved: '.$webhookMasked.'. ' : 'Not saved. ').'Webhook URL: '.route('webhooks.razorpay').' — events: payment.captured, payment.failed, order.paid, refund.processed'" />
            <label class="flex items-start gap-3 rounded-xl border border-gold-200 bg-gold-50 p-3 text-sm"><input type="checkbox" name="live_enabled" value="1" class="mt-1 size-4 accent-ink-900" @checked($live)>
                <span>Allow <strong>live</strong> payments. Only tick this after testing with test keys and agreeing limits with your accountant.</span></label>
            <button class="btn-primary">Save</button>
        </form>
        <section class="card p-5 sm:p-6" aria-labelledby="pk-h">
            <h2 id="pk-h" class="text-lg">Wallet recharge packages</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($packages->push((object) ['id' => null, 'amount_minor' => null, 'bonus_minor' => 0, 'active' => true]) as $p)
                    <li><form method="POST" action="{{ route('admin.payments.package') }}" class="grid grid-cols-[1fr_1fr_auto_auto] items-end gap-2">
                        @csrf <input type="hidden" name="id" value="{{ $p->id }}">
                        <x-field name="amount" label="Amount (₹)" :value="$p->amount_minor ? $p->amount_minor / 100 : null" :id="'pa-'.($p->id ?? 'new')" />
                        <x-field name="bonus" label="Bonus (₹)" :value="$p->bonus_minor ? $p->bonus_minor / 100 : null" :id="'pb-'.($p->id ?? 'new')" />
                        <label class="flex min-h-11 items-center gap-1 text-sm"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked($p->active)> On</label>
                        <button class="btn-outline px-3">{{ $p->id ? 'Save' : 'Add' }}</button>
                    </form></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.admin>
