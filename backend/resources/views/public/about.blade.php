<x-layouts.public title="About Acharya Dr. Rajnath Jha" description="Biography, qualifications, awards, research and media work of Acharya Dr. Rajnath Jha, Director, Jyotirveda Vigyan Sansthan, Patna." image="images/owner/portrait-red-480.jpg" type="profile" :jsonld="[['@type' => 'ProfilePage', 'url' => route('about'), 'mainEntity' => ['@id' => \App\Support\Seo\JsonLd::id('founder')], 'dateModified' => '2026-10-28'], \App\Support\Seo\JsonLd::breadcrumbs([['About', route('about')]])]">
    <section class="container-page grid gap-10 py-12 lg:grid-cols-[0.8fr_1.2fr]">
        <figure class="mx-auto w-full max-w-sm">
            <x-photo src="images/owner/portrait-red-480.jpg" alt="Acharya Dr. Rajnath Jha" eager class="aspect-[480/640] w-full rounded-[var(--radius-card)] object-cover object-top shadow-[var(--shadow-card)]" />
        </figure>
        <div>
            <p class="eyebrow">About the Acharya</p>
            <h1 class="mt-2 text-3xl sm:text-4xl">Acharya Dr. Rajnath Jha</h1>
            <p class="mt-2 text-ink-600">Ph.D. in Astrology · Director, Jyotirveda Vigyan Sansthan · Patna, Bihar</p>
            <div class="mt-6 space-y-4 leading-7">
                <p>Acharya Dr. Rajnath Jha is an astrologer from Bihar and Director of Jyotirveda Vigyan Sansthan. His work covers Vedic astrology, horoscope analysis, palmistry, Vastu Shastra and numerology, and he has guided more than 1,00,000 clients over 30+ years.</p>
                <p>For 17 years he served as the Jyotish and Karmkand consultant at Jyotish Mandap, Mahavir Mandir Trust, Patna. He became widely known through the "Kaal Dhamal" programme on Radio Mirchi 98.3 FM, and has been associated with Hindustan, Dainik Jagran and Dainik Bhaskar, and with channels including Maurya TV, ETV Bihar-Jharkhand, News18, Kashish News and Darsh TV.</p>
                <p>He has lectured at Banaras Hindu University and K.S.D.S.U. Darbhanga, and presented research on earthquakes, cancer, arthritis and asthma from the perspective of Jyotish. He is now laying the foundation of a Gurukul in Madhubani to preserve Vedic and Vedanga education.</p>
            </div>
            <dl class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['30+' => 'Years of practice', '1,00,000+' => 'Clients guided', '13+' => 'Awards', '4' => 'Languages'] as $n => $l)
                    <div class="card p-4"><dt class="text-xs text-ink-600">{{ $l }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $n }}</dd></div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="bg-ivory-100 py-14" aria-labelledby="edu-h">
        <div class="container-page grid gap-10 lg:grid-cols-2">
            <div>
                <h2 id="edu-h" class="text-2xl">Education</h2>
                <ol class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        ['Ph.D. (2014) — 1st Division', 'K.S.D.S.U., Darbhanga — "Varahmihiracharyakrit Mayurchitraka: Pathlochan Purvak Samikshatmak Adhyayan"'],
                        ['Acharya (Phalit Jyotish) — 2003, 1st Division', 'K.S.D.S.U., Darbhanga'],
                        ['Acharya (Ganit Jyotish) — 1999, 1st Division, Gold Medalist', 'K.S.D.S.U., Darbhanga'],
                        ['Shastri (Hons.) — 1996', 'K.S.D.S.U., Darbhanga'],
                        ['Upashastri — 1993, 1st Division', 'K.S.D.S.U., Darbhanga'],
                        ['Madhyama — 1991, 1st Division', 'B.S.S.B., Patna'],
                    ] as [$a, $b])
                        <li class="card p-4"><p class="font-semibold">{{ $a }}</p><p class="text-ink-600">{{ $b }}</p></li>
                    @endforeach
                </ol>
            </div>
            <div>
                <h2 class="text-2xl">Positions</h2>
                <ul class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        ['Director — Jyotirveda Vigyan Sansthan', '2011 – present. Research and education in Vedic sciences: agriculture, disaster prediction and health.'],
                        ['Founder & Director — Sanatan Vedic Gurukul, Madhubani', 'April 2024 – present.'],
                        ['International Chief Consultant — International Astrology Federation (USA)', ''],
                        ['Vice Chairman — World Climate Change Foundation, Bihar', ''],
                        ['Astrologer — Jyotish Mandap, Mahavir Mandir, Patna', '2002 – 2016.'],
                        ['Faculty — Vinodanand Research Center, Patna', '2004.'],
                    ] as [$a, $b])
                        <li class="card p-4"><p class="font-semibold">{{ $a }}</p>@if ($b)<p class="text-ink-600">{{ $b }}</p>@endif</li>
                    @endforeach
                </ul>
                <h3 class="mt-8 text-lg">Languages</h3>
                <p class="mt-2 text-sm text-ink-700">Hindi (all dialects), Sanskrit, Maithili, English.</p>
            </div>
        </div>
    </section>

    <section class="container-page py-14" aria-labelledby="aw-h">
        <h2 id="aw-h" class="text-2xl">Awards</h2>
        <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <h3 class="text-base">International</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li class="card p-3"><strong>Professor Award 2026</strong> — IVAF International Vedic Astrology Federation, for participation in the Sri Lanka Sacred Astrological Symposium, Kandy, 8 Sep 2026</li>
                        <li class="card p-3">Global Astrological Award &amp; Lifetime Achievement Honour — Malaysia, 2025</li>
                        <li class="card p-3">US Global Excellence Award — New Delhi, 2024</li>
                        <li class="card p-3">Maharshi Gyan Ratan Award — Vietnam, 2024</li>
                        <li class="card p-3">Jyotish Siromani International Award — Chennai, 2024</li>
                        <li class="card p-3">Hind Gaurav Award — Indore, 2024</li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base">National</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li class="card p-3">Jyotish Samridhi Award — Tamil Nadu, 2024</li>
                        <li class="card p-3">Mithila Ratna Award — Madhubani, 2024</li>
                        <li class="card p-3">Jyotish Gyan Ratna Award — Tirupati, 2023</li>
                        <li class="card p-3">Jyotish Chakravarthy Award — Mumbai, 2023</li>
                        <li class="card p-3">Golden Achievement Award — 28th International Conference, Kolkata, 2005</li>
                        <li class="card p-3">Vishwabandhu Award — 27th International Conference, Kolkata, 2004</li>
                        <li class="card p-3">Jyotish Shri Award — 44th National Astrology Seminar, Madhubani, 2002</li>
                        <li class="card p-3">Gold Medal, Acharya Pariksha (1999) and 1st position, Shalaka Pariksha (1998) — K.S.D.S.U.</li>
                    </ul>
                </div>
            </div>
            <div class="space-y-4">
                <x-photo src="images/owner/award-malaysia-2025-480.jpg" alt="Global Astrological Iconic Award Malaysia 2025" class="w-full rounded-[var(--radius-card)] object-cover" />
                <x-photo src="images/owner/awards-wall-wide-960.jpg" alt="Awards and trophies in Acharya Ji's office" class="w-full rounded-[var(--radius-card)] object-cover" />
            </div>
        </div>
    </section>

    <section class="bg-ivory-100 py-14" aria-labelledby="res-h">
        <div class="container-page grid gap-10 lg:grid-cols-3">
            <div>
                <h2 id="res-h" class="text-xl">Research papers</h2>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-ink-700">
                    <li>Jyotish Shastra ke Drishti se Bhukamp ka Purvanuman — Rashtriya Sanskrit Sansthan, Vedavyas Campus (2020)</li>
                    <li>Kurma Chakra and Bhukamp research — B.H.U., Varanasi (2015)</li>
                    <li>Bhukamp ke Purvanuman ka Jyotishshastriya Anusandhan — K.S.D.S.U., Darbhanga (2016)</li>
                    <li>Papers at B.H.U. (2007–2017) and the Oriental Conference, Tirupati (2010)</li>
                </ul>
            </div>
            <div>
                <h2 class="text-xl">Books</h2>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-ink-700">
                    <li>Mayurchitraka Mimamsa — Varahamihira</li><li>Jyotirved Vigyan</li><li>Jyotish Drishtya Mansik Rog Vimarsh</li>
                    <li>Jyotish Shastra ke Drishti se Netra Rog Vimarsh</li><li>Prakritik Aapda aur Jyotish Shastra</li>
                    <li>Cancer Rog ka Jyotishshastriya Adhyayan</li><li>Ved Manjari, Nitya Karma Ratna Mala &amp; Sanskrit Praveshika</li>
                </ul>
            </div>
            <div>
                <h2 class="text-xl">Media</h2>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-ink-700">
                    <li>Dainik Bhaskar — columnist (2014 – present)</li><li>Dainik Jagran — columnist</li><li>Hindustan — weekly column (2006–2010)</li>
                    <li>Radio Mirchi 98.3 FM &amp; Radio City — "Kaal Dhamal"</li><li>ETV Patna (2010) and Maurya TV (2011) — daily programmes</li>
                    <li>News18 and Kashish News</li><li>Editor — Jyotishprabha Traimasik Patrika, Patna</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="container-page py-14 text-center">
        <h2 class="text-2xl">Speak with Acharya Ji</h2>
        <p class="mx-auto mt-2 max-w-xl text-ink-600">In person in Patna, or by video and audio call.</p>
        <a href="{{ route('services.index', ['family' => 'consultations']) }}" class="btn-primary mt-6">See consultation options</a>
    </section>
</x-layouts.public>
