<x-layouts.public title="Enter your code" robots="noindex,nofollow" :jsonld="false">
    <div class="container-page py-12">
        <div class="card mx-auto w-full max-w-md p-6 sm:p-8">
            <h1 class="text-2xl">Enter your code</h1>
            <p class="mt-2 text-sm text-ink-600">We sent a 6-digit code by {{ $channel === 'email' ? 'email' : 'SMS' }} to <strong class="text-ink-900">{{ $masked }}</strong>. It is valid for 10 minutes.</p>
            <form method="POST" action="{{ route('account.verify') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus />
                @if ($isNew)
                    <div class="rounded-2xl bg-ivory-100 p-4">
                        <p class="text-sm font-medium">New here? Tell us your name.</p>
                        <div class="mt-3"><x-field name="name" label="Your name" autocomplete="name" /></div>
                        <label class="mt-3 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="terms" value="1" class="mt-1 size-4 accent-ink-900" @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror>
                            <span>I accept the <a href="{{ route('terms') }}">Terms of use</a> and <a href="{{ route('privacy') }}">Privacy policy</a>.</span>
                        </label>
                        @error('terms')<p id="terms-error" class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <button class="btn-primary w-full">{{ $isNew ? 'Create account' : 'Sign in' }}</button>
            </form>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
                <form method="POST" action="{{ route('account.verify.resend') }}" x-data="countdown" data-seconds="{{ $resendIn }}">
                    @csrf
                    <button class="btn-ghost px-3" :disabled="waiting" x-text="label">Resend code</button>
                </form>
                <a href="{{ route('account.sign-in') }}">Use a different email or number</a>
            </div>
        </div>
    </div>
</x-layouts.public>
