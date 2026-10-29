<?php

namespace App\Domain\Astro;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Location-aware daily Panchang for a civil date. The Vedic day runs sunrise to next sunrise.
 * Angas (tithi, nakshatra, yoga, karana) are those prevailing at sunrise; every transition
 * until the next sunrise is listed with local time (transitions after midnight show the next date).
 * Time intervals use the classical eight-part day division. See docs/astro/methods.md.
 */
class PanchangService
{
    // Day-part (1..8) of Rahu Kaal, Yamaganda, Gulika for Sunday..Saturday.
    private const RAHU = [8, 2, 7, 5, 6, 4, 3];

    private const YAMA = [5, 4, 3, 2, 1, 7, 6];

    private const GULIKA = [7, 6, 5, 4, 3, 2, 1];

    /** Choghadiya cycle; day and night each have 8 parts. */
    public const CHOGHADIYA = ['Udveg', 'Char', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog'];

    public const CHOGHADIYA_QUALITY = ['Udveg' => 'bad', 'Char' => 'neutral', 'Labh' => 'good', 'Amrit' => 'good', 'Kaal' => 'bad', 'Shubh' => 'good', 'Rog' => 'bad'];

    /** Index in CHOGHADIYA of the first day / night part, Sunday..Saturday. Day steps +1, night steps −2 (mod 7). */
    private const CHOG_DAY = [0, 3, 6, 2, 5, 1, 4];

    private const CHOG_NIGHT = [5, 1, 4, 0, 3, 6, 2];

    /** Chaldean order of hora lords; each hour's lord is the next in this list. */
    public const HORA_ORDER = ['saturn', 'jupiter', 'mars', 'sun', 'venus', 'mercury', 'moon'];

    private const WEEKDAY_LORD = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn'];

    public function __construct(private Engine $engine) {}

    /**
     * Choghadiya: daytime (sunrise→sunset) and night (sunset→next sunrise) each split into 8 equal parts.
     *
     * @return list<array{name: string, quality: string, period: string, start: string, end: string}>
     */
    public static function choghadiya(int $weekday, float $sunrise, float $sunset, float $next, callable $fmt): array
    {
        $out = [];
        foreach ([['day', $sunrise, $sunset, self::CHOG_DAY[$weekday], 1], ['night', $sunset, $next, self::CHOG_NIGHT[$weekday], 5]] as [$period, $a, $b, $first, $step]) {
            $part = ($b - $a) / 8;
            for ($i = 0; $i < 8; $i++) {
                $name = self::CHOGHADIYA[($first + $i * $step) % 7];
                $out[] = ['name' => $name, 'quality' => self::CHOGHADIYA_QUALITY[$name], 'period' => $period,
                    'start' => $fmt($a + $i * $part)->format(DATE_ATOM), 'end' => $fmt($a + ($i + 1) * $part)->format(DATE_ATOM)];
            }
        }

        return $out;
    }

    /**
     * Hora: 12 day and 12 night planetary hours; the first day hora belongs to the weekday's lord.
     *
     * @return list<array{lord: string, period: string, start: string, end: string}>
     */
    public static function hora(int $weekday, float $sunrise, float $sunset, float $next, callable $fmt): array
    {
        $out = [];
        $k = array_search(self::WEEKDAY_LORD[$weekday], self::HORA_ORDER, true);
        foreach ([['day', $sunrise, $sunset], ['night', $sunset, $next]] as [$period, $a, $b]) {
            $part = ($b - $a) / 12;
            for ($i = 0; $i < 12; $i++, $k++) {
                $out[] = ['lord' => self::HORA_ORDER[$k % 7], 'period' => $period, 'start' => $fmt($a + $i * $part)->format(DATE_ATOM), 'end' => $fmt($a + ($i + 1) * $part)->format(DATE_ATOM)];
            }
        }

        return $out;
    }

    public function day(string $date, float $lat, float $lng, string $tzName): array
    {
        $tz = new DateTimeZone($tzName);
        $localMidnight = new DateTimeImmutable($date.' 00:00:00', $tz);
        $startJd = Engine::jd($localMidnight->setTimezone(new DateTimeZone('UTC')));
        $r1 = $this->engine->rise($startJd, $lat, $lng);
        $sunrise = $r1['sunrise'];
        $sunset = $r1['sunset'];
        $polar = $sunrise === null || $sunset === null || $sunrise > $startJd + 1;
        $fmt = fn (?float $jd) => $jd === null ? null : Engine::fromJd($jd)->setTimezone($tz);

        // Reference instant: sunrise, or local noon when the Sun does not rise/set (high latitude).
        $ref = $polar ? $startJd + 0.5 : $sunrise;
        $next = $polar ? $ref + 1 : ($this->engine->rise($sunrise + 0.01, $lat, $lng)['sunrise'] ?? $ref + 1);
        $p = $this->engine->panchang($ref, $next);
        $at = $p['at'];
        $moonEv = $this->engine->rise($startJd, $lat, $lng);

        $label = function (string $anga, int $i) {
            return match ($anga) {
                'tithi' => Vedic::paksha($i).' '.Vedic::tithiName($i),
                'nakshatra' => Vedic::NAKSHATRAS[$i],
                'yoga' => Vedic::YOGAS[$i],
                'karana' => Vedic::karanaName($i),
                'pada' => Vedic::NAKSHATRAS[intdiv($i, 4)].' pada '.($i % 4 + 1),
            };
        };
        $angas = [];
        foreach (['tithi', 'nakshatra', 'yoga', 'karana'] as $a) {
            $list = [['name' => $label($a, $at[$a]), 'index' => $at[$a], 'ends' => null]];
            foreach ($p['transitions'] as $t) {
                if ($t['anga'] === $a) {
                    $list[count($list) - 1]['ends'] = $fmt($t['jd'])->format(DATE_ATOM);
                    $list[] = ['name' => $label($a, $t['to']), 'index' => $t['to'], 'ends' => null];
                }
            }
            $angas[$a] = $list;
        }
        $padaEnds = array_values(array_filter($p['transitions'], fn ($t) => $t['anga'] === 'pada'));
        $weekday = (int) $fmt($ref)->format('w');

        $intervals = [];
        if (! $polar) {
            $part = ($sunset - $sunrise) / 8;
            $seg = fn (int $n) => ['start' => $fmt($sunrise + ($n - 1) * $part)->format(DATE_ATOM), 'end' => $fmt($sunrise + $n * $part)->format(DATE_ATOM)];
            $muhurta = ($sunset - $sunrise) / 15;
            $intervals = [
                'rahu_kaal' => $seg(self::RAHU[$weekday]),
                'yamaganda' => $seg(self::YAMA[$weekday]),
                'gulika' => $seg(self::GULIKA[$weekday]),
                // Abhijit: 8th of 15 day muhurtas (traditionally not observed on Wednesdays).
                'abhijit' => ['start' => $fmt($sunrise + 7 * $muhurta)->format(DATE_ATOM), 'end' => $fmt($sunrise + 8 * $muhurta)->format(DATE_ATOM), 'note' => $weekday === 3 ? 'Traditionally avoided on Wednesday' : null],
                // Brahma muhurta: 14th night muhurta = from 96 to 48 minutes before sunrise (fixed 48-min convention).
                'brahma_muhurta' => ['start' => $fmt($sunrise - 96 / 1440)->format(DATE_ATOM), 'end' => $fmt($sunrise - 48 / 1440)->format(DATE_ATOM)],
            ];
        }

        [$choghadiya, $hora] = $polar ? [[], []] : [self::choghadiya($weekday, $sunrise, $sunset, $next, $fmt), self::hora($weekday, $sunrise, $sunset, $next, $fmt)];

        return [
            'date' => $date, 'place' => ['lat' => $lat, 'lng' => $lng, 'tz' => $tzName, 'utc_offset' => $localMidnight->format('P')],
            'choghadiya' => $choghadiya, 'hora' => $hora,
            'vara' => Vedic::VARAS[$weekday],
            'sunrise' => $fmt($sunrise)?->format(DATE_ATOM), 'sunset' => $fmt($sunset)?->format(DATE_ATOM),
            'moonrise' => $fmt($moonEv['moonrise'])?->format(DATE_ATOM), 'moonset' => $fmt($moonEv['moonset'])?->format(DATE_ATOM),
            'polar' => $polar, 'polar_note' => $polar ? 'The Sun does not rise or set here on this date. Angas are shown for local noon and day-part intervals are not computed.' : null,
            'angas' => $angas, 'moon_sign' => Vedic::SIGNS[Vedic::sign($p['moon'])], 'sun_sign' => Vedic::SIGNS[Vedic::sign($p['sun'])],
            'moon_pada_ends' => array_map(fn ($t) => ['pada' => $label('pada', $t['from']), 'ends' => $fmt($t['jd'])->format(DATE_ATOM)], $padaEnds),
            'intervals' => $intervals,
            'method' => 'Swiss Ephemeris, Lahiri ayanamsha, sunrise = upper limb with refraction; angas at sunrise.',
        ];
    }
}
