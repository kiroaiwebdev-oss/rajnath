<x-layouts.auth title="Authenticator code">
    <form method="POST" action="{{ route('staff.mfa') }}" class="space-y-4" novalidate>
        @csrf
        <x-field name="code" label="6-digit code from your authenticator app" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus />
        <button class="btn-primary w-full">Verify and sign in</button>
    </form>
    <p class="mt-4 text-sm"><a href="{{ route('staff.login') }}">Start again</a></p>
</x-layouts.auth>
