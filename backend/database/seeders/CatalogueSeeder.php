<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\ServiceFamily;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Safe, idempotent catalogue seed. Content is based on the owner's own previous website.
 * - Services Acharya Ji personally provides today are seeded ACTIVE, with "Price on request"
 *   until the owner enters prices in Admin → Catalogue.
 * - Reports, bundles, puja and chadhava from the feature inventory are seeded as DRAFT (hidden)
 *   because their fulfilment workflows arrive in later phases; puja/chadhava additionally need
 *   an authorised supplier before activation.
 * Existing rows are never overwritten (owner edits win).
 */
class CatalogueSeeder extends Seeder
{
    /** Team astrologer tiers: audio/video/chat, 30 min or 1 hour, couple, normal or urgent. */
    private const TEAM_VARIANTS = [
        ['name' => 'Audio — 30 min', 'mode' => 'audio', 'duration_min' => 30], ['name' => 'Video — 30 min', 'mode' => 'video', 'duration_min' => 30],
        ['name' => 'Chat — 30 min', 'mode' => 'chat', 'duration_min' => 30], ['name' => 'Urgent — audio, 30 min', 'mode' => 'audio', 'duration_min' => 30, 'urgent' => true],
        ['name' => 'Urgent — video, 30 min', 'mode' => 'video', 'duration_min' => 30, 'urgent' => true],
        ['name' => 'Audio — 1 hour', 'mode' => 'audio', 'duration_min' => 60], ['name' => 'Video — 1 hour', 'mode' => 'video', 'duration_min' => 60],
        ['name' => 'Couple — video, 1 hour', 'mode' => 'video', 'duration_min' => 60, 'participants' => 2],
    ];

    /** Ask an Astrologer: one, two or three questions answered in writing. */
    private const QUESTION_VARIANTS = [
        ['name' => 'One question — written answer', 'mode' => 'written'], ['name' => 'Two questions — written answers', 'mode' => 'written'],
        ['name' => 'Three questions — written answers', 'mode' => 'written'],
    ];

    /** Online puja packages: the number of names taken in the sankalp. */
    private const PUJA_PACKAGES = [
        ['name' => 'Single — sankalp for 1 person', 'mode' => 'puja', 'participants' => 1], ['name' => 'Couple — sankalp for 2 people', 'mode' => 'puja', 'participants' => 2],
        ['name' => 'Family — sankalp for up to 6 members', 'mode' => 'puja', 'participants' => 6],
    ];

    private const PUJA_INCLUDES = [
        'Sankalp in your name and gotra (Single: 1 person, Couple: 2 people, Family: up to 6 family members)',
        'Performed by an authorised priest or temple on the scheduled date, with full vidhi and mantra',
        'Date, time and status updates in your account, by email and app notification; our team also messages you on WhatsApp',
        'Photos of the puja in your account, and a video link where the temple allows it',
        'Prasad sent by courier within India with the Family package',
    ];

    private const CHADHAVA_INCLUDES = [
        'Offering made at the temple in your name and gotra',
        'Date and status updates in your account, by email and app notification',
        'Photo of the offering in your account',
    ];

    private const CONSULT_VARIANTS = [
        ['name' => 'In person at Patna office', 'mode' => 'in_person', 'duration_min' => 30],
        ['name' => 'Video call', 'mode' => 'video', 'duration_min' => 30],
        ['name' => 'Audio call', 'mode' => 'audio', 'duration_min' => 30],
    ];

