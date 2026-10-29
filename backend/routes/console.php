<?php

use App\Domain\Assistant\Assistant;
use App\Domain\Booking\Slots;
use App\Domain\Palmistry\PalmReadings;
use App\Domain\Payments\Reconciler;
use App\Domain\Practitioners\Payouts;
use App\Domain\Practitioners\RazorpayX;
use App\Domain\Store\Shiprocket;
use App\Http\Controllers\Admin\HealthController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Payment reconciliation + ledger invariant check (needs `php artisan schedule:work` or cron).
Schedule::call(fn () => app(Reconciler::class)->run())->everyTenMinutes()->name('payments-reconcile')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::call(fn () => app(PalmReadings::class)->purgeOldPhotos())->dailyAt('03:30')->name('palm-photo-retention');
Schedule::call(fn () => app(Slots::class)->expireHolds())->everyMinute()->name('booking-holds')->withoutOverlapping();
Schedule::call(fn () => app(Slots::class)->sendReminders())->everyTenMinutes()->name('booking-reminders')->withoutOverlapping();
Schedule::call(fn () => app(Payouts::class)->release())->dailyAt('02:00')->name('earnings-release');
Schedule::call(fn () => app(Assistant::class)->purge())->dailyAt('03:45')->name('assistant-retention');
// Heartbeat for Admin › Operational health, and provider status polling (webhook fallbacks).
Schedule::call(fn () => Cache::forever(HealthController::HEARTBEAT, now()->toIso8601String()))->everyMinute()->name('scheduler-heartbeat');
Schedule::call(fn () => app(Shiprocket::class)->sync())->hourly()->name('courier-tracking')->withoutOverlapping();
Schedule::call(fn () => app(RazorpayX::class)->sync())->everyFifteenMinutes()->name('razorpayx-payouts')->withoutOverlapping();
