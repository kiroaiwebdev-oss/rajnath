@php use App\Support\Money; use App\Models\Payment; @endphp
<x-layouts.admin title="Payments">
    @include('admin.finance._nav')
    <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end" role="search">
        <div class="sm:w-56"><x-select name="status" label="Status" :options="['' => 'All'] + Payment::STATUSES" :value="$status" /></div>
        <div class="flex-1"><x-field name="q" label="Reference / Razorpay order or payment ID" :value="$q" /></div>
        <button class="btn-outline">Filter</button>
    </form>
    <div class="table-wrap mt-5" role="region" aria-label="Payments" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">Started (IST)</th><th scope="col">Customer</th><th scope="col">For</th><th scope="col" class="text-right">Amount</th><th scope="col">Mode</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($list as $p)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('admin.finance.payment', $p) }}">{{ $p->created_at->tz('Asia/Kolkata')->format('d M Y, H:i') }}</a></td>
                    <td>{{ $p->user?->name }}</td>
                    <td>{{ $p->snapshot['name'] ?? $p->label() }}</td>
                    <td class="text-right tabular-nums">{{ Money::format($p->amount_minor) }}</td>
                    <td>{{ $p->provider_mode }}</td>
                    <td><x-payment-status :status="$p->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-ink-600">No payments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $list->links() }}</div>
</x-layouts.admin>
