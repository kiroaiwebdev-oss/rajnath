<x-layouts.auth title="Staff sign in">
    <form method="POST" action="{{ route('staff.login') }}" class="space-y-4" novalidate>
        @csrf
        <x-field name="email" label="Work email" type="email" autocomplete="username" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        <button class="btn-primary w-full">Continue</button>
    </form>
    <p class="mt-6 text-xs text-ink-600">Staff access only. Every sign-in requires an authenticator code and is recorded in the audit log.</p>
</x-layouts.auth>
