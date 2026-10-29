<x-layouts.account title="Join as an astrologer">
    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        @php $open = $apps->firstWhere('status', 'submitted'); @endphp
        @if ($open)
            <section class="card h-fit p-5 sm:p-6">
                <h2 class="text-lg">Your application is with our team</h2>
                <p class="mt-2 text-sm">Sent on {{ \Illuminate\Support\Carbon::parse($open->created_at)->tz('Asia/Kolkata')->format('d M Y') }}. We reply by email and in your notifications.</p>
                <form method="POST" action="{{ route('account.apply.withdraw', $open->ref) }}" class="mt-4">@csrf<button class="btn-outline">Withdraw application</button></form>
            </section>
        @else
            <form method="POST" action="{{ route('account.apply') }}" class="card space-y-4 p-5 sm:p-6">
                @csrf
                <h2 class="text-lg">Apply to join Acharya Ji's team</h2>
                <p class="text-sm text-ink-600">Experienced astrologers can apply to give consultations through this website and app. If accepted, you receive an invitation to a separate team account with its own password and authenticator app.</p>
                <x-field name="name" label="Full name" :value="old('name', $user->name)" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="email" label="Email for your astrologer account" type="email" :value="old('email')" required hint="Must be different from the email of this customer account." />
                    <x-field name="phone" label="Mobile number" :value="old('phone', $user->phone_e164)" inputmode="tel" required />
                    <x-field name="city" label="City" :value="old('city')" />
                    <x-field name="experience_years" label="Years of experience" type="number" min="0" max="70" :value="old('experience_years')" required />
                </div>
                <x-field name="languages" label="Languages you consult in" :value="old('languages')" required placeholder="Hindi, English, Maithili" />
                <x-field name="specialties" label="Specialties" :value="old('specialties')" required placeholder="Vedic astrology, Vastu, Numerology" />
                <div><label for="f-about" class="field-label">About you (training, guru, years of practice)</label><textarea id="f-about" name="about" rows="6" maxlength="3000" class="input" required>{{ old('about') }}</textarea>@error('about')<p class="field-error">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1 size-4 accent-ink-900" @checked(old('consent'))> <span>The details above are true, and I agree that the team may contact me about this application.</span></label>
                <button class="btn-primary">Send application</button>
            </form>
        @endif
        <aside class="space-y-3">
            <h2 class="text-base">Earlier applications</h2>
            @forelse ($apps->where('status', '!=', 'submitted') as $a)
                <p class="card p-4 text-sm">{{ \Illuminate\Support\Carbon::parse($a->created_at)->tz('Asia/Kolkata')->format('d M Y') }} · <strong>{{ ucfirst($a->status) }}</strong>@if ($a->status === 'rejected' && $a->decision_note)<span class="mt-1 block text-ink-600">{{ $a->decision_note }}</span>@endif @if ($a->status === 'accepted')<span class="mt-1 block text-ink-600">Invitation sent to {{ $a->email }}.</span>@endif</p>
            @empty
                <p class="text-sm text-ink-600">None.</p>
            @endforelse
        </aside>
    </div>
</x-layouts.account>
