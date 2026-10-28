<x-layouts.public description="Vedic astrology consultations, Kundli, Vastu and remedies with Acharya Dr. Rajnath Jha, Ph.D. in Astrology, Director of Jyotirveda Vigyan Sansthan, Patna.">
    {{-- Hero --}}
    <section class="container-page grid items-center gap-10 pt-10 pb-16 md:grid-cols-[1.1fr_0.9fr] md:pt-16">
        <div>
            <p class="eyebrow">Vedic astrology · Vastu · Remedies</p>
            <h1 class="mt-3 text-4xl leading-tight sm:text-5xl">Clear, practical guidance from {{ $site('brand.practitioner_name') }}</h1>
            <p class="mt-5 max-w-xl text-lg text-ink-600">Ph.D. in Astrology and Director of Jyotirveda Vigyan Sansthan, Patna, with more than 30 years of practice in Jyotish, Vastu, numerology and palmistry.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="#" class="btn-primary">Book a consultation</a>
                <a href="#" class="btn-outline">Get your free Kundli</a>
            </div>
            <dl class="mt-10 grid max-w-lg grid-cols-3 gap-4">
                <div class="card p-4"><dt class="text-xs text-ink-600">Years of practice</dt><dd class="mt-1 text-2xl font-semibold">30+</dd></div>
                <div class="card p-4"><dt class="text-xs text-ink-600">Clients guided</dt><dd class="mt-1 text-2xl font-semibold">1,00,000+</dd></div>
                <div class="card p-4"><dt class="text-xs text-ink-600">Awards</dt><dd class="mt-1 text-2xl font-semibold">13+</dd></div>
            </dl>
        </div>
        <figure class="relative mx-auto w-full max-w-sm">
            <div aria-hidden="true" class="absolute -inset-3 rounded-[2rem] bg-gold-50 ring-1 ring-gold-200"></div>
            <picture>
                <source type="image/webp" srcset="/images/owner/portrait-navy-480.webp 480w, /images/owner/portrait-navy-960.webp 960w" sizes="(min-width: 768px) 24rem, 90vw">
                <img src="/images/owner/portrait-navy-480.jpg" width="480" height="699" alt="{{ $site('brand.practitioner_name') }} at his office in Patna"
                     class="relative aspect-[480/699] w-full rounded-[1.75rem] object-cover shadow-[var(--shadow-card)]" fetchpriority="high">
            </picture>
        </figure>
    </section>

    {{-- Recognition (wording matches the certificate) --}}
    <section class="bg-ivory-100 py-14" id="about">
        <div class="container-page grid gap-8 md:grid-cols-2">
            <div>
                <p class="eyebrow">Recognition</p>
                <h2 class="mt-2 text-2xl">Professor Award 2026</h2>
                <p class="mt-3 text-ink-600">Conferred by IVAF – International Vedic Astrology Federation in recognition of his participation in the Sri Lanka Sacred Astrological Symposium, Kandy, 8 September 2026 (Astrology, Vastu &amp; Gemstone).</p>
            </div>
            <ul class="grid gap-3 text-sm sm:grid-cols-2">
                <li class="card p-4">Ph.D. in Astrology (2014), K.S.D.S.U., Darbhanga</li>
                <li class="card p-4">Acharya (Ganit Jyotish), Gold Medalist</li>
                <li class="card p-4">17 years Jyotish &amp; Karmkand consultant, Jyotish Mandap, Mahavir Mandir Trust, Patna</li>
                <li class="card p-4">"Kaal Dhamal" on Radio Mirchi 98.3 FM</li>
            </ul>
        </div>
    </section>

    {{-- Service families: catalogue arrives in phase 4 --}}
    <section class="container-page py-16" aria-labelledby="services-h">
        <p class="eyebrow">Services</p>
        <h2 id="services-h" class="mt-2 text-2xl">How we can help</h2>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Consultations', 'Chat, audio, video or in-person sessions.'],
                ['Personalised reports', 'Kundli, Varshphal, career, marriage and more.'],
                ['Puja & Chadhava', 'Performed by authorised priests with your Sankalp.'],
                ['Free tools', 'Kundli, matching, Panchang and calculators.'],
            ] as [$t, $d])
                <article class="card p-6">
                    <h3 class="text-base">{{ $t }}</h3>
                    <p class="mt-2 text-sm text-ink-600">{{ $d }}</p>
                    <p class="mt-4"><span class="badge-gold">Opening soon</span></p>
                </article>
            @endforeach
        </div>
    </section>
</x-layouts.public>
