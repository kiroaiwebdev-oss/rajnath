@php
    $courses = [
        ['book', __('Vedic astrology for beginners'), __('Rashi, bhava, graha and nakshatra; reading a birth chart step by step.')],
        ['numbers', __('Numerology'), __('Mulank, Bhagyank, name numbers and the Lo Shu grid.')],
        ['hand', __('Palmistry (Hastrekha)'), __('Lines, mounts and signs of the hand.')],
        ['house', __('Vastu Shastra'), __('Directions, zones and corrections without demolition.')],
        ['kalash', __('Karmkand and puja vidhi'), __('Sankalp, nitya puja and the main samskaras.')],
        ['chart', __('Advanced predictive astrology'), __('Dasha, transits, divisional charts and muhurat.')],
    ];
@endphp
<x-layouts.public :title="__('Astrology courses — coming soon')" :description="__('Courses in Vedic astrology, numerology, palmistry, Vastu and karmkand by Acharya Dr. Rajnath Jha are being prepared.')" image="images/art/courses.jpg"
    :jsonld="[\App\Support\Seo\JsonLd::breadcrumbs([[__('Courses'), route('courses')]])]">
    <section class="container-page grid items-center gap-10 py-12 lg:grid-cols-2">
        <div>
            <span class="badge-gold">{{ __('Coming soon') }}</span>
            <h1 class="mt-4 text-3xl sm:text-4xl">{{ __('Learn Jyotish with Acharya Ji') }}</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-600">{{ __('Acharya Dr. Rajnath Jha has taught at B.H.U. and K.S.D.S.U. and is founding a Vedic Gurukul in Madhubani. Online courses in Hindi and English are being prepared.') }}</p>
            <p class="mt-4 text-ink-600">{{ __('Leave your number below and we will tell you when admissions open.') }}</p>
        </div>
        <x-photo src="images/art/courses.jpg" :alt="__('Astrology courses')" eager class="w-full rounded-[var(--radius-card)] shadow-[var(--shadow-card)]" />
    </section>

    <section class="container-page pb-6" aria-labelledby="planned-h">
        <h2 id="planned-h" class="text-2xl">{{ __('Courses being prepared') }}</h2>
        <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($courses as [$motif, $title, $text])
                <li class="card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gold-700">{{ __('Coming soon') }}</p>
                    <h3 class="mt-1 text-base">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-ink-600">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="container-page py-12">
        <div class="card mx-auto max-w-2xl p-5 sm:p-6">
            <h2 class="text-lg">{{ __('Tell me when admissions open') }}</h2>
            @include('public.partials.enquiry-form', ['offering' => null])
        </div>
    </section>
</x-layouts.public>
