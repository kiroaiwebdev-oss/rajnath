<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Scheduled every 10 minutes (routes/console.php):
 *  1. Pending payments older than 5 minutes are checked with Razorpay; captures are applied
 *     (covers missed webhooks / closed browsers). Older than 24 h with nothing captured → cancelled.
 *  2. Ledger invariants are verified; any problem becomes a critical finding.
 */
class Reconciler
{
    public function __construct(private Checkout $checkout, private Razorpay $rzp, private Ledger $ledger) {}

    public function run(): array
    {
        $summary = ['checked' => 0, 'applied' => 0, 'cancelled' => 0, 'ledger_problems' => 0];
        if ($this->rzp->configured()) {
            $pending = DB::table('payments')->whereIn('status', ['created', 'authorized', 'ambiguous'])->where('created_at', '<', now()->subMinutes(5))->limit(200)->get();
            foreach ($pending as $p) {
                $summary['checked']++;
                try {
                    $items = $this->rzp->orderPayments($p->provider_order_id);
                } catch (Throwable $e) {
                    report($e);

                    continue;
                }
                $captured = collect($items)->firstWhere('status', 'captured');
                if ($captured) {
                    if ($this->checkout->applyCapture($p->provider_order_id, $captured['id'], (int) $captured['amount'], $captured['method'] ?? null) === 'applied') {
                        $summary['applied']++;
                    }
                } elseif ($p->created_at < now()->subDay()) {
                    DB::table('payments')->where('id', $p->id)->update(['status' => 'failed', 'error' => 'Expired without capture', 'updated_at' => now()]);
                    $this->checkout->cancel(DB::table('orders')->find($p->order_id));
                    $summary['cancelled']++;
                }
                DB::table('payments')->where('id', $p->id)->update(['last_checked_at' => now()]);
            }
        }
        foreach ($this->ledger->verify() as $problem) {
            $summary['ledger_problems']++;
            DB::table('reconciliation_findings')->insert(['check' => 'ledger_invariant', 'severity' => 'critical', 'subject' => null, 'detail' => $problem, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $summary;
    }
}
