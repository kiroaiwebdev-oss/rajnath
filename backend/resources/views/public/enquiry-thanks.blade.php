<x-layouts.public title="Request received">
    <div class="container-page max-w-2xl py-16">
        <div class="card p-6 text-center sm:p-10" role="status">
            <span aria-hidden="true" class="mx-auto grid size-14 place-items-center rounded-full bg-green-50 text-2xl text-success-700 ring-1 ring-green-200">✓</span>
            <h1 class="mt-5 text-2xl">Thank you, {{ $e->name }}</h1>
            <p class="mt-3 text-ink-600">We have received your request{{ $e->offering ? ' for '.$e->offering->t()?->title : '' }}. Our team will call you on {{ \App\Support\Logging\Redactor::mask($e->phone_e164) }} to confirm the time and fee.</p>
            <p class="mt-2 text-sm text-ink-600">Reference: <span class="font-mono">{{ substr($e->ref, -8) }}</span></p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ route('services.index') }}" class="btn-outline">Browse more services</a>
                @guest<a href="{{ route('account.sign-in') }}" class="btn-primary">Create an account to track requests</a>@endguest
                @auth<a href="{{ route('account.dashboard') }}" class="btn-primary">Go to my account</a>@endauth
            </div>
        </div>
    </div>
</x-layouts.public>
