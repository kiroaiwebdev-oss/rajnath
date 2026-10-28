<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starting prices at current Indian market rates (October 2026, compared with leading online
 * astrology services). Only fills prices that are still empty, so anything the owner sets in
 * Admin › Catalogue is never overwritten. Expert-prepared reports, bundles and written answers
 * are published once priced; pujas/chadhava stay draft until an authorised priest/temple is
 * linked, and team consultations until an astrologer is approved (publishing rules).
 *
 * Values are rupees; key = offering slug, value = [variant-name fragment => price] or a single price
 * for every variant.
 */
class MarketPriceSeeder extends Seeder
{
    private const CONSULT = ['1 hour' => ['audio' => 3500, 'video' => 4100, 'in_person' => 3500], '30' => ['audio' => 2100, 'video' => 2500, 'in_person' => 2100]];

    public const PRICES = [
        // Acharya Ji — consultations (30 min unless stated)
        'consultation-with-acharya-ji' => 'consult', 'janma-kundali-reading' => 'consult', 'business-astrology' => 'consult', 'numerology' => 'consult',
        'palmistry' => 'consult', 'baby-muhurat-consultation' => 'consult', 'gemstone-guidance' => 'consult', 'personalized-mantra' => 'consult', 'vastu-pyramid' => 'consult',
        'love-marriage-compatibility' => ['in person' => 3100, 'video' => 3100],
        'vastu-consultation' => ['Online' => 5100, 'On-site' => 11000],
        // Team astrologers (bookable only after an astrologer is approved)
        'team-astrologer-consultation' => ['Urgent' => 1799, '*' => 899],
        'premium-astrologer-consultation' => ['Urgent' => 4999, '*' => 2499],
        'elite-astrologer-consultation' => ['Urgent' => 9999, '*' => 4999],
        // Expert-prepared reports
        'premium-personalized-kundli' => 499, 'fortune-report' => 499, 'fortune-plus-report' => 1300, 'love-report' => 499,
        'couple-kundli-compatibility-report' => 511, 'dhanyog-finance-report' => 460, 'career-report' => 999, 'sade-sati-report' => 499,
        'saturn-in-birth-chart-report' => 1170, 'jupiter-transit-report' => 999, 'rahu-ketu-transit-report' => 994, 'kaal-sarp-and-manglik-dosh-report' => 549,
        'lal-kitab-report' => 1699, 'yearly-horoscope-report' => 299, 'varshphal-yearly-report' => 999, 'baby-name-report' => 999,
        'name-analysis-and-correction' => 1100, 'soul-purpose-report' => 799, 'palmistry-photo-report' => 1100,
        'ask-one-question' => 699,
        // Bundles
        'career-destiny-bundle' => 1999, 'kundli-suraksha-pack' => 1499, 'lal-kitab-wisdom-pack' => 2499, 'love-compatibility-kit' => 1299,
        'navratri-guidance-package' => 1100, 'seasonal-guidance-package' => 999,
        // Puja (starting price) — published only with an authorised priest/temple
        'puja-rituals' => ['home' => 5100, 'temple' => 7100],
        'navgrah-shanti-puja' => 21000, 'surya-grah-shanti-puja' => 21000, 'chandra-grah-shanti-puja' => 21000, 'mangal-grah-shanti-puja' => 21000,
        'budh-grah-shanti-puja' => 21000, 'guru-grah-shanti-puja' => 21000, 'shukra-grah-shanti-puja' => 21000, 'shani-grah-shanti-puja' => 21000,
        'rahu-grah-shanti-puja' => 21000, 'ketu-grah-shanti-puja' => 21000,
        'mesh-lagna-puja' => 11000, 'vrishabh-lagna-puja' => 11000, 'mithun-lagna-puja' => 11000, 'kark-lagna-puja' => 11000, 'simha-lagna-puja' => 11000,
        'kanya-lagna-puja' => 11000, 'tula-lagna-puja' => 11000, 'vrishchik-lagna-puja' => 11000, 'dhanu-lagna-puja' => 11000, 'makar-lagna-puja' => 11000,
        'kumbh-lagna-puja' => 11000, 'meen-lagna-puja' => 11000,
        'mahamrityunjay-puja' => 11000, 'kaal-sarp-dosh-nivaran-puja' => 11000, 'pitra-dosh-nivaran-puja' => 11000, 'videsh-yatra-siddhi-yagya' => 11000,
        'uma-maheshwar-puja' => 11000, 'dhan-lakshmi-navratri-anushthan' => 11000,
        'shatru-badha-nazar-dosh-puja' => 5100, 'vivah-siddhi-puja' => 5100, 'rin-mukti-puja' => 5100, 'durga-ashtami-puja' => 5100, 'kaal-bhairav-puja' => 5100,
        'sarva-karya-siddhi-puja' => 5100,
        'maha-lakshmi-puja' => 899, 'parivar-sukh-samriddhi-puja' => 899, 'santan-badha-puja' => 899, 'maha-gayatri-jaap-and-tulsi-vivah' => 899,
        'dev-diwali-sarva-devta-puja' => 899, 'three-day-surya-puja' => 899,
        'rudrabhishek' => 501, 'maa-baglamukhi-puja' => 501, 'nasha-mukti-prarthana' => 501, 'shiv-gauri-puja-karwa-chauth' => 501, 'dhan-lakshmi-ganesh-puja' => 501,
        // Chadhava (starting price)
        'shani-grah-chadhava' => 251, 'rahu-grah-chadhava' => 251, 'hanuman-garhi-bajrangbali-chadhava' => 251, 'mahamrityunjaya-chadhava' => 251,
        'maa-baglamukhi-chadhava' => 251, 'diwali-lakshmi-ganesh-chadhava' => 251, 'karwa-chauth-sindoor-shringar-chadhava' => 251,
    ];

