<?php

use Illuminate\Support\Facades\Schedule;

// Run by `php artisan schedule:work` (container) or a cron entry calling `schedule:run` every minute.
Schedule::command('arj:payments-reconcile')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer();
Schedule::command('arj:ledger-verify')->dailyAt('02:30')->timezone('Asia/Kolkata')->withoutOverlapping()->onOneServer();
