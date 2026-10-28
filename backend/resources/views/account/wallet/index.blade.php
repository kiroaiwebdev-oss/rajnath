@php use App\Support\Money; use App\Http\Controllers\Web\WalletController; $tz = auth()->user()->display_timezone; @endphp
<x-layouts.account title="Wallet">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-1" aria-labelledby="bal-h">
            <h2 id="bal-h" class="text-lg">Balance</h2>
            <p class="mt-3 text-3xl font-semibold tabular-nums" data-testid="available">{{ Money::format($bal['available']) }}</p>
            <p class="text-sm text-ink-600">Available to spend</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-ink-600">Paid balance (refundable)</dt><dd class="tabular-nums">{{ Money::format($bal['cash']) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-600">Bonus balance</dt><dd class="tabular-nums">{{ Money::format($bal['bonus']) }}</dd></div>
                @if ($bal['reserved'])<div class="flex justify-between gap-3"><dt class="text-ink-600">Reserved for bookings</dt><dd class="tabular-nums">{{ Money::format($bal['reserved']) }}</dd></div>@endif
                @if ($bal['held'])<div class="flex justify-between gap-3"><dt class="text-ink-600">On hold (payment dispute)</dt><dd class="tabular-nums">{{ Money::format($bal['held']) }}</dd></div>@endif
            </dl>
            <p class="mt-4 text-xs text-ink-600">1 wallet rupee = ₹1. Bonus credit is spent first and cannot be refunded as money.</p>
        </section>

        <section class="card p-5 lg:col-span-2" aria-labelledby="rc-h">
            <h2 id="rc-h" class="text-lg">Add money</h2>
            @if (! $paymentsReady)
                <p class="mt-3 rounded-xl bg-ivory-100 p-4 text-sm text-ink-700">Online payment is not available right now. Please contact us to book.</p>
            @elseif ($packages->isEmpty())
                <p class="mt-3 rounded-xl bg-ivory-100 p-4 text-sm text-ink-700">No recharge options are available at the moment.</p>
            @else
                @if ($testMode)<p class="mt-2"><span class="badge-gold">Test mode — no real money is charged</span></p>@endif
                <form method="POST" action="{{ route('account.wallet.recharge') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="idem" value="{{ WalletController::newIdem() }}">
                    <fieldset>
                        <legend class="sr-only">Choose an amount</legend>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($packages as $i => $pk)
                                <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-2xl border border-ink-200 p-4 has-[:checked]:border-ink-900 has-[:checked]:bg-ivory-100">
                                    <input type="radio" name="package" value="{{ $pk->ref }}" class="mt-1 size-4 accent-ink-900" @checked($i === 0) required>
                                    <span>
                                        <span class="block font-semibold tabular-nums">{{ Money::format($pk->amount_minor) }}</span>
                                        <span class="block text-xs text-ink-600">{{ $pk->name }}</span>
                                        @if ($pk->bonus_minor)<span class="badge-success mt-1">+ {{ Money::format($pk->bonus_minor) }} bonus</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('package')<p class="field-error">{{ $message }}</p>@enderror
                    <button class="btn-primary mt-4 w-full sm:w-auto">Continue to secure payment</button>
                    <p class="mt-2 text-xs text-ink-600">Payments are processed by Razorpay. We never see or store your card or UPI details.</p>
                </form>
            @endif
        </section>
    </div>

    <section class="mt-8" aria-labelledby="pay-h">
        <h2 id="pay-h" class="text-lg">Payments</h2>
        <div class="table-wrap mt-3" role="region" aria-label="Payments" tabindex="0">
            <table class="table">
                <thead><tr><th scope="col">Date</th><th scope="col">For</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="whitespace-nowrap">{{ $p->created_at->tz($tz)->format('d M Y, g:i A') }}</td>
                        <td>{{ $p->snapshot['name'] ?? $p->label() }}</td>
                        <td class="tabular-nums">{{ Money::format($p->amount_minor) }}</td>
                        <td><x-payment-status :status="$p->status" /></td>
                        <td><a href="{{ route('account.payments.show', $p) }}">Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-600">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8" aria-labelledby="st-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="st-h" class="text-lg">Statement</h2>
            <a href="{{ route('account.wallet.statement') }}" class="btn-outline">Download CSV</a>
        </div>
        <div class="table-wrap mt-3" role="region" aria-label="Wallet statement" tabindex="0">
            <table class="table">
                <thead><tr><th scope="col">Date</th><th scope="col">Description</th><th scope="col">Balance</th><th scope="col" class="text-right">Amount</th><th scope="col" class="text-right">Balance after</th></tr></thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($e->created_at, 'UTC')->tz($tz)->format('d M Y, g:i A') }}</td>
                        <td>{{ $e->memo }}</td>
                        <td>{{ WalletController::KINDS[$e->kind] ?? $e->kind }}</td>
                        <td class="text-right tabular-nums {{ $e->direction === 'credit' ? 'text-success-700' : '' }}">{{ $e->direction === 'credit' ? '+' : '−' }}{{ Money::format($e->amount_minor) }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($e->balance_after_minor) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-600">No wallet activity yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.account>
