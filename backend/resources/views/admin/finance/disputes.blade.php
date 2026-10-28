@php use App\Support\Money; @endphp
<x-layouts.admin title="Disputes">
    @include('admin.finance._nav')
    <p class="max-w-3xl text-sm text-ink-600">Disputes (chargebacks) arrive from Razorpay webhooks. When one opens, the disputed amount is held from the customer's unspent paid balance. Respond to the dispute in the Razorpay Dashboard; the outcome updates here automatically.</p>
    <div class="table-wrap mt-5" role="region" aria-label="Disputes" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">Opened</th><th scope="col">Customer</th><th scope="col">Razorpay dispute</th><th scope="col" class="text-right">Amount</th><th scope="col" class="text-right">Held</th><th scope="col">Respond by</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($list as $d)
                <tr>
                    <td class="whitespace-nowrap">{{ $d->created_at->tz('Asia/Kolkata')->format('d M Y') }}</td>
                    <td>{{ $d->user?->name }}</td>
                    <td class="font-mono text-xs"><a href="{{ route('admin.finance.payment', $d->payment) }}">{{ $d->provider_dispute_id }}</a></td>
                    <td class="text-right tabular-nums">{{ Money::format($d->amount_minor) }}</td>
                    <td class="text-right tabular-nums">{{ Money::format($d->held_minor) }}</td>
                    <td>{{ $d->respond_by?->tz('Asia/Kolkata')->format('d M Y') ?? '—' }}</td>
                    <td><span class="{{ $d->status === 'won' ? 'badge-success' : ($d->status === 'lost' ? 'badge-danger' : 'badge-gold') }}">{{ ucfirst(str_replace('_', ' ', $d->status)) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-ink-600">No disputes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $list->links() }}</div>
</x-layouts.admin>
