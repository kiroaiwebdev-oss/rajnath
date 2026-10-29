{{-- Tax invoice / credit note. $inv (row), $sup, $buyer, $lines, $order, $original (invoice for a credit note) --}}
@php $M = fn ($m) => \App\Support\Money::format((int) $m); $rate = $inv->rate_bps / 100; @endphp
@extends('pdf.layout', ['headRight' => '<strong>'.($inv->type === 'invoice' ? 'TAX INVOICE' : 'CREDIT NOTE').'</strong><br>'.e($inv->number).'<br>'.e(\Illuminate\Support\Carbon::parse($inv->issued_at)->tz('Asia/Kolkata')->format('d M Y'))])
@section('content')
    <div class="grid2">
        <div>
            <h3>Supplier</h3>
            <p><strong>{{ $sup['legal_name'] }}</strong><br>{{ $sup['address'] }}<br>GSTIN: {{ $sup['gstin'] }}<br>State: {{ $sup['state'] }} ({{ $sup['state_code'] }})</p>
        </div>
        <div>
            <h3>{{ $inv->type === 'invoice' ? 'Billed to' : 'Issued to' }}</h3>
            <p><strong>{{ $buyer['name'] }}</strong>@if ($buyer['address'])<br>{{ $buyer['address'] }}@endif<br>{{ collect([$buyer['email'] ?? null, $buyer['phone'] ?? null])->filter()->implode(' · ') }}<br>Unregistered (consumer)</p>
        </div>
    </div>
    <p class="small">Place of supply: {{ $inv->place_of_supply }} · Order {{ $order->ref }}@if ($original) · Against invoice {{ $original->number }} dated {{ \Illuminate\Support\Carbon::parse($original->issued_at)->tz('Asia/Kolkata')->format('d M Y') }}@endif</p>
    <table>
        <thead><tr><th>Description</th><th>{{ $lines[0]['code_type'] ?? 'SAC' }}</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Discount</th><th class="num">Amount (incl. GST)</th></tr></thead>
        <tbody>@foreach ($lines as $l)<tr><td>{{ $l['description'] }}</td><td>{{ $l['code'] }}</td><td class="num">{{ $l['quantity'] }}</td><td class="num">{{ $M($l['list_minor']) }}</td><td class="num">{{ $l['discount_minor'] ? '−'.$M($l['discount_minor']) : '—' }}</td><td class="num">{{ $M($l['total_minor']) }}</td></tr>@endforeach</tbody>
    </table>
    <table style="width:55%;margin-left:auto">
        <tr><td>Taxable value</td><td class="num">{{ $M($inv->taxable_minor) }}</td></tr>
        @if ($inv->igst_minor > 0)
            <tr><td>IGST @ {{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%</td><td class="num">{{ $M($inv->igst_minor) }}</td></tr>
        @else
            <tr><td>CGST @ {{ rtrim(rtrim(number_format($rate / 2, 2), '0'), '.') }}%</td><td class="num">{{ $M($inv->cgst_minor) }}</td></tr>
            <tr><td>SGST @ {{ rtrim(rtrim(number_format($rate / 2, 2), '0'), '.') }}%</td><td class="num">{{ $M($inv->sgst_minor) }}</td></tr>
        @endif
        <tr><th>{{ $inv->type === 'invoice' ? 'Total' : 'Total credited' }}</th><th class="num">{{ $M($inv->total_minor) }}</th></tr>
    </table>
    <p class="small">Prices include GST. Tax payable on reverse charge: No.</p>
    <p class="stamp">This is a computer-generated document and needs no signature.</p>
@endsection
