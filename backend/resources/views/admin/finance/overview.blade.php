@php use App\Support\Money; @endphp
<x-layouts.admin title="Finance">
    @include('admin.finance._nav')
    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.finance.refunds') }}" class="card block p-5 no-underline"><p class="text-sm text-ink-600">Refunds awaiting approval</p><p class="mt-1 text-2xl font-semibold">{{ $counts['pendingRefunds'] }}</p></a>
        <a href="{{ route('admin.finance.disputes') }}" class="card block p-5 no-underline"><p class="text-sm text-ink-600">Open disputes</p><p class="mt-1 text-2xl font-semibold">{{ $counts['openDisputes'] }}</p></a>
        <a href="{{ route('admin.finance.payments', ['status' => 'ambiguous']) }}" class="card block p-5 no-underline"><p class="text-sm text-ink-600">Payments being checked</p><p class="mt-1 text-2xl font-semibold">{{ $counts['ambiguous'] }}</p></a>
    </div>

    <section class="card mt-6 p-5" aria-labelledby="int-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="int-h" class="text-lg">Ledger integrity</h2>
            <form method="POST" action="{{ route('admin.finance.verify') }}">@csrf<button class="btn-outline">Run full check</button></form>
        </div>
        @if ($verify)
            @if ($verify['ok'])<p class="mt-3 text-sm text-success-700">All {{ $verify['transactions'] }} transactions balance and all {{ $verify['accounts'] }} account balances match their entries.</p>
            @else<ul class="mt-3 list-disc pl-5 text-sm text-danger-700">@foreach ($verify['problems'] as $pr)<li>{{ $pr }}</li>@endforeach</ul>@endif
        @endif
        <p class="mt-3 text-sm">
            Razorpay clearing balance: <strong class="tabular-nums">{{ Money::format($clearing) }}</strong>
            · expected from payments − refunds − lost disputes: <strong class="tabular-nums">{{ Money::format($expectedClearing) }}</strong>
            @if ($clearing === $expectedClearing)<span class="badge-success ml-1">Matches</span>@else<span class="badge-danger ml-1">Mismatch</span>@endif
        </p>
        <p class="mt-1 text-xs text-ink-600">Settlement-report import from Razorpay (bank payouts and fees) is not built yet; this check covers the platform's own records.</p>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="min-w-0" aria-labelledby="sys-h">
            <h2 id="sys-h" class="text-base">Platform accounts</h2>
            <div class="table-wrap mt-3" role="region" aria-label="Platform accounts" tabindex="0">
                <table class="table"><thead><tr><th scope="col">Account</th><th scope="col" class="text-right">Balance</th></tr></thead><tbody>
                    @forelse ($system as $a)<tr><td>{{ str_replace('_', ' ', ucfirst($a->code)) }}</td><td class="text-right tabular-nums">{{ Money::format((int) $a->balance_minor) }}</td></tr>
                    @empty<tr><td colspan="2" class="py-6 text-center text-ink-600">No activity yet.</td></tr>@endforelse
                </tbody></table>
            </div>
        </section>
        <section class="min-w-0" aria-labelledby="cust-h">
            <h2 id="cust-h" class="text-base">Customer balances (total owed to customers)</h2>
            <div class="table-wrap mt-3" role="region" aria-label="Customer balances" tabindex="0">
                <table class="table"><thead><tr><th scope="col">Type</th><th scope="col" class="text-right">Total</th></tr></thead><tbody>
                    @foreach (['customer_cash' => 'Paid wallet balance', 'customer_bonus' => 'Bonus balance', 'customer_reserved' => 'Reserved for bookings', 'customer_held' => 'Held (disputes)'] as $k => $l)
                        <tr><td>{{ $l }}</td><td class="text-right tabular-nums">{{ Money::format((int) ($customerTotals[$k] ?? 0)) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        </section>
    </div>

    <section class="mt-8" aria-labelledby="fd-h">
        <h2 id="fd-h" class="text-base">Open findings</h2>
        <p class="mt-1 text-xs text-ink-600">Raised automatically when something does not match (amount mismatch, unknown webhook, failed refund, dispute…).</p>
        <div class="mt-3 space-y-3">
            @forelse ($findings as $f)
                <div class="card p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="{{ $f->severity === 'critical' ? 'badge-danger' : ($f->severity === 'warning' ? 'badge-gold' : 'badge-ink') }}">{{ ucfirst($f->severity) }}</span>
                        <span class="font-medium">{{ $f->kind }}</span>
                        @if ($f->subject_ref)<span class="font-mono text-xs text-ink-600">{{ $f->subject_type }} {{ $f->subject_ref }}</span>@endif
                        <span class="text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($f->created_at)->tz('Asia/Kolkata')->format('d M, H:i') }}</span>
                    </div>
                    <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-ivory-100 p-2 text-xs">{{ $f->details }}</pre>
                    @can('reconciliation.manage')
                        <form method="POST" action="{{ route('admin.finance.findings.resolve', $f->id) }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            <div class="flex-1"><x-field name="note" :id="'note-'.$f->id" label="Resolution note" /></div>
                            <button class="btn-outline">Mark resolved</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="rounded-xl bg-ivory-100 p-6 text-center text-sm text-ink-600">No open findings.</p>
            @endforelse
        </div>
    </section>
</x-layouts.admin>
