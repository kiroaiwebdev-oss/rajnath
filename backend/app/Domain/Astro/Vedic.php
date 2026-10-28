<?php

namespace App\Domain\Astro;

/** Reference tables for Vedic (sidereal) astrology. Sources listed in docs/astro/methods.md. */
final class Vedic
{
    public const SIGNS = ['Mesha (Aries)', 'Vrishabha (Taurus)', 'Mithuna (Gemini)', 'Karka (Cancer)', 'Simha (Leo)', 'Kanya (Virgo)',
        'Tula (Libra)', 'Vrishchika (Scorpio)', 'Dhanu (Sagittarius)', 'Makara (Capricorn)', 'Kumbha (Aquarius)', 'Meena (Pisces)'];

    public const SIGN_LORDS = ['mars', 'venus', 'mercury', 'moon', 'sun', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'saturn', 'jupiter'];

    public const NAKSHATRAS = ['Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashira', 'Ardra', 'Punarvasu', 'Pushya', 'Ashlesha', 'Magha',
        'Purva Phalguni', 'Uttara Phalguni', 'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha', 'Mula', 'Purva Ashadha',
        'Uttara Ashadha', 'Shravana', 'Dhanishta', 'Shatabhisha', 'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati'];

    /** Vimshottari order starting from Ashwini's lord, and years. */
    public const DASHA_ORDER = ['ketu', 'venus', 'sun', 'moon', 'mars', 'rahu', 'jupiter', 'saturn', 'mercury'];
    public const DASHA_YEARS = ['ketu' => 7, 'venus' => 20, 'sun' => 6, 'moon' => 10, 'mars' => 7, 'rahu' => 18, 'jupiter' => 16, 'saturn' => 19, 'mercury' => 17];

    public const PLANET_NAMES = ['sun' => 'Surya (Sun)', 'moon' => 'Chandra (Moon)', 'mars' => 'Mangal (Mars)', 'mercury' => 'Budh (Mercury)',
        'jupiter' => 'Guru (Jupiter)', 'venus' => 'Shukra (Venus)', 'saturn' => 'Shani (Saturn)', 'rahu' => 'Rahu', 'ketu' => 'Ketu'];

    public const TITHIS = ['Pratipada', 'Dwitiya', 'Tritiya', 'Chaturthi', 'Panchami', 'Shashthi', 'Saptami', 'Ashtami', 'Navami', 'Dashami',
        'Ekadashi', 'Dwadashi', 'Trayodashi', 'Chaturdashi'];

    public const YOGAS = ['Vishkambha', 'Priti', 'Ayushman', 'Saubhagya', 'Shobhana', 'Atiganda', 'Sukarma', 'Dhriti', 'Shula', 'Ganda', 'Vriddhi',
        'Dhruva', 'Vyaghata', 'Harshana', 'Vajra', 'Siddhi', 'Vyatipata', 'Variyana', 'Parigha', 'Shiva', 'Siddha', 'Sadhya', 'Shubha', 'Shukla',
        'Brahma', 'Indra', 'Vaidhriti'];

    public const VARAS = ['Ravivara (Sunday)', 'Somavara (Monday)', 'Mangalavara (Tuesday)', 'Budhavara (Wednesday)', 'Guruvara (Thursday)', 'Shukravara (Friday)', 'Shanivara (Saturday)'];

    public static function sign(float $lon): int
    {
        return ((int) floor(fmod($lon + 360, 360) / 30)) % 12;
    }

    public static function nakshatra(float $lon): int
    {
        return ((int) floor(fmod($lon + 360, 360) * 27 / 360)) % 27;
    }

    public static function pada(float $lon): int
    {
        return ((int) floor(fmod($lon + 360, 360) * 108 / 360)) % 4 + 1;
    }

    public static function navamsaSign(float $lon): int
    {
        return ((int) floor(fmod($lon + 360, 360) * 9 / 30)) % 12;
    }

    public static function dms(float $lonInSign): string
    {
        $d = (int) floor($lonInSign);
        $m = (int) floor(($lonInSign - $d) * 60);
        $s = (int) round((($lonInSign - $d) * 60 - $m) * 60);
        if ($s === 60) {
            $s = 0;
            $m++;
        }

        return sprintf('%d°%02d′%02d″', $d, $m, $s);
    }

    public static function tithiName(int $i): string
    {
        // 0..29: Shukla Pratipada .. Purnima (14), Krishna Pratipada .. Amavasya (29).
        if ($i === 14) {
            return 'Purnima';
        }
        if ($i === 29) {
            return 'Amavasya';
        }

        return self::TITHIS[$i % 15];
    }

    public static function paksha(int $tithi): string
    {
        return $tithi < 15 ? 'Shukla' : 'Krishna';
    }

    public static function karanaName(int $i): string
    {
        if ($i === 0) {
            return 'Kimstughna';
        }
        if ($i >= 57) {
            return ['Shakuni', 'Chatushpada', 'Naga'][$i - 57];
        }

        return ['Bava', 'Balava', 'Kaulava', 'Taitila', 'Garaja', 'Vanija', 'Vishti (Bhadra)'][($i - 1) % 7];
    }
}
