<?php

namespace App\Domain\Astro;

/**
 * Traditional numerology (Chaldean letter values; Vedic planet-number correspondences).
 * These are symbolic, traditional methods — not scientific measurements.
 */
final class Numerology
{
    public const CHALDEAN = ['A' => 1, 'I' => 1, 'J' => 1, 'Q' => 1, 'Y' => 1, 'B' => 2, 'K' => 2, 'R' => 2, 'C' => 3, 'G' => 3, 'L' => 3, 'S' => 3,
        'D' => 4, 'M' => 4, 'T' => 4, 'E' => 5, 'H' => 5, 'N' => 5, 'X' => 5, 'U' => 6, 'V' => 6, 'W' => 6, 'O' => 7, 'Z' => 7, 'F' => 8, 'P' => 8];

    /** Number → ruling planet (Vedic numerology). */
    public const PLANET = [1 => 'sun', 2 => 'moon', 3 => 'jupiter', 4 => 'rahu', 5 => 'mercury', 6 => 'venus', 7 => 'ketu', 8 => 'saturn', 9 => 'mars'];

    /** Traditional associations per number: colours, days, gem, friendly and unfriendly numbers. */
    public const TRAITS = [
        1 => ['colours' => ['Gold', 'Orange', 'Yellow'], 'avoid_colours' => ['Black', 'Dark blue'], 'day' => 'Sunday', 'gem' => 'Ruby', 'friendly' => [1, 2, 3, 9], 'unfriendly' => [8]],
        2 => ['colours' => ['White', 'Cream', 'Light green'], 'avoid_colours' => ['Red', 'Black'], 'day' => 'Monday', 'gem' => 'Pearl', 'friendly' => [1, 2, 5], 'unfriendly' => [8, 9]],
        3 => ['colours' => ['Yellow', 'Pink', 'Violet'], 'avoid_colours' => ['Black'], 'day' => 'Thursday', 'gem' => 'Yellow sapphire', 'friendly' => [1, 3, 5, 9], 'unfriendly' => [6]],
        4 => ['colours' => ['Grey', 'Light blue', 'Khaki'], 'avoid_colours' => ['Black'], 'day' => 'Saturday', 'gem' => 'Hessonite (Gomed)', 'friendly' => [1, 5, 6, 7], 'unfriendly' => [2, 9]],
        5 => ['colours' => ['Green', 'Light grey', 'White'], 'avoid_colours' => ['Dark red'], 'day' => 'Wednesday', 'gem' => 'Emerald', 'friendly' => [1, 4, 5, 6], 'unfriendly' => [2]],
        6 => ['colours' => ['White', 'Light blue', 'Pink'], 'avoid_colours' => ['Black'], 'day' => 'Friday', 'gem' => 'Diamond / white sapphire', 'friendly' => [4, 5, 6, 8], 'unfriendly' => [3]],
        7 => ['colours' => ['Light green', 'White', 'Pastel shades'], 'avoid_colours' => ['Dark red', 'Black'], 'day' => 'Monday', 'gem' => "Cat's eye", 'friendly' => [2, 4, 6, 7], 'unfriendly' => [8, 9]],
        8 => ['colours' => ['Dark blue', 'Black', 'Purple'], 'avoid_colours' => ['Red'], 'day' => 'Saturday', 'gem' => 'Blue sapphire', 'friendly' => [4, 5, 6, 8], 'unfriendly' => [1, 2]],
        9 => ['colours' => ['Red', 'Coral', 'Pink'], 'avoid_colours' => ['Black', 'Blue'], 'day' => 'Tuesday', 'gem' => 'Red coral', 'friendly' => [1, 3, 9], 'unfriendly' => [4, 8]],
    ];

    public static function reduce(int $n): int
    {
        while ($n > 9) {
            $n = array_sum(str_split((string) $n));
        }

        return max(1, $n);
    }

    public static function mulank(int $day): int
    {
        return self::reduce($day);
    }

