<x-layouts.account title="Orders">
    @if ($orders->isEmpty())
        <div class="card p-8 text-center"><p class="text-ink-600">No orders yet.</p><a href="{{ route('services.index') }}" class="btn-primary mt-4">Browse services</a></div>
    @else
        <ul class="space-y-3">
            @foreach ($orders as $o)
                @php $s = json_decode($o->snapshot, true); @endphp
                <li class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <div><p class="font-medium">{{ \App\Domain\Payments\Checkout::label($o->kind, $s) }}</p>
                        <p class="text-xs text-ink-600">{{ \Illuminate\Support\Carbon::parse($o->created_at)->tz(auth()->user()->display_timezone)->format('d M Y, g:i A') }} · {{ \App\Support\Money::format((int) $o->subtotal_minor) }}</p></div>
                    <div class="flex items-center gap-3">
                        <span @class(['badge-success' => $o->status === 'paid', 'badge-gold' => $o->status === 'pending_payment', 'badge-ink' => ! in_array($o->status, ['paid', 'pending_payment'])])>{{ ucwords(str_replace('_', ' ', $o->status)) }}</span>
                        @if (in_array($o->status, \App\Domain\Reviews\Reviews::PAID, true) && in_array($o->kind, ['service', 'product'], true))<a href="{{ route('account.reviews.create', $o->ref) }}" class="btn-ghost">Review</a>@endif
                        <a href="{{ $o->status === 'pending_payment' ? route('checkout.show', $o->ref) : route('account.receipt', $o->ref) }}" class="btn-outline">{{ $o->status === 'pending_payment' ? 'Pay' : 'Receipt' }}</a>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.account>
