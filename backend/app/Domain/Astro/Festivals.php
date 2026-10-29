<?php

namespace App\Domain\Astro;

use DateTimeImmutable;
use DateTimeZone;
use IntlCalendar;

/**
 * Yearly festival and holiday calendar for Hindu, Islamic, Christian, Sikh, Jain and Buddhist
 * observances. Method (see docs/astro/methods.md, "Festivals"):
 * - Lunar festivals: amanta month (LunarCalendar) + tithi, observed on the local day on which the
 *   tithi prevails at the time its tradition requires (sunrise, midday, afternoon, pradosh,
 *   nishita or moonrise). Adhik months are skipped. If the tithi never touches that time
 *   (kshaya), the day the tithi begins is used.
 * - Solar: Makar and Mesha Sankranti from the Sun's sidereal ingress; after sunset → next day.
 * - Christian: Gregorian Easter (computus) and fixed dates.
 * - Islamic: Umm al-Qura calendar (ICU). Indian dates often fall one day later because they
 *   follow local moon sighting; this is shown on the page.
 * Regional and sampradaya differences (Smarta/Vaishnava Janmashtami, Bhadra for Raksha
 * Bandhan/Holika Dahan, etc.) are not modelled; the page says so.
 */
class Festivals
{
    public const RELIGIONS = ['hindu' => 'Hindu', 'islamic' => 'Islamic', 'christian' => 'Christian', 'sikh' => 'Sikh', 'jain' => 'Jain', 'buddhist' => 'Buddhist'];

    public const RULES = ['sunrise' => 'at sunrise', 'purvahna' => 'in the forenoon (purvahna)', 'madhyahna' => 'at midday', 'aparahna' => 'in the afternoon (aparahna)', 'pradosh' => 'in the evening (pradosh)', 'nishita' => 'at midnight (nishita)', 'moonrise' => 'at moonrise', 'holika' => 'in the evening, after Bhadra'];

