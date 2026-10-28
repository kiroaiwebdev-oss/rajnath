<?php

namespace App\Domain\Astro;

/**
 * Ashtakoota Guna Milan (36 points) from the Moon nakshatra and Moon sign of both partners.
 * Tables follow the commonly published North Indian convention; regional variants exist and
 * dosha-cancellation (parihara) rules are NOT applied automatically. See docs/astro/methods.md.
 */
final class Matching
{
    // Varna by Moon sign: 0 Brahmin (water signs), 1 Kshatriya (fire), 2 Vaishya (earth), 3 Shudra (air).
    private const VARNA_BY_SIGN = [1, 2, 3, 0, 1, 2, 3, 0, 1, 2, 3, 0];

    private const VASHYA_MATRIX = [ // groom row, bride col: chatushpad, manav, jalachar, vanachar, keeta
        [2, 1, 1, 0.5, 1], [1, 2, 0.5, 0, 1], [1, 0.5, 2, 1, 1], [0.5, 0, 1, 2, 0], [1, 1, 1, 0, 2],
    ];

    private const YONI_BY_NAK = [0, 1, 2, 3, 3, 4, 5, 2, 5, 6, 6, 7, 8, 9, 8, 9, 10, 10, 4, 11, 12, 11, 13, 0, 13, 7, 1];
    private const YONI_NAMES = ['Horse', 'Elephant', 'Sheep', 'Serpent', 'Dog', 'Cat', 'Rat', 'Cow', 'Buffalo', 'Tiger', 'Deer', 'Monkey', 'Mongoose', 'Lion'];
    private const YONI = [
        [4, 2, 2, 3, 2, 2, 2, 1, 0, 1, 3, 3, 2, 1], [2, 4, 3, 3, 2, 2, 2, 2, 3, 1, 2, 3, 2, 0], [2, 3, 4, 2, 1, 2, 1, 3, 3, 1, 2, 0, 3, 1],
        [3, 3, 2, 4, 2, 1, 1, 1, 1, 2, 2, 2, 0, 2], [2, 2, 1, 2, 4, 2, 1, 2, 2, 1, 0, 2, 1, 1], [2, 2, 2, 1, 2, 4, 0, 2, 2, 1, 3, 3, 2, 1],
        [2, 2, 1, 1, 1, 0, 4, 2, 2, 2, 2, 2, 1, 2], [1, 2, 3, 1, 2, 2, 2, 4, 3, 0, 3, 2, 2, 1], [0, 3, 3, 1, 2, 2, 2, 3, 4, 1, 2, 2, 2, 1],
        [1, 1, 1, 2, 1, 1, 2, 0, 1, 4, 1, 1, 2, 1], [3, 2, 2, 2, 0, 3, 2, 3, 2, 1, 4, 2, 2, 1], [3, 3, 0, 2, 2, 3, 2, 2, 2, 1, 2, 4, 3, 2],
        [2, 2, 3, 0, 1, 2, 1, 2, 2, 2, 2, 3, 4, 2], [1, 0, 1, 2, 1, 1, 2, 1, 1, 1, 1, 2, 2, 4],
    ];

    // Natural friendship: 1 friend, 0 neutral, -1 enemy. Row = planet, col = other.
    private const FRIEND = [
        'sun' => ['moon' => 1, 'mars' => 1, 'jupiter' => 1, 'mercury' => 0, 'venus' => -1, 'saturn' => -1],
        'moon' => ['sun' => 1, 'mercury' => 1, 'mars' => 0, 'jupiter' => 0, 'venus' => 0, 'saturn' => 0],
        'mars' => ['sun' => 1, 'moon' => 1, 'jupiter' => 1, 'venus' => 0, 'saturn' => 0, 'mercury' => -1],
        'mercury' => ['sun' => 1, 'venus' => 1, 'mars' => 0, 'jupiter' => 0, 'saturn' => 0, 'moon' => -1],
        'jupiter' => ['sun' => 1, 'moon' => 1, 'mars' => 1, 'saturn' => 0, 'mercury' => -1, 'venus' => -1],
        'venus' => ['mercury' => 1, 'saturn' => 1, 'mars' => 0, 'jupiter' => 0, 'sun' => -1, 'moon' => -1],
        'saturn' => ['mercury' => 1, 'venus' => 1, 'jupiter' => 0, 'sun' => -1, 'moon' => -1, 'mars' => -1],
    ];

    private const GANA_BY_NAK = [0, 1, 2, 1, 0, 1, 0, 0, 2, 2, 1, 1, 0, 2, 0, 2, 0, 2, 2, 1, 1, 0, 2, 2, 1, 1, 0]; // 0 Deva 1 Manushya 2 Rakshasa
    private const GANA = [[6, 6, 1], [5, 6, 0], [1, 0, 6]]; // groom row, bride col

