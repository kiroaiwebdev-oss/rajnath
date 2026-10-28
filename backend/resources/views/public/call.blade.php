<x-layouts.base :title="'Consultation call'" robots="noindex,nofollow" class="bg-ivory-50">
    <main class="container-page py-8" x-data="callRoom" data-token-url="{{ route('call.token', $b->ref, false) }}" data-audio-only="{{ $audioOnly ? '1' : '0' }}">
        <a href="{{ $isStaff ? route('admin.dashboard') : route('account.bookings') }}" class="text-sm">← Back</a>
        <h1 class="mt-3 text-2xl">{{ $title ?? 'Consultation' }}</h1>
        <p class="text-ink-600">{{ $when }} · {{ $audioOnly ? 'Audio call' : 'Video call' }}</p>

        <div class="relative mt-6 aspect-video w-full max-w-4xl overflow-hidden rounded-2xl bg-ink-900 text-white">
            <div x-ref="remote" class="absolute inset-0 grid place-items-center" role="region" aria-label="Other participant"></div>
            <p class="absolute inset-0 grid place-items-center p-6 text-center" x-show="waiting">Connected. Waiting for the other person to join…</p>
            <p class="absolute inset-0 grid place-items-center p-6 text-center" x-show="idle">Press “Join call” when you are ready. You can join from 15 minutes before the start.</p>
            <div x-ref="local" class="absolute bottom-3 right-3 h-28 w-40 overflow-hidden rounded-xl bg-ink-800 sm:h-36 sm:w-52" x-show="camOn" role="region" aria-label="Your camera"></div>
        </div>

        <p class="field-error mt-3" role="alert" x-show="error" x-text="error"></p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn-primary" x-show="idle" @click="join">Join call</button>
            <p x-show="connecting" class="text-sm" role="status">Connecting…</p>
            <button type="button" class="btn-outline" x-show="live" @click="toggleMic" x-text="micLabel"></button>
            <button type="button" class="btn-outline" x-show="camControl" @click="toggleCam" x-text="camLabel"></button>
            <button type="button" class="btn-danger" x-show="live" @click="leave">Leave</button>
        </div>
        <p class="mt-4 max-w-2xl text-xs text-ink-600">The call is private between you and your astrologer and is not recorded. Use headphones for the best sound. If the call does not connect, please call the office: {{ config('brand')['contact.phone'] }}.</p>
    </main>
</x-layouts.base>
