<x-layouts.admin title="Catalogue">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" class="grid w-full gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end lg:max-w-3xl" role="search">
            <x-field name="q" label="Search title" :value="$q" />
            <x-select name="family" label="Family" :value="$family" :options="['' => 'All'] + $families->pluck('name', 'code')->all()" />
            <x-select name="status" label="Status" :value="$status" :options="['' => 'All'] + \App\Models\Offering::STATUSES" />
            <button class="btn-outline">Filter</button>
        </form>
        @can('catalogue.manage')<a href="{{ route('admin.catalogue.create') }}" class="btn-primary">New service</a>@endcan
    </div>
    <div class="table-wrap mt-5" role="region" aria-label="Services" tabindex="0">
        <table class="table">
            <thead><tr><th scope="col">Service</th><th scope="col">Family</th><th scope="col">Status</th><th scope="col">From price</th><th scope="col">Variants</th></tr></thead>
            <tbody>
            @forelse ($offerings as $o)
                <tr>
                    <td><a href="{{ route('admin.catalogue.edit', $o->ref) }}" class="font-medium">{{ $o->t('en')?->title }}</a>@if ($o->featured)<span class="badge-gold ml-2">Featured</span>@endif<br><span class="text-xs text-ink-600">/services/{{ $o->slug }}</span></td>
                    <td>{{ $o->family->name }}</td>
                    <td>@if ($o->status === 'active')<span class="badge-success">Active</span>@else<span class="badge-ink">{{ ucfirst($o->status) }}</span>@endif</td>
                    <td class="whitespace-nowrap">{{ \App\Support\Money::format($o->fromPriceMinor()) }}</td>
                    <td>{{ $o->variants->where('active', true)->count() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-ink-600">No services match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $offerings->links() }}</div>
</x-layouts.admin>
