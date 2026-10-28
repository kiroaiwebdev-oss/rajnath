@php use App\Support\Money; $canEdit = auth()->user()->can('prices.manage'); @endphp
<x-layouts.admin title="Recharge options">
    @include('admin.finance._nav')
    <p class="max-w-3xl text-sm text-ink-600">Amounts customers can add to their wallet. Bonus is optional promotional credit; it is spent first and is never refunded as money. Changes apply to new payments only. Saving asks you to confirm your identity.</p>
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach ($list->concat($canEdit ? [null] : []) as $pk)
            <form method="POST" action="{{ route('admin.finance.packages.save') }}" class="card space-y-4 p-5" aria-label="{{ $pk ? 'Edit '.$pk->name : 'New recharge option' }}">
                @csrf
                <input type="hidden" name="ref" value="{{ $pk?->ref }}">
                <h2 class="text-base">{{ $pk ? $pk->name : 'Add a recharge option' }} @if ($pk && ! $pk->active)<span class="badge-ink">Hidden</span>@endif</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="name" :id="'nm-'.($pk?->ref ?? 'new')" label="Label" :value="$pk?->name" />
                    <x-field name="amount" :id="'am-'.($pk?->ref ?? 'new')" label="Customer pays (₹)" inputmode="decimal" :value="$pk ? number_format($pk->amount_minor / 100, 2, '.', '') : null" />
                    <x-field name="bonus" :id="'bo-'.($pk?->ref ?? 'new')" label="Bonus credit (₹, optional)" inputmode="decimal" :value="$pk && $pk->bonus_minor ? number_format($pk->bonus_minor / 100, 2, '.', '') : null" />
                    <x-field name="sort" :id="'so-'.($pk?->ref ?? 'new')" label="Order" type="number" :value="$pk?->sort ?? 0" />
                </div>
                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked($pk?->active ?? true)> Show to customers</label>
                @if ($canEdit)<button class="btn-primary">{{ $pk ? 'Save' : 'Add option' }}</button>@endif
            </form>
        @endforeach
    </div>
</x-layouts.admin>
