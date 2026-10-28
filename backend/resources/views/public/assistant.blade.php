<x-layouts.public title="Ask our assistant" description="Quick answers about consultations, reports, puja, prices and booking.">
    <section class="container-page py-10">
        <p class="eyebrow">Assistant</p>
        <h1 class="mt-2 text-3xl">Ask a quick question</h1>
        <p class="mt-2 max-w-2xl text-ink-600">Questions about our services, prices, booking, puja or how things work — in Hindi, Hinglish or English. For personal guidance, please book a consultation with Acharya Ji.</p>

        @if (! $enabled)
            <p class="card mt-6 max-w-2xl p-5">The assistant is not available right now. Please <a href="{{ route('contact') }}">contact our office</a> — we are happy to help.</p>
        @else
            <div class="card mt-6 max-w-3xl p-0" x-data="assistant" data-endpoint="{{ route('assistant.chat', [], false) }}">
                <div x-ref="log" class="max-h-[60vh] min-h-64 space-y-3 overflow-y-auto p-5" role="log" aria-live="polite" aria-label="Conversation">
                    <p class="text-sm text-ink-600" x-show="empty">Example: “Online consultation kaise book karein?” or “What is included in the Kundli report?”</p>
                    <template x-for="m in messages" :key="m.id">
                        <div :class="m.cls"><p class="whitespace-pre-line" x-text="m.content"></p></div>
                    </template>
                    <p class="text-sm text-ink-600" x-show="busy">Typing…</p>
                </div>
                <div class="border-t border-ink-200/60 p-4">
                    <p class="field-error mb-2" role="alert" x-show="error" x-text="error"></p>
                    <form class="flex items-end gap-2" @submit.prevent="send">
                        <div class="flex-1">
                            <label for="f-message" class="sr-only">Your question</label>
                            <textarea id="f-message" x-model="draft" rows="2" maxlength="1000" class="input" placeholder="Type your question…"></textarea>
                        </div>
                        <button class="btn-primary" :disabled="cannotSend">Send</button>
                    </form>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-600">
                        <span>{{ \App\Domain\Assistant\Assistant::DISCLAIMER }}</span>
                        <a x-show="handoff" class="btn-outline" href="{{ $signedIn ? route('account.support.index') : route('contact') }}">Talk to our team</a>
                    </div>
                    @unless ($signedIn)<p class="mt-2 text-xs text-ink-600"><a href="{{ route('account.sign-in') }}">Sign in</a> to ask more questions each day.</p>@endunless
                </div>
            </div>
        @endif
    </section>
</x-layouts.public>
