<x-layouts.admin :title="$p ? 'Edit '.$p->name : 'New product'">
    <form method="POST" action="{{ $p ? route('admin.store.update', $p->ref) : route('admin.store.create') }}" enctype="multipart/form-data" class="card max-w-3xl space-y-5 p-5 sm:p-6">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2"><x-field name="name" label="Name" :value="$p?->name" required placeholder="Yellow Sapphire (Pukhraj), Ceylon" /></div>
            <x-select name="category" label="Category" :value="$p?->category ?? 'gemstone'" :options="\App\Domain\Store\Store::CATEGORIES" />
            <x-field name="slug" label="Web address" :value="$p?->slug" hint="Leave empty to create from the name." />
            <div class="sm:col-span-2"><x-field name="summary" label="Short summary" :value="$p?->summary" required /></div>
            <x-field name="weight" label="Weight" :value="$p?->weight" placeholder="5.25 carat (5.8 ratti)" />
            <x-field name="origin" label="Origin" :value="$p?->origin" placeholder="Sri Lanka" />
            <div class="sm:col-span-2"><x-field name="certification" label="Certificate (lab and number, as on the certificate)" :value="$p?->certification" /></div>
            <x-field name="price" label="Price (₹, incl. shipping)" :value="$p ? $p->price_minor / 100 : null" inputmode="decimal" required hint="Changing price needs price permission." />
            <x-field name="stock" label="Stock" type="number" min="0" :value="$p?->stock ?? 1" required />
            <x-field name="max_per_order" label="Max per order" type="number" min="1" max="10" :value="$p?->max_per_order ?? 1" required />
            <x-field name="sort" label="Order" type="number" :value="$p?->sort ?? 0" />
        </div>
        <div><label for="f-description" class="field-label">Description</label><textarea id="f-description" name="description" rows="5" class="input">{{ old('description', $p?->description) }}</textarea></div>
        <div><label for="f-image" class="field-label">Photo (JPG/PNG/WebP, re-encoded on upload)</label><input id="f-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="input">
            @error('image')<p class="field-error">{{ $message }}</p>@enderror
            @if ($p?->image_path)<img src="/{{ $p->image_path }}" alt="" class="mt-3 h-32 rounded-xl object-cover">@endif</div>
        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="active" value="1" class="size-4 accent-ink-900" @checked(old('active', $p?->active))> On sale</label>
        <button class="btn-primary">Save product</button>
    </form>
</x-layouts.admin>
