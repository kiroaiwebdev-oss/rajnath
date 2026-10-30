<x-layouts.account title="Profile & contact">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('account.profile') }}" class="card space-y-5 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="text-lg">Profile</h2>
            <x-field name="name" label="Name" :value="$user->name" autocomplete="name" required />
            <x-select name="locale" label="Preferred language" :value="$user->locale" :options="collect(\App\Http\Middleware\SetLocale::enabled())->mapWithKeys(fn ($l) => [$l => config('locales.available')[$l]])->all()" hint="Pages not yet translated show English." />
            <fieldset>
                <legend class="field-label">Notifications</legend>
                @foreach ($prefs as $k => $label)
                    <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="prefs[{{ $k }}]" value="1" class="size-4 accent-ink-900" @checked($current[$k] ?? false)> {{ $label }}</label>
                @endforeach
            </fieldset>
            <button class="btn-primary">Save profile</button>
        </form>

        <section class="card p-5 sm:p-6" aria-labelledby="contact-h">
            <h2 id="contact-h" class="text-lg">Sign-in email & mobile</h2>
            <p class="mt-1 text-sm text-ink-600">Add both so you can still sign in if you lose access to one.</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-3 rounded-xl bg-ivory-100 p-3"><dt>Email</dt><dd class="text-right">{{ $user->email ?? 'Not added' }} @if ($user->email_verified_at)<span class="badge-success ml-1">Verified</span>@endif</dd></div>
                <div class="flex justify-between gap-3 rounded-xl bg-ivory-100 p-3"><dt>Mobile</dt><dd class="text-right">{{ $user->phone_e164 ?? 'Not added' }} @if ($user->phone_verified_at)<span class="badge-success ml-1">Verified</span>@endif</dd></div>
            </dl>
            @if (session('contact_pending') || $errors->has('contact_code'))
                <form method="POST" action="{{ route('account.contact.confirm') }}" class="mt-5 space-y-3">
                    @csrf
                    <p class="text-sm">Enter the code sent to <strong>{{ session('contact_pending') }}</strong>.</p>
                    <x-field name="contact_code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required />
                    <button class="btn-primary">Verify and save</button>
                </form>
            @else
                <form method="POST" action="{{ route('account.contact.start') }}" class="mt-5 space-y-3">
                    @csrf
                    <x-field name="contact" label="Add or change email / mobile" placeholder="98xxxxxxxx or you@example.com" />
                    <button class="btn-outline">Send verification code</button>
                </form>
            @endif
        </section>

        <section class="card p-5 sm:p-6 lg:col-span-2" aria-labelledby="data-h">
            <h2 id="data-h" class="text-lg">Your data</h2>
            <p class="mt-1 text-sm text-ink-600">Download a copy of your data or delete your account. Self-service export and deletion are being built; until then, email <a href="mailto:{{ $site('contact.email') }}">{{ $site('contact.email') }}</a> from your registered address and we will handle it.</p>
        </section>
    </div>
</x-layouts.account>
