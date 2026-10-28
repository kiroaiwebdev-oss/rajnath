<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Readiness probe: verifies dependencies. Returns no secrets or internal hostnames. */
class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $checks = [];
        $checks['database'] = $this->check(fn () => DB::select('select 1'));
        $checks['cache'] = $this->check(function () {
            Cache::put('ready.probe', 1, 10);
            if (Cache::get('ready.probe') !== 1) {
                throw new \RuntimeException('cache read-back failed');
            }
        });
        $checks['storage'] = $this->check(function () {
            Storage::disk('local')->put('.ready-probe', (string) time());
            Storage::disk('local')->delete('.ready-probe');
        });
        $checks['migrations'] = $this->check(function () {
            if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
                throw new \RuntimeException('pending');
            }
        });
        $ok = ! in_array(false, $checks, true);

        return response()->json(['status' => $ok ? 'ready' : 'not_ready', 'checks' => $checks], $ok ? 200 : 503);
    }

    private function check(callable $fn): bool
    {
        try {
            $fn();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
