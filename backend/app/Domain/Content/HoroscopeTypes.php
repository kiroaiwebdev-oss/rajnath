<?php

namespace App\Domain\Content;

use App\Domain\Astro\Gochar;
use App\Domain\Astro\Vedic;
use App\Http\Controllers\Web\ContentController;
use Carbon\CarbonImmutable;

/**
 * Kinds, topics and periods of staff-written horoscopes (table `horoscopes`).
 * `sign` stores the subject index within the kind: 0–11 rashi/tarot/Chinese sign, 0–26 nakshatra,
 * 0–8 mulank 1–9; for gochar, the Moon sign 0–11. Topics: Rashifal topics for rashi, the
 * transiting planet for gochar, "general" for every other kind.
 */
final class HoroscopeTypes
{
    public const PERIODS = [
        'daily' => ['आज का राशिफल', 'Today'], 'weekly' => ['साप्ताहिक', 'This week'], 'monthly' => ['मासिक', 'This month'],
        'halfyearly' => ['अर्धवार्षिक', 'Half-yearly'], 'yearly' => ['वार्षिक', 'Yearly'],
    ];

    public const TOPICS = [
        'general' => ['सामान्य', 'General'], 'love' => ['प्रेम', 'Love'], 'career' => ['करियर', 'Career'],
        'finance' => ['धन', 'Finance'], 'health' => ['स्वास्थ्य', 'Health'], 'education' => ['शिक्षा', 'Education'],
    ];

    public const KINDS = [
        'rashi' => ['राशिफल', 'Rashifal (Moon sign)'], 'nakshatra' => ['नक्षत्र फल', 'Nakshatra horoscope'], 'chinese' => ['चीनी राशिफल', 'Chinese horoscope'],
        'numerology' => ['अंक ज्योतिष', 'Numerology forecast'], 'tarot' => ['टैरो', 'Tarot reading'],
        'gochar' => ['गोचर फल', 'Transit effects (by Moon sign)'],
    ];

    /** Kinds listed on the public Rashifal pages (gochar notes appear on the transit pages). */
    public const PUBLIC_KINDS = ['rashi', 'nakshatra', 'chinese', 'numerology', 'tarot'];

    /** Allowed topics per kind: Rashifal topics, the transiting planet for gochar, otherwise general. */
    public static function topics(string $kind): array
    {
        return match ($kind) {
            'rashi' => array_keys(self::TOPICS),
            'gochar' => Gochar::PLANETS,
            default => ['general'],
        };
    }

    public const NAKSHATRAS_HI = ['अश्विनी', 'भरणी', 'कृत्तिका', 'रोहिणी', 'मृगशिरा', 'आर्द्रा', 'पुनर्वसु', 'पुष्य', 'आश्लेषा', 'मघा', 'पूर्वा फाल्गुनी', 'उत्तरा फाल्गुनी', 'हस्त', 'चित्रा',
        'स्वाति', 'विशाखा', 'अनुराधा', 'ज्येष्ठा', 'मूल', 'पूर्वाषाढ़ा', 'उत्तराषाढ़ा', 'श्रवण', 'धनिष्ठा', 'शतभिषा', 'पूर्वा भाद्रपद', 'उत्तरा भाद्रपद', 'रेवती'];

    public const CHINESE = ['Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake', 'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'];

    public const CHINESE_HI = ['चूहा', 'बैल', 'बाघ', 'खरगोश', 'ड्रैगन', 'सांप', 'घोड़ा', 'बकरी', 'बंदर', 'मुर्गा', 'कुत्ता', 'सूअर'];

    /** Subject names for a kind, in Hindi or English. */
    public static function subjects(string $kind, string $locale = 'en'): array
    {
        $hi = $locale === 'hi';

        return match ($kind) {
            'rashi', 'tarot', 'gochar' => $hi ? ContentController::SIGNS_HI : Vedic::SIGNS,
            'nakshatra' => $hi ? self::NAKSHATRAS_HI : Vedic::NAKSHATRAS,
            'chinese' => $hi ? self::CHINESE_HI : self::CHINESE,
            'numerology' => array_map(fn ($n) => ($hi ? 'मूलांक ' : 'Mulank ').$n, range(1, 9)),
        };
    }

    /** End date (inclusive) of a period that starts on $start. */
    public static function endOf(string $period, CarbonImmutable $start): CarbonImmutable
    {
        return match ($period) {
            'daily' => $start, 'weekly' => $start->addDays(6), 'monthly' => $start->endOfMonth(),
            'halfyearly' => $start->addMonthsNoOverflow(6)->subDay(), 'yearly' => $start->endOfYear(),
        };
    }

    /** Chinese zodiac animal index for a Gregorian year (approximate: the Chinese year starts in Jan/Feb). */
    public static function chineseAnimal(int $year): int
    {
        return (($year - 4) % 12 + 12) % 12;
    }
}
