<x-layouts.public title="Questions & answers" description="Answers to common questions about consultations, birth details, languages, timings, remedies and privacy.">
    <div class="container-page max-w-3xl py-12">
        <p class="eyebrow">Help</p>
        <h1 class="mt-2 text-3xl">Questions & answers</h1>
        <div class="mt-8 space-y-3">
            @forelse ($faqs as $f)
                <details class="card group p-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                        {{ $f->question }}<span aria-hidden="true" class="text-gold-700 transition group-open:rotate-45">+</span>
                    </summary>
                    <p class="mt-3 leading-7 text-ink-700">{{ $f->answer }}</p>
                </details>
            @empty
                <p class="card p-6 text-center text-ink-600">No questions published yet.</p>
            @endforelse
        </div>
        <p class="mt-10 text-center text-ink-600">Still have a question? <a href="{{ route('contact') }}">Contact us</a>.</p>
    </div>
</x-layouts.public>
