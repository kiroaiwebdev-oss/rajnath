<?php

namespace App\Domain\Astro;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Planetary transits (gochar) from the ephemeris: current sidereal positions, sign ingresses
 * (including retrograde re-entries), retrograde/direct stations, and the classical
 * favourable-house table counted from the Moon sign (Phaladeepika ch. 26; see methods.md).
 */
class Gochar
{
    public const PLANETS = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'];

    public const HI = ['sun' => 'सूर्य', 'moon' => 'चंद्र', 'mars' => 'मंगल', 'mercury' => 'बुध', 'jupiter' => 'गुरु (बृहस्पति)', 'venus' => 'शुक्र', 'saturn' => 'शनि', 'rahu' => 'राहु', 'ketu' => 'केतु'];

    /** Houses from the natal Moon in which a transit is traditionally favourable. */
    public const GOOD_HOUSES = [
        'sun' => [3, 6, 10, 11], 'moon' => [1, 3, 6, 7, 10, 11], 'mars' => [3, 6, 11], 'mercury' => [2, 4, 6, 8, 10, 11],
        'jupiter' => [2, 5, 7, 9, 11], 'venus' => [1, 2, 3, 4, 5, 8, 9, 11, 12], 'saturn' => [3, 6, 11], 'rahu' => [3, 6, 11], 'ketu' => [3, 6, 11],
    ];

    /** Static reference facts for the nine planets (classical, Parashari). */
    public const FACTS = [
        'sun' => ['exalt' => [0, 10], 'debil' => 6, 'day' => 'Sunday', 'gem' => 'Ruby (Manik)', 'colour' => 'Red, saffron', 'stay' => 'About 1 month in each sign', 'signifies' => 'Soul, father, authority, health and vitality'],
        'moon' => ['exalt' => [1, 3], 'debil' => 7, 'day' => 'Monday', 'gem' => 'Pearl (Moti)', 'colour' => 'White', 'stay' => 'About 2¼ days in each sign', 'signifies' => 'Mind, mother, emotions and peace'],
        'mars' => ['exalt' => [9, 28], 'debil' => 3, 'day' => 'Tuesday', 'gem' => 'Red coral (Moonga)', 'colour' => 'Red', 'stay' => 'About 45 days in each sign (longer when retrograde)', 'signifies' => 'Courage, siblings, land and energy'],
        'mercury' => ['exalt' => [5, 15], 'debil' => 11, 'day' => 'Wednesday', 'gem' => 'Emerald (Panna)', 'colour' => 'Green', 'stay' => 'About 3–4 weeks in each sign (varies with retrogression)', 'signifies' => 'Intellect, speech, trade and learning'],
        'jupiter' => ['exalt' => [3, 5], 'debil' => 9, 'day' => 'Thursday', 'gem' => 'Yellow sapphire (Pukhraj)', 'colour' => 'Yellow', 'stay' => 'About 1 year in each sign', 'signifies' => 'Wisdom, teachers, children, dharma and fortune'],
        'venus' => ['exalt' => [11, 27], 'debil' => 5, 'day' => 'Friday', 'gem' => 'Diamond (Heera)', 'colour' => 'White, pink', 'stay' => 'About 1 month in each sign', 'signifies' => 'Love, marriage, comfort, art and vehicles'],
        'saturn' => ['exalt' => [6, 20], 'debil' => 0, 'day' => 'Saturday', 'gem' => 'Blue sapphire (Neelam)', 'colour' => 'Black, dark blue', 'stay' => 'About 2½ years in each sign', 'signifies' => 'Discipline, work, longevity, delay and justice'],
        'rahu' => ['exalt' => [1, null], 'debil' => 7, 'day' => '—', 'gem' => 'Hessonite (Gomed)', 'colour' => 'Smoky', 'stay' => 'About 18 months in each sign (always retrograde)', 'signifies' => 'Desire, foreign things, sudden events'],
        'ketu' => ['exalt' => [7, null], 'debil' => 1, 'day' => '—', 'gem' => "Cat's eye (Lahsuniya)", 'colour' => 'Grey, multicoloured', 'stay' => 'About 18 months in each sign (always retrograde)', 'signifies' => 'Detachment, spirituality, past karma'],
    ];

    public function __construct(private Engine $engine) {}

    /** Current sidereal positions of the nine planets. */
    public function now(?DateTimeImmutable $at = null): array
    {
        $at ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $c = $this->engine->chart(Engine::jd($at->setTimezone(new DateTimeZone('UTC'))), 25.59408, 85.13563);
        $out = [];
        foreach (self::PLANETS as $p) {
            $lon = $c['planets'][$p]['lon'];
            $out[$p] = ['sign' => Vedic::sign($lon), 'deg' => Vedic::dms(fmod($lon, 30)), 'nakshatra' => Vedic::NAKSHATRAS[Vedic::nakshatra($lon)], 'retro' => in_array($p, ['rahu', 'ketu'], true) ? true : $c['planets'][$p]['retro']];
        }

        return $out;
    }

    /** Ingresses and stations of $planet between two instants, in local time ($tz). */
    public function events(string $planet, DateTimeImmutable $from, DateTimeImmutable $to, string $tz): array
    {
        $utc = new DateTimeZone('UTC');
        [$a, $b] = [Engine::jd($from->setTimezone($utc)), Engine::jd($to->setTimezone($utc))];
        $zone = new DateTimeZone($tz);
        $out = [];
        foreach ($this->engine->signIngresses($planet, $a, $b) as $e) {
            $out[] = ['type' => 'ingress', 'at' => Engine::fromJd($e['jd'])->setTimezone($zone), 'sign' => $e['sign'], 'from' => ($e['sign'] + ($e['retro'] ? 1 : 11)) % 12, 'retro' => $e['retro']];
        }
        if (in_array($planet, ['mars', 'mercury', 'jupiter', 'venus', 'saturn'], true)) {
            foreach ($this->engine->stations($planet, $a, $b) as $e) {
                $out[] = ['type' => $e['retro'] ? 'retrograde' : 'direct', 'at' => Engine::fromJd($e['jd'])->setTimezone($zone), 'sign' => $e['sign'], 'deg' => Vedic::dms(fmod($e['lon'], 30))];
            }
        }
        usort($out, fn ($x, $y) => $x['at'] <=> $y['at']);

        return $out;
    }

    /** House (1..12) the planet in $sign occupies counted from each Moon sign, with the classical verdict. */
    public static function fromMoon(string $planet, int $sign): array
    {
        $out = [];
        for ($moon = 0; $moon < 12; $moon++) {
            $house = ($sign - $moon + 12) % 12 + 1;
            $out[$moon] = ['house' => $house, 'good' => in_array($house, self::GOOD_HOUSES[$planet], true)];
        }

        return $out;
    }

    /** Signs a planet rules (from Vedic::SIGN_LORDS); Rahu and Ketu rule none in this table. */
    public static function ownSigns(string $planet): array
    {
        return array_keys(array_filter(Vedic::SIGN_LORDS, fn ($l) => $l === $planet));
    }

    /** Nakshatras ruled by a planet in the Vimshottari scheme. */
    public static function nakshatras(string $planet): array
    {
        $i = array_search($planet, Vedic::DASHA_ORDER, true);

        return [Vedic::NAKSHATRAS[$i], Vedic::NAKSHATRAS[$i + 9], Vedic::NAKSHATRAS[$i + 18]];
    }
}
