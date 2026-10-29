<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Assistant\AiProviders;
use App\Domain\Astro\Engine;
use App\Domain\Meetings\Meetings;
use App\Domain\Messaging\ProviderConfig;
use App\Domain\Notify\Fcm;
use App\Domain\Payments\Invoices;
use App\Domain\Payments\Razorpay;
use App\Domain\Practitioners\RazorpayX;
use App\Domain\Store\Shiprocket;
use App\Http\Controllers\Controller;
use App\Support\Pdf\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * Admin › Operational health (AD-18): database, cache, storage and disk space, queue backlog and
 * failed jobs, scheduler heartbeat, astronomy engine, PDF printer, provider set-up, delivery
 * failures and open reconciliation findings. Read-only; needs health.view.
 */
class HealthController extends Controller
{
    public const HEARTBEAT = 'ops:scheduler:heartbeat';

    public function __invoke(): View
    {
        $this->authorize('health.view');
        $checks = [];
        $add = function (string $name, callable $fn) use (&$checks) {
            try {
                [$state, $detail] = $fn();
            } catch (Throwable $e) {
                report($e);
                [$state, $detail] = ['fail', 'Error: '.mb_substr($e->getMessage(), 0, 160)];
            }
            $checks[] = ['name' => $name, 'state' => $state, 'detail' => $detail];
        };

        $add('Database', function () {
            DB::select('select 1');
            $files = count(glob(database_path('migrations/*.php')));
            $ran = DB::table('migrations')->count();

            return $ran >= $files ? ['ok', "Connected · $ran migrations applied"] : ['fail', ($files - $ran).' migrations not applied (run php artisan migrate)'];
        });
        $add('Cache', function () {
            Cache::put('health.probe', 1, 10);

            return Cache::get('health.probe') === 1 ? ['ok', 'Read and write work ('.config('cache.default').')'] : ['fail', 'Value not read back'];
        });
        $add('Storage and disk', function () {
            Storage::disk('local')->put('.health-probe', '1');
            Storage::disk('local')->delete('.health-probe');
            $free = @disk_free_space(storage_path());
            $total = @disk_total_space(storage_path());
            $pct = $free && $total ? (int) round($free * 100 / $total) : null;

            return [$pct !== null && $pct < 10 ? 'warn' : 'ok', 'Writable'.($free ? ' · '.round($free / 1073741824, 1).' GB free'.($pct !== null ? " ($pct%)" : '') : '')];
        });
        $add('Queue', function () {
            if (config('queue.default') === 'sync') {
                return ['ok', 'Jobs run immediately (sync driver)'];
            }
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $age = $oldest ? now()->timestamp - (int) $oldest : 0;

            return [$age > 600 ? 'fail' : 'ok', $pending.' waiting'.($oldest ? ' · oldest '.intdiv($age, 60).' min' : '').($age > 600 ? ' — is the queue worker running?' : '')];
        });
        $add('Failed jobs (24 h)', function () {
            $n = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count();

            return [$n > 0 ? 'warn' : 'ok', $n.' failed'];
        });
        $add('Scheduler', function () {
            $beat = Cache::get(self::HEARTBEAT);
            if (! $beat) {
                return ['fail', 'No heartbeat yet — cron must run php artisan schedule:run every minute'];
            }
            $age = now()->diffInSeconds(CarbonImmutable::parse($beat), true);

            return [$age > 180 ? 'fail' : 'ok', 'Last run '.CarbonImmutable::parse($beat)->tz('Asia/Kolkata')->format('d M, g:i:s A').' IST'];
        });
        $add('Astronomy engine', fn () => app(Engine::class)->available() ? ['ok', 'Swiss Ephemeris engine and data files found'] : ['fail', 'Engine or ephemeris files missing (engine/build.sh)']);
        $add('PDF printing', fn () => app(Pdf::class)->available() ? ['ok', 'Chromium found ('.config('pdf.binary').')'] : ['warn', 'Chromium not found — PDF downloads unavailable (set PDF_CHROME_BINARY)']);
        $add('Open reconciliation findings', function () {
            $crit = DB::table('reconciliation_findings')->whereNull('resolved_at')->where('severity', 'critical')->count();
            $all = DB::table('reconciliation_findings')->whereNull('resolved_at')->count();

            return [$crit ? 'fail' : ($all ? 'warn' : 'ok'), "$all open ($crit critical) — Finance"];
        });
        $add('Email and SMS deliveries (24 h)', function () {
            $failed = DB::table('provider_deliveries')->where('created_at', '>', now()->subDay())->where('status', 'failed')->count();
            $sent = DB::table('provider_deliveries')->where('created_at', '>', now()->subDay())->where('status', 'accepted')->count();

            return [$failed > 0 ? 'warn' : 'ok', "$sent accepted · $failed failed"];
        });

        $rzp = app(Razorpay::class);
        $cfg = app(ProviderConfig::class);
        $meet = app(Meetings::class);
        $providers = [
            'Email (SMTP)' => $cfg->mailConfigured(),
            'SMS' => $cfg->smsDriver() !== 'none',
            'Razorpay payments' => $rzp->configured() ? ($rzp->mode() === 'live' ? ($rzp->liveAllowed() ? 'Live' : 'Live keys, live off') : 'Test mode') : false,
            'GST invoices' => app(Invoices::class)->enabled(),
            'RazorpayX payouts' => app(RazorpayX::class)->configured(),
            'Shiprocket courier' => app(Shiprocket::class)->configured(),
            'Push (Firebase)' => app(Fcm::class)->configured(),
            'In-app calls (LiveKit)' => $meet->configured('livekit'),
            'AI assistant' => app(AiProviders::class)->available(),
        ];

        return view('admin.health', ['checks' => $checks, 'providers' => $providers,
            'failed' => DB::table('failed_jobs')->orderByDesc('id')->limit(10)->get(['uuid', 'queue', 'exception', 'failed_at']),
            'app' => ['Environment' => app()->environment(), 'Debug mode' => config('app.debug') ? 'ON — turn off in production' : 'off', 'PHP' => PHP_VERSION, 'Laravel' => app()->version(),
                'Time (IST)' => now('Asia/Kolkata')->format('d M Y, g:i A')]]);
    }
}
