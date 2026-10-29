<?php

namespace App\Domain\Astro;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Candidate dates for samskara and marriage muhurats in a month (rule set v1, see
 * docs/astro/methods.md "Muhurat"). Each day is judged by the Panchang at local sunrise:
 * tithi, vara and nakshatra must be in the event's lists; Vyatipata/Vaidhriti yoga and Vishti
 * (Bhadra) karana are avoided for every event; some events also avoid Adhik masa, Kharmas
 * (Sun in sidereal Dhanu or Meena), Chaturmas (Devshayani to Dev Uthani Ekadashi) and the
 * combustion of Jupiter or Venus. Lagna shuddhi, personal charts, regional customs and
 * family traditions are NOT applied: the page asks the reader to confirm with Acharya Ji.
 */
class Muhurat
{
    /** Nakshatra indexes: 0 Ashwini … 26 Revati. Vara: 0 Sunday … 6 Saturday. Tithis: numbers within the paksha. */
    public const EVENTS = [
        'vivah' => ['name' => 'Vivah (marriage)', 'hi' => 'विवाह मुहूर्त',
            'nakshatras' => [3, 4, 9, 11, 12, 14, 16, 18, 20, 25, 26], 'varas' => [0, 1, 3, 4, 5, 6],
            'shukla' => [2, 3, 5, 7, 10, 11, 12, 13], 'krishna' => [2, 3, 5, 7, 10], 'avoid' => ['adhik', 'kharmas', 'chaturmas', 'combust']],
        'namkaran' => ['name' => 'Namkaran (naming)', 'hi' => 'नामकरण मुहूर्त',
            'nakshatras' => [0, 3, 4, 6, 7, 11, 12, 13, 14, 16, 20, 21, 22, 23, 25, 26], 'varas' => [1, 3, 4, 5],
            'shukla' => [1, 2, 3, 5, 7, 10, 11, 12, 13], 'krishna' => [1, 2, 3, 5, 7, 10], 'avoid' => []],
        'annaprashan' => ['name' => 'Annaprashan (first rice)', 'hi' => 'अन्नप्राशन मुहूर्त',
            'nakshatras' => [0, 3, 4, 6, 7, 11, 12, 13, 14, 16, 20, 21, 22, 23, 25, 26], 'varas' => [1, 3, 4, 5],
            'shukla' => [2, 3, 5, 7, 10, 12, 13], 'krishna' => [2, 3, 5], 'avoid' => ['adhik']],
        'mundan' => ['name' => 'Mundan (first haircut)', 'hi' => 'मुंडन मुहूर्त',
            'nakshatras' => [0, 4, 6, 7, 12, 13, 14, 17, 21, 22, 23, 26], 'varas' => [1, 3, 4, 5],
            'shukla' => [2, 3, 5, 7, 10, 11, 13], 'krishna' => [2, 3, 5], 'avoid' => ['adhik', 'kharmas', 'chaturmas']],
        'karnavedha' => ['name' => 'Karnavedha (ear piercing)', 'hi' => 'कर्णवेध मुहूर्त',
            'nakshatras' => [0, 4, 6, 7, 12, 13, 16, 21, 22, 26], 'varas' => [1, 3, 4, 5],
            'shukla' => [2, 3, 5, 6, 7, 10, 11, 12, 13], 'krishna' => [2, 3, 5], 'avoid' => ['adhik', 'kharmas', 'chaturmas']],
        'vidyarambh' => ['name' => 'Vidyarambh (start of learning)', 'hi' => 'विद्यारंभ मुहूर्त',
            'nakshatras' => [0, 4, 5, 6, 7, 10, 12, 13, 14, 18, 19, 21, 22, 23, 24, 26], 'varas' => [0, 3, 4, 5],
            'shukla' => [2, 3, 5, 6, 10, 11, 12], 'krishna' => [2, 3, 5], 'avoid' => ['adhik']],
        'upanayan' => ['name' => 'Upanayan / Yagyopavit (sacred thread)', 'hi' => 'उपनयन / यज्ञोपवीत मुहूर्त',
            'nakshatras' => [0, 3, 4, 6, 7, 11, 12, 13, 14, 20, 21, 22, 23, 25, 26], 'varas' => [0, 1, 3, 4, 5],
            'shukla' => [2, 3, 5, 10, 11, 12], 'krishna' => [2, 3, 5], 'avoid' => ['adhik', 'kharmas', 'chaturmas', 'combust']],
    ];

    public const AVOID_LABELS = ['adhik' => 'Adhik (Mal) masa', 'kharmas' => 'Kharmas (Sun in Dhanu or Meena)', 'chaturmas' => 'Chaturmas (Devshayani to Dev Uthani Ekadashi)', 'combust' => 'Jupiter or Venus combust (asta)'];

    private const BAD_YOGAS = [16, 26];          // Vyatipata, Vaidhriti

    private const COMBUST = ['jupiter' => 11.0, 'venus' => 10.0, 'venus_retro' => 8.0];

    public function __construct(private PanchangService $panchang, private Engine $engine, private LunarCalendar $lunar) {}

