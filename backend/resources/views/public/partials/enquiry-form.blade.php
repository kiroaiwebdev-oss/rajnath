@php $u = auth()->user(); $modes = $offering ? $offering->variants->where('active', true)->pluck('mode')->filter()->unique()->values() : collect(); @endphp
<form method="POST" action="{{ route('enquiries.store') }}" class="mt-4 space-y-4" novalidate>
    @csrf
    @if ($offering)<input type="hidden" name="offering" value="{{ $offering->slug }}">@endif
    {{-- Honeypot field for bots; hidden from people and assistive technology. --}}
    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <x-field name="name" :label="__('Your name')" :value="$u && ! $u->isStaff() ? $u->name : null" autocomplete="name" required />
    <x-field name="phone" :label="__('Mobile number')" type="tel" :value="$u && ! $u->isStaff() ? $u->phone_e164 : null" autocomplete="tel" inputmode="tel" required :hint="__('We will call or WhatsApp you on this number.')" />
    <x-field name="email" :label="__('Email (optional)')" type="email" :value="$u && ! $u->isStaff() ? $u->email : null" autocomplete="email" />
    @if ($modes->count() > 1)
        <x-select name="mode" :label="__('Preferred mode')" :options="['' => __('No preference')] + $modes->mapWithKeys(fn ($m) => [$m => __(\App\Models\OfferingVariant::MODES[$m] ?? $m)])->all()" />
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="language" :label="__('Language')" :options="['Hindi' => __('Hindi'), 'Maithili' => __('Maithili'), 'English' => __('English'), 'Sanskrit' => __('Sanskrit')]" />
        <x-field name="preferred_date" :label="__('Preferred date')" type="date" :min="now('Asia/Kolkata')->format('Y-m-d')" />
    </div>
    <details class="rounded-xl border border-ink-200 p-3">
        <summary class="cursor-pointer text-sm font-medium">{{ __('Add birth details (optional)') }}</summary>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <x-field name="birth_date" :label="__('Date of birth')" type="date" :max="now('Asia/Kolkata')->format('Y-m-d')" />
            <x-field name="birth_time" :label="__('Time of birth')" type="time" />
            <div class="sm:col-span-2"><x-field name="birth_place" :label="__('Place of birth')" /></div>
        </div>
    </details>
    <div>
        <label for="f-message" class="field-label">{{ __('Your question or message (optional)') }}</label>
        <textarea id="f-message" name="message" rows="3" maxlength="2000" class="input">{{ old('message') }}</textarea>
    </div>
    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="consent" value="1" class="mt-1 size-4 accent-ink-900" @checked(old('consent')) @if ($errors->has('consent')) aria-invalid="true" aria-describedby="consent-error" @endif>
        <span>{{ __('I agree to be contacted about this request. My details are handled as described in the') }} <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>.</span>
    </label>
    @error('consent')<p id="consent-error" class="field-error">{{ $message }}</p>@enderror
    <button class="btn-primary w-full">{{ __('Send request') }}</button>
</form>
