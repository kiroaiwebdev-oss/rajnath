<?php

namespace App\Domain\Astro;

use Carbon\CarbonImmutable;

/**
 * Monthly vrat calendar from the tithi prevailing at local sunrise (udaya tithi).
 * Marks: Ekadashi, Pradosh (Trayodashi), Purnima, Amavasya, Sankashti Chaturthi (Krishna 4),
 * Vinayaka Chaturthi (Shukla 4), Masik Shivaratri (Krishna 14) and Sankranti (Sun's sidereal sign change).
 * Regional rules (e.g. dashami-viddha Ekadashi, Pradosh at dusk, Shivaratri at midnight) are NOT
 * applied; observance can differ by a day. Labelled as such on the page.
 */
class VratCalendar
{
    public const MARKS = [10 => 'Ekadashi (Shukla)', 25 => 'Ekadashi (Krishna)', 12 => 'Pradosh (Shukla Trayodashi)', 27 => 'Pradosh (Krishna Trayodashi)',
        14 => 'Purnima', 29 => 'Amavasya', 18 => 'Sankashti Chaturthi', 3 => 'Vinayaka Chaturthi', 28 => 'Masik Shivaratri'];

    public function __construct(private PanchangService $panchang, private Engine $engine) {}

    public function month(int $year, int $month, float $lat, float $lng, string $tz): array
    {
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $days = [];
        $seen = [];
        for ($d = $first; $d->month === $month; $d = $d->addDay()) {
            $p = $this->panchang->day($d->toDateString(), $lat, $lng, $tz);
            $t = $p['angas']['tithi'][0]['index'];
            $events = [];
            if (isset(self::MARKS[$t]) && ! isset($seen[$t.'-'.$d->format('Ym')])) {
                $events[] = self::MARKS[$t];
                $seen[$t.'-'.$d->format('Ym')] = true;   // udaya tithi rule: first sunrise only
            }
            $days[$d->toDateString()] = ['tithi' => $p['angas']['tithi'][0]['name'], 'nakshatra' => $p['angas']['nakshatra'][0]['name'], 'events' => $events, 'weekday' => $d->dayOfWeek];
        }
        $from = Engine::jd($first->utc()->toDateTimeImmutable());
        $to = Engine::jd($first->addMonth()->utc()->toDateTimeImmutable());
        foreach ($this->engine->signIngresses('sun', $from, $to) as $e) {
            $local = Engine::fromJd($e['jd'])->setTimezone(new \DateTimeZone($tz));
            $key = $local->format('Y-m-d');
            if (isset($days[$key])) {
                $days[$key]['events'][] = Vedic::SIGNS[$e['sign']].' Sankranti ('.$local->format('g:i A').')';
            }
        }

        return $days;
    }
}