    public function run(): void
    {
        $families = [
            ['consultation', 'Consultations', 'consultations', 'One-to-one guidance with Acharya Dr. Rajnath Jha — in person in Patna, or by video and audio call.', false, 1],
            ['vastu', 'Vastu', 'vastu', 'Vastu analysis for homes, offices, shops and plots, mostly without demolition.', false, 2],
            ['remedy', 'Remedies', 'remedies', 'Gemstones, mantras and Vastu pyramids — prescribed only after studying your chart or floor plan.', false, 3],
            ['puja', 'Puja & rituals', 'puja', 'Vedic puja and samskara ceremonies with correct vidhi, muhurta and mantra.', false, 4],
            ['written_question', 'Ask a question', 'ask-a-question', 'A written answer to your question based on your birth chart.', false, 5],
            ['report', 'Reports', 'reports', 'Personalised written reports based on your birth details.', false, 6],
            ['bundle', 'Report bundles', 'bundles', 'Combinations of related reports.', false, 7],
            ['chadhava', 'Chadhava', 'chadhava', 'Offerings made at temples on your behalf.', true, 8],
        ];
        foreach ($families as [$code, $name, $slug, $summary, $sup, $sort]) {
            ServiceFamily::firstOrCreate(['code' => $code], ['name' => $name, 'slug' => $slug, 'summary' => $summary, 'requires_supplier' => $sup, 'sort' => $sort]);
        }
        $fam = ServiceFamily::pluck('id', 'code');

        // Principal consultation with all modes/durations; team tiers stay draft until real,
        // approved practitioners are added (no invented astrologers).
        $this->offering($fam['consultation'], 'consultation-with-acharya-ji', 'active', [
            'image' => 'images/owner/portrait-navy-480.jpg', 'title' => 'Personal Consultation with Acharya Ji',
            'summary' => 'Speak one-to-one with Acharya Dr. Rajnath Jha about career, marriage, health, finance or any life question.',
            'body' => "Acharya Ji studies your birth chart before and during the session and gives practical guidance with simple remedies.\n\nChoose audio, video or an in-person meeting in Patna, for 30 minutes or 1 hour. Couple sessions include both charts. Urgent sessions are given priority scheduling.",
            'includes' => ['Birth chart reviewed before the call', 'Answers to your questions', 'Remedies suited to your chart', 'Couple option with both charts'],
            'audience' => ['Anyone facing an important decision', 'Couples', 'Families'],
            'disclaimer' => 'Guidance is traditional and interpretive. It does not replace medical, legal or financial advice.',
        ], [
            ['name' => 'Audio call — 30 min', 'mode' => 'audio', 'duration_min' => 30], ['name' => 'Audio call — 1 hour', 'mode' => 'audio', 'duration_min' => 60],
            ['name' => 'Video call — 30 min', 'mode' => 'video', 'duration_min' => 30], ['name' => 'Video call — 1 hour', 'mode' => 'video', 'duration_min' => 60],
            ['name' => 'In person (Patna) — 30 min', 'mode' => 'in_person', 'duration_min' => 30], ['name' => 'In person (Patna) — 1 hour', 'mode' => 'in_person', 'duration_min' => 60],
            ['name' => 'Couple — video, 1 hour', 'mode' => 'video', 'duration_min' => 60, 'participants' => 2], ['name' => 'Couple — in person, 1 hour', 'mode' => 'in_person', 'duration_min' => 60, 'participants' => 2],
            ['name' => 'Urgent — audio, 30 min', 'mode' => 'audio', 'duration_min' => 30, 'urgent' => true], ['name' => 'Urgent — video, 30 min', 'mode' => 'video', 'duration_min' => 30, 'urgent' => true],
        ], 0, true);
        foreach ([['elite-astrologer-consultation', 'Elite Astrologer Consultation'], ['premium-astrologer-consultation', 'Premium Astrologer Consultation'], ['team-astrologer-consultation', 'Team Astrologer Consultation']] as $i => [$slug, $title]) {
            $this->offering($fam['consultation'], $slug, 'draft', ['title' => $title, 'summary' => 'Consultation with an approved astrologer from our team. Opens when practitioners are approved.'], self::TEAM_VARIANTS, 90 + $i);
            $this->ensureVariants($slug, self::TEAM_VARIANTS);
        }
        $this->offering($fam['consultation'], 'baby-muhurat-consultation', 'active', [
            'image' => 'images/owner/writing-navy-480.jpg', 'title' => 'Baby Muhurat Consultation',
            'summary' => 'Guidance on auspicious windows for naming, first feeding, mundan and other samskaras of your child.',
            'body' => 'Acharya Ji studies the Panchang and the family charts to suggest suitable dates and times for your child\'s samskaras.',
            'includes' => ['Namkaran, Annaprashana, Mundan, Karnavedha and Vidyarambh muhurat', 'Dates checked against tithi, nakshatra and vara', 'Simple puja guidance'],
            'audience' => ['New parents', 'Families planning a samskara'],
            'disclaimer' => 'Muhurat guidance is traditional. The timing and method of childbirth are medical decisions for your doctors and must never be changed for astrological reasons.',
        ], [['name' => 'Video call — 30 min', 'mode' => 'video', 'duration_min' => 30], ['name' => 'Audio call — 30 min', 'mode' => 'audio', 'duration_min' => 30], ['name' => 'In person (Patna) — 30 min', 'mode' => 'in_person', 'duration_min' => 30]], 5);
        $this->offering($fam['report'], 'palmistry-photo-report', 'active', [
            'image' => 'images/owner/seated-navy-480.jpg', 'title' => 'Palmistry Report from Photos',
            'summary' => 'Send clear photos of both palms and receive a written palmistry report prepared by Acharya Ji\'s team.',
            'body' => "After payment, upload one photo of each palm from your account. Acharya Ji\'s team studies the lines, mounts and signs and writes your report.\n\nThe report is prepared by a person, not generated by a computer. If a photo is unclear, we will ask you for a new one.",
            'includes' => ['Life, head, heart, fate and sun lines', 'Mounts and special signs', 'Relationship lines', 'Answer to one question you ask', 'Simple guidance and remedies', 'Hindi or English'],
            'audience' => ['People who do not know their birth time', 'Anyone wanting a second view alongside the horoscope'],
            'disclaimer' => 'Palmistry is a traditional interpretive practice. It is not a medical, legal or financial assessment. Photos are kept private and deleted automatically after the report period.',
        ], [['name' => 'Written report (both palms)', 'mode' => 'report']], 3, true);
        $ask = [
            'title' => 'Ask an Astrologer — written answer',
            'summary' => 'Send your question with your birth details and receive a personal written answer from Acharya Ji\'s team, in Hindi or English.',
            'body' => "After payment, choose your birth profile and write your question (for two or three questions, write each on a new line). Acharya Ji's team studies your birth chart and writes the answer, with the reasoning and simple guidance.\n\nThe answer appears in your account and we email you when it is ready, usually within 48 working hours of receiving your birth details.",
            'includes' => ['Answer written by a person after studying your chart', 'Reasoning from your chart', 'Practical advice and simple remedies', 'Hindi or English', 'Answer in your account, with an email when it is ready'],
            'audience' => ['Anyone with one specific question', 'People who prefer a written answer to a call'],
            'disclaimer' => 'Guidance is traditional and interpretive. It does not replace medical, legal or financial advice.',
        ];
        $this->offering($fam['written_question'], 'ask-one-question', 'draft', $ask, self::QUESTION_VARIANTS, 100);
        $this->ensureVariants('ask-one-question', self::QUESTION_VARIANTS, ['One written answer' => 'One question — written answer']);
        $this->fillPlaceholderText('ask-one-question', $ask);

        $this->offering($fam['bundle'], 'navratri-guidance-package', 'draft', ['title' => 'Navratri Guidance Package', 'summary' => 'Seasonal package for Navratri — details to be finalised before publication.'], [['name' => 'Package', 'mode' => 'report']], 130);

        foreach ($this->activeServices() as $i => $s) {
            $this->offering($fam[$s['family']], $s['slug'], 'active', $s, $s['variants'] ?? self::CONSULT_VARIANTS, $i, $s['featured'] ?? false);
        }

        $drafts = [
            'report' => ['Premium Personalized Kundli', 'Couple Kundli Compatibility Report', 'Varshphal (Yearly) Report', 'Yearly Horoscope Report', 'Soul Purpose Report', 'Career Report',
                'Dhanyog / Finance Report', 'Love Report', 'Name Analysis and Correction', 'Fortune Report', 'Fortune Plus Report', 'Baby Name Report', 'Lal Kitab Report',
                'Rahu–Ketu Transit Report', 'Jupiter Transit Report', 'Saturn in Birth Chart Report', 'Sade Sati Report', 'Kaal Sarp and Manglik Dosh Report'],
            'bundle' => ['Career Destiny Bundle', 'Kundli Suraksha Pack', 'Lal Kitab Wisdom Pack', 'Love Compatibility Kit', 'Seasonal Guidance Package'],
            'puja' => array_merge(
                array_map(fn ($g) => "$g Grah Shanti Puja", ['Surya', 'Chandra', 'Mangal', 'Budh', 'Guru', 'Shukra', 'Shani', 'Rahu', 'Ketu']),
                ['Navgrah Shanti Puja'],
                array_map(fn ($l) => "$l Lagna Puja", ['Mesh', 'Vrishabh', 'Mithun', 'Kark', 'Simha', 'Kanya', 'Tula', 'Vrishchik', 'Dhanu', 'Makar', 'Kumbh', 'Meen']),
                ['Mahamrityunjay Puja', 'Rudrabhishek', 'Pitra Dosh Nivaran Puja', 'Kaal Sarp Dosh Nivaran Puja', 'Kaal Bhairav Puja', 'Maa Baglamukhi Puja', 'Rin Mukti Puja',
                    'Vivah Siddhi Puja', 'Uma Maheshwar Puja', 'Parivar Sukh Samriddhi Puja', 'Santan Badha Puja', 'Sarva Karya Siddhi Puja', 'Shatru Badha / Nazar Dosh Puja',
                    'Nasha Mukti Prarthana', 'Videsh Yatra Siddhi Yagya', 'Dhan Lakshmi Ganesh Puja', 'Maha Lakshmi Puja', 'Dhan Lakshmi Navratri Anushthan', 'Durga Ashtami Puja',
                    'Dev Diwali Sarva Devta Puja', 'Maha Gayatri Jaap and Tulsi Vivah', 'Shiv Gauri Puja (Karwa Chauth)', 'Three-day Surya Puja']
            ),
            'chadhava' => ['Shani Grah Chadhava', 'Hanuman Garhi / Bajrangbali Chadhava', 'Mahamrityunjaya Chadhava', 'Rahu Grah Chadhava', 'Maa Baglamukhi Chadhava',
                'Diwali Lakshmi Ganesh Chadhava', 'Karwa Chauth Sindoor Shringar Chadhava'],
        ];
        $variantsFor = ['report' => [['name' => 'PDF report', 'mode' => 'report']], 'bundle' => [['name' => 'Bundle', 'mode' => 'report']],
            'puja' => self::PUJA_PACKAGES, 'chadhava' => [['name' => 'Standard', 'mode' => 'puja']]];
        $includes = ['puja' => self::PUJA_INCLUDES, 'chadhava' => self::CHADHAVA_INCLUDES];
        $disclaimer = [
            'puja' => 'Rituals are performed as a spiritual practice. They are not a guaranteed outcome and do not replace medical, psychological, addiction or legal help.',
            'chadhava' => 'Offerings are a spiritual practice and do not guarantee any outcome.',
        ];
        foreach ($drafts as $family => $titles) {
            foreach ($titles as $i => $title) {
                $slug = \Illuminate\Support\Str::slug(str_replace(['–', '/'], ' ', $title));
                $this->offering($fam[$family], $slug, 'draft', [
                    'title' => $title, 'summary' => $title.' — details to be finalised before publication.',
                    'includes' => $includes[$family] ?? null, 'disclaimer' => $disclaimer[$family] ?? null,
                ], $variantsFor[$family], 100 + $i);
                if ($family === 'puja') {
                    // older installs: the single "Standard" variant becomes the Single package
                    $this->ensureVariants($slug, self::PUJA_PACKAGES, ['Standard' => self::PUJA_PACKAGES[0]['name']]);
                }
                if (isset($includes[$family])) {
                    $this->fillPlaceholderText($slug, ['includes' => $includes[$family]]);
                }
            }
        }

        $faqs = [
            ['How can I book a consultation?', 'Choose a service and send a booking request, call +91-9835258203, or message on WhatsApp. Consultations are available in person at Kankarbagh, Patna, and online by video or audio call.'],
            ['What details do I need for a kundali reading?', 'Your date of birth, time of birth and place of birth. If you do not know your exact birth time, Acharya Ji can still guide you through palmistry and Prashna Kundali.'],
            ['Which languages are consultations available in?', 'Hindi (all dialects), Maithili, Sanskrit and English.'],
            ['What are the office timings?', 'Monday–Wednesday and Friday–Sunday, 8:00 AM–12:00 PM and 3:00 PM–7:00 PM. Closed on Thursday. Online consultations can be scheduled flexibly.'],
            ['Are the remedies expensive or difficult?', 'No. Acharya Ji prefers simple, affordable remedies — mantras, donations, small rituals and lifestyle changes — chosen according to your chart and budget.'],
            ['Is my personal information kept confidential?', 'Yes. Your birth details and the discussion are confidential and are not shared for marketing. See the Privacy Policy for details.'],
            ['Do you recommend gemstones?', 'Only after a complete chart analysis. The recommendation includes the stone, weight, metal, finger, day and mantra for wearing it.'],
            ['Can Vastu be corrected without demolition?', 'In most cases, yes — through placement changes, colours, elements, mirrors and Vastu pyramids.'],
        ];
        if (DB::table('faqs')->count() === 0) {
            foreach ($faqs as $i => [$q, $a]) {
                DB::table('faqs')->insert(['locale' => 'en', 'question' => $q, 'answer' => $a, 'published' => true, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function offering(int $familyId, string $slug, string $status, array $t, array $variants, int $sort, bool $featured = false): void
    {
        if (Offering::where('slug', $slug)->exists()) {
            return;
        }
        $o = Offering::create(['family_id' => $familyId, 'slug' => $slug, 'status' => $status, 'featured' => $featured, 'sort' => $sort, 'image_path' => $t['image'] ?? null]);
        if ($status === 'active') {
            $o->forceFill(['activated_at' => now()])->save();
        }
        $o->translations()->create([
            'locale' => 'en', 'title' => $t['title'], 'summary' => $t['summary'], 'body' => $t['body'] ?? null,
            'includes' => $t['includes'] ?? null, 'audience' => $t['audience'] ?? null, 'disclaimer' => $t['disclaimer'] ?? null,
            'meta_description' => $t['summary'],
        ]);
        foreach ($variants as $i => $v) {
            $o->variants()->create($v + ['sort' => $i, 'participants' => $v['participants'] ?? 1]);
        }
    }

    /**
     * Adds variants missing (by name) from an existing offering, so catalogue additions reach
     * databases seeded earlier. $rename maps an old variant name to a new one when the old
     * variant is otherwise unchanged; prices and owner-added variants are left alone.
     */
    private function ensureVariants(string $slug, array $variants, array $rename = []): void
    {
        $o = Offering::where('slug', $slug)->first();
        if (! $o) {
            return;
        }
        foreach ($rename as $old => $new) {
            if (! $o->variants()->where('name', $new)->exists()) {
                $target = collect($variants)->firstWhere('name', $new);
                $o->variants()->where('name', $old)->update(['name' => $new, 'participants' => $target['participants'] ?? 1]);
            }
        }
        $sort = (int) $o->variants()->max('sort');
        foreach ($variants as $v) {
            if (! $o->variants()->where('name', $v['name'])->exists()) {
                $o->variants()->create($v + ['sort' => ++$sort, 'participants' => $v['participants'] ?? 1]);
            }
        }
    }

    /** Fills English text that is still the seeded placeholder (never overwrites owner text). */
    private function fillPlaceholderText(string $slug, array $t): void
    {
        $o = Offering::where('slug', $slug)->first();
        $tr = $o?->translations()->where('locale', 'en')->first();
        if (! $tr || ! str_ends_with((string) $tr->summary, '— details to be finalised before publication.')) {
            return;
        }
        $fields = array_intersect_key($t, array_flip(['title', 'summary', 'body', 'includes', 'audience', 'disclaimer']));
        if (isset($fields['summary'])) {
            $fields['meta_description'] = $fields['summary'];
        } elseif ($tr->includes) {
            unset($fields['includes']);
        }
        $tr->fill($fields)->save();
    }

    private function activeServices(): array
    {
        $general = 'Guidance is traditional and interpretive. It does not replace medical, legal or financial advice.';

        return [
            ['family' => 'consultation', 'slug' => 'janma-kundali-reading', 'image' => 'images/owner/writing-red-480.jpg', 'featured' => true, 'title' => 'Janma Kundali Reading',
                'summary' => 'A detailed reading of your birth chart — life path, strengths, challenges and timing.',
                'body' => "Your Janma Kundali maps the planets at the moment of your birth. Acharya Dr. Rajnath Jha reads it using the classical Parashari system together with more than 30 years of practice.\n\nEach reading is prepared personally by Acharya Ji. It covers your Lagna, Moon sign, Nakshatra, planetary strengths, yogas, doshas and the Vimshottari Dasha timeline.",
                'includes' => ['Twelve-house birth chart analysis with planetary strengths', 'Lagna, Rashi, Nakshatra and Dasha–Antardasha timeline', 'Career, finance, health, education, marriage and family', 'Yogas and doshas (such as Manglik and Kaal Sarp)', 'Simple remedies suited to your chart'],
                'audience' => ['Anyone wanting a complete picture of their life path', 'People facing important decisions', 'Parents seeking guidance for a child'], 'disclaimer' => $general],
            ['family' => 'consultation', 'slug' => 'love-marriage-compatibility', 'image' => 'images/owner/portrait-red-480.jpg', 'featured' => true, 'title' => 'Love & Marriage Compatibility',
                'summary' => 'Kundali Milan and relationship guidance that goes beyond the 36-guna score.',
                'body' => "Acharya Ji performs a full Ashtakoota Guna Milan and studies the 7th house, Venus, Jupiter, Mangal dosha and the Navamsa (D-9) charts of both partners.\n\nFor love marriages he gives honest, practical guidance along with remedies where needed.",
                'variants' => [['name' => 'Couple — in person', 'mode' => 'in_person', 'duration_min' => 45, 'participants' => 2], ['name' => 'Couple — video call', 'mode' => 'video', 'duration_min' => 45, 'participants' => 2]],
                'includes' => ['Ashtakoota 36-guna milan with explanation', 'Mangal dosha check from Lagna, Moon and Venus', '7th house and 7th lord analysis', 'Navamsa (D-9) comparison', 'Timing of marriage', 'Remedies where appropriate'],
                'audience' => ['Families arranging a marriage', 'Couples planning a love marriage', 'Married couples facing difficulties'], 'disclaimer' => $general],
            ['family' => 'consultation', 'slug' => 'business-astrology', 'image' => 'images/owner/desk-black-480.jpg', 'title' => 'Business Astrology',
                'summary' => 'Timing and direction for launches, partnerships, investments and expansion.',
                'body' => "Acharya Ji studies your personal chart together with the muhurta of your business registration or launch to identify supportive periods, risky periods and suitable sectors.",
                'includes' => ['Business vs. job suitability', 'Suitable business sectors', 'Partner compatibility', 'Muhurta for launch, registration and contracts', 'Strong and weak financial periods ahead', 'Vastu review of office or shop'],
                'audience' => ['Entrepreneurs and business owners', 'People planning a new venture', 'Partners entering an agreement'], 'disclaimer' => $general],
            ['family' => 'consultation', 'slug' => 'numerology', 'image' => 'images/owner/writing-navy-480.jpg', 'title' => 'Numerology',
                'summary' => 'Mulank, Bhagyank and name-number analysis with practical corrections.',
                'body' => 'Acharya Ji combines Chaldean and Vedic numerology to study your root number, destiny number and name number, and suggests simple corrections.',
                'includes' => ['Mulank, Bhagyank and Namank with interpretation', 'Name spelling analysis for people and businesses', 'Favourable dates, days and colours', 'Mobile, vehicle and house number analysis', 'Personal year forecast'],
                'audience' => ['Anyone choosing or correcting a name', 'Business owners naming a brand'], 'disclaimer' => $general],
            ['family' => 'consultation', 'slug' => 'palmistry', 'image' => 'images/owner/seated-navy-480.jpg', 'title' => 'Palmistry (Hastrekha)',
                'summary' => 'Palm reading of lines, mounts and signs — useful even without a birth time.',
                'body' => 'Hastrekha Shastra reads the lines, mounts, shapes and signs of your hands. It works well alongside your horoscope, and can answer important questions when the birth time is unknown.',
                'includes' => ['Life, head, heart and fate lines', 'Planetary mounts', 'Special signs and their meaning', 'Career, relationship and health indications'],
                'audience' => ['People who do not know their birth time', 'Anyone wanting a second view alongside the horoscope'], 'disclaimer' => $general],
            ['family' => 'vastu', 'slug' => 'vastu-consultation', 'image' => 'images/owner/office-wide-960.jpg', 'featured' => true, 'title' => 'Vastu Consultation',
                'summary' => 'Vastu analysis for homes, offices, shops and plots — mostly without demolition.',
                'body' => "Acharya Ji gives practical Vastu corrections using direction remedies, elemental balancing, placement changes and pyramids that suit modern homes and offices.\n\nShare your floor plan for an online review, or book an on-site visit.",
                'variants' => [['name' => 'Online review of floor plan', 'mode' => 'video', 'duration_min' => 45], ['name' => 'On-site visit (Patna)', 'mode' => 'onsite', 'duration_min' => 90]],
                'includes' => ['Direction-wise defect identification with a zone map', 'No-demolition remedies', 'Entrance, kitchen, bedroom, toilet and staircase corrections', 'Vastu for business premises', 'Plot selection and pre-construction planning'],
                'audience' => ['Home buyers and builders', 'Shop and office owners', 'Families facing repeated problems at home'], 'disclaimer' => 'Vastu suggestions are traditional guidance and are not structural or engineering advice.'],
            ['family' => 'remedy', 'slug' => 'gemstone-guidance', 'image' => 'images/owner/writing-red-desk-480.jpg', 'title' => 'Gemstone Guidance',
                'summary' => 'Gemstone recommendations made only after studying your chart.',
                'body' => 'A gemstone is recommended only after studying your Lagna, planetary strengths, current dasha and goals. The recommendation names the stone, weight, metal, finger, day and mantra.',
                'includes' => ['Chart analysis before any recommendation', 'Stone, carat weight, metal and finger', 'Day, time and mantra for first wearing', 'Primary and alternative (uparatna) options', 'Stones to avoid'],
                'audience' => ['Anyone advised to wear a gemstone', 'People already wearing a stone who want it checked'], 'disclaimer' => $general],
            ['family' => 'remedy', 'slug' => 'vastu-pyramid', 'image' => 'images/owner/awards-wall-wide-960.jpg', 'title' => 'Vastu Pyramid Remedies',
                'summary' => 'Pyramid placement plans to correct Vastu defects without construction.',
                'body' => 'Based on your floor plan, Acharya Ji recommends the pyramid type, material, quantity and placement zones. This suits rented homes, flats, offices and shops.',
                'includes' => ['Zone-wise placement plan', 'Choice of pyramid type', 'Remedies for entrance, toilet, kitchen and staircase positions', 'Installation muhurta'],
                'audience' => ['Flat and apartment owners', 'Tenants who cannot alter the structure'], 'disclaimer' => 'Vastu suggestions are traditional guidance and are not structural or engineering advice.'],
            ['family' => 'remedy', 'slug' => 'personalized-mantra', 'image' => 'images/owner/portrait-navy-480.jpg', 'title' => 'Personalised Mantra',
                'summary' => 'A mantra chosen for your chart, with count, pronunciation and routine.',
                'body' => 'After studying your horoscope, current dasha and inclination, Acharya Ji prescribes a mantra with the number of repetitions, correct pronunciation, mala, direction and daily routine.',
                'includes' => ['Ishta mantra selection from your chart', 'Jaap count, mala and schedule', 'Pronunciation and viniyog guidance', 'Beej mantras for weak planets'],
                'audience' => ['Spiritual seekers beginning sadhana', 'Anyone wanting a simple daily remedy'], 'disclaimer' => $general],
            ['family' => 'puja', 'slug' => 'puja-rituals', 'image' => 'images/owner/seated-navy-480.jpg', 'title' => 'Puja & Vedic Rituals by Acharya Ji',
                'summary' => 'Puja and samskara ceremonies at your home, office or a temple, conducted by Acharya Ji.',
                'body' => "Acharya Ji has performed and supervised rituals for over 30 years, including 17 years as Jyotish and Karmkand consultant at Jyotish Mandap, Mahavir Mandir Trust, Patna.\n\nEach puja follows the complete Vedic procedure with the right muhurta and samagri.",
                'variants' => [['name' => 'At your home or office (Patna)', 'mode' => 'puja'], ['name' => 'At a temple (arranged with you)', 'mode' => 'puja']],
                'includes' => ['Rudrabhishek and Maha Mrityunjaya Jaap', 'Navgraha Shanti and graha shanti puja', 'Vastu Shanti, Griha Pravesh and Bhoomi Pujan', 'Kaal Sarp and Pitra Dosh nivaran', 'Vivah, Mundan, Naamkaran and other samskaras', 'Satyanarayan Katha and Sundarkand'],
                'audience' => ['Families planning a ceremony', 'Anyone advised a specific puja'],
                'disclaimer' => 'Rituals are performed as a spiritual practice. They are not a guaranteed outcome and do not replace medical, psychological, addiction or legal help.'],
        ];
    }
}
