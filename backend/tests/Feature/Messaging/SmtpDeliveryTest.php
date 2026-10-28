<?php

namespace Tests\Feature\Messaging;

use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/**
 * Sends a real email over SMTP (with AUTH) to a local test SMTP server process and checks what
 * the server received. Proves the admin settings drive the actual mail transport end to end.
 */
class SmtpDeliveryTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    public function test_admin_test_email_is_delivered_over_smtp_with_auth(): void
    {
        $this->syncRbac();
        $port = 2525 + random_int(1, 400);
        $out = storage_path('framework/testing/smtp-capture-'.$port.'.txt');
        @mkdir(dirname($out), 0775, true);
        @unlink($out);
        $server = new Process([PHP_BINARY, base_path('tests/Support/smtp-capture-server.php'), (string) $port, $out]);
        $server->start();
        usleep(400000);

        config(['services.providers.allow_private_hosts' => true]); // local test server on 127.0.0.1
        $s = app(SettingsRepository::class);
        foreach (['mail.host' => '127.0.0.1', 'mail.port' => $port, 'mail.encryption' => 'tls', 'mail.username' => 'otp-user',
            'mail.from_address' => 'otp@astrorajnathjha.com', 'mail.from_name' => 'Astro Rajnath Jha'] as $k => $v) {
            $s->set($k, $v);
        }
        $s->set('mail.password', 'pw-123', secret: true);

        $owner = $this->makeStaff(['owner'], ['email' => 'owner@example.com']);
        app(\App\Domain\Messaging\MailConfigurator::class)->apply();
        // The capture server has no TLS. Real use requires TLS (see next test); relax it only here.
        config(['mail.mailers.smtp.require_tls' => false, 'mail.mailers.smtp.auto_tls' => false]);
        \Illuminate\Support\Facades\Mail::purge('smtp');
        $mailer = \Illuminate\Support\Facades\Mail::mailer('smtp');
        $mailer->to('owner@example.com')->send(new \App\Mail\ProviderTestMail('Owner'));
        $mailer->getSymfonyTransport()->stop(); // sends QUIT
        $server->wait();
        $captured = (string) @file_get_contents($out);
        $this->assertStringContainsString('AUTH', $captured, 'client authenticated');
        $this->assertTrue(
            str_contains($captured, base64_encode("\0otp-user\0pw-123")) || str_contains($captured, base64_encode('otp-user')),
            'credentials from admin settings were used for SMTP AUTH'
        );
        $this->assertStringContainsString('RCPT TO:<owner@example.com>', $captured);
        $this->assertStringContainsString('MAIL FROM:<otp@astrorajnathjha.com>', $captured);
        $this->assertStringContainsString('Subject: Test email from Astro Rajnath Jha', $captured);
        $this->assertStringContainsString('Email is working', quoted_printable_decode($captured));
    }

    public function test_tls_mode_refuses_plain_server(): void
    {
        $port = 2525 + random_int(401, 800);
        $out = storage_path('framework/testing/smtp-capture-'.$port.'.txt');
        @unlink($out);
        $server = new Process([PHP_BINARY, base_path('tests/Support/smtp-capture-server.php'), (string) $port, $out]);
        $server->start();
        usleep(400000);
        config(['services.providers.allow_private_hosts' => true]);
        $s = app(SettingsRepository::class);
        foreach (['mail.host' => '127.0.0.1', 'mail.port' => $port, 'mail.encryption' => 'tls', 'mail.username' => 'u', 'mail.from_address' => 'otp@astrorajnathjha.com'] as $k => $v) {
            $s->set($k, $v);
        }
        $s->set('mail.password', 'secret-pw', secret: true);
        app(\App\Domain\Messaging\MailConfigurator::class)->apply();

        $threw = false;
        try {
            \Illuminate\Support\Facades\Mail::to('x@example.com')->send(new \App\Mail\ProviderTestMail('Owner'));
        } catch (\Throwable $e) {
            $threw = true;
        }
        $server->stop(1);
        $this->assertTrue($threw, 'must not send over a connection without TLS');
        $this->assertStringNotContainsString('secret-pw', (string) @file_get_contents($out));
        $this->assertStringNotContainsString(base64_encode("\0u\0secret-pw"), (string) @file_get_contents($out));
    }
}
