<?php

namespace Tests\Feature\Foundation;

use App\Support\Audit\AuditLogger;
use App\Support\Logging\Redactor;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_chain_verifies_and_detects_tampering(): void
    {
        $audit = app(AuditLogger::class);
        for ($i = 0; $i < 5; $i++) {
            $audit->record('test.event', null, ['n' => $i], actorLabel: 'test');
        }
        $this->assertSame(['ok' => true, 'checked' => 5, 'broken_at' => null], $audit->verify());

        $id = DB::table('audit_logs')->orderBy('id')->skip(2)->value('id');
        DB::table('audit_logs')->where('id', $id)->update(['context' => '{"n":99}']);
        $r = $audit->verify();
        $this->assertFalse($r['ok']);
        $this->assertSame($id, $r['broken_at']);
    }

    public function test_audit_detects_deleted_row(): void
    {
        $audit = app(AuditLogger::class);
        foreach (range(1, 4) as $i) {
            $audit->record('x', null, [], actorLabel: 'test');
        }
        DB::table('audit_logs')->where('id', DB::table('audit_logs')->orderBy('id')->skip(1)->value('id'))->delete();
        $this->assertFalse($audit->verify()['ok']);
    }

    public function test_audit_context_is_redacted(): void
    {
        app(AuditLogger::class)->record('x', null, ['password' => 'hunter2', 'email' => 'person@example.com', 'note' => 'call +91 98352 58203'], actorLabel: 'test');
        $ctx = DB::table('audit_logs')->value('context');
        $this->assertStringNotContainsString('hunter2', $ctx);
        $this->assertStringNotContainsString('person@example.com', $ctx);
        $this->assertStringNotContainsString('98352 58203', $ctx);
    }

    public function test_secret_settings_are_encrypted_and_masked(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('providers.razorpay.key_secret', 'rzp_secret_ABCDEF1234', secret: true);
        $raw = DB::table('settings')->where('key', 'providers.razorpay.key_secret')->value('value');
        $this->assertStringNotContainsString('ABCDEF1234', $raw);
        $this->assertSame('rzp_secret_ABCDEF1234', $s->get('providers.razorpay.key_secret'));
        $this->assertSame('••••••1234', $s->masked('providers.razorpay.key_secret'));
        $this->assertStringNotContainsString('ABCDEF', DB::table('audit_logs')->value('context'));
    }

    public function test_redactor_masks_nested_values(): void
    {
        $out = Redactor::redact(['user' => ['phone' => '+919835258203', 'token' => 'abc'], 'list' => ['x@y.com']]);
        $this->assertSame('[REDACTED]', $out['user']['token']);
        $this->assertStringNotContainsString('9835258', $out['user']['phone']);
        $this->assertStringNotContainsString('x@y.com', $out['list'][0]);
    }
}
