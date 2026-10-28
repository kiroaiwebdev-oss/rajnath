<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Payment reconciliation + ledger invariant check (needs `php artisan schedule:work` or cron).
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Domain\Payments\Reconciler::class)->run())->everyTenMinutes()->name('payments-reconcile')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=720')->daily();
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Domain\Palmistry\PalmReadings::class)->purgeOldPhotos())->dailyAt('03:30')->name('palm-photo-retention');
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Domain\Booking\Slots::class)->expireHolds())->everyMinute()->name('booking-holds')->withoutOverlapping();