    /**
     * Per-day facts for a month (shared by every event; cache this).
     *
     * @return array<string, array> date => facts
     */
    public function monthFacts(int $year, int $month, float $lat, float $lng, string $tz): array
    {
        $zone = new DateTimeZone($tz);
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $zone);
        $months = $this->lunar->months(Engine::jd($first->setTimezone(new DateTimeZone('UTC'))), Engine::jd($first->modify('+1 month')->setTimezone(new DateTimeZone('UTC'))));
        $out = [];
        $last = (int) $first->format('t');
        for ($i = 0; $i < $last; $i++) {
            $d = $first->modify("+$i day");
            $date = $d->format('Y-m-d');
            $p = $this->panchang->day($date, $lat, $lng, $tz);
            if ($p['sunrise'] === null) {
                continue;                                                 // polar day/night: no muhurat
            }
            $sr = Engine::jd((new DateTimeImmutable($p['sunrise']))->setTimezone(new DateTimeZone('UTC')));
            $c = $this->engine->chart($sr, $lat, $lng)['planets'];
            $lm = LunarCalendar::monthAt($months, $sr);
            $sep = fn (string $pl) => abs(fmod($c[$pl]['lon'] - $c['sun']['lon'] + 540, 360) - 180);
            $out[$date] = [
                'weekday' => (int) $d->format('w'), 'sunrise' => $p['sunrise'],
                // the Vedic day ends at the next sunrise (≈ 24 h later; exact value set below)
                'day_end' => (new DateTimeImmutable($p['sunrise']))->modify('+1 day')->format(DATE_ATOM),
                'angas' => array_map(fn ($list) => array_map(fn ($a) => ['index' => $a['index'], 'name' => $a['name'], 'ends' => $a['ends']], $list), $p['angas']),
                'sun_sign' => Vedic::sign($c['sun']['lon']),
                'masa' => $lm ? LunarCalendar::name($lm) : null, 'masa_index' => $lm['masa'] ?? null, 'adhik' => $lm['adhik'] ?? false,
                'jupiter_combust' => $sep('jupiter') < self::COMBUST['jupiter'],
                'venus_combust' => $sep('venus') < ($c['venus']['retro'] ? self::COMBUST['venus_retro'] : self::COMBUST['venus']),
            ];
        }
        $dates = array_keys($out);
        foreach ($dates as $i => $date) {
            if (isset($dates[$i + 1]) && $dates[$i + 1] === (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d')) {
                $out[$date]['day_end'] = $out[$dates[$i + 1]]['sunrise'];
            }
        }

        return $out;
    }

    /**
     * Candidate dates for $event: list of ['date', 'windows' => [[start ISO, end ISO, tithi, nakshatra]], 'facts'].
     * A window is a stretch of the Vedic day (sunrise to next sunrise) of at least one muhurta
     * (48 min) in which tithi, nakshatra, yoga and karana all pass at the same time.
     */
    public static function candidates(string $event, array $facts): array
    {
        $r = self::EVENTS[$event];
        $out = [];
        foreach ($facts as $date => $f) {
            if (! in_array($f['weekday'], $r['varas'], true) || self::avoided($r['avoid'], $f)) {
                continue;
            }
            $start = strtotime($f['sunrise']);
            $end = strtotime($f['day_end']);
            $cuts = [$start, $end];
            foreach ($f['angas'] as $list) {
                foreach ($list as $a) {
                    if ($a['ends'] && ($t = strtotime($a['ends'])) > $start && $t < $end) {
                        $cuts[] = $t;
                    }
                }
            }
            $cuts = array_values(array_unique($cuts));
            sort($cuts);
            $windows = [];
            for ($i = 0; $i < count($cuts) - 1; $i++) {
                $mid = ($cuts[$i] + $cuts[$i + 1]) / 2;
                $at = fn (string $anga) => self::at($f['angas'][$anga], $mid);
                $t = $at('tithi')['index'];
                $num = $t % 15 + 1;
                $ok = ($t < 15 ? in_array($num, $r['shukla'], true) : ($t !== 29 && in_array($num, $r['krishna'], true)))
                    && in_array($at('nakshatra')['index'], $r['nakshatras'], true)
                    && ! in_array($at('yoga')['index'], self::BAD_YOGAS, true)
                    && ! str_starts_with($at('karana')['name'], 'Vishti');
                if (! $ok) {
                    continue;
                }
                if ($windows && end($windows)[1] === $cuts[$i]) {
                    $windows[count($windows) - 1][1] = $cuts[$i + 1];
                } else {
                    $windows[] = [$cuts[$i], $cuts[$i + 1], $at('tithi')['name'], $at('nakshatra')['name']];
                }
            }
            $windows = array_values(array_filter($windows, fn ($w) => 48 * 60 <= $w[1] - $w[0]));
            if ($windows) {
                $tz = (new DateTimeImmutable($f['sunrise']))->getTimezone();
                $iso = fn (int $ts) => (new DateTimeImmutable('@'.$ts))->setTimezone($tz)->format(DATE_ATOM);
                $out[] = ['date' => $date, 'windows' => array_map(fn ($w) => [$iso($w[0]), $iso($w[1]), $w[2], $w[3]], $windows), 'facts' => $f];
            }
        }

        return $out;
    }

    /** The anga entry prevailing at unix time $ts in a Panchang list (entries end at 'ends'). */
    private static function at(array $list, float $ts): array
    {
        foreach ($list as $a) {
            if ($a['ends'] === null || strtotime($a['ends']) > $ts) {
                return $a;
            }
        }

        return end($list);
    }

    private static function avoided(array $avoid, array $f): bool
    {
        $t = $f['angas']['tithi'][0]['index'];
        foreach ($avoid as $a) {
            $hit = match ($a) {
                'adhik' => $f['adhik'],
                'kharmas' => in_array($f['sun_sign'], [8, 11], true),
                // Ashadha Shukla Ekadashi … day before Kartik Shukla Ekadashi (amanta months 3..7)
                'chaturmas' => $f['masa_index'] !== null && (($f['masa_index'] === 3 && $t >= 10 && $t < 30) || in_array($f['masa_index'], [4, 5, 6], true) || ($f['masa_index'] === 7 && $t < 10)),
                'combust' => $f['jupiter_combust'] || $f['venus_combust'],
            };
            if ($hit) {
                return true;
            }
        }

        return false;
    }
}
