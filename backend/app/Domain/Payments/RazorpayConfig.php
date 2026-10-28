<?php

namespace App\Domain\Payments;

use App\Support\Settings\SettingsRepository;

/**
 * Razorpay settings edited in Admin → Payments. Keys and secrets are encrypted in the settings
 * table. Live mode is a separate, owner-only switch with a per-payment limit; test keys can
 * never be used in live mode and vice versa.
 */
class RazorpayConfig
{
    public const FIELDS = [
        'payments.razorpay.key_id' => ['label' => 'Key ID', 'hint' => 'Razorpay Dashboard → Account & Settings → API keys. Starts with rzp_test_ or rzp_live_', 'secret' => false],
        'payments.razorpay.key_secret' => ['label' => 'Key secret', 'hint' => 'Shown only once by Razorpay', 'secret' => true],
        'payments.razorpay.webhook_secret' => ['label' => 'Webhook secret', 'hint' => 'The secret you typed when adding the webhook in Razorpay', 'secret' => true],
    ];

    public function __construct(private SettingsRepository $settings) {}

    public function keyId(): ?string
    {
        return $this->settings->get('payments.razorpay.key_id') ?: null;
    }

    public function keySecret(): ?string
    {
        return $this->settings->get('payments.razorpay.key_secret') ?: null;
    }

    public function webhookSecret(): ?string
    {
        return $this->settings->get('payments.razorpay.webhook_secret') ?: null;
    }

    /** "test" unless the owner has explicitly switched to live. */
    public function mode(): string
    {
        return $this->settings->get('payments.mode') === 'live' ? 'live' : 'test';
    }

    /** Largest single payment allowed in live mode (paise). */
    public function liveLimitMinor(): int
    {
        return (int) ($this->settings->get('payments.live_limit_minor') ?? 0);
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('payments.enabled', false);
    }

    /** @return string|null why payments cannot be taken right now */
    public function problem(): ?string
    {
        $id = $this->keyId();
        if (! $id || ! $this->keySecret()) {
            return 'Razorpay keys are not saved yet.';
        }
        $prefix = $this->mode() === 'live' ? 'rzp_live_' : 'rzp_test_';
        if (! str_starts_with($id, $prefix)) {
            return "The saved Key ID does not match {$this->mode()} mode (it must start with {$prefix}).";
        }
        if (! $this->webhookSecret()) {
            return 'The webhook secret is not saved yet.';
        }
        if ($this->mode() === 'live' && $this->liveLimitMinor() <= 0) {
            return 'Live mode needs a per-payment limit.';
        }

        return null;
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->problem() === null;
    }
}
