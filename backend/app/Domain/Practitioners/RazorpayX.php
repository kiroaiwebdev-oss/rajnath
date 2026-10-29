<?php

namespace App\Domain\Practitioners;

use App\Domain\Payments\Razorpay;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sends approved practitioner payouts through RazorpayX (Composite Payout API: contact, fund
 * account and payout in one call) instead of a manual bank transfer + UTR.
 * Payout: approved ─send→ processing ─payout.processed (UTR)→ paid
 *                                   └─failed / reversed / rejected / cancelled→ approved (retry or pay by hand)
 * Uses the Razorpay API keys from Payments setup (same live/test gating) and the RazorpayX
 * account number. Idempotent: X-Payout-Idempotency = our payout ref.
 * Docs: https://razorpay.com/docs/api/x/payout-composite/
 */
class RazorpayX
{
    public const API = 'https://api.razorpay.com/v1';

    public const FAILED = ['failed', 'reversed', 'rejected', 'cancelled'];

    public function __construct(private SettingsRepository $settings, private Payouts $payouts, private AuditLogger $audit) {}

    public function configured(): bool
    {
        return app(Razorpay::class)->configured() && (bool) $this->settings->get('payouts.razorpayx.account_number');
    }

    private function http(): PendingRequest
    {
        $rzp = app(Razorpay::class);
        if (! $this->configured()) {
            throw new RuntimeException('RazorpayX is not set up (Admin › Payments setup).');
        }
        if ($rzp->mode() === 'live' && ! $rzp->liveAllowed()) {
            throw new RuntimeException('Live Razorpay keys are saved but live payments are switched off.');
        }

        return Http::asJson()->acceptJson()->timeout(20)->connectTimeout(5)
            ->withBasicAuth((string) $rzp->keyId(), (string) $this->settings->get('payments.razorpay.key_secret'));
    }

    /** Sends an approved payout. The approver and sender are recorded; the sender must not be the practitioner. */
    public function send(object $payout, User $by): object
    {
        if ($payout->status !== 'approved' || $payout->provider_payout_id) {
            throw ValidationException::withMessages(['payout' => 'Only approved payouts that were not sent yet can be sent through RazorpayX.']);
        }
        $p = DB::table('practitioners')->find($payout->practitioner_id);
        if ((int) $p->user_id === $by->id) {
            throw ValidationException::withMessages(['payout' => 'You cannot send your own payout.']);
        }
        $m = Payouts::method($payout);
        $contact = ['name' => mb_substr($p->display_name, 0, 50), 'type' => 'vendor', 'reference_id' => 'practitioner:'.$p->ref,
            'email' => DB::table('users')->where('id', $p->user_id)->value('email')];
        $fund = ($m['method'] ?? null) === 'upi'
            ? ['account_type' => 'vpa', 'vpa' => ['address' => $m['upi']], 'contact' => $contact]
            : ['account_type' => 'bank_account', 'bank_account' => ['name' => $m['account_name'], 'ifsc' => strtoupper($m['ifsc']), 'account_number' => $m['account_number']], 'contact' => $contact];
        $body = ['account_number' => $this->settings->get('payouts.razorpayx.account_number'), 'amount' => (int) $payout->amount_minor, 'currency' => 'INR',
            'mode' => ($m['method'] ?? null) === 'upi' ? 'UPI' : $this->settings->get('payouts.razorpayx.mode', 'IMPS'), 'purpose' => 'payout',
            'fund_account' => $fund, 'queue_if_low_balance' => true, 'reference_id' => $payout->ref, 'narration' => 'Astro payout '.substr($payout->ref, -8)];
        try {
            $r = $this->http()->withHeaders(['X-Payout-Idempotency' => $payout->ref])->post(self::API.'/payouts', $body);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['payout' => $e->getMessage()]);
        }
        if (! $r->successful() || ! $r->json('id')) {
            $msg = mb_substr((string) ($r->json('error.description') ?? 'RazorpayX refused the payout ('.$r->status().').'), 0, 300);
            DB::table('payouts')->where('id', $payout->id)->update(['provider_error' => $msg, 'updated_at' => now()]);
            $this->audit->record('payout.razorpayx_failed', null, ['payout' => $payout->ref, 'status' => $r->status()], $by);
            throw ValidationException::withMessages(['payout' => $msg]);
        }
        DB::table('payouts')->where('id', $payout->id)->where('status', 'approved')->update(['status' => 'processing', 'provider' => 'razorpayx', 'provider_payout_id' => $r->json('id'),
            'provider_status' => $r->json('status'), 'provider_error' => null, 'sent_at' => now(), 'sent_by' => $by->id, 'updated_at' => now()]);
        $this->audit->record('payout.sent', null, ['payout' => $payout->ref, 'provider_payout' => $r->json('id')], $by);
        $fresh = DB::table('payouts')->find($payout->id);
        $this->apply($fresh, (string) $r->json('status'), $r->json('utr'), $r->json('status_details.description'));

        return DB::table('payouts')->find($payout->id);
    }

    /** Applies a RazorpayX payout status (from the send response, the webhook or polling). */
    public function apply(object $payout, string $status, ?string $utr, ?string $reason = null): string
    {
        if ($payout->status !== 'processing') {
            return 'ignored';
        }
        DB::table('payouts')->where('id', $payout->id)->update(['provider_status' => mb_substr($status, 0, 20), 'updated_at' => now()]);
        if ($status === 'processed' && $utr) {
            $this->payouts->markPaid($payout, mb_substr($utr, 0, 40), null, 'processing');

            return 'paid';
        }
        if (in_array($status, self::FAILED, true)) {
            DB::table('payouts')->where('id', $payout->id)->where('status', 'processing')->update(['status' => 'approved', 'provider_payout_id' => null,
                'provider_error' => mb_substr('RazorpayX: '.$status.($reason ? ' — '.$reason : ''), 0, 300), 'updated_at' => now()]);
            DB::table('reconciliation_findings')->insert(['check' => 'payout_'.$status, 'severity' => 'warning', 'subject' => 'payout:'.$payout->ref,
                'detail' => 'RazorpayX payout '.$payout->provider_payout_id.' '.$status.($reason ? ': '.$reason : '').'. The payout is back to approved; send again or pay by hand.', 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record('payout.razorpayx_'.$status, null, ['payout' => $payout->ref], null, 'system');

            return 'returned';
        }

        return 'pending';
    }

    /** Every 15 minutes: poll payouts still processing (webhook fallback). */
    public function sync(): int
    {
        if (! $this->configured()) {
            return 0;
        }
        $n = 0;
        foreach (DB::table('payouts')->where('status', 'processing')->whereNotNull('provider_payout_id')->limit(50)->get() as $p) {
            try {
                $r = $this->http()->get(self::API.'/payouts/'.rawurlencode($p->provider_payout_id));
                if ($r->successful()) {
                    $this->apply($p, (string) $r->json('status'), $r->json('utr'), $r->json('status_details.description'));
                    $n++;
                }
            } catch (RuntimeException $e) {
                report($e);
            }
        }

        return $n;
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) $this->settings->get('payouts.razorpayx.webhook_secret');

        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