    public static function bhagyank(string $ymd): int
    {
        return self::reduce(array_sum(str_split(str_replace('-', '', $ymd))));
    }

    /** @return array{total:int, number:int, letters: array<array{0:string,1:int}>} */
    public static function nameNumber(string $name): array
    {
        $letters = [];
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($name)))) as $ch) {
            $letters[] = [$ch, self::CHALDEAN[$ch] ?? 0];
        }
        $total = array_sum(array_column($letters, 1));

        return ['total' => $total, 'number' => $total ? self::reduce($total) : 0, 'letters' => $letters];
    }

    /** Lo Shu grid: count of each digit 1–9 in the date of birth (zeros ignored). */
    public static function loShu(string $ymd): array
    {
        $counts = array_fill(1, 9, 0);
        foreach (str_split(str_replace('-', '', $ymd)) as $d) {
            if ($d !== '0') {
                $counts[(int) $d]++;
            }
        }
        $layout = [[4, 9, 2], [3, 5, 7], [8, 1, 6]];
        $planes = ['Mental (4-9-2)' => [4, 9, 2], 'Emotional (3-5-7)' => [3, 5, 7], 'Practical (8-1-6)' => [8, 1, 6], 'Thought (4-3-8)' => [4, 3, 8], 'Will (9-5-1)' => [9, 5, 1], 'Action (2-7-6)' => [2, 7, 6]];
        $full = [];
        $empty = [];
        foreach ($planes as $n => $digits) {
            $have = count(array_filter($digits, fn ($d) => $counts[$d] > 0));
            if ($have === 3) {
                $full[] = $n;
            } elseif ($have === 0) {
                $empty[] = $n;
            }
        }

        return ['counts' => $counts, 'layout' => $layout, 'missing' => array_keys(array_filter($counts, fn ($c) => $c === 0)), 'complete_planes' => $full, 'empty_planes' => $empty];
    }

    public static function personalYear(int $day, int $month, int $year): int
    {
        return self::reduce(self::reduce($day) + self::reduce($month) + self::reduce($year));
    }

    /** Dates in a month whose day number (reduced) is friendly / unfriendly to the person's Mulank. */
    public static function dates(int $mulank, int $year, int $month): array
    {
        $t = self::TRAITS[$mulank];
        $days = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        $lucky = $unlucky = [];
        for ($d = 1; $d <= $days; $d++) {
            $r = self::reduce($d);
            if (in_array($r, $t['friendly'], true)) {
                $lucky[] = $d;
            } elseif (in_array($r, $t['unfriendly'], true)) {
                $unlucky[] = $d;
            }
        }

        return ['favourable' => $lucky, 'less_favourable' => $unlucky];
    }

    public static function vehicle(string $plate, int $mulank): array
    {
        $digits = preg_replace('/\D/', '', $plate);
        $n = $digits === '' ? 0 : self::reduce(array_sum(str_split($digits)));
        $t = self::TRAITS[$mulank];

        return ['number' => $n, 'verdict' => $n === 0 ? 'no digits' : (in_array($n, $t['friendly'], true) ? 'favourable' : (in_array($n, $t['unfriendly'], true) ? 'less favourable' : 'neutral'))];
    }

    /** FLAMES — a children's game. Entertainment only. */
    public static function flames(string $a, string $b): string
    {
        $x = str_split(strtolower(preg_replace('/[^a-z]/i', '', $a)));
        $y = str_split(strtolower(preg_replace('/[^a-z]/i', '', $b)));
        foreach ($x as $i => $ch) {
            $j = array_search($ch, $y, true);
            if ($j !== false) {
                unset($x[$i], $y[$j]);
            }
        }
        $n = count($x) + count($y);
        $f = ['Friends', 'Love', 'Affection', 'Marriage', 'Enemies', 'Siblings'];
        if ($n === 0) {
            return 'Same letters — play again with full names';
        }
        $i = 0;
        while (count($f) > 1) {
            $i = ($i + $n - 1) % count($f);
            array_splice($f, $i, 1);
        }

        return $f[0];
    }
}
