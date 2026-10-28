<?php

namespace App\Console\Commands;

use App\Domain\Payments\PaymentService;
use App\Domain\Payments\RazorpayConfig;
use App\Domain\Payments\RefundService;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Console\Command;
use Throwable;

/**
 * Scheduled every 5 minutes. Confirms unfinished payments and refunds directly with Razorpay so
 * that a lost browser callback or webhook never leaves money unaccounted for.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'arj:payments-reconcile {--limit=100}';

    protected $description = 'Re-check unfinished Razorpay payments and refunds';

    public function handle(PaymentService $payments, RefundService $refunds, RazorpayConfig $cfg): int
    {
        if (! $cfg->keyId() || ! $cfg->keySecret()) {
            $this->info('Razorpay keys not configured; nothing to do.');

            return self::SUCCESS;
        }
        $limit = (int) $this->option('limit');
        $checked = $failed = 0;
        $due = Payment::whereIn('status', ['created', 'pending', 'ambiguous', 'failed'])
            ->whereNotNull('provider_order_id')
            ->where('created_at', '<', now()->subMinutes(2))
            ->where('created_at', '>', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(5)))
            ->orderBy('last_checked_at')->limit($limit)->get();
        foreach ($due as $p) {
            try {
                $payments->reconcile($p);
                $checked++;
            } catch (Throwable $e) {
                $failed++;
                report($e);
            }
        }
        foreach (Refund::where('status', 'processing')->limit($limit)->get() as $r) {
            try {
                $refunds->poll($r);
                $checked++;
            } catch (Throwable $e) {
                $failed++;
                report($e);
            }
        }
        $this->info("Checked {$checked}, errors {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
