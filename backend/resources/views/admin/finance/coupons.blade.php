@php $M = fn ($m) => $m === null ? '—' : \App\Support\Money::format((int) $m); $S = \App\Domain\Payments\Coupons::SCOPES; $ist = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t, 'UTC')->tz('Asia/Kolkata') : null; $edit = request('edit') ? $coupons->firstWhere('id', (int) request('edit')) : null; @endphp
<x-layouts.admin title="Coupons">
    <div class="grid gap-6 xl:grid-cols-[1fr_26rem]">
        <section class="min-w-0">
            <div class="table-wrap" role="region" aria-label="Coupons" tabindex="0"><table class="table">
                <thead><tr><th scope="col">Code</th><th scope="col">Offer</th><th scope="col">Rules</th><th scope="col">Used</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead>
                <tbody>@forelse ($coupons as $c)@php $u = $uses[$c->id] ?? null; $scopes = $c->applies_to ? json_decode($c->applies_to, true) : null; @endphp<tr>
                    <td class="font-mono font-semibold">{{ $c->code }}@if ($c->description)<span class="block font-sans text-xs font-normal text-ink-600">{{ $c->description }}</span>@endif</td>
                    <td>{{ \App\Domain\Payments\Coupons::describe($c) }}</td>
                    <td class="text-xs">{{ $scopes ? implode(', ', array_map(fn ($s) => $S[$s] ?? $s, $scopes)) : 'All services and gems' }}
                        @if ($c->min_order_minor) · min {{ $M($c->min_order_minor) }}@endif · {{ $c->per_user_limit }} per customer
                        @if ($c->usage_limit) · {{ $c->usage_limit }} total @endif @if ($c->first_order_only) · first order only @endif
                        @if ($c->starts_at || $c->ends_at)<br>{{ $ist($c->starts_at)?->format('d M Y') ?? 'now' }} – {{ $ist($c->ends_at)?->format('d M Y, g:i A') ?? 'no end' }}@endif</td>
                    <td>{{ $u->n ?? 0 }}@if ($u)<span class="block text-xs text-ink-600">{{ $M($u->total) }} given</span>@endif</td>
                    <td><span @class(['badge-success' => $c->active, 'badge-ink' => ! $c->active])>{{ $c->active ? 'On' : 'Off' }}</span></td>
                    <td><a href="{{ route('admin.coupons.index', ['edit' => $c->id]) }}">Edit</a></td></tr>
                @empty<tr><td colspan="6" class="py-8 text-center text-ink-600">No coupons yet.</td></tr>@endforelse</tbody>
            </table></div>
            <p class="mt-3 text-xs text-ink-600">Uses count only orders that are pending, paid or refunded; cancelled checkouts free the coupon again. Wallet recharges never take coupons. The discount is shown on the checkout, receipt and tax invoice.</p>
        </section>
        <form method="POST" action="{{ route('admin.coupons.save') }}" class="card h-fit space-y-4 p-5">
            @csrf <h2 class="text-lg">{{ $edit ? 'Edit '.$edit->code : 'New coupon' }}</h2>
            @if ($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
            <x-field name="code" label="Code" :value="$edit?->code" required hint="Letters, digits, - or _. Customers can type it in any case." />
            <x-field name="description" label="Note (shown to staff)" :value="$edit?->description" />
            <div class="grid grid-cols-2 gap-3">
                <x-select name="type" label="Type" :value="$edit?->type ?? 'percent'" :options="['percent' => '% off', 'flat' => '₹ off']" />
                <x-field name="value" label="Value (% or ₹)" :value="$edit ? ($edit->type === 'percent' ? $edit->value : $edit->value / 100) : null" required inputmode="decimal" />
                <x-field name="max_discount" label="Max discount (₹)" :value="$edit?->max_discount_minor ? $edit->max_discount_minor / 100 : null" inputmode="decimal" />
                <x-field name="min_order" label="Min order (₹)" :value="$edit?->min_order_minor ? $edit->min_order_minor / 100 : null" inputmode="decimal" />
                <x-field name="usage_limit" label="Total uses" type="number" :value="$edit?->usage_limit" hint="Empty = no limit" />
                <x-field name="per_user_limit" label="Uses per customer" type="number" :value="$edit?->per_user_limit ?? 1" required />
                <x-field name="starts_at" label="Starts (IST)" type="datetime-local" :value="$ist($edit?->starts_at)?->format('Y-m-d\TH:i')" />
                <x-field name="ends_at" label="Ends (IST)" type="datetime-local" :value="$ist($edit?->ends_at)?->format('Y-m-d\TH:i')" />
            </div>
            <fieldset><legend class="field-label">Applies to (none ticked = everything)</legend>
                @php $sel = old('applies_to', $edit?->applies_to ? json_decode($edit->applies_to, true) : []); @endphp
                <div class="mt-1 grid grid-cols-2 gap-1 text-sm">@foreach ($S as $k => $l)<label class="flex items-center gap-2"><input type="checkbox" name="applies_to[]" value="{{ $k }}" class="size-4 accent-ink-900" @checked(in_array($k, $sel, true))> {{ $l }}</label>@endforeach</div>
            </fieldset>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="first_order_only" value="1" class="size-4 accent-ink-900" @checked(old('first_order_only', $edit?->first_order_only))> First order only</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked(old('active', $edit?->active ?? true))> Active</label>
            <button class="btn-primary w-full">Save coupon</button>
            @if ($edit)<a href="{{ route('admin.coupons.index') }}" class="btn-ghost w-full">New coupon instead</a>@endif
            <p class="text-xs text-ink-600">Saving needs a fresh password and authenticator check.</p>
        </form>
    </div>
</x-layouts.admin>
