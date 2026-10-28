<x-layouts.auth title="Confirm it's you">
    <p class="mb-4 text-sm text-ink-600">This action changes sensitive settings. Re-enter your password and a fresh authenticator code.</p>
    <form method="POST" action="{{ route('staff.step-up') }}" class="space-y-4" novalidate>
        @csrf
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <x-field name="code" label="Authenticator code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required />
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
            <a href="{{ route('admin.dashboard') }}" class="btn-outline">Cancel</a>
            <button class="btn-primary">Confirm</button>
        </div>
    </form>
</x-layouts.auth>
