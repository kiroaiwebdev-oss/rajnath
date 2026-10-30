<?php

namespace App\Domain\Astro;

/**
 * Amanta lunar months (new moon to new moon). A month is named from the Sun's sidereal sign at
 * the new moon that starts it: Sun in Meena → Chaitra, Sun in Mesha → Vaishakha, and so on.
 * A month in which the Sun does not change sign (both bounding new moons have the Sun in the
 * same sign) is Adhik (extra) and takes the name of the month that follows it.
 * Kshaya masa (very rare) is not handled. Festival rules in Festivals use amanta names.
 *
 * Purnimanta (North India: Bihar, UP, MP, Rajasthan…): the month runs full moon to full moon,
 * so the Shukla paksha has the same name as amanta and the Krishna paksha takes the NEXT
 * month's name (amanta Ashwin Krishna = purnimanta Kartik Krishna). purnimantaName() gives it.
 */
class LunarCalendar
{
    public const MASAS = ['Chaitra', 'Vaishakha', 'Jyeshtha', 'Ashadha', 'Shravana', 'Bhadrapada', 'Ashwin', 'Kartik', 'Margashirsha', 'Pausha', 'Magha', 'Phalguna'];

    public const MASAS_HI = ['चैत्र', 'वैशाख', 'ज्येष्ठ', 'आषाढ़', 'श्रावण', 'भाद्रपद', 'आश्विन', 'कार्तिक', 'मार्गशीर्ष', 'पौष', 'माघ', 'फाल्गुन'];

    public function __construct(private Engine $engine) {}

    /**
     * Lunar months overlapping the Julian Day window [from, to].
     *
     * @return list<array{start: float, end: float, masa: int, adhik: bool, full: ?float}>
     */
    public function months(float $from, float $to): array
    {
        $events = $this->engine->lunations($from - 32, $to + 64);
        $news = array_values(array_filter($events, fn ($e) => $e['type'] === 'new'));
        $fulls = array_values(array_filter($events, fn ($e) => $e['type'] === 'full'));
        $out = [];
        for ($i = 0; $i < count($news) - 1; $i++) {
            [$s, $e] = [$news[$i], $news[$i + 1]];
            if ($e['jd'] < $from || $s['jd'] > $to) {
                continue;
            }
            $full = null;
            foreach ($fulls as $f) {
                if ($f['jd'] > $s['jd'] && $f['jd'] < $e['jd']) {
                    $full = $f['jd'];
                    break;
                }
            }
            $out[] = ['start' => $s['jd'], 'end' => $e['jd'], 'masa' => ($s['sun_sign'] + 1) % 12, 'adhik' => $s['sun_sign'] === $e['sun_sign'], 'full' => $full];
        }

        return $out;
    }

    /** The month containing the instant $jd, from a list returned by months(). */
    public static function monthAt(array $months, float $jd): ?array
    {
        foreach ($months as $m) {
            if ($jd >= $m['start'] && $jd < $m['end']) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Purnimanta name of the paksha containing a tithi (0..29) in amanta month $m.
     * An adhik month keeps "Adhik" for its own pakshas.
     */
    public static function purnimantaName(array $m, int $tithi, string $locale = 'en'): string
    {
        if ($tithi < 15 || $m['adhik']) {
            return self::name($m, $locale);
        }
        $next = ['masa' => ($m['masa'] + 1) % 12, 'adhik' => false];

        return self::name($next, $locale);
    }

    public static function name(array $m, string $locale = 'en'): string
    {
        $n = ($locale === 'hi' ? self::MASAS_HI : self::MASAS)[$m['masa']];

        return $m['adhik'] ? ($locale === 'hi' ? 'अधिक '.$n : 'Adhik '.$n) : $n;
    }
}
