<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Imports bundled GeoNames cities (pop. ≥ 15,000) into `places`. Idempotent (upsert). */
class ImportPlaces extends Command
{
    protected $signature = 'arj:import-places {--dir= : directory with the GeoNames .gz files}';

    protected $description = 'Import GeoNames cities (CC BY 4.0) for birth-place search.';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: database_path('data/geonames');
        $admin = [];
        foreach (gzfile("$dir/admin1CodesASCII.txt.gz") as $line) {
            $c = explode("\t", rtrim($line, "\n"));
            if (count($c) >= 2) {
                $admin[$c[0]] = $c[1];
            }
        }
        $countries = [];
        foreach (gzfile("$dir/countryInfo.txt.gz") as $line) {
            if ($line[0] === '#') {
                continue;
            }
            $c = explode("\t", $line);
            if (count($c) > 4) {
                $countries[$c[0]] = $c[4];
            }
        }
        $fh = gzopen("$dir/cities15000.txt.gz", 'r');
        $batch = [];
        $n = 0;
        while (($line = gzgets($fh)) !== false) {
            $c = explode("\t", rtrim($line, "\n"));
            if (count($c) < 18 || ! $c[17]) {
                continue;
            }
            $batch[] = [
                'id' => (int) $c[0], 'name' => mb_substr($c[1], 0, 200), 'ascii_name' => mb_substr($c[2] ?: $c[1], 0, 200),
                'admin1' => $admin[$c[8].'.'.$c[10]] ?? null, 'country_code' => $c[8], 'country' => $countries[$c[8]] ?? $c[8],
                'lat' => round((float) $c[4], 6), 'lng' => round((float) $c[5], 6), 'tz_name' => $c[17], 'population' => (int) $c[14],
            ];
            if (count($batch) === 1000) {
                $n += $this->flush($batch);
            }
        }
        $n += $this->flush($batch);
        gzclose($fh);
        $this->info("Imported $n places.");

        return self::SUCCESS;
    }

    private function flush(array &$batch): int
    {
        if (! $batch) {
            return 0;
        }
        DB::table('places')->upsert($batch, ['id'], ['name', 'ascii_name', 'admin1', 'country_code', 'country', 'lat', 'lng', 'tz_name', 'population']);
        $n = count($batch);
        $batch = [];

        return $n;
    }
}