    /** Families published automatically once priced (fulfilment needs no outside partner). */
    public const AUTO_PUBLISH = ['report', 'bundle', 'written_question'];

    public function run(): void
    {
        $settings = app(\App\Support\Settings\SettingsRepository::class);
        if ($settings->get('catalogue.market_prices_applied')) {
            return; // applied once; later changes belong to the owner
        }
        $offerings = DB::table('offerings as o')->join('service_families as f', 'f.id', '=', 'o.family_id')->get(['o.id', 'o.slug', 'o.status', 'f.code']);
        $priced = 0;
        $published = 0;
        foreach ($offerings as $o) {
            $rule = self::PRICES[$o->slug] ?? null;
            if ($rule === null) {
                continue;
            }
            foreach (DB::table('offering_variants')->where('offering_id', $o->id)->whereNull('price_minor')->get() as $v) {
                $rupees = $this->priceFor($rule, $v);
                if ($rupees) {
                    DB::table('offering_variants')->where('id', $v->id)->update(['price_minor' => $rupees * 100, 'updated_at' => now()]);
                    $priced++;
                }
            }
            $hasTitle = DB::table('offering_translations')->where('offering_id', $o->id)->where('locale', 'en')->whereNotNull('summary')->exists();
            $allPriced = ! DB::table('offering_variants')->where('offering_id', $o->id)->where('active', true)->whereNull('price_minor')->exists();
            if ($o->status === 'draft' && in_array($o->code, self::AUTO_PUBLISH, true) && $hasTitle && $allPriced && ! DB::table('offerings')->where('id', $o->id)->whereNotNull('activated_at')->exists()) {
                DB::table('offerings')->where('id', $o->id)->update(['status' => 'active', 'activated_at' => now(), 'updated_at' => now()]);
                $published++;
            }
        }
        $settings->set('catalogue.market_prices_applied', now()->toDateString());
        $this->command?->info("Market prices: $priced variants priced, $published services published.");
    }

    private function priceFor(string|int|array $rule, object $v): ?int
    {
        if (is_int($rule)) {
            return $rule;
        }
        if ($rule === 'consult') {
            $len = (int) $v->duration_min >= 60 ? '1 hour' : '30';

            return self::CONSULT[$len][$v->mode] ?? self::CONSULT[$len]['audio'];
        }
        foreach ($rule as $fragment => $price) {
            if ($fragment !== '*' && stripos($v->name, $fragment) !== false) {
                return $price;
            }
        }

        return $rule['*'] ?? null;
    }
}
