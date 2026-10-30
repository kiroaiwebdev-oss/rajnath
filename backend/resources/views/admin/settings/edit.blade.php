<x-layouts.admin title="Brand & contact settings">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="card max-w-3xl space-y-5 p-5 sm:p-6">
        @csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($fields as $key => $f)
                @php $n = str_replace('.', '__', $key); @endphp
                <div @class(['sm:col-span-2' => in_array($key, ['contact.street', 'brand.tagline', 'source.repository_url'])])>
                    <x-field :name="$n" :label="$f['label']" :value="$values[$key] ?? (config('brand')[$key] ?? null)" />
                </div>
            @endforeach
        </div>
        <div class="flex flex-col gap-3 border-t border-ink-200/60 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-ink-600">Saving requires a fresh password + authenticator confirmation. Changes are audited.</p>
            @can('settings.manage')<button class="btn-primary">Save settings</button>@endcan
        </div>
    </form>
</x-layouts.admin>