    /** [key, name, religion, amanta masa 0=Chaitra, tithi 0..29 (14 Purnima, 29 Amavasya), rule] */
    public const LUNAR = [
        ['chaitra-navratri', 'Chaitra Navratri begins (Ugadi, Gudi Padwa)', 'hindu', 0, 0, 'sunrise'],
        ['ram-navami', 'Ram Navami', 'hindu', 0, 8, 'madhyahna'],
        ['mahavir-jayanti', 'Mahavir Jayanti', 'jain', 0, 12, 'sunrise'],
        ['hanuman-jayanti', 'Hanuman Jayanti', 'hindu', 0, 14, 'sunrise'],
        ['akshaya-tritiya', 'Akshaya Tritiya', 'hindu', 1, 2, 'purvahna'],
        ['buddha-purnima', 'Buddha Purnima (Vesak)', 'buddhist', 1, 14, 'sunrise'],
        ['vat-savitri', 'Vat Savitri Vrat', 'hindu', 1, 29, 'aparahna'],
        ['devshayani-ekadashi', 'Devshayani Ekadashi (Chaturmas begins)', 'hindu', 3, 10, 'sunrise'],
        ['guru-purnima', 'Guru Purnima', 'hindu', 3, 14, 'sunrise'],
        ['dhamma-day', 'Asalha Puja (Dhamma Day)', 'buddhist', 3, 14, 'sunrise'],
        ['nag-panchami', 'Nag Panchami', 'hindu', 4, 4, 'sunrise'],
        ['raksha-bandhan', 'Raksha Bandhan', 'hindu', 4, 14, 'sunrise'],
        ['janmashtami', 'Krishna Janmashtami', 'hindu', 4, 22, 'nishita'],
        ['hartalika-teej', 'Hartalika Teej', 'hindu', 5, 2, 'sunrise'],
        ['ganesh-chaturthi', 'Ganesh Chaturthi', 'hindu', 5, 3, 'madhyahna'],
        ['samvatsari', 'Samvatsari (Paryushan ends, Shvetambar)', 'jain', 5, 3, 'sunrise'],
        ['das-lakshana', 'Das Lakshana Parva begins (Digambar)', 'jain', 5, 4, 'sunrise'],
        ['anant-chaturdashi', 'Anant Chaturdashi', 'hindu', 5, 13, 'sunrise'],
        ['pitru-paksha', 'Pitru Paksha begins', 'hindu', 5, 15, 'aparahna'],
        ['jitiya', 'Jivitputrika (Jitiya) Vrat', 'hindu', 5, 22, 'sunrise'],
        ['mahalaya', 'Mahalaya / Sarva Pitru Amavasya', 'hindu', 5, 29, 'aparahna'],
        ['sharad-navratri', 'Sharad Navratri begins (Ghatasthapana)', 'hindu', 6, 0, 'sunrise'],
        ['durga-ashtami', 'Durga Ashtami', 'hindu', 6, 7, 'sunrise'],
        ['maha-navami', 'Maha Navami', 'hindu', 6, 8, 'sunrise'],
        ['dussehra', 'Dussehra (Vijayadashami)', 'hindu', 6, 9, 'aparahna'],
        ['sharad-purnima', 'Sharad Purnima', 'hindu', 6, 14, 'pradosh'],
        ['karwa-chauth', 'Karwa Chauth', 'hindu', 6, 18, 'moonrise'],
        ['dhanteras', 'Dhanteras', 'hindu', 6, 27, 'pradosh'],
        ['narak-chaturdashi', 'Narak Chaturdashi (Chhoti Diwali)', 'hindu', 6, 28, 'sunrise'],
        ['diwali', 'Diwali (Lakshmi Puja)', 'hindu', 6, 29, 'pradosh'],
        ['bandi-chhor', 'Bandi Chhor Divas', 'sikh', 6, 29, 'pradosh'],
        ['mahavir-nirvan', 'Mahavir Nirvan Divas', 'jain', 6, 29, 'pradosh'],
        ['govardhan', 'Govardhan Puja', 'hindu', 7, 0, 'sunrise'],
        ['bhai-dooj', 'Bhai Dooj', 'hindu', 7, 1, 'aparahna'],
        ['chhath', 'Chhath Puja (Sandhya Arghya)', 'hindu', 7, 5, 'pradosh'],
        ['dev-uthani', 'Dev Uthani (Prabodhini) Ekadashi', 'hindu', 7, 10, 'sunrise'],
        ['tulsi-vivah', 'Tulsi Vivah', 'hindu', 7, 11, 'sunrise'],
        ['dev-diwali', 'Kartik Purnima / Dev Diwali', 'hindu', 7, 14, 'pradosh'],
        ['guru-nanak-jayanti', 'Guru Nanak Jayanti (Gurpurab)', 'sikh', 7, 14, 'sunrise'],
        ['gita-jayanti', 'Gita Jayanti (Mokshada Ekadashi)', 'hindu', 8, 10, 'sunrise'],
        ['guru-gobind-singh-jayanti', 'Guru Gobind Singh Jayanti', 'sikh', 9, 6, 'sunrise'],
        ['vasant-panchami', 'Vasant Panchami (Saraswati Puja)', 'hindu', 10, 4, 'purvahna'],
        ['maha-shivaratri', 'Maha Shivaratri', 'hindu', 10, 28, 'nishita'],
        ['holika-dahan', 'Holika Dahan', 'hindu', 11, 14, 'holika'],
    ];

    /** [key, name, religion, base key, day offset] */
    public const DERIVED = [
        ['holi', 'Holi', 'hindu', 'holika-dahan', 1],
        ['hola-mohalla', 'Hola Mohalla', 'sikh', 'holika-dahan', 2],
        ['paryushan', 'Paryushan begins (Shvetambar)', 'jain', 'samvatsari', -7],
        ['lohri', 'Lohri', 'hindu', 'makar-sankranti', -1],
    ];

