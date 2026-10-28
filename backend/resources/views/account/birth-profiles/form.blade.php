<x-layouts.account :title="$profile->exists ? 'Edit birth profile' : 'Add birth profile'">
    <form method="POST" action="{{ $profile->exists ? route('account.birth-profiles.update', $profile) : route('account.birth-profiles.store') }}" class="card max-w-2xl space-y-5 p-5 sm:p-6" novalidate>
        @csrf @if ($profile->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="name" label="Full name" :value="$profile->name" required />
            <x-select name="label" label="Relation" :value="$profile->label" :options="\App\Models\BirthProfile::LABELS" />
            <x-select name="gender" label="Gender" :value="$profile->gender" :options="['' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />
            <x-field name="birth_date" label="Date of birth" type="date" :value="$profile->birth_date?->format('Y-m-d')" min="1800-01-01" :max="now('Asia/Kolkata')->format('Y-m-d')" required />
            <x-select name="time_accuracy" label="How sure are you of the birth time?" :value="$profile->time_accuracy" :options="\App\Models\BirthProfile::ACCURACY" />
            <x-field name="birth_time" label="Time of birth (local time)" type="time" :value="$profile->birth_time ? substr($profile->birth_time, 0, 5) : null" hint="Leave empty only if the time is unknown." />
        </div>

        @php $pErr = $errors->first('place_id'); @endphp
        <div x-data="placePicker" data-label="{{ old('place_label', $placeLabel) }}" data-id="{{ old('place_id', $profile->place_id) }}" class="relative">
            <label for="place-input" class="field-label">Place of birth</label>
            <input id="place-input" type="text" name="place_label" class="input" autocomplete="off" role="combobox" aria-autocomplete="list"
                   aria-controls="place-list" :aria-expanded="expanded" :aria-activedescendant="activeId" x-model="query"
                   @input="onInput" @keydown="onKey" @blur="close" placeholder="Start typing a town or city"
                   @if ($pErr) aria-invalid="true" aria-describedby="place-error place-status" @else aria-describedby="place-hint place-status" @endif>
            <input type="hidden" name="place_id" :value="placeId">
            <ul id="place-list" role="listbox" x-show="open" x-cloak class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-ink-200 bg-white py-1 shadow-[var(--shadow-card)]">
                <template x-for="(p, index) in results" :key="p.id">
                    <li role="option" :id="'place-opt-' + index" :data-index="index" :aria-selected="index === active ? 'true' : 'false'"
                        class="cursor-pointer px-3 py-2.5 text-sm aria-selected:bg-ivory-100 hover:bg-ivory-50" @mousedown.prevent="choose">
                        <span x-text="p.label"></span> <span class="text-xs text-ink-600" x-text="p.tz"></span>
                    </li>
                </template>
            </ul>
            @if ($pErr)<p id="place-error" class="field-error">{{ $pErr }}</p>@else<p id="place-hint" class="field-hint">Choose from the list. If your village is not listed, choose the nearest town. Place data © GeoNames (CC BY 4.0).</p>@endif
            <p id="place-status" class="sr-only" role="status" aria-live="polite" x-text="status"></p>
        </div>

        @if (in_array($profile->tz_resolution, ['ambiguous_earlier', 'ambiguous_later']))
            <x-select name="dst_choice" label="This time occurred twice (clocks went back). Which one?" :value="$profile->tz_resolution === 'ambiguous_later' ? 'later' : 'earlier'" :options="['earlier' => 'First occurrence (before clocks went back)', 'later' => 'Second occurrence (after clocks went back)']" />
        @endif

        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="is_primary" value="1" class="size-4 accent-ink-900" @checked(old('is_primary', $profile->is_primary))> Use as my primary profile</label>

        <div class="flex flex-col-reverse gap-3 border-t border-ink-200/60 pt-5 sm:flex-row sm:justify-between">
            <a href="{{ route('account.birth-profiles.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-primary">Save</button>
        </div>
    </form>
</x-layouts.account>
