<?php

namespace App\Support\Art;

/**
 * Original vector illustrations for services: a coloured sky with a mandala, stars and a
 * central medallion holding a motif (planet, lotus, diya, kalash, rings…). Each service gets its
 * own palette and motif, so cards no longer repeat the same photograph. Rendered to JPG/WebP by
 * `php artisan arj:generate-art` (headless Chromium), so the website, the app and link previews
 * can all use them.
 */
class ServiceArt
{
    public const W = 960;

    public const H = 720;

    /** Family palettes: [sky top, sky bottom, accent]. */
    public const PALETTES = [
        'consultation' => ['#141a3c', '#2b356f', '#d9a441'],
        'report' => ['#0f3b45', '#1d6a73', '#e6b65c'],
        'bundle' => ['#3a1747', '#6b2d73', '#f0c060'],
        'puja' => ['#5a1410', '#b4471b', '#ffd27a'],
        'chadhava' => ['#4a0f22', '#9c2440', '#ffcf6e'],
        'remedy' => ['#0f3d2c', '#1f7a55', '#ead27a'],
        'vastu' => ['#4a2412', '#9a5323', '#f3d08a'],
        'written_question' => ['#13285a', '#2f56a8', '#f1c85b'],
        'other' => ['#1e2656', '#444d80', '#d9a441'],
    ];

    public static function svg(string $key, string $motif, string $family): string
    {
        [$top, $bottom, $gold] = self::PALETTES[$family] ?? self::PALETTES['other'];
        $seed = crc32($key);
        $shift = ($seed % 40) - 20; // slight hue change per service
        $top = self::hueShift($top, $shift);
        $bottom = self::hueShift($bottom, $shift);
        $w = self::W;
        $h = self::H;
        $cx = $w / 2;
        $cy = $h / 2 + 10;

        $stars = '';
        $r = $seed;
        for ($i = 0; $i < 70; $i++) {
            $r = ($r * 1103515245 + 12345) & 0x7FFFFFFF;
            $x = $r % $w;
            $r = ($r * 1103515245 + 12345) & 0x7FFFFFFF;
            $y = $r % $h;
            $r = ($r * 1103515245 + 12345) & 0x7FFFFFFF;
            $s = 0.6 + ($r % 20) / 10;
            $o = 0.25 + ($r % 60) / 100;
            $stars .= sprintf('<circle cx="%d" cy="%d" r="%.1f" fill="#fff" opacity="%.2f"/>', $x, $y, $s, $o);
        }

        $petals = '';
        for ($i = 0; $i < 24; $i++) {
            $a = $i * 15;
            $petals .= "<ellipse cx=\"$cx\" cy=\"".($cy - 250).'" rx="22" ry="62" transform="rotate('.$a." $cx $cy)\" fill=\"none\" stroke=\"$gold\" stroke-width=\"1.4\" opacity=\"0.28\"/>";
        }
        $ticks = '';
        for ($i = 0; $i < 72; $i++) {
            $ticks .= "<line x1=\"$cx\" y1=\"".($cy - 196).'" x2="'.$cx.'" y2="'.($cy - ($i % 3 === 0 ? 182 : 189)).'" transform="rotate('.($i * 5)." $cx $cy)\" stroke=\"$gold\" stroke-width=\"1.5\" opacity=\"0.55\"/>";
        }

        $motifSvg = Motifs::draw($motif, $gold);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="$w" height="$h" viewBox="0 0 $w $h">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0.4" y2="1"><stop offset="0" stop-color="$top"/><stop offset="1" stop-color="$bottom"/></linearGradient>
    <radialGradient id="glow" cx="0.5" cy="0.52" r="0.55"><stop offset="0" stop-color="$gold" stop-opacity="0.45"/><stop offset="0.55" stop-color="$gold" stop-opacity="0.08"/><stop offset="1" stop-color="$gold" stop-opacity="0"/></radialGradient>
    <radialGradient id="disc" cx="0.42" cy="0.38" r="0.75"><stop offset="0" stop-color="#fffaf0"/><stop offset="1" stop-color="#f3e3c2"/></radialGradient>
    <filter id="soft" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="14"/></filter>
  </defs>
  <rect width="$w" height="$h" fill="url(#sky)"/>
  $stars
  <rect width="$w" height="$h" fill="url(#glow)"/>
  <g>$petals</g>
  <circle cx="$cx" cy="$cy" r="300" fill="none" stroke="$gold" stroke-width="1" opacity="0.25"/>
  <circle cx="$cx" cy="$cy" r="232" fill="none" stroke="$gold" stroke-width="2" stroke-dasharray="2 10" opacity="0.6"/>
  <circle cx="$cx" cy="$cy" r="200" fill="none" stroke="$gold" stroke-width="2.5" opacity="0.8"/>
  <g>$ticks</g>
  <circle cx="$cx" cy="$cy" r="168" fill="#000" opacity="0.35" filter="url(#soft)"/>
  <circle cx="$cx" cy="$cy" r="164" fill="url(#disc)" stroke="$gold" stroke-width="6"/>
  <circle cx="$cx" cy="$cy" r="146" fill="none" stroke="$gold" stroke-width="1.5" opacity="0.7"/>
  <g transform="translate($cx $cy) scale(1.3)">$motifSvg</g>
</svg>
SVG;
    }

    /** Rotates a #rrggbb colour's hue by $deg degrees. */
    private static function hueShift(string $hex, int $deg): string
    {
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        $h = 0.0;
        $s = 0.0;
        if ($d > 0) {
            $s = $d / (1 - abs(2 * $l - 1));
            $h = match ($max) {
                $r => fmod(($g - $b) / $d, 6), $g => ($b - $r) / $d + 2, default => ($r - $g) / $d + 4
            } * 60;
        }
        $h = fmod($h + $deg + 360, 360);
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
