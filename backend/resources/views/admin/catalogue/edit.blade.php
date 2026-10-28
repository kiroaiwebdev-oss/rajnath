<x-layouts.admin :title="$o->exists ? ($t?->title ?? 'Service') : 'New service'">
    <div class="grid gap-6 2xl:grid-cols-[1fr_28rem]">
        <form method="POST" action="{{ $o->exists ? route('admin.catalogue.update', $o->ref) : route('admin.catalogue.store') }}" class="card min-w-0 space-y-5 p-5 sm:p-6">
            @csrf @if ($o->exists) @method('PUT') @endif
            <h2 class="text-lg">Details (English)</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="family_id" label="Family" :value="$o->family_id" :options="$families->pluck('name', 'id')->all()" />
                <x-field name="slug" label="Web address" :value="$o->slug" hint="Lowercase words with dashes. Leave empty to create from the title." />
                <div class="sm:col-span-2"><x-field name="title" label="Title" :value="$t?->title" required /></div>
                <div class="sm:col-span-2"><x-field name="summary" label="Short summary" :value="$t?->summary" required maxlength="300" /></div>
            </div>
            @foreach ([['body', 'Description (blank line between paragraphs)', $t?->body, 6], ['includes', "What's included (one item per line)", implode("\n", $t?->includes ?? []), 5], ['audience', 'Who it is for (one per line)', implode("\n", $t?->audience ?? []), 3], ['exclusions', 'Not included', $t?->exclusions, 2], ['disclaimer', 'Disclaimer shown to customers', $t?->disclaimer, 2]] as [$n, $l, $v, $rows])
                <div><label for="f-{{ $n }}" class="field-label">{{ $l }}</label><textarea id="f-{{ $n }}" name="{{ $n }}" rows="{{ $rows }}" class="input">{{ old($n, $v) }}</textarea>@error($n)<p class="field-error">{{ $message }}</p>@enderror</div>
            @endforeach
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2"><x-field name="image_path" label="Image" :value="$o->image_path" hint="Path under public/, e.g. images/owner/portrait-navy-480.jpg" /></div>
                <x-field name="sort" label="Order" type="number" :value="$o->sort" min="0" />
            </div>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="featured" value="1" class="size-4 accent-ink-900" @checked(old('featured', $o->featured))> Feature on the home page</label>
            @can('catalogue.manage')<button class="btn-primary">{{ $o->exists ? 'Save details' : 'Create draft' }}</button>@endcan
        </form>

        @if ($o->exists)
        <div class="space-y-6">
            <section class="card p-5" aria-labelledby="st-h">
                <h2 id="st-h" class="text-lg">Publication</h2>
                <p class="mt-1 text-sm">Current: <strong>{{ \App\Models\Offering::STATUSES[$o->status] }}</strong>@if ($o->status === 'active') · <a href="{{ route('services.show', $o->slug) }}">View page</a>@endif</p>
                @if ($o->family->requires_supplier)<p class="mt-2 text-xs text-warning-700">Needs an authorised supplier before it can go live.</p>@endif
                @can('catalogue.manage')
                <form method="POST" action="{{ route('admin.catalogue.status', $o->ref) }}" class="mt-3 flex gap-2">
                    @csrf @method('PUT')
                    <label for="f-status" class="sr-only">New status</label>
                    <select id="f-status" name="status" class="input">@foreach (\App\Models\Offering::STATUSES as $k => $v)<option value="{{ $k }}" @selected($o->status === $k)>{{ $v }}</option>@endforeach</select>
                    <button class="btn-outline">Apply</button>
                </form>
                @endcan
            </section>

            <section class="card p-5" aria-labelledby="var-h">
                <h2 id="var-h" class="text-lg">Variants & prices</h2>
                <p class="mt-1 text-xs text-ink-600">Prices in rupees. Leave the price empty to show "Price on request". Saving asks for your password and authenticator code.</p>
                @foreach ($o->variants->push(new \App\Models\OfferingVariant(['participants' => 1, 'active' => true])) as $v)
                    <details class="mt-3 rounded-xl border border-ink-200 p-3" @if (! $v->exists && $o->variants->count() <= 1) open @endif>
                        <summary class="cursor-pointer text-sm font-medium">{{ $v->exists ? $v->name.' — '.\App\Support\Money::format($v->price_minor).($v->active ? '' : ' (off)') : '+ Add variant' }}</summary>
                        @can('prices.manage')
                        <form method="POST" action="{{ route('admin.catalogue.variant', $o->ref) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf
                            <input type="hidden" name="variant" value="{{ $v->ref }}">
                            <div class="sm:col-span-2"><x-field name="name" label="Name" :value="$v->name" :id="'v-name-'.($v->ref ?? 'new')" /></div>
                            <x-select name="mode" label="Mode" :value="$v->mode" :options="['' => '—'] + \App\Models\OfferingVariant::MODES" :id="'v-mode-'.($v->ref ?? 'new')" />
                            <x-field name="duration_min" label="Minutes" type="number" :value="$v->duration_min" :id="'v-dur-'.($v->ref ?? 'new')" />
                            <x-field name="participants" label="People" type="number" :value="$v->participants" min="1" :id="'v-par-'.($v->ref ?? 'new')" />
                            <x-field name="price" label="Price (₹)" :value="$v->price_minor !== null ? number_format($v->price_minor / 100, $v->price_minor % 100 ? 2 : 0, '.', '') : null" inputmode="decimal" :id="'v-price-'.($v->ref ?? 'new')" />
                            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="urgent" value="1" class="size-4 accent-ink-900" @checked($v->urgent)> Priority / urgent</label>
                            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked($v->active)> Offered</label>
                            <button class="btn-outline sm:col-span-2">Save variant</button>
                        </form>
                        @else
                        <p class="mt-2 text-xs text-ink-600">You can view but not change prices.</p>
                        @endcan
                    </details>
                @endforeach
            </section>
        </div>
        @endif
    </div>
</x-layouts.admin>
