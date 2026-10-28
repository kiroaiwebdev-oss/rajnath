<x-layouts.public description="Vedic astrology consultations, Kundli, Vastu and remedies with Acharya Dr. Rajnath Jha, Ph.D. in Astrology, Director of Jyotirveda Vigyan Sansthan, Patna.">
    {{-- Hero --}}
    <section class="container-page grid items-center gap-10 pt-10 pb-16 md:grid-cols-[1.1fr_0.9fr] md:pt-16">
        <div>
            <p class="eyebrow">Vedic astrology · Vastu · Remedies</p>
            <h1 class="mt-3 text-4xl leading-tight sm:text-5xl">Clear, practical guidance from {{ $site('brand.practitioner_name') }}</h1>
            <p class="mt-5 max-w-xl text-lg text-ink-600">Ph.D. in Astrology and Director of Jyotirveda Vigyan Sansthan, Patna, with more than 30 years of practice in Jyotish, Vastu, numerology and palmistry.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary">Book a consultation</a>
                <a href="{{ route('services.index') }}" class="btn-outline">Browse all services</a>
            </div>
            <dl class="mt-10 grid max-w-lg grid-cols-3 gap-3 sm:gap-4">
                <div class="card p-4"><dt class="text-xs text-ink-600">Years of practice</dt><dd class="mt-1 text-xl font-semibold sm:text-2xl">30+</dd></div>
                <div class="card p-4"><dt class="text-xs text-ink-600">Clients guided</dt><dd class="mt-1 text-xl font-semibold sm:text-2xl">1,00,000+</dd></div>
                <div class="card p-4"><dt class="text-xs text-ink-600">Awards</dt><dd class="mt-1 text-xl font-semibold sm:text-2xl">13+</dd></div>
            </dl>
        </div>
        <figure class="relative mx-auto w-full max-w-sm">
            <div aria-hidden="true" class="absolute -inset-3 rounded-[2rem] bg-gold-50 ring-1 ring-gold-200"></div>
            <x-photo src="images/owner/portrait-navy-960.jpg" :alt="$site('brand.practitioner_name').' at his office in Patna'" eager
                     class="relative aspect-[480/699] w-full rounded-[1.75rem] object-cover shadow-[var(--shadow-card)]" />
        </figure>
    </section>

    {{-- Featured services from the live catalogue --}}
    @if ($featured->isNotEmpty())
    <section class="container-page pb-16" aria-labelledby="featured-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Services</p>
                <h2 id="featured-h" class="mt-2 text-2xl">How Acharya Ji can help</h2>
            </div>
            <a href="{{ route('services.index') }}" class="btn-outline">All services</a>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($featured as $o)<div class="relative"><x-service-card :o="$o" /></div>@endforeach
        </div>
    </section>
    @endif

    {{-- Recognition --}}
    <section class="bg-ivory-100 py-16" aria-labelledby="rec-h">
        <div class="container-page grid items-center gap-10 lg:grid-cols-2">
            <div>
                <p class="eyebrow">Recognition</p>
                <h2 id="rec-h" class="mt-2 text-2xl">Honoured in India and abroad</h2>
                <ul class="mt-6 space-y-3 text-sm">
                    <li class="card p-4"><strong>Professor Award 2026</strong> — IVAF International Vedic Astrology Federation, in recognition of his participation in the Sri Lanka Sacred Astrological Symposium, Kandy, 8 September 2026 (Astrology, Vastu &amp; Gemstone).</li>
                    <li class="card p-4"><strong>Global Astrological Iconic Award, Malaysia 2025</strong> — Subramanyam Astrological &amp; Spiritual Convocation.</li>
                    <li class="card p-4"><strong>Ph.D. in Astrology (2014)</strong> and <strong>Acharya (Ganit Jyotish), Gold Medalist</strong> — K.S.D.S.U., Darbhanga.</li>
                    <li class="card p-4"><strong>17 years</strong> as Jyotish &amp; Karmkand consultant, Jyotish Mandap, Mahavir Mandir Trust, Patna.</li>
                </ul>
                <a href="{{ route('about') }}" class="btn-outline mt-6">Full biography & awards</a>
            </div>
            <div class="grid grid-cols-[1fr_0.75fr] gap-4">
                <x-photo src="images/owner/awards-wall-wide-960.jpg" alt="Acharya Ji in front of his awards and trophies" class="h-full w-full rounded-[var(--radius-card)] object-cover" />
                <x-photo src="images/owner/award-malaysia-2025-480.jpg" alt="Global Astrological Iconic Award Malaysia 2025 trophy" class="h-full w-full rounded-[var(--radius-card)] object-cover" />
            </div>
        </div>
    </section>

    {{-- Families --}}
    <section class="container-page py-16" aria-labelledby="fam-h">
        <p class="eyebrow">Find what you need</p>
        <h2 id="fam-h" class="mt-2 text-2xl">Browse by category</h2>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($families as $f)
                <li class="card relative p-5">
                    <h3 class="text-base"><a href="{{ route('services.index', ['family' => $f->slug]) }}" class="no-underline after:absolute after:inset-0">{{ $f->name }}</a></h3>
                    <p class="mt-2 text-sm text-ink-600">{{ $f->summary }}</p>
                    <p class="mt-3 text-xs font-semibold text-gold-700">{{ $f->offerings_count }} {{ \Illuminate\Support\Str::plural('service', $f->offerings_count) }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Visit --}}
    <section class="container-page" aria-labelledby="visit-h">
        <div class="card grid overflow-hidden md:grid-cols-2">
            <x-photo src="images/owner/office-wide-960.jpg" alt="Acharya Ji at work in his Kankarbagh office" class="h-full min-h-56 w-full object-cover" />
            <div class="p-6 sm:p-8">
                <p class="eyebrow">Visit or call</p>
                <h2 id="visit-h" class="mt-2 text-2xl">Kankarbagh, Patna</h2>
                <p class="mt-3 text-ink-600">B-Block, 1st Floor, Chanakya Plaza, near Chandan Automobile, Kankarbagh Main Road, Patna 800020.</p>
                <p class="mt-3 text-sm text-ink-600">Mon–Wed and Fri–Sun: 8 AM–12 PM and 3 PM–7 PM · Thursday closed.<br>Online consultations by video or audio call.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a href="tel:{{ $site('contact.phone') }}" class="btn-primary">Call {{ $site('contact.phone') }}</a>
                    <a href="{{ route('contact') }}" class="btn-outline">Directions & contact</a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
