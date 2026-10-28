{{-- Birth details fieldset. $p = field prefix ('' or 'a_'/'b_'), $legend optional. --}}
<fieldset class="space-y-4 {{ $legend ? 'rounded-2xl border border-ink-200 p-4' : '' }}">
    @if ($legend)<legend class="px-1 text-sm font-semibold">{{ $legend }}</legend>@endif
    <x-field :name="$p.'name'" label="Name (optional)" :id="'f-'.$p.'name'" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field :name="$p.'birth_date'" label="Date of birth" type="date" min="1800-01-01" max="2399-12-31" required :id="'f-'.$p.'birth_date'" />
        <x-field :name="$p.'birth_time'" label="Time of birth" type="time" required :id="'f-'.$p.'birth_time'" />
    </div>
    @php $err = $errors->first($p.'place_id'); @endphp
    <div x-data="placePicker" data-label="{{ old($p.'place_label') }}" data-id="{{ old($p.'place_id') }}" class="relative">
        <label for="place-{{ $p }}" class="field-label">Place of birth</label>
        <input id="place-{{ $p }}" type="text" name="{{ $p }}place_label" class="input" autocomplete="off" role="combobox" aria-autocomplete="list"
               aria-controls="list-{{ $p }}" :aria-expanded="expanded" x-model="query" @input="onInput" @keydown="onKey" @blur="close" placeholder="Start typing a town or city"
               @if ($err) aria-invalid="true" @endif aria-describedby="status-{{ $p }}">
        <input type="hidden" name="{{ $p }}place_id" :value="placeId">
        <ul id="list-{{ $p }}" role="listbox" x-show="open" x-cloak class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-ink-200 bg-white py-1 shadow-[var(--shadow-card)]">
            <template x-for="(pl, index) in results" :key="pl.id">
                <li role="option" :data-index="index" :aria-selected="index === active ? 'true' : 'false'" class="cursor-pointer px-3 py-2.5 text-sm aria-selected:bg-ivory-100 hover:bg-ivory-50" @mousedown.prevent="choose">
                    <span x-text="pl.label"></span> <span class="text-xs text-ink-600" x-text="pl.tz"></span>
                </li>
            </template>
        </ul>
        @if ($err)<p class="field-error">{{ $err }}</p>@endif
        <p id="status-{{ $p }}" class="sr-only" role="status" aria-live="polite" x-text="status"></p>
    </div>
</fieldset>
