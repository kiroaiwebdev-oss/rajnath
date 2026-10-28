@php $u = auth()->user(); $modes = $offering ? $offering->variants->where('active', true)->pluck('mode')->filter()->unique()->values() : collect(); @endphp
<form method="POST" action="{{ route('enquiries.store') }}" class="mt-4 space-y-4" novalidate>
    @csrf
    @if ($offering)<input type="hidden" name="offering" value="{{ $offering->slug }}">@endif
    {{-- Honeypot field for bots; hidden from people and assistive technology. --}}
    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <x-field name="name" label="Your name" :value="$u && ! $u->isStaff() ? $u->name : null" autocomplete="name" required />
    <x-field name="phone" label="Mobile number" type="tel" :value="$u && ! $u->isStaff() ? $u->phone_e164 : null" autocomplete="tel" inputmode="tel" required hint="We will call or WhatsApp you on this number." />
    <x-field name="email" label="Email (optional)" type="email" :value="$u && ! $u->isStaff() ? $u->email : null" autocomplete="email" />
    @if ($modes->count() > 1)
        <x-select name="mode" label="Preferred mode" :options="['' => 'No preference'] + $modes->mapWithKeys(fn ($m) => [$m => \App\Models\OfferingVariant::MODES[$m] ?? $m])->all()" />
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="language" label="Language" :options="['Hindi' => 'Hindi', 'Maithili' => 'Maithili', 'English' => 'English', 'Sanskrit' => 'Sanskrit']" />
        <x-field name="preferred_date" label="Preferred date" type="date" :min="now('Asia/Kolkata')->format('Y-m-d')" />
    </div>
    <details class="rounded-xl border border-ink-200 p-3">
        <summary class="cursor-pointer text-sm font-medium">Add birth details (optional)</summary>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <x-field name="birth_date" label="Date of birth" type="date" :max="now('Asia/Kolkata')->format('Y-m-d')" />
            <x-field name="birth_time" label="Time of birth" type="time" />
            <div class="sm:col-span-2"><x-field name="birth_place" label="Place of birth" /></div>
        </div>
    </details>
    <div>
        <label for="f-message" class="field-label">Your question or message (optional)</label>
        <textarea id="f-message" name="message" rows="3" maxlength="2000" class="input">{{ old('message') }}</textarea>
    </div>
    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="consent" value="1" class="mt-1 size-4 accent-ink-900" @checked(old('consent')) @if ($errors->has('consent')) aria-invalid="true" aria-describedby="consent-error" @endif>
        <span>I agree to be contacted about this request. My details are handled as described in the <a href="{{ route('privacy') }}">Privacy Policy</a>.</span>
    </label>
    @error('consent')<p id="consent-error" class="field-error">{{ $message }}</p>@enderror
    <button class="btn-primary w-full">Send request</button>
</form>
