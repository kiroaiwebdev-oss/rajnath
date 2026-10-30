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
        <fieldset class="border-t border-ink-200/60 pt-5">
            <legend class="text-lg font-semibold">Opening hours (India time)</legend>
            <p class="mt-1 text-sm text-ink-600">Write each session as 08:00-12:00 (24-hour clock). Leave both empty for a closed day. Shown on the contact page, the home page, the app and Google.</p>
            <div class="mt-4 space-y-3">
                @foreach (\App\Support\Business::DAYS as $key => $day)
                    <div class="grid items-end gap-3 sm:grid-cols-[8rem_1fr_1fr]">
                        <p class="font-medium sm:pb-3">{{ $day }}</p>
                        <x-field :name="'hours__'.$key.'_1'" :label="'Morning'" :value="isset($hours[$key][0]) ? implode('-', $hours[$key][0]) : ''" placeholder="08:00-12:00" />
                        <x-field :name="'hours__'.$key.'_2'" :label="'Evening'" :value="isset($hours[$key][1]) ? implode('-', $hours[$key][1]) : ''" placeholder="15:00-19:00" />
                    </div>
                @endforeach
            </div>
        </fieldset>
        <p class="text-sm text-ink-600">Biography, awards, stats and home-page text are in <a href="{{ route('admin.profile.edit') }}">About &amp; awards</a>. Photos and videos are in <a href="{{ route('admin.gallery.index') }}">Gallery</a>.</p>
        <div class="flex flex-col gap-3 border-t border-ink-200/60 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-ink-600">Saving requires a fresh password + authenticator confirmation. Changes are audited.</p>
            @can('settings.manage')<button class="btn-primary">Save settings</button>@endcan
        </div>
    </form>
</x-layouts.admin>
