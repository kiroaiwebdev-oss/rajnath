<x-layouts.account title="Wallet">
    <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
        <section class="card h-fit p-5" aria-labelledby="bal-h">
            <h2 id="bal-h" class="text-sm text-ink-600">Balance</h2>
            <p class="mt-1 text-3xl font-semibold">{{ \App\Support\Money::format($balance) }}</p>
            <p class="mt-2 text-xs text-ink-600">1 wallet rupee = ₹1. Wallet money can be used for any service on this website and app.</p>
            @if ($payable && $packages->isNotEmpty())
                <form method="POST" action="{{ route('account.wallet.recharge') }}" class="mt-5 space-y-3">
                    @csrf <input type="hidden" name="key" value="{{ \App\Http\Controllers\Web\PaymentController::newKey() }}">
                    <fieldset><legend class="field-label">Add money</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($packages as $p)
                                <label class="flex min-h-14 cursor-pointer flex-col justify-center rounded-xl border border-ink-200 p-3 text-sm has-[:checked]:border-ink-900 has-[:checked]:bg-ivory-100">
                                    <input type="radio" name="package" value="{{ $p->id }}" class="sr-only" @checked($loop->first)>
                                    <span class="font-semibold">{{ \App\Support\Money::format((int) $p->amount_minor) }}</span>
                                    @if ($p->bonus_minor)<span class="text-xs text-success-700">+ {{ \App\Support\Money::format((int) $p->bonus_minor) }} bonus</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('package')<p class="field-error">{{ $message }}</p>@enderror
                    <button class="btn-primary w-full">Continue to pay</button>
                </form>
            @else
                <p class="mt-5 rounded-xl bg-ivory-100 p-3 text-sm">Online recharge opens once payments are switched on.</p>
            @endif
        </section>
        <section class="card min-w-0 p-5" aria-labelledby="tx-h">
            <h2 id="tx-h" class="text-lg">Statement</h2>
            @if ($entries->isEmpty())
                <p class="mt-4 text-sm text-ink-600">No transactions yet.</p>
            @else
                <ul class="mt-3 divide-y divide-ink-200/60">
                    @foreach ($entries as $e)
                        <li class="flex items-center justify-between gap-3 py-3 text-sm">
                            <span><span class="font-medium">{{ $e->memo ?: ucwords(str_replace('_', ' ', $e->kind)) }}</span><span class="block text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($e->occurred_at)->tz(auth()->user()->display_timezone)->format('d M Y, g:i A') }}</span></span>
                            <span class="whitespace-nowrap font-semibold {{ $e->direction === 'credit' ? 'text-success-700' : 'text-ink-900' }}">{{ $e->direction === 'credit' ? '+' : '−' }}{{ \App\Support\Money::format((int) $e->amount_minor) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.account>
