<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\RazorpayClient;
use App\Domain\Payments\RazorpayConfig;
use App\Domain\Payments\RazorpayException;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin → Payments setup (Razorpay). providers.manage + step-up for keys; switching to live mode
 * and changing the live limit is owner-only.
 */
class PaymentSetupController extends Controller
{
    public function __construct(private SettingsRepository $settings, private RazorpayConfig $cfg, private AuditLogger $audit) {}

    public function edit(): View
    {
        $this->authorize('providers.manage');

        return view('admin.finance.setup', [
            'keyId' => $this->cfg->keyId(),
            'masked' => ['payments.razorpay.key_secret' => $this->settings->masked('payments.razorpay.key_secret'), 'payments.razorpay.webhook_secret' => $this->settings->masked('payments.razorpay.webhook_secret')],
            'mode' => $this->cfg->mode(), 'enabled' => $this->cfg->enabled(), 'problem' => $this->cfg->problem(),
            'liveLimit' => $this->cfg->liveLimitMinor(),
            'webhookUrl' => url('/api/webhooks/razorpay'),
            'lastWebhook' => DB::table('payment_events')->orderByDesc('id')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('providers.manage');
        $data = $request->validate([
            'key_id' => ['nullable', 'regex:/^rzp_(test|live)_[A-Za-z0-9]{6,40}$/'],
            'key_secret' => ['nullable', 'string', 'max:100'],
            'webhook_secret' => ['nullable', 'string', 'min:8', 'max:100'],
            'enabled' => ['nullable', 'in:1'],
        ], ['key_id.regex' => 'The Key ID must start with rzp_test_ or rzp_live_.']);
        if (($data['key_id'] ?? null) !== null && $data['key_id'] !== $this->cfg->keyId()) {
            $this->settings->set('payments.razorpay.key_id', $data['key_id']);
        }
        foreach (['key_secret', 'webhook_secret'] as $f) {
            if (! empty($data[$f])) {
                $this->settings->set("payments.razorpay.{$f}", $data[$f], secret: true);
            }
        }
        $enabled = (bool) ($data['enabled'] ?? false);
        if ($enabled !== $this->cfg->enabled()) {
            $this->settings->set('payments.enabled', $enabled);
        }
        $problem = $this->cfg->problem();

        return back()->with('status', $problem && $enabled ? 'Saved, but payments cannot start yet: '.$problem : 'Payment settings saved. Use "Test connection" to confirm the keys work.');
    }

    public function updateMode(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('owner'), 403, 'Only the owner can change live payment settings.');
        $data = $request->validate([
            'mode' => ['required', Rule::in(['test', 'live'])],
            'live_limit' => ['required_if:mode,live', 'nullable', 'string', 'max:20'],
            'confirm' => ['required_if:mode,live', 'nullable', 'in:LIVE'],
        ], ['confirm.required_if' => 'Type LIVE to confirm.', 'confirm.in' => 'Type LIVE to confirm.']);
        $limit = null;
        if ($data['mode'] === 'live') {
            try {
                $limit = Money::parseRupees($data['live_limit']);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['live_limit' => $e->getMessage()]);
            }
            if (! $limit || $limit < 100) {
                throw ValidationException::withMessages(['live_limit' => 'Set a per-payment limit of at least ₹1.']);
            }
            $this->settings->set('payments.live_limit_minor', $limit);
        }
        $this->settings->set('payments.mode', $data['mode']);
        $this->audit->record('payments.mode_changed', null, ['mode' => $data['mode'], 'live_limit_minor' => $limit]);
        $problem = $this->cfg->problem();

        return back()->with('status', "Payments are now in {$data['mode']} mode.".($problem ? ' Note: '.$problem : ''));
    }

    public function test(RazorpayClient $rzp): RedirectResponse
    {
        $this->authorize('providers.manage');
        if (! $this->cfg->keyId() || ! $this->cfg->keySecret()) {
            return back()->withErrors(['key_id' => 'Save the Key ID and Key secret first.']);
        }
        try {
            $rzp->ping();
            $this->audit->record('payments.connection_test', null, ['result' => 'ok']);

            return back()->with('status', 'Razorpay accepted these keys ('.(str_starts_with($this->cfg->keyId(), 'rzp_live_') ? 'live' : 'test').' account).');
        } catch (RazorpayException $e) {
            $this->audit->record('payments.connection_test', null, ['result' => 'failed', 'status' => $e->status]);

            return back()->withErrors(['key_id' => $e->status === 401 ? 'Razorpay rejected the Key ID / Key secret.' : $e->getMessage()]);
        }
    }
}