    /** @param array{nak:int,sign:int,moon_lon:float} $groom @param array{nak:int,sign:int,moon_lon:float} $bride */
    public static function ashtakoota(array $groom, array $bride): array
    {
        $k = [];
        $vg = self::VARNA_BY_SIGN[$groom['sign']];
        $vb = self::VARNA_BY_SIGN[$bride['sign']];
        $k['varna'] = ['max' => 1, 'score' => $vg <= $vb ? 1 : 0, 'detail' => self::varnaName($vg).' / '.self::varnaName($vb)];

        $vashG = self::vashya($groom['sign'], $groom['moon_lon']);
        $vashB = self::vashya($bride['sign'], $bride['moon_lon']);
        $k['vashya'] = ['max' => 2, 'score' => self::VASHYA_MATRIX[$vashG][$vashB], 'detail' => self::vashyaName($vashG).' / '.self::vashyaName($vashB)];

        $tara = function (int $from, int $to) {
            $n = (($to - $from + 27) % 27) + 1;

            return in_array($n % 9, [3, 5, 7], true) ? 0 : 1.5;
        };
        $k['tara'] = ['max' => 3, 'score' => $tara($bride['nak'], $groom['nak']) + $tara($groom['nak'], $bride['nak']), 'detail' => 'Counted both ways between birth stars'];

        $yg = self::YONI_BY_NAK[$groom['nak']];
        $yb = self::YONI_BY_NAK[$bride['nak']];
        $k['yoni'] = ['max' => 4, 'score' => self::YONI[$yg][$yb], 'detail' => self::YONI_NAMES[$yg].' / '.self::YONI_NAMES[$yb]];

        $lg = Vedic::SIGN_LORDS[$groom['sign']];
        $lb = Vedic::SIGN_LORDS[$bride['sign']];
        $k['graha_maitri'] = ['max' => 5, 'score' => self::maitri($lg, $lb), 'detail' => ucfirst($lg).' / '.ucfirst($lb)];

        $gg = self::GANA_BY_NAK[$groom['nak']];
        $gb = self::GANA_BY_NAK[$bride['nak']];
        $k['gana'] = ['max' => 6, 'score' => self::GANA[$gg][$gb], 'detail' => ['Deva', 'Manushya', 'Rakshasa'][$gg].' / '.['Deva', 'Manushya', 'Rakshasa'][$gb]];

        $d = (($groom['sign'] - $bride['sign'] + 12) % 12) + 1;
        $back = (($bride['sign'] - $groom['sign'] + 12) % 12) + 1;
        $k['bhakoot'] = ['max' => 7, 'score' => in_array($d, [2, 12, 5, 9, 6, 8], true) ? 0 : 7, 'detail' => "Moon signs $d/$back from each other"];

        $ng = [0, 1, 2, 2, 1, 0][$groom['nak'] % 6];
        $nb = [0, 1, 2, 2, 1, 0][$bride['nak'] % 6];
        $k['nadi'] = ['max' => 8, 'score' => $ng === $nb ? 0 : 8, 'detail' => ['Adi', 'Madhya', 'Antya'][$ng].' / '.['Adi', 'Madhya', 'Antya'][$nb]];

        $total = array_sum(array_column($k, 'score'));

        return ['kootas' => $k, 'total' => $total, 'max' => 36,
            'doshas' => array_values(array_filter([$k['nadi']['score'] === 0 ? 'Nadi dosha' : null, $k['bhakoot']['score'] === 0 ? 'Bhakoot dosha' : null, $k['gana']['score'] <= 1 ? 'Gana dosha' : null])),
            'band' => $total >= 28 ? 'very good' : ($total >= 18 ? 'acceptable' : 'below the traditional minimum of 18')];
    }

    private static function vashya(int $sign, float $lon): int
    {
        $deg = fmod($lon, 30);

        return match ($sign) {
            0, 1 => 0,
            8 => $deg < 15 ? 1 : 0,
            9 => $deg < 15 ? 0 : 2,
            2, 5, 6, 10 => 1,
            3, 11 => 2,
            4 => 3,
            7 => 4,
        };
    }

    private static function maitri(string $a, string $b): float
    {
        if ($a === $b) {
            return 5;
        }
        $x = self::FRIEND[$a][$b];
        $y = self::FRIEND[$b][$a];
        $pair = [$x, $y];
        sort($pair);

        return match ($pair) {
            [1, 1] => 5, [0, 1] => 4, [0, 0] => 3, [-1, 1] => 1, [-1, 0] => 0.5, default => 0,
        };
    }

    private static function varnaName(int $v): string
    {
        return ['Brahmin', 'Kshatriya', 'Vaishya', 'Shudra'][$v];
    }

    private static function vashyaName(int $v): string
    {
        return ['Chatushpad', 'Manav', 'Jalachar', 'Vanachar', 'Keeta'][$v];
    }
}
