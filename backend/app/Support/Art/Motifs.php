<?php

namespace App\Support\Art;

/**
 * Central motifs for ServiceArt, drawn around (0,0) inside a 220 px circle.
 * Ink is the brand indigo; $gold is the family accent.
 */
class Motifs
{
    public const INK = '#1e2656';

    public const NAMES = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu', 'navgrah', 'om', 'lotus', 'diya', 'kalash',
        'trishul', 'heart', 'rings', 'baby', 'briefcase', 'coins', 'house', 'yantra', 'star', 'scroll', 'hand', 'gem', 'rudraksha', 'chart', 'eye',
        'bell', 'conch', 'plane', 'shield', 'book', 'flame', 'family', 'crown', 'pyramid', 'numbers', 'zodiac'];

    public static function draw(string $motif, string $gold): string
    {
        $i = self::INK;
        $s = "stroke=\"$i\" stroke-width=\"7\" stroke-linecap=\"round\" stroke-linejoin=\"round\" fill=\"none\"";
        $sg = "stroke=\"$gold\" stroke-width=\"6\" stroke-linecap=\"round\" stroke-linejoin=\"round\" fill=\"none\"";
        $rays = fn (int $n, int $r1, int $r2, string $st) => implode('', array_map(fn ($k) => "<line x1=\"0\" y1=\"-$r1\" x2=\"0\" y2=\"-$r2\" transform=\"rotate(".($k * 360 / $n).")\" $st/>", range(0, $n - 1)));

        return match ($motif) {
            'sun' => "<circle r=\"42\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/>".$rays(12, 58, 92, $s)."<circle r=\"14\" fill=\"$i\"/>",
            'moon' => "<path d=\"M30 -78 A82 82 0 1 0 30 78 A62 62 0 1 1 30 -78 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><circle cx=\"52\" cy=\"-30\" r=\"6\" fill=\"$i\"/><circle cx=\"70\" cy=\"18\" r=\"4\" fill=\"$i\"/>",
            'mars' => "<circle cx=\"-18\" cy=\"18\" r=\"52\" $s/><path d=\"M20 -20 L72 -72 M34 -72 H72 V-34\" $s/><circle cx=\"-18\" cy=\"18\" r=\"20\" fill=\"$gold\"/>",
            'mercury' => "<circle cy=\"-6\" r=\"42\" $s/><path d=\"M-34 -84 A34 30 0 0 0 34 -84 M0 36 V92 M-28 66 H28\" $s/><circle cy=\"-6\" r=\"16\" fill=\"$gold\"/>",
            'jupiter' => "<path d=\"M-58 -60 C-20 -96 30 -60 -10 -10 L-60 40 H70 M30 -50 V96\" $s/><circle cx=\"30\" cy=\"40\" r=\"10\" fill=\"$gold\"/>",
            'venus' => "<circle cy=\"-28\" r=\"52\" $s/><path d=\"M0 24 V96 M-32 62 H32\" $s/><circle cy=\"-28\" r=\"22\" fill=\"$gold\"/>",
            'saturn' => "<circle r=\"50\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><ellipse rx=\"100\" ry=\"28\" transform=\"rotate(-18)\" $s/><path d=\"M-50 -4 A50 50 0 0 1 50 -4\" fill=\"$gold\" transform=\"rotate(-18)\"/><path d=\"M-50 -4 A50 50 0 0 1 50 -4\" stroke=\"$i\" stroke-width=\"6\" fill=\"none\" transform=\"rotate(-18)\"/>",
            'rahu' => "<path d=\"M-60 60 V-10 A60 60 0 0 1 60 -10 V60\" $s/><circle cx=\"-60\" cy=\"72\" r=\"16\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><circle cx=\"60\" cy=\"72\" r=\"16\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/>",
            'ketu' => "<path d=\"M-60 -60 V10 A60 60 0 0 0 60 10 V-60\" $s/><circle cx=\"-60\" cy=\"-72\" r=\"16\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><circle cx=\"60\" cy=\"-72\" r=\"16\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/>",
            'navgrah' => "<circle r=\"28\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/>".implode('', array_map(fn ($k) => '<circle cx="0" cy="-82" r="'.(10 + ($k % 3) * 3).'" fill="'.($k % 2 ? $gold : $i)."\" stroke=\"$i\" stroke-width=\"3\" transform=\"rotate(".($k * 45).')"/>', range(0, 7)))."<circle r=\"82\" fill=\"none\" stroke=\"$i\" stroke-width=\"2\" stroke-dasharray=\"4 8\"/>",
            'om' => "<text y=\"52\" text-anchor=\"middle\" font-family=\"Noto Sans Devanagari, sans-serif\" font-size=\"170\" font-weight=\"700\" fill=\"$i\">ॐ</text>",
            'lotus' => self::lotus($i, $gold),
            'diya' => "<path d=\"M-92 10 Q0 96 92 10 Q60 0 0 0 Q-60 0 -92 10 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M0 -6 C-30 -40 -6 -70 0 -100 C6 -70 30 -40 0 -6 Z\" fill=\"#f28c28\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M0 -18 C-10 -34 -2 -48 0 -62 C2 -48 10 -34 0 -18 Z\" fill=\"#fff3b0\"/><path d=\"M-60 70 H60\" $s/>",
            'kalash' => "<path d=\"M-50 -40 H50 L40 -22 Q80 30 40 80 H-40 Q-80 30 -40 -22 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-30 -40 Q-50 -80 -10 -70 M30 -40 Q50 -80 10 -70 M0 -40 Q0 -86 0 -96\" stroke=\"#2f7a3b\" stroke-width=\"8\" stroke-linecap=\"round\" fill=\"none\"/><circle cy=\"-96\" r=\"18\" fill=\"#b4471b\" stroke=\"$i\" stroke-width=\"4\"/><path d=\"M-56 24 H56\" $s/>",
            'trishul' => "<path d=\"M0 -96 V96 M-56 -70 Q-56 -10 0 -10 Q56 -10 56 -70 M-30 30 H30\" $s/><path d=\"M0 -96 L-10 -76 H10 Z M-56 -70 L-64 -54 H-48 Z M56 -70 L48 -54 H64 Z\" fill=\"$i\"/><circle cy=\"52\" r=\"12\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"4\"/>",
            'heart' => "<path d=\"M0 78 C-110 10 -80 -86 0 -38 C80 -86 110 10 0 78 Z\" fill=\"#d6455d\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-40 -30 C-30 -46 -14 -48 -4 -40\" stroke=\"#fff\" stroke-width=\"6\" fill=\"none\" stroke-linecap=\"round\" opacity=\"0.7\"/>",
            'rings' => "<circle cx=\"-30\" cy=\"10\" r=\"52\" $s/><circle cx=\"34\" cy=\"10\" r=\"52\" stroke=\"$gold\" stroke-width=\"9\" fill=\"none\"/><path d=\"M18 -54 L34 -76 L50 -54 Z\" fill=\"#8fd3ff\" stroke=\"$i\" stroke-width=\"4\"/>",
            'baby' => "<circle cy=\"-36\" r=\"40\" fill=\"#ffe3c4\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-70 92 Q-70 6 0 6 Q70 6 70 92 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><circle cx=\"-14\" cy=\"-38\" r=\"4\" fill=\"$i\"/><circle cx=\"14\" cy=\"-38\" r=\"4\" fill=\"$i\"/><path d=\"M-12 -20 Q0 -10 12 -20\" $s/><path d=\"M0 -76 Q14 -96 4 -104\" $s/>",
            'briefcase' => "<rect x=\"-86\" y=\"-40\" width=\"172\" height=\"112\" rx=\"14\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-32 -40 V-62 Q-32 -72 -22 -72 H22 Q32 -72 32 -62 V-40 M-86 6 H86\" $s/><rect x=\"-14\" y=\"-6\" width=\"28\" height=\"24\" rx=\"4\" fill=\"$i\"/>",
            'coins' => "<ellipse cy=\"62\" rx=\"70\" ry=\"20\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><ellipse cy=\"34\" rx=\"70\" ry=\"20\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><ellipse cy=\"6\" rx=\"70\" ry=\"20\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><circle cy=\"-52\" r=\"46\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><text y=\"-34\" text-anchor=\"middle\" font-family=\"Noto Sans, sans-serif\" font-size=\"56\" font-weight=\"700\" fill=\"$i\">₹</text>",
            'house' => "<path d=\"M-90 0 L0 -84 L90 0\" $s/><rect x=\"-66\" y=\"-6\" width=\"132\" height=\"92\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/><rect x=\"-18\" y=\"30\" width=\"36\" height=\"56\" fill=\"$i\"/><rect x=\"-52\" y=\"14\" width=\"24\" height=\"24\" fill=\"#fff\" stroke=\"$i\" stroke-width=\"4\"/><rect x=\"28\" y=\"14\" width=\"24\" height=\"24\" fill=\"#fff\" stroke=\"$i\" stroke-width=\"4\"/>",
            'yantra' => "<rect x=\"-90\" y=\"-90\" width=\"180\" height=\"180\" $s/><circle r=\"70\" stroke=\"$gold\" stroke-width=\"5\" fill=\"none\"/><path d=\"M0 -62 L54 32 H-54 Z\" $s/><path d=\"M0 62 L54 -32 H-54 Z\" stroke=\"$gold\" stroke-width=\"6\" fill=\"none\" stroke-linejoin=\"round\"/><circle r=\"8\" fill=\"$i\"/>",
            'star' => "<path d=\"M0 -96 L24 -30 L94 -30 L38 12 L58 80 L0 40 L-58 80 L-38 12 L-94 -30 L-24 -30 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><circle r=\"14\" fill=\"$i\"/>",
            'scroll' => "<rect x=\"-66\" y=\"-80\" width=\"132\" height=\"160\" rx=\"8\" fill=\"#fffaf0\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-40 -44 H40 M-40 -16 H40 M-40 12 H24 M-40 40 H10\" $s/><circle cx=\"44\" cy=\"52\" r=\"22\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M36 52 L42 58 L54 44\" $s/>",
            'hand' => "<path d=\"M-50 90 V-6 Q-50 -20 -38 -20 Q-26 -20 -26 -6 V-60 Q-26 -74 -12 -74 Q2 -74 2 -60 V-84 Q2 -98 16 -98 Q30 -98 30 -84 V-56 Q30 -68 44 -68 Q58 -68 58 -54 V20 Q58 70 20 90 Z\" fill=\"#ffe3c4\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-30 30 Q4 6 44 22 M-30 50 Q10 40 40 52 M6 70 Q0 30 18 -20\" stroke=\"$gold\" stroke-width=\"5\" fill=\"none\" stroke-linecap=\"round\"/>",
            'gem' => "<path d=\"M-80 -30 L-44 -78 H44 L80 -30 L0 86 Z\" fill=\"#3fa7d6\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><path d=\"M-80 -30 H80 M-44 -78 L-24 -30 L0 86 L24 -30 L44 -78 M-24 -30 L0 -78 L24 -30\" stroke=\"$i\" stroke-width=\"4\" fill=\"none\" stroke-linejoin=\"round\"/><path d=\"M-60 -30 L-36 -66\" stroke=\"#fff\" stroke-width=\"5\" opacity=\"0.7\" stroke-linecap=\"round\"/>",
            'rudraksha' => implode('', array_map(fn ($k) => "<circle cx=\"0\" cy=\"-78\" r=\"13\" fill=\"#8a4b25\" stroke=\"$i\" stroke-width=\"3\" transform=\"rotate(".($k * 30).')"/>', range(0, 11)))."<circle r=\"34\" fill=\"#8a4b25\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M0 -30 V30 M-26 -16 Q0 0 -26 16 M26 -16 Q0 0 26 16\" stroke=\"#3d1f0c\" stroke-width=\"4\" fill=\"none\"/>",
            'chart' => "<rect x=\"-90\" y=\"-90\" width=\"180\" height=\"180\" fill=\"#fffaf0\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-90 -90 L90 90 M90 -90 L-90 90 M0 -90 L90 0 L0 90 L-90 0 Z\" stroke=\"$i\" stroke-width=\"4\" fill=\"none\"/><circle cy=\"-44\" r=\"9\" fill=\"$gold\"/><circle cx=\"44\" r=\"9\" fill=\"$gold\"/><circle cx=\"-50\" cy=\"58\" r=\"7\" fill=\"$i\"/>",
            'eye' => "<path d=\"M-96 0 Q0 -86 96 0 Q0 86 -96 0 Z\" fill=\"#fffaf0\" stroke=\"$i\" stroke-width=\"6\"/><circle r=\"36\" fill=\"#2f56a8\" stroke=\"$i\" stroke-width=\"5\"/><circle r=\"14\" fill=\"$i\"/><circle cx=\"10\" cy=\"-10\" r=\"6\" fill=\"#fff\"/>".$rays(8, 70, 96, $sg),
            'bell' => "<path d=\"M-62 50 Q-62 -60 0 -66 Q62 -60 62 50 L76 66 H-76 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><circle cy=\"82\" r=\"14\" fill=\"$i\"/><path d=\"M0 -66 V-90\" $s/><circle cy=\"-96\" r=\"8\" fill=\"$i\"/>",
            'conch' => "<path d=\"M-80 40 Q-90 -40 -10 -70 Q60 -96 84 -20 Q96 30 40 60 Q0 80 -40 66 Z\" fill=\"#fffaf0\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-40 30 Q-30 -30 30 -40 Q60 -20 40 20 Q20 40 0 20\" stroke=\"$gold\" stroke-width=\"6\" fill=\"none\" stroke-linecap=\"round\"/><path d=\"M-80 40 L-96 72\" $s/>",
            'plane' => "<path d=\"M-96 14 L96 -20 Q108 -22 104 -8 L-80 40 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\" stroke-linejoin=\"round\"/><path d=\"M-10 2 L-50 -70 L-24 -72 L40 -10 M-20 30 L-40 82 L-16 80 L20 22 M-82 22 L-96 -14 L-80 -14 L-60 18\" fill=\"$i\" stroke=\"$i\" stroke-width=\"3\" stroke-linejoin=\"round\"/>",
            'shield' => "<path d=\"M0 -96 L80 -64 V4 Q80 70 0 98 Q-80 70 -80 4 V-64 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><path d=\"M-34 2 L-8 30 L40 -26\" stroke=\"$i\" stroke-width=\"10\" fill=\"none\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>",
            'book' => "<path d=\"M0 -64 Q-50 -90 -96 -70 V70 Q-50 52 0 74 Q50 52 96 70 V-70 Q50 -90 0 -64 Z\" fill=\"#fffaf0\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><path d=\"M0 -64 V74 M-74 -40 Q-40 -50 -20 -38 M-74 -10 Q-40 -20 -20 -8 M20 -38 Q40 -50 74 -40 M20 -8 Q40 -20 74 -10\" stroke=\"$gold\" stroke-width=\"5\" fill=\"none\" stroke-linecap=\"round\"/>",
            'flame' => "<path d=\"M-80 80 H80 L66 52 H-66 Z\" fill=\"#8a4b25\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M0 50 C-70 30 -50 -40 -14 -70 C-20 -30 10 -30 0 -96 C50 -60 76 0 40 50 Z\" fill=\"#f28c28\" stroke=\"$i\" stroke-width=\"5\" stroke-linejoin=\"round\"/><path d=\"M0 50 C-30 34 -20 -6 0 -26 C20 -6 30 34 0 50 Z\" fill=\"#ffd27a\"/>",
            'family' => "<circle cx=\"-46\" cy=\"-44\" r=\"26\" fill=\"#ffe3c4\" stroke=\"$i\" stroke-width=\"5\"/><circle cx=\"46\" cy=\"-44\" r=\"26\" fill=\"#ffe3c4\" stroke=\"$i\" stroke-width=\"5\"/><circle cy=\"6\" r=\"20\" fill=\"#ffe3c4\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M-90 84 Q-90 -10 -46 -10 Q-10 -10 -6 30 M90 84 Q90 -10 46 -10 Q10 -10 6 30\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M-34 90 Q-34 30 0 30 Q34 30 34 90 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"5\"/>",
            'crown' => "<path d=\"M-86 60 L-96 -50 L-46 -4 L0 -76 L46 -4 L96 -50 L86 60 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><path d=\"M-86 60 H86 V82 H-86 Z\" fill=\"$i\"/><circle cy=\"-82\" r=\"10\" fill=\"#d6455d\"/><circle cx=\"-96\" cy=\"-56\" r=\"8\" fill=\"#3fa7d6\"/><circle cx=\"96\" cy=\"-56\" r=\"8\" fill=\"#3fa7d6\"/>",
            'pyramid' => "<path d=\"M0 -92 L96 76 H-96 Z\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\" stroke-linejoin=\"round\"/><path d=\"M0 -92 L22 76\" stroke=\"$i\" stroke-width=\"5\"/><path d=\"M-60 12 H70 M-30 -40 H38\" stroke=\"$i\" stroke-width=\"3\" opacity=\"0.6\"/>".$rays(6, 100, 106, $sg),
            'numbers' => "<rect x=\"-90\" y=\"-90\" width=\"180\" height=\"180\" rx=\"10\" fill=\"#fffaf0\" stroke=\"$i\" stroke-width=\"6\"/><path d=\"M-30 -90 V90 M30 -90 V90 M-90 -30 H90 M-90 30 H90\" stroke=\"$i\" stroke-width=\"3\"/>"
                .implode('', array_map(fn ($n, $k) => '<text x="'.((($k % 3) - 1) * 60).'" y="'.((intdiv($k, 3) - 1) * 60 + 16).'" text-anchor="middle" font-family="Noto Sans, sans-serif" font-size="40" font-weight="700" fill="'.($n % 2 ? $i : '#b4471b').'">'.$n.'</text>', [4, 9, 2, 3, 5, 7, 8, 1, 6], range(0, 8))),
            'zodiac' => "<circle r=\"92\" $s/><circle r=\"52\" stroke=\"$gold\" stroke-width=\"5\" fill=\"none\"/>".$rays(12, 52, 92, "stroke=\"$i\" stroke-width=\"3\"").implode('', array_map(fn ($k) => '<circle cx="0" cy="-72" r="6" fill="'.($k % 2 ? $gold : $i).'" transform="rotate('.($k * 30 + 15).')"/>', range(0, 11)))."<circle r=\"18\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"4\"/>",
            default => "<circle r=\"60\" fill=\"$gold\" stroke=\"$i\" stroke-width=\"6\"/>",
        };
    }

    private static function lotus(string $i, string $gold): string
    {
        $petal = fn ($a, $fill) => "<path d=\"M0 40 C-34 0 -26 -50 0 -86 C26 -50 34 0 0 40 Z\" transform=\"rotate($a 0 40)\" fill=\"$fill\" stroke=\"$i\" stroke-width=\"5\" stroke-linejoin=\"round\"/>";

        return $petal(-64, '#f7a8b8').$petal(64, '#f7a8b8').$petal(-32, '#f28aa0').$petal(32, '#f28aa0').$petal(0, '#e8607c')
            ."<path d=\"M-90 56 Q0 86 90 56\" stroke=\"$gold\" stroke-width=\"7\" fill=\"none\" stroke-linecap=\"round\"/>";
    }
}
