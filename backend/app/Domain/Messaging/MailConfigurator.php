<?php

namespace App\Domain\Messaging;

use Illuminate\Support\Facades\Mail;

/**
 * Applies SMTP settings saved in admin to Laravel's mailer at runtime, so the owner never edits
 * files. If nothing is saved in admin, the .env MAIL_* configuration stays in effect.
 */
class MailConfigurator
{
    public function __construct(private ProviderConfig $cfg) {}

    public function apply(): bool
    {
        if (! $this->cfg->mailConfigured()) {
            return false;
        }
        $enc = $this->cfg->get('mail.encryption');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
                'host' => $this->cfg->get('mail.host'),
                'port' => (int) $this->cfg->get('mail.port'),
                'username' => $this->cfg->get('mail.username'),
                'password' => $this->cfg->get('mail.password'),
                'timeout' => 15,
                // TLS mode refuses to send credentials over an unencrypted connection.
                'require_tls' => $enc === 'tls',
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
            'mail.from' => [
                'address' => $this->cfg->get('mail.from_address'),
                'name' => $this->cfg->get('mail.from_name'),
            ],
        ]);
        Mail::purge('smtp');

        return true;
    }
}
