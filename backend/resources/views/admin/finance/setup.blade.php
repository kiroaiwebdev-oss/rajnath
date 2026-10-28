@php use App\Support\Money; $owner = auth()->user()->hasRole('owner'); @endphp
<x-layouts.admin title="Razorpay setup">
    @include('admin.finance._nav')
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="rk-h">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="rk-h" class="text-lg">API keys and webhook</h2>
                @if ($enabled && ! $problem)<span class="badge-success">Taking payments ({{ $mode }})</span>
                @elseif (! $enabled)<span class="badge-ink">Online payment switched off</span>
                @else<span class="badge-danger">Not ready</span>@endif
            </div>
            @if ($problem)<p class="mt-2 text-sm text-danger-700">{{ $problem }}</p>@endif
            <form method="POST" action="{{ route('admin.payments-setup.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <x-field name="key_id" label="Key ID" :value="$keyId" hint="Razorpay Dashboard → Account & Settings → API keys. Use a rzp_test_ key until the owner switches to live." />
                <x-field name="key_secret" label="Key secret" type="password" autocomplete="new-password" :hint="($masked['payments.razorpay.key_secret'] ? 'Saved: '.$masked['payments.razorpay.key_secret'].'. ' : 'Not saved yet. ').'Leave blank to keep it.'" />
                <x-field name="webhook_secret" label="Webhook secret" type="password" autocomplete="new-password" :hint="($masked['payments.razorpay.webhook_secret'] ? 'Saved: '.$masked['payments.razorpay.webhook_secret'].'. ' : 'Not saved yet. ').'Leave blank to keep it.'" />
                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" class="size-4 accent-ink-900" @checked($enabled)> Allow customers to pay online</label>
                <button class="btn-primary">Save</button>
            </form>
            <form method="POST" action="{{ route('admin.payments-setup.test') }}" class="mt-5 border-t border-ink-200/60 pt-5">@csrf<button class="btn-outline">Test connection</button></form>

            <div class="mt-6 rounded-xl bg-ivory-100 p-4 text-sm">
                <p class="font-medium">Webhook to add in Razorpay (Account & Settings → Webhooks)</p>
                <p class="mt-1 break-all font-mono text-xs">{{ $webhookUrl }}</p>
                <p class="mt-2 text-xs text-ink-600">Events: payment.authorized, payment.captured, payment.failed, order.paid, refund.processed, refund.failed, payment.dispute.created, payment.dispute.won, payment.dispute.lost, payment.dispute.closed, payment.dispute.under_review.</p>
                <p class="mt-2 text-xs text-ink-600">Last webhook received: {{ $lastWebhook ? $lastWebhook->type.' at '.\Illuminate\Support\Carbon::parse($lastWebhook->received_at)->tz('Asia/Kolkata')->format('d M Y, H:i') : 'none yet' }}</p>
            </div>
        </section>

        <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="lm-h">
            <h2 id="lm-h" class="text-lg">Test or live mode</h2>
            <p class="mt-2 text-sm text-ink-600">Currently <strong>{{ $mode }}</strong>@if ($mode === 'live') with a limit of <strong>{{ Money::format($liveLimit) }}</strong> per payment @endif. Test mode never moves real money. Only the owner can switch to live, and a per-payment limit is required.</p>
            @if ($owner)
                <form method="POST" action="{{ route('admin.payments-setup.mode') }}" class="mt-5 space-y-5">
                    @csrf @method('PUT')
                    <x-select name="mode" label="Mode" :options="['test' => 'Test (no real money)', 'live' => 'Live (real payments)']" :value="$mode" />
                    <x-field name="live_limit" label="Largest single payment allowed in live mode (₹)" inputmode="decimal" :value="$liveLimit ? number_format($liveLimit / 100, 2, '.', '') : null" />
                    <x-field name="confirm" label="To switch to live, type LIVE" autocomplete="off" />
                    <button class="btn-primary">Save mode</button>
                </form>
            @else
                <p class="mt-4 text-sm">Ask the owner to change this.</p>
            @endif
        </section>
    </div>
</x-layouts.admin>
