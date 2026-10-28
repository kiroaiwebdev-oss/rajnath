<x-layouts.admin title="Gem store">
    <div class="flex justify-end">@can('catalogue.manage')<a href="{{ route('admin.store.new') }}" class="btn-primary">Add product</a>@endcan</div>
    <div class="table-wrap mt-4" role="region" aria-label="Products" tabindex="0"><table class="table">
        <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Stock</th><th scope="col">Status</th></tr></thead>
        <tbody>@forelse ($products as $p)<tr>
            <td>@can('catalogue.manage')<a href="{{ route('admin.store.edit', $p->ref) }}" class="font-medium">{{ $p->name }}</a>@else{{ $p->name }}@endcan<br><span class="text-xs text-ink-600">{{ $p->weight }}</span></td>
            <td>{{ \App\Domain\Store\Store::CATEGORIES[$p->category] }}</td><td class="tabular-nums">{{ \App\Support\Money::format((int) $p->price_minor) }}</td>
            <td class="tabular-nums">{{ $p->stock }}</td><td>@if ($p->active)<span class="badge-success">On sale</span>@else<span class="badge-ink">Hidden</span>@endif</td></tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-ink-600">No products yet. Add your first gemstone.</td></tr>@endforelse</tbody>
    </table></div>
</x-layouts.admin>
