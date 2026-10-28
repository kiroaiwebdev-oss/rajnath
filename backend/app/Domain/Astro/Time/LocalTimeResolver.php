<?php

namespace App\Domain\Astro\Time;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Converts a local wall-clock birth time in an IANA zone to a UTC instant using the historical
 * tz database (e.g. India's +06:30 war time 1942–45, US/UK DST history).
 *
 *  - exact: one valid mapping
 *  - ambiguous_earlier / ambiguous_later: clock went back (time occurred twice); caller chooses
 *  - gap_shifted: clock jumped forward (time never existed); shifted forward by the gap length,
 *    the common convention, and flagged so the customer can confirm.
 */
class LocalTimeResolver
{
    /** @return array{utc: DateTimeImmutable, offset: int, resolution: string, alternatives: int} */
    public function resolve(string $date, string $time, string $tzName, string $prefer = 'earlier'): array
    {
        try {
            $tz = new DateTimeZone($tzName);
        } catch (\Exception) {
            throw new InvalidArgumentException("Unknown time zone: $tzName");
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            throw new InvalidArgumentException('Invalid date or time.');
        }
        $time = strlen($time) === 5 ? $time.':00' : $time;
        $utcTz = new DateTimeZone('UTC');
        // Wall time interpreted as if it were UTC → "local seconds".
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', "$date $time", $utcTz);
        if (! $wall || $wall->format('Y-m-d H:i:s') !== "$date $time") {
            throw new InvalidArgumentException('Invalid date or time.');
        }
        $local = $wall->getTimestamp();

        // Candidate offsets: those in effect within ±2 days.
        $offsets = [];
        foreach ($tz->getTransitions($local - 2 * 86400, $local + 2 * 86400) ?: [] as $t) {
            $offsets[$t['offset']] = true;
        }
        if ($offsets === []) {
            $offsets[$tz->getOffset($wall)] = true;
        }
        $valid = [];
        foreach (array_keys($offsets) as $off) {
            $utc = $local - $off;
            if ($tz->getOffset(new DateTimeImmutable('@'.$utc)) === $off) {
                $valid[$utc] = $off;
            }
        }
        ksort($valid);

        if (count($valid) === 1) {
            $utc = array_key_first($valid);

            return ['utc' => (new DateTimeImmutable('@'.$utc))->setTimezone($utcTz), 'offset' => $valid[$utc], 'resolution' => 'exact', 'alternatives' => 1];
        }
        if (count($valid) > 1) {
            $keys = array_keys($valid);
            $utc = $prefer === 'later' ? end($keys) : $keys[0];

            return ['utc' => (new DateTimeImmutable('@'.$utc))->setTimezone($utcTz), 'offset' => $valid[$utc], 'resolution' => $prefer === 'later' ? 'ambiguous_later' : 'ambiguous_earlier', 'alternatives' => count($valid)];
        }
        // Gap: use the offset in effect *before* the jump; the resulting instant reads as the
        // wall time shifted forward by the gap length.
        $before = $tz->getOffset(new DateTimeImmutable('@'.($local - 86400)));
        $utc = $local - $before;

        return ['utc' => (new DateTimeImmutable('@'.$utc))->setTimezone($utcTz), 'offset' => $tz->getOffset(new DateTimeImmutable('@'.$utc)), 'resolution' => 'gap_shifted', 'alternatives' => 0];
    }
}