    /** [hijri month 1..12, day, name] */
    public const ISLAMIC = [
        [1, 1, 'Islamic New Year (1 Muharram)'], [1, 10, 'Ashura (10 Muharram)'], [3, 12, 'Milad-un-Nabi (12 Rabi al-Awwal)'],
        [7, 27, 'Shab-e-Miraj (27 Rajab, night before)'], [8, 15, "Shab-e-Barat (15 Sha'ban, night before)"], [9, 1, 'Ramadan begins (1 Ramadan)'],
        [9, 27, 'Shab-e-Qadr (27 Ramadan, night before)'], [10, 1, 'Eid al-Fitr (1 Shawwal)'], [12, 10, 'Eid al-Adha / Bakrid (10 Dhu al-Hijjah)'],
    ];

    private DateTimeZone $tz;

    private float $lat;

    private float $lng;

    private array $days = [];

    public function __construct(private Engine $engine, private LunarCalendar $lunar) {}

    /** @return list<array{date: string, key: string, name: string, religion: string, basis: string}> */
    public function year(int $year, float $lat, float $lng, string $tz): array
    {
        [$this->lat, $this->lng, $this->tz, $this->days] = [$lat, $lng, new DateTimeZone($tz), []];
        $jan1 = Engine::jd((new DateTimeImmutable("$year-01-01 00:00:00", $this->tz))->setTimezone(new DateTimeZone('UTC')));
        $dec31 = Engine::jd((new DateTimeImmutable("$year-12-31 23:59:59", $this->tz))->setTimezone(new DateTimeZone('UTC')));
        $out = [];

        $months = $this->lunar->months($jan1 - 31, $dec31 + 1);
        foreach (self::LUNAR as [$key, $name, $religion, $masa, $tithi, $rule]) {
            foreach ($months as $m) {
                if ($m['masa'] !== $masa || $m['adhik']) {
                    continue;
                }
                $date = $this->lunarDate($m, $tithi, $rule);
                if ($date && str_starts_with($date, (string) $year)) {
                    $out[] = ['date' => $date, 'key' => $key, 'name' => $name, 'religion' => $religion,
                        'basis' => LunarCalendar::MASAS[$masa].' '.Vedic::paksha($tithi).' '.Vedic::tithiName($tithi).' (amanta) '.self::RULES[$rule]];
                }
            }
        }

        foreach ($this->engine->signIngresses('sun', $jan1 - 1, $dec31 + 1) as $e) {
            if (! in_array($e['sign'], [0, 9], true) || $e['retro']) {
                continue;
            }
            $local = Engine::fromJd($e['jd'])->setTimezone($this->tz);
            $date = $local->format('Y-m-d');
            $sunset = $this->day($date)['sunset'];
            if ($sunset !== null && $e['jd'] > $sunset) {
                $date = $local->modify('+1 day')->format('Y-m-d');   // punya kaal moves to the next day
            }
            if (! str_starts_with($date, (string) $year)) {
                continue;
            }
            $basis = 'Sun enters sidereal '.Vedic::SIGNS[$e['sign']].' at '.$local->format('g:i A, d M');
            if ($e['sign'] === 9) {
                $out[] = ['date' => $date, 'key' => 'makar-sankranti', 'name' => 'Makar Sankranti (Pongal, Uttarayan)', 'religion' => 'hindu', 'basis' => $basis];
            } else {
                $out[] = ['date' => $date, 'key' => 'mesha-sankranti', 'name' => 'Mesha Sankranti (Satuani, Baisakhi)', 'religion' => 'hindu', 'basis' => $basis];
                $out[] = ['date' => $date, 'key' => 'vaisakhi', 'name' => 'Vaisakhi (Khalsa Sajna Divas)', 'religion' => 'sikh', 'basis' => $basis];
            }
        }

        foreach (self::DERIVED as [$key, $name, $religion, $base, $offset]) {
            foreach (array_filter($out, fn ($f) => $f['key'] === $base) as $b) {
                $date = (new DateTimeImmutable($b['date']))->modify(($offset >= 0 ? '+' : '').$offset.' day')->format('Y-m-d');
                if (str_starts_with($date, (string) $year)) {
                    $out[] = ['date' => $date, 'key' => $key, 'name' => $name, 'religion' => $religion, 'basis' => abs($offset).' day'.(abs($offset) === 1 ? '' : 's').($offset > 0 ? ' after ' : ' before ').$b['name']];
                }
            }
        }

        $out = array_merge($out, self::christian($year), self::islamic($year));
        usort($out, fn ($a, $b) => [$a['date'], $a['religion'], $a['name']] <=> [$b['date'], $b['religion'], $b['name']]);

        return $out;
    }

