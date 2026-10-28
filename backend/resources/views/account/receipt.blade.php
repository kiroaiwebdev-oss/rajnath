<x-layouts.account title="Receipt">
    <article class="card max-w-2xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><p class="text-sm text-ink-600">{{ $site('brand.name') }}</p><h2 class="text-xl">Order {{ substr($o->ref, -10) }}</h2></div>
            <span @class(['badge-success' => $o->status === 'paid', 'badge-gold' => $o->status === 'pending_payment', 'badge-ink' => ! in_array($o->status, ['paid', 'pending_payment'])])>{{ ucwords(str_replace('_', ' ', $o->status)) }}</span>
        </div>
        @if ($o->status === 'pending_payment')
            <p class="mt-4 rounded-xl bg-gold-50 p-3 text-sm" role="status">We are confirming your payment with the bank. This page updates when it is confirmed (usually within a few minutes). If money was deducted and the payment is not confirmed, it is refunded automatically by your bank or by us.</p>
        @endif
        <dl class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Item</dt><dd class="text-right font-medium">{{ \App\Domain\Payments\Checkout::label($o->kind, $snap) }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Date</dt><dd>{{ \Illuminate\Support\Carbon::parse($o->created_at)->tz(auth()->user()->display_timezone)->format('d M Y, g:i A') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Amount</dt><dd>{{ \App\Support\Money::format((int) $o->subtotal_minor) }}</dd></div>
            @if ($o->wallet_applied_minor)<div class="flex justify-between gap-3"><dt class="text-ink-600">Paid from wallet</dt><dd>{{ \App\Support\Money::format((int) $o->wallet_applied_minor) }}</dd></div>@endif
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Paid online</dt><dd>{{ \App\Support\Money::format((int) $o->payable_minor) }}{{ $payment ? ' ('.($payment->method ?? 'Razorpay').')' : '' }}</dd></div>
            @if (($snap['bonus_minor'] ?? 0) > 0)<div class="flex justify-between gap-3"><dt class="text-ink-600">Bonus credit</dt><dd>{{ \App\Support\Money::format((int) $snap['bonus_minor']) }}</dd></div>@endif
            @foreach ($refunds as $r)<div class="flex justify-between gap-3 text-success-700"><dt>Refunded ({{ $r->destination === 'wallet' ? 'to wallet' : 'to original payment' }})</dt><dd>−{{ \App\Support\Money::format((int) $r->amount_minor) }}</dd></div>@endforeach
        </dl>
        @if ($payment)<p class="mt-4 text-xs text-ink-600">Payment reference: {{ $payment->provider_payment_id }}</p>@endif
        @php $pj = $o->status === 'paid' ? \Illuminate\Support\Facades\DB::table('puja_bookings')->where('order_id', $o->id)->first() : null; $rj = $o->status === 'paid' ? \Illuminate\Support\Facades\DB::table('report_jobs')->where('order_id', $o->id)->first() : null; $bk = \Illuminate\Support\Facades\DB::table('bookings')->where('order_id', $o->id)->first(); @endphp
        @if ($pj)<a href="{{ route('account.puja.show', $pj->ref) }}" class="btn-primary mt-5">{{ $pj->status === 'awaiting_details' ? 'Add sankalp details' : 'Open your puja booking' }}</a>@endif
        @if ($rj)<a href="{{ route('account.reports.show', $rj->ref) }}" class="btn-primary mt-5">{{ $rj->status === 'awaiting_details' ? 'Choose birth details for your report' : 'Open your report' }}</a>@endif
        @if ($bk && $bk->status === 'confirmed')<p class="mt-4 text-sm">Booked for <strong>{{ \Illuminate\Support\Carbon::parse($bk->start_utc, 'UTC')->tz('Asia/Kolkata')->format('l, d M Y, g:i A') }} IST</strong>. <a href="{{ route('account.bookings') }}">Manage booking</a></p>@endif
        @php $palm = $o->status === 'paid' ? \Illuminate\Support\Facades\DB::table('palm_readings')->where('order_id', $o->id)->first() : null; $ship = $o->kind === 'product' ? \Illuminate\Support\Facades\DB::table('shipments')->where('order_id', $o->id)->first() : null; @endphp
        @if ($palm)
            <a href="{{ route('account.palm.show', $palm->ref) }}" class="btn-primary mt-5">{{ $palm->status === 'awaiting_photos' ? 'Upload your palm photos' : 'Open your palmistry report' }}</a>
        @elseif ($ship)
            <p class="mt-4 text-sm">Delivery: <strong>{{ str_replace('_', ' ', $ship->status) }}</strong>@if ($ship->awb) · {{ $ship->courier }} {{ $ship->awb }}@endif @if ($ship->tracking_url) · <a href="{{ $ship->tracking_url }}" rel="noopener">Track</a>@endif</p>
        @elseif ($o->kind === 'service' && $o->status === 'paid' && ! $pj && ! $rj && ! $bk)<p class="mt-4 text-sm">Our team will contact you to schedule this service. You can also call {{ $site('contact.phone') }}.</p>@endif
    </article>
</x-layouts.account>
