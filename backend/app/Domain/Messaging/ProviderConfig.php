<?php

namespace App\Domain\Messaging;

use App\Support\Settings\SettingsRepository;

/**
 * Field definitions for Email (SMTP) and SMS providers. Values live in the encrypted settings
 * table and are edited in Admin → Email & SMS. If a mail value is not set in admin, the
 * corresponding .env value (MAIL_*) is used.
 */
class ProviderConfig
{
    public const SMS_DRIVERS = ['none' => 'Not configured', 'twilio' => 'Twilio', 'fast2sms' => 'Fast2SMS'];

    public const MAIL_FIELDS = [
        'mail.host' => ['label' => 'SMTP host', 'hint' => 'e.g. smtp.zoho.in, smtp.gmail.com, smtp-relay.brevo.com', 'secret' => false],
        'mail.port' => ['label' => 'Port', 'hint' => '587 (TLS) or 465 (SSL)', 'secret' => false],
        'mail.encryption' => ['label' => 'Encryption', 'hint' => null, 'secret' => false, 'options' => ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)']],
        'mail.username' => ['label' => 'Username', 'hint' => 'Usually your full email address', 'secret' => false],
        'mail.password' => ['label' => 'Password / app password', 'hint' => 'Leave blank to keep the saved password', 'secret' => true],
        'mail.from_address' => ['label' => 'From email', 'hint' => 'Must be allowed by your mail provider', 'secret' => false],
        'mail.from_name' => ['label' => 'From name', 'hint' => null, 'secret' => false],
    ];

    public const SMS_FIELDS = [
        'twilio' => [
            'sms.twilio.account_sid' => ['label' => 'Account SID', 'hint' => 'Starts with AC…', 'secret' => false],
            'sms.twilio.auth_token' => ['label' => 'Auth token', 'hint' => 'Leave blank to keep the saved token', 'secret' => true],
            'sms.twilio.from' => ['label' => 'From number (E.164)', 'hint' => 'e.g. +15005550006. Leave empty if you use a Messaging Service', 'secret' => false],
            'sms.twilio.messaging_service_sid' => ['label' => 'Messaging Service SID (optional)', 'hint' => 'Starts with MG…', 'secret' => false],
        ],
        'fast2sms' => [
            'sms.fast2sms.api_key' => ['label' => 'API key', 'hint' => 'Fast2SMS › Dev API. Leave blank to keep the saved key', 'secret' => true],
            'sms.fast2sms.route' => ['label' => 'Route', 'hint' => 'DLT uses your own approved header and template. OTP route uses Fast2SMS’s standard OTP message.', 'secret' => false, 'options' => ['dlt' => 'DLT route (recommended)', 'otp' => 'OTP route']],
            'sms.fast2sms.sender_id' => ['label' => 'DLT sender ID / header', 'hint' => '6 letters, approved on DLT (DLT route only)', 'secret' => false],
            'sms.fast2sms.template_id' => ['label' => 'DLT message ID', 'hint' => 'From Fast2SMS DLT Manager (DLT route only)', 'secret' => false],
            'sms.fast2sms.variables' => ['label' => 'Template variables', 'hint' => 'Values for {#var#} in order, separated by |. Use {otp} and {minutes}. Example: {otp}|{minutes}', 'secret' => false],
        ],
    ];

    public const COMMON_SMS_FIELDS = [
        'sms.otp_text' => ['label' => 'OTP message text (Twilio)', 'hint' => 'Placeholders: {otp}, {minutes}, {brand}. For Indian numbers the text must match your DLT template.', 'secret' => false],
        'sms.international_driver' => ['label' => 'Non-Indian numbers', 'hint' => 'Fast2SMS can only send to Indian numbers', 'secret' => false, 'options' => ['none' => 'Do not send', 'twilio' => 'Send via Twilio']],
    ];

    public const DEFAULTS = [
        'mail.port' => 587,
        'mail.encryption' => 'tls',
        'mail.from_name' => 'Astro Rajnath Jha',
        'sms.driver' => 'none',
        'sms.fast2sms.route' => 'dlt',
        'sms.fast2sms.variables' => '{otp}',
        'sms.otp_text' => '{otp} is your {brand} verification code. It is valid for {minutes} minutes. Do not share it with anyone.',
        'sms.international_driver' => 'none',
    ];

    public function __construct(private SettingsRepository $settings) {}

    public function get(string $key): mixed
    {
        $v = $this->settings->get($key);

        return ($v === null || $v === '') ? (self::DEFAULTS[$key] ?? null) : $v;
    }

    public function isSecret(string $key): bool
    {
        foreach ([self::MAIL_FIELDS, ...array_values(self::SMS_FIELDS), self::COMMON_SMS_FIELDS] as $group) {
            if (isset($group[$key])) {
                return $group[$key]['secret'];
            }
        }

        return false;
    }

    public function mailConfigured(): bool
    {
        return (bool) ($this->get('mail.host') && $this->get('mail.from_address'));
    }

    public function smsDriver(): string
    {
        $d = (string) $this->get('sms.driver');

        return array_key_exists($d, self::SMS_DRIVERS) ? $d : 'none';
    }
}
