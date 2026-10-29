<x-layouts.admin title="Payments setup">
    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.payments.save') }}" class="card space-y-5 p-5 sm:p-6">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg">Razorpay</h2>
                @if (! $configured)<span class="badge-danger">Not configured</span>@elseif ($mode === 'live' && ! $live)<span class="badge-danger">Live key saved, live payments off</span>@else<span class="badge-success">{{ ucfirst($mode) }} mode</span>@endif</div>
            <x-field name="key_id" label="Key ID" :value="$keyId" hint="Razorpay Dashboard › Settings › API Keys. Start with test keys (rzp_test_…)." />
            <x-field name="key_secret" label="Key secret" type="password" autocomplete="new-password" :hint="($secretMasked ? 'Saved: '.$secretMasked.'. ' : 'Not saved. ').'Leave blank to keep.'" />
            <x-field name="webhook_secret" label="Webhook secret" type="password" autocomplete="new-password" :hint="($webhookMasked ? 'Saved: '.$webhookMasked.'. ' : 'Not saved. ').'Webhook URL: '.route('webhooks.razorpay').' — events: payment.captured, payment.failed, order.paid, refund.processed'" />
            <label class="flex items-start gap-3 rounded-xl border border-gold-200 bg-gold-50 p-3 text-sm"><input type="checkbox" name="live_enabled" value="1" class="mt-1 size-4 accent-ink-900" @checked($live)>
                <span>Allow <strong>live</strong> payments. Only tick this after testing with test keys and agreeing limits with your accountant.</span></label>
            <button class="btn-primary">Save</button>
        </form>
        <form method="POST" action="{{ route('admin.payments.tax') }}" class="card space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg">GST tax invoices</h2><span @class(['badge-success' => $tax['enabled'], 'badge-ink' => ! $tax['enabled']])>{{ $tax['enabled'] ? 'On' : 'Off' }}</span></div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="gst_enabled" value="1" class="mt-1 size-4 accent-ink-900" @checked(old('gst_enabled', $tax['enabled']))>
                <span>Issue GST tax invoices and credit notes. Prices stay as they are (GST included); the tax part is shown on each invoice and kept apart in the accounts.</span></label>
            <x-field name="gstin" label="GSTIN" :value="$tax['gstin']" maxlength="15" class="input uppercase" />
            <x-field name="legal_name" label="Legal name (as registered)" :value="$tax['legal_name']" />
            <x-field name="address" label="Registered address" :value="$tax['address']" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="service_rate" label="GST on services (%)" :value="$tax['service_rate_bps'] / 100" inputmode="decimal" />
                <x-field name="service_sac" label="SAC for services" :value="$tax['service_sac']" />
                <x-field name="product_rate" label="GST on gemstones (%)" :value="$tax['product_rate_bps'] / 100" inputmode="decimal" />
                <x-field name="product_hsn" label="HSN for gemstones" :value="$tax['product_hsn']" />
                <x-field name="invoice_prefix" label="Invoice prefix" :value="$tax['prefix']" maxlength="3" hint="e.g. ARJ → ARJ/26-27/000001" />
            </div>
            <p class="text-xs text-ink-600">Check the rates, SAC and HSN codes with your accountant. Services are billed in your state (CGST + SGST); gems use the delivery state (IGST outside your state). Wallet recharges are advances and are not invoiced.</p>
            <button class="btn-primary">Save GST settings</button>
        </form>
        <form method="POST" action="{{ route('admin.payments.payouts') }}" class="card space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg">RazorpayX payouts</h2>@if ($rzx['account'] && $configured)<span class="badge-success">Ready</span>@else<span class="badge-ink">Not set up</span>@endif</div>
            <x-field name="account_number" label="RazorpayX account number" :value="$rzx['account']" hint="RazorpayX Dashboard › My Account. Uses the Razorpay API keys above." />
            <x-select name="mode" label="Bank transfer mode" :value="$rzx['mode']" :options="['IMPS' => 'IMPS (instant)', 'NEFT' => 'NEFT', 'RTGS' => 'RTGS (₹2 lakh and above)']" />
            <x-field name="webhook_secret" label="RazorpayX webhook secret" type="password" autocomplete="new-password" :hint="($rzx['webhook'] ? 'Saved: '.$rzx['webhook'].'. ' : 'Not saved. ').'Webhook URL: '.route('webhooks.razorpayx').' — events: payout.processed, payout.failed, payout.reversed, payout.rejected'" />
            <p class="text-xs text-ink-600">Approved payouts get a “Send via RazorpayX” button. UPI details are paid by UPI; bank details by the mode above.</p>
            <button class="btn-primary">Save payouts</button>
        </form>
        <form method="POST" action="{{ route('admin.payments.courier') }}" class="card space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg">Courier (Shiprocket)</h2>@if ($courier['email'] && $courier['password'] && $courier['pickup'])<span class="badge-success">Ready</span>@else<span class="badge-ink">Not set up</span>@endif</div>
            <x-field name="email" label="API user email" :value="$courier['email']" hint="Shiprocket › Settings › API › Create an API user (a different email from your login)." />
            <x-field name="password" label="API user password" type="password" autocomplete="new-password" :hint="($courier['password'] ? 'Saved: '.$courier['password'].'. ' : 'Not saved. ').'Leave blank to keep.'" />
            <x-field name="pickup_location" label="Pickup location name" :value="$courier['pickup']" hint="Exactly as named in Shiprocket › Settings › Pickup addresses (e.g. Primary)." />
            <x-field name="webhook_token" label="Tracking webhook token" type="password" autocomplete="new-password" :hint="($courier['webhook'] ? 'Saved: '.$courier['webhook'].'. ' : 'Not saved. ').'At least 16 characters. In Shiprocket › Settings › Webhooks set URL '.route('webhooks.courier').' and this token.'" />
            <button class="btn-primary">Save courier</button>
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
