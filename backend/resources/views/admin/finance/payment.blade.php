@php use App\Support\Money; use App\Models\Refund; @endphp
<x-layouts.admin title="Payment {{ substr($p->ref, -8) }}">
    @include('admin.finance._nav')
    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card p-5 xl:col-span-2" aria-labelledby="pd-h">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="pd-h" class="text-lg">{{ $p->snapshot['name'] ?? $p->label() }}</h2>
                <x-payment-status :status="$p->status" />
            </div>
            <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-600">Customer</dt><dd>@can('customers.view')<a href="{{ route('admin.customers.show', $p->user) }}">{{ $p->user->name }}</a>@else{{ $p->user->name }}@endcan</dd></div>
                <div><dt class="text-ink-600">Amount / bonus</dt><dd class="tabular-nums">{{ Money::format($p->amount_minor) }} / {{ Money::format($p->bonus_minor) }}</dd></div>
                <div><dt class="text-ink-600">Refunded</dt><dd class="tabular-nums">{{ Money::format($p->refunded_minor) }}</dd></div>
                <div><dt class="text-ink-600">Mode</dt><dd>{{ $p->provider_mode }}</dd></div>
                <div><dt class="text-ink-600">Razorpay order</dt><dd class="font-mono text-xs break-all">{{ $p->provider_order_id ?? '—' }}</dd></div>
                <div><dt class="text-ink-600">Razorpay payment</dt><dd class="font-mono text-xs break-all">{{ $p->provider_payment_id ?? '—' }}</dd></div>
                <div><dt class="text-ink-600">Reference</dt><dd class="font-mono text-xs break-all">{{ $p->ref }}</dd></div>
                <div><dt class="text-ink-600">Last checked</dt><dd>{{ $p->last_checked_at?->tz('Asia/Kolkata')->format('d M, H:i') ?? 'Never' }} ({{ $p->check_attempts }} checks)</dd></div>
                @if ($p->status_reason)<div class="sm:col-span-2"><dt class="text-ink-600">Note</dt><dd>{{ $p->status_reason }}</dd></div>@endif
            </dl>
            @if ($p->status !== 'captured')
                <form method="POST" action="{{ route('admin.finance.payment.recheck', $p) }}" class="mt-5">@csrf<button class="btn-outline">Re-check with Razorpay</button></form>
            @endif

            <h3 class="mt-6 text-base">Webhook events</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($events as $e)<li class="flex flex-wrap gap-2"><span class="font-mono text-xs">{{ $e->type }}</span><span class="badge-ink">{{ $e->result }}</span><span class="text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($e->received_at)->tz('Asia/Kolkata')->format('d M H:i:s') }}</span></li>
                @empty<li class="text-ink-600">None received for this payment.</li>@endforelse
            </ul>
        </section>

        <section class="card p-5" aria-labelledby="rf-h">
            <h2 id="rf-h" class="text-lg">Refunds</h2>
            <p class="mt-1 text-sm text-ink-600">Customer's unspent paid balance: <strong class="tabular-nums">{{ Money::format($bal['cash']) }}</strong>. Refundable on this payment: <strong class="tabular-nums">{{ Money::format($p->refundableMinor()) }}</strong>.</p>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($p->refunds as $r)
                    <li class="rounded-xl border border-ink-200/70 p-3">
                        <div class="flex justify-between gap-2"><span class="tabular-nums font-medium">{{ Money::format($r->amount_minor) }}</span><span class="badge-ink">{{ Refund::STATUSES[$r->status] }}</span></div>
                        <p class="mt-1 text-xs text-ink-600">Requested by {{ $r->requester->name }}{{ $r->decider ? ' · decided by '.$r->decider->name : '' }}</p>
                        <p class="mt-1 text-xs">{{ $r->reason }}</p>
                        @if ($r->failure_reason)<p class="mt-1 text-xs text-danger-700">{{ $r->failure_reason }}</p>@endif
                    </li>
                @endforeach
            </ul>
            @can('refunds.request')
                @if ($p->refundableMinor() > 0)
                    <form method="POST" action="{{ route('admin.finance.payment.refund', $p) }}" class="mt-4 space-y-4 border-t border-ink-200/60 pt-4">
                        @csrf
                        <x-field name="amount" label="Refund amount (₹)" inputmode="decimal" :value="number_format(min($p->refundableMinor(), $bal['cash']) / 100, 2, '.', '')" />
                        <div>
                            <label for="f-reason" class="field-label">Reason</label>
                            <textarea id="f-reason" name="reason" rows="3" class="input" required minlength="5" maxlength="500">{{ old('reason') }}</textarea>
                            @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn-primary w-full">Request refund</button>
                        <p class="text-xs text-ink-600">A second person with refund approval rights must approve it.</p>
                    </form>
                @endif
            @endcan
            @if ($disputes->isNotEmpty())
                <h3 class="mt-6 text-base">Disputes</h3>
                @foreach ($disputes as $d)<p class="mt-2 text-sm">{{ $d->provider_dispute_id }} · {{ Money::format($d->amount_minor) }} · <span class="badge-danger">{{ $d->status }}</span></p>@endforeach
            @endif
        </section>
    </div>
</x-layouts.admin>
