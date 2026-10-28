<x-layouts.public title="Sign in">
    <div class="container-page grid items-center gap-10 py-12 md:grid-cols-2">
        <div class="card mx-auto w-full max-w-md p-6 sm:p-8">
            <h1 class="text-2xl">Sign in or create an account</h1>
            <p class="mt-2 text-sm text-ink-600">Enter your email or mobile number. We will send you a 6-digit code — no password needed.</p>
            <form method="POST" action="{{ route('account.sign-in') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <x-field name="identifier" label="Email or mobile number" autocomplete="username" required autofocus placeholder="98xxxxxxxx or you@example.com" />
                <button class="btn-primary w-full">Send code</button>
            </form>
            <p class="mt-5 text-xs text-ink-600">Lost access to your number or email? Sign in with the other one if you added it, or <a href="{{ route('contact') }}">contact us</a>.</p>
        </div>
        <div class="hidden md:block">
            <x-photo src="images/owner/writing-red-480.jpg" alt="Acharya Ji preparing a kundali" class="mx-auto aspect-[3/4] w-full max-w-sm rounded-[var(--radius-card)] object-cover object-top" />
        </div>
    </div>
</x-layouts.public>
