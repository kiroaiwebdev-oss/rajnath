<?php

namespace App\Domain\Astro;

use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin, strict wrapper around engine/bin/arj-ephem (Swiss Ephemeris, AGPL-3.0, Lahiri ayanamsha).
 * Arguments are numeric and validated before the process is started; output is JSON.
 */
class Engine
{
    public function __construct(private ?string $binary = null, private ?string $ephePath = null)
    {
        $this->binary ??= config('astro.binary');
        $this->ephePath ??= config('astro.ephe_path');
    }

    public static function jd(DateTimeImmutable $utc): float
    {
        return $utc->getTimestamp() / 86400 + 2440587.5 + ((int) $utc->format('u')) / 86400e6;
    }

    public static function fromJd(float $jd): DateTimeImmutable
    {
        $sec = (int) round(($jd - 2440587.5) * 86400);

        return (new DateTimeImmutable('@'.$sec))->setTimezone(new \DateTimeZone('UTC'));
    }

    public function chart(float $jd, float $lat, float $lng, string $node = 'mean'): array
    {
        return $this->run(['chart', self::f($jd), self::f($lat), self::f($lng), $node === 'true' ? 'true' : 'mean']);
    }

    public function rise(float $jd, float $lat, float $lng): array
    {
        return $this->run(['rise', self::f($jd), self::f($lat), self::f($lng)]);
    }

    public function panchang(float $from, float $to): array
    {
        return $this->run(['panchang', self::f($from), self::f($to)]);
    }

    public function signIngresses(string $planet, float $from, float $to): array
    {
        if (! in_array($planet, ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'], true)) {
            throw new RuntimeException('Unknown planet');
        }

        return $this->run(['signs', $planet, self::f($from), self::f($to)])['events'];
    }

    public function available(): bool
    {
        return is_executable((string) $this->binary) && is_file($this->ephePath.'/sepl_18.se1');
    }

    private static function f(float $v): string
    {
        if (! is_finite($v)) {
            throw new RuntimeException('Invalid number');
        }

        return sprintf('%.8F', $v);
    }

    private function run(array $args): array
    {
        if (! is_executable((string) $this->binary)) {
            throw new RuntimeException('Astronomy engine is not installed (engine/build.sh).');
        }
        $p = new Process([$this->binary, ...$args], null, ['SE_EPHE_PATH' => $this->ephePath], null, 20);
        $p->run();
        $json = json_decode($p->getOutput(), true);
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            throw new RuntimeException('Astronomy calculation failed: '.($json['error'] ?? 'no output'));
        }

        return $json;
    }
}
