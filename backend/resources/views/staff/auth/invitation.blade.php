<x-layouts.auth title="Set up your staff account">
    <p class="text-sm text-ink-600">Welcome, <strong class="text-ink-900">{{ $invitation->name }}</strong> ({{ $invitation->email }}).
        Roles: {{ implode(', ', json_decode($invitation->roles, true)) }}.</p>

    <form method="POST" action="{{ route('staff.invitation.accept', $token) }}" class="mt-6 space-y-5" novalidate>
        @csrf
        <x-field name="password" label="Choose a password" type="password" autocomplete="new-password" required
                 hint="At least 12 characters with upper and lower case letters, a number and a symbol." />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <fieldset class="rounded-2xl border border-ink-200 p-4">
            <legend class="px-1 text-sm font-medium">Two-factor authentication (required)</legend>
            <p class="text-sm text-ink-600">Scan this QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password …).</p>
            <div class="mx-auto mt-3 w-48 rounded-xl bg-white p-2 ring-1 ring-ink-200" role="img" aria-label="QR code for authenticator setup">{!! $qr !!}</div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer text-ink-700">Can't scan? Enter this key manually</summary>
                <code class="mt-2 block break-all rounded-lg bg-ivory-100 p-2 text-xs">{{ chunk_split($secret, 4, ' ') }}</code>
            </details>
            <div class="mt-4">
                <x-field name="code" label="Code shown in the app" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required />
            </div>
        </fieldset>
        <button class="btn-primary w-full">Activate account</button>
    </form>
</x-layouts.auth>