    /** Gregorian Easter (anonymous Gregorian computus) and fixed Christian dates. */
    public static function christian(int $y): array
    {
        $a = $y % 19;
        $b = intdiv($y, 100);
        $c = $y % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv($b - intdiv($b + 8, 25) + 1, 3) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - $c % 4) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $easter = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, intdiv($h + $l - 7 * $m + 114, 31), ($h + $l - 7 * $m + 114) % 31 + 1));
        $rel = fn (int $d, string $key, string $name) => ['date' => $easter->modify(($d >= 0 ? '+' : '').$d.' day')->format('Y-m-d'), 'key' => $key, 'name' => $name, 'religion' => 'christian', 'basis' => 'Gregorian Easter'.($d ? ' '.($d > 0 ? '+' : '').$d.' days' : '')];

        return [
            $rel(-46, 'ash-wednesday', 'Ash Wednesday'), $rel(-7, 'palm-sunday', 'Palm Sunday'), $rel(-2, 'good-friday', 'Good Friday'),
            $rel(0, 'easter', 'Easter Sunday'), $rel(49, 'pentecost', 'Pentecost'),
            ['date' => "$y-12-25", 'key' => 'christmas', 'name' => 'Christmas', 'religion' => 'christian', 'basis' => 'Fixed date (25 December)'],
        ];
    }

    /** Islamic observances from the Umm al-Qura calendar (ICU). */
    public static function islamic(int $y): array
    {
        if (! class_exists(IntlCalendar::class)) {
            return [];
        }
        $cal = IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');
        $out = [];
        $h0 = (int) floor(($y - 622) * 33 / 32);
        for ($h = $h0 - 1; $h <= $h0 + 2; $h++) {
            foreach (self::ISLAMIC as [$month, $day, $name]) {
                $cal->clear();
                $cal->set($h, $month - 1, $day);
                $date = (new DateTimeImmutable('@'.intdiv((int) $cal->getTime(), 1000)))->format('Y-m-d');
                if (str_starts_with($date, (string) $y)) {
                    $out[] = ['date' => $date, 'key' => 'hijri-'.$month.'-'.$day, 'name' => $name, 'religion' => 'islamic', 'basis' => "$day/$month/$h AH (Umm al-Qura); in India it may be a day later, depending on moon sighting"];
                }
            }
        }

        return $out;
    }

    /** Local civil date on which tithi $t of lunar month $m is observed under $rule. */
    private function lunarDate(array $m, int $t, string $rule): ?string
    {
        if ($rule === 'holika') {
            return $this->holika($m);
        }
        $span = $this->tithiSpan($t, $m['start'] + ($t + 0.5) * ($m['end'] - $m['start']) / 30);
        if (! $span) {
            return null;
        }
        [$ts, $te] = $span;
        $firstDay = $this->localDate($ts - 1);
        $lastDay = $this->localDate($te);
        $best = null;
        $bestOverlap = 0.0;
        for ($d = new DateTimeImmutable($firstDay); $d->format('Y-m-d') <= $lastDay; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            $w = $this->window($date, $rule);
            if ($w === null) {
                continue;
            }
            if ($w[0] === $w[1]) {                       // instant rule: first day it prevails
                if ($w[0] >= $ts && $w[0] < $te) {
                    return $date;
                }

                continue;
            }
            $overlap = min($w[1], $te) - max($w[0], $ts);
            if ($overlap > $bestOverlap + 1e-6) {
                [$best, $bestOverlap] = [$date, $overlap];
            }
        }

        if ($best === null && $rule === 'moonrise') {
            return $this->lunarDate($m, $t, 'sunrise');  // no moonrise inside the tithi: udaya tithi
        }

        return $best ?? $this->localDate($ts);          // kshaya at that time: the day the tithi begins
    }

    /**
     * Holika Dahan: Purnima in pradosh, avoiding Bhadra (Vishti, the first half of Purnima).
     * If Bhadra covers pradosh but ends before midnight, the dahan is that night after Bhadra;
     * if Bhadra lasts past midnight and Purnima is still current at the next sunrise, it moves
     * to the next evening.
     */
    private function holika(array $m): ?string
    {
        $span = $this->tithiSpan(14, $m['start'] + 14.5 * ($m['end'] - $m['start']) / 30);
        $day = $this->lunarDate($m, 14, 'pradosh');
        if (! $span || ! $day) {
            return $day;
        }
        [$ts, $te] = $span;
        $bhadraEnd = ($ts + $te) / 2;
        $d = $this->day($day);
        if ($d['sunset'] === null || $bhadraEnd <= $d['sunset'] || $bhadraEnd < ($d['sunset'] + $d['next']) / 2) {
            return $day;
        }
        $next = (new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');

        return ($this->day($next)['sunrise'] ?? INF) < $te ? $next : $day;
    }

    /** [start, end] JD of tithi $t around $approx. */
    private function tithiSpan(int $t, float $approx): ?array
    {
        foreach ([0.0, -1.0, 1.0] as $shift) {
            $from = $approx - 1.45 + $shift;
            $to = $approx + 1.45 + $shift;
            $p = $this->engine->panchang($from, $to);
            $segments = [[$p['at']['tithi'], null]];
            foreach ($p['transitions'] as $tr) {
                if ($tr['anga'] === 'tithi') {
                    $segments[] = [$tr['to'], $tr['jd']];
                }
            }
            foreach ($segments as $i => [$idx, $start]) {
                if ($idx !== $t) {
                    continue;
                }
                $end = $segments[$i + 1][1] ?? null;
                if ($start !== null && $end !== null) {
                    return [$start, $end];
                }
            }
        }

        return null;
    }

    /** Observation window [start, end] (JD) for a local date; start === end for instant rules. */
    private function window(string $date, string $rule): ?array
    {
        $d = $this->day($date);
        if ($d['sunrise'] === null || $d['sunset'] === null) {
            return null;
        }
        $len = $d['sunset'] - $d['sunrise'];

        return match ($rule) {
            'sunrise' => [$d['sunrise'], $d['sunrise']],
            'moonrise' => $d['moonrise'] === null ? null : [$d['moonrise'], $d['moonrise']],
            'purvahna' => [$d['sunrise'], $d['sunrise'] + $len / 3],
            'madhyahna' => [$d['sunrise'] + 0.4 * $len, $d['sunrise'] + 0.6 * $len],
            'aparahna' => [$d['sunrise'] + 0.6 * $len, $d['sunrise'] + 0.8 * $len],
            'pradosh' => [$d['sunset'], $d['sunset'] + 0.1],                 // 3 muhurtas ≈ 2 h 24 min after sunset
            'nishita' => [($d['sunset'] + $d['next']) / 2 - 1 / 60, ($d['sunset'] + $d['next']) / 2 + 1 / 60],
        };
    }

    /** Sunrise, sunset, next sunrise and moonrise (JD) for a local date, memoised per run. */
    public function day(string $date): array
    {
        if (! isset($this->days[$date])) {
            $mid = Engine::jd((new DateTimeImmutable($date.' 00:00:00', $this->tz))->setTimezone(new DateTimeZone('UTC')));
            $r = $this->engine->rise($mid, $this->lat, $this->lng);
            $next = $r['sunrise'] !== null ? ($this->engine->rise($r['sunrise'] + 0.01, $this->lat, $this->lng)['sunrise'] ?? $r['sunrise'] + 1) : null;
            $this->days[$date] = ['sunrise' => $r['sunrise'], 'sunset' => $r['sunset'], 'next' => $next, 'moonrise' => $r['moonrise']];
        }

        return $this->days[$date];
    }

    private function localDate(float $jd): string
    {
        return Engine::fromJd($jd)->setTimezone($this->tz)->format('Y-m-d');
    }
}
