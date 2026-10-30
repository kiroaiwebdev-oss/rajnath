<x-layouts.public title="Terms of use" :description="__('Terms for consultations, reports, puja, gem purchases, payments, refunds and use of this website and app.')">
    <article class="container-page max-w-3xl py-12 leading-7">
        <p class="eyebrow">Legal</p>
        <h1 class="mt-2 text-3xl">Terms of use</h1>
        <p class="mt-2 text-sm text-ink-600">Version {{ \App\Domain\Identity\CustomerAuth::TERMS_VERSION }}</p>
        <div class="mt-8 space-y-6">
            <section><h2 class="text-xl">Nature of the services</h2>
                <p class="mt-2">Astrology, numerology, palmistry, Vastu, mantra and puja are traditional and interpretive practices. Guidance is given in good faith but no particular outcome is promised. It is not medical, psychological, legal, financial or engineering advice and must not replace a qualified professional. Decisions about medical treatment, including the timing of childbirth, remain with your doctors.</p>
            </section>
            <section><h2 class="text-xl">Your account</h2>
                <p class="mt-2">You sign in with a one-time code sent to your email or mobile. Keep these codes private. You are responsible for the accuracy of the birth details you provide; results depend on them.</p>
            </section>
            <section><h2 class="text-xl">Requests, fees and refunds</h2>
                <p class="mt-2">Fees, durations and any cancellation, rescheduling or refund conditions are shown before you pay for a service. Until online payment opens, our team confirms the fee with you before any booking.</p>
            </section>
            <section><h2 class="text-xl">Acceptable use</h2>
                <p class="mt-2">Do not misuse the website or app, attempt to access other people's data, or harass practitioners or staff. We may suspend accounts that do.</p>
            </section>
            <section><h2 class="text-xl">Source code</h2>
                <p class="mt-2">The software running this website is free software under the GNU Affero General Public License v3. You can obtain the source code <a href="{{ $site('source.repository_url') }}" rel="noopener">here</a>. This licence covers the software only, not our brand, photographs or content.</p>
            </section>
            <section><h2 class="text-xl">Contact</h2>
                <p class="mt-2"><a href="mailto:{{ $site('contact.email') }}">{{ $site('contact.email') }}</a> · {{ $site('contact.phone') }}</p>
            </section>
        </div>
    </article>
</x-layouts.public>
