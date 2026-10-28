<?php

namespace App\Domain\Astro;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Birth chart (Rashi D1 + Navamsa D9), whole-sign houses from the sidereal Lagna, Moon sign,
 * Nakshatra/pada, Vimshottari dasha, Manglik check and Sade Sati periods.
 */
class KundliService
{
    public function __construct(private Engine $engine) {}

    /** @param DateTimeImmutable $utc birth instant in UTC */
    public function chart(DateTimeImmutable $utc, float $lat, float $lng, string $tz, string $node = 'mean'): array
    {
        $jd = Engine::jd($utc);
        $raw = $this->engine->chart($jd, $lat, $lng, $node);
        $lagnaSign = Vedic::sign($raw['ascendant']);
        $planets = [];
        foreach ($raw['planets'] as $name => $p) {
            $sign = Vedic::sign($p['lon']);
            $planets[$name] = [
                'name' => Vedic::PLANET_NAMES[$name] ?? ucfirst($name),
                'lon' => round($p['lon'], 6), 'sign' => $sign, 'sign_name' => Vedic::SIGNS[$sign],
                'degree' => Vedic::dms(fmod($p['lon'], 30)), 'house' => (($sign - $lagnaSign + 12) % 12) + 1,
                'nakshatra' => Vedic::NAKSHATRAS[Vedic::nakshatra($p['lon'])], 'pada' => Vedic::pada($p['lon']),
                'navamsa_sign' => Vedic::navamsaSign($p['lon']), 'retro' => in_array($name, ['rahu', 'ketu'], true) ? true : $p['retro'],
                'classical' => in_array($name, ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'], true),
            ];
        }
        $moon = $raw['planets']['moon']['lon'];

        return [
            'input' => ['utc' => $utc->format('Y-m-d\TH:i:s\Z'), 'local' => $utc->setTimezone(new DateTimeZone($tz))->format('Y-m-d H:i'), 'tz' => $tz, 'lat' => $lat, 'lng' => $lng],
            'method' => ['zodiac' => 'sidereal', 'ayanamsha' => 'Lahiri (Chitrapaksha)', 'ayanamsha_value' => Vedic::dms($raw['ayanamsha']), 'houses' => 'Whole sign from Lagna', 'node' => $raw['node'], 'ephemeris' => $raw['ephemeris'] === 'swiss' ? 'Swiss Ephemeris 2.10.03' : 'Moshier (fallback, lower precision)'],
            'lagna' => ['lon' => round($raw['ascendant'], 6), 'sign' => $lagnaSign, 'sign_name' => Vedic::SIGNS[$lagnaSign], 'degree' => Vedic::dms(fmod($raw['ascendant'], 30)), 'nakshatra' => Vedic::NAKSHATRAS[Vedic::nakshatra($raw['ascendant'])], 'navamsa_sign' => Vedic::navamsaSign($raw['ascendant'])],
            'moon_sign' => ['sign' => Vedic::sign($moon), 'name' => Vedic::SIGNS[Vedic::sign($moon)]],
            'nakshatra' => ['index' => Vedic::nakshatra($moon), 'name' => Vedic::NAKSHATRAS[Vedic::nakshatra($moon)], 'pada' => Vedic::pada($moon), 'lord' => Vedic::DASHA_ORDER[Vedic::nakshatra($moon) % 9]],
            'planets' => $planets,
            'houses' => $this->houses($planets, $lagnaSign),
            'navamsa' => $this->navamsaHouses($planets, Vedic::navamsaSign($raw['ascendant'])),
            'dasha' => $this->vimshottari($moon, $utc),
            'manglik' => $this->manglik($planets, $lagnaSign),
        ];
    }

    private function houses(array $planets, int $lagnaSign): array
    {
        $h = [];
        for ($i = 1; $i <= 12; $i++) {
            $sign = ($lagnaSign + $i - 1) % 12;
            $h[$i] = ['sign' => $sign, 'sign_name' => Vedic::SIGNS[$sign], 'lord' => Vedic::SIGN_LORDS[$sign], 'planets' => []];
        }
        foreach ($planets as $k => $p) {
            if ($p['classical']) {
                $h[$p['house']]['planets'][] = $k;
            }
        }

        return $h;
    }

    private function navamsaHouses(array $planets, int $d9Lagna): array
    {
        $h = [];
        for ($i = 1; $i <= 12; $i++) {
            $h[$i] = ['sign' => ($d9Lagna + $i - 1) % 12, 'planets' => []];
        }
        foreach ($planets as $k => $p) {
            if ($p['classical']) {
                $h[(($p['navamsa_sign'] - $d9Lagna + 12) % 12) + 1]['planets'][] = $k;
            }
        }

        return $h;
    }

    /** Vimshottari: 120-year cycle; years of 365.25 days; balance from the Moon's position in its nakshatra. */
    public function vimshottari(float $moonLon, DateTimeImmutable $birthUtc): array
    {
        $span = 360 / 27;
        $n = Vedic::nakshatra($moonLon);
        $elapsedFraction = fmod($moonLon, $span) / $span;
        $startLordIdx = $n % 9;
        $yearSec = 365.25 * 86400;
        $t = $birthUtc->getTimestamp() - (int) round($elapsedFraction * Vedic::DASHA_YEARS[Vedic::DASHA_ORDER[$startLordIdx]] * $yearSec);
        $out = [];
        for ($k = 0; $k < 9; $k++) {
            $lord = Vedic::DASHA_ORDER[($startLordIdx + $k) % 9];
            $len = Vedic::DASHA_YEARS[$lord] * $yearSec;
            $antar = [];
            $ta = $t;
            for ($j = 0; $j < 9; $j++) {
                $al = Vedic::DASHA_ORDER[($startLordIdx + $k + $j) % 9];
                $alen = $len * Vedic::DASHA_YEARS[$al] / 120;
                $antar[] = ['lord' => $al, 'start' => gmdate('Y-m-d', (int) $ta), 'end' => gmdate('Y-m-d', (int) ($ta + $alen))];
                $ta += $alen;
            }
            $out[] = ['lord' => $lord, 'start' => gmdate('Y-m-d', (int) $t), 'end' => gmdate('Y-m-d', (int) ($t + $len)), 'antardasha' => $antar];
            $t += $len;
        }

        return ['balance_years' => round((1 - $elapsedFraction) * Vedic::DASHA_YEARS[Vedic::DASHA_ORDER[$startLordIdx]], 2), 'periods' => $out];
    }

    /** Mars in houses 1, 2, 4, 7, 8, 12 counted from Lagna, Moon and Venus (traditional rule; cancellations not applied). */
    public function manglik(array $planets, int $lagnaSign): array
    {
        $mars = $planets['mars']['sign'];
        $houseFrom = fn (int $refSign) => (($mars - $refSign + 12) % 12) + 1;
        $bad = [1, 2, 4, 7, 8, 12];
        $from = ['lagna' => $houseFrom($lagnaSign), 'moon' => $houseFrom($planets['moon']['sign']), 'venus' => $houseFrom($planets['venus']['sign'])];
        $hits = array_keys(array_filter($from, fn ($h) => in_array($h, $bad, true)));

        return ['mars_house_from' => $from, 'present_from' => $hits, 'is_manglik' => in_array('lagna', $hits, true) || in_array('moon', $hits, true)];
    }

    /** Sade Sati: Saturn transiting the 12th, 1st and 2nd sign from the natal Moon sign. */
    public function sadeSati(int $moonSign, DateTimeImmutable $fromUtc, int $years = 60): array
    {
        $jd0 = Engine::jd($fromUtc);
        $jd1 = $jd0 + $years * 365.25;
        $start = Vedic::sign($this->engine->chart($jd0, 0, 0)['planets']['saturn']['lon']);
        $events = $this->engine->signIngresses('saturn', $jd0, $jd1);
        $window = [($moonSign + 11) % 12, $moonSign, ($moonSign + 1) % 12];
        $segments = [];
        $curSign = $start;
        $curStart = $jd0;
        foreach ($events as $e) {
            $segments[] = [$curSign, $curStart, $e['jd']];
            $curSign = $e['sign'];
            $curStart = $e['jd'];
        }
        $segments[] = [$curSign, $curStart, $jd1];
        $periods = [];
        $open = null;
        foreach ($segments as [$sign, $a, $b]) {
            $in = in_array($sign, $window, true);
            if ($in) {
                $phase = $sign === $window[0] ? 'rising' : ($sign === $moonSign ? 'peak' : 'setting');
                if ($open && $a - $open['end_jd'] < 1) {
                    $open['end_jd'] = $b;
                    $open['phases'][] = ['phase' => $phase, 'start' => Engine::fromJd($a)->format('Y-m-d'), 'end' => Engine::fromJd($b)->format('Y-m-d')];
                } else {
                    if ($open) {
                        $periods[] = $open;
                    }
                    $open = ['start_jd' => $a, 'end_jd' => $b, 'phases' => [['phase' => $phase, 'start' => Engine::fromJd($a)->format('Y-m-d'), 'end' => Engine::fromJd($b)->format('Y-m-d')]]];
                }
            } elseif ($open) {
                $periods[] = $open;
                $open = null;
            }
        }
        if ($open) {
            $periods[] = $open;
        }

        return array_map(fn ($p) => [
            'start' => Engine::fromJd($p['start_jd'])->format('Y-m-d'), 'end' => Engine::fromJd($p['end_jd'])->format('Y-m-d'),
            'ongoing_at_window_edge' => $p['start_jd'] <= $jd0 + 0.5 || $p['end_jd'] >= $jd1 - 0.5, 'phases' => $p['phases'],
        ], $periods);
    }
}
