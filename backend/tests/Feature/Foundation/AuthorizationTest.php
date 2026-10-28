<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncRbac();
    }

    public function test_default_deny_matrix(): void
    {
        $matrix = [
            // role => [team.index, settings.edit, audit.index]
            'administrator' => [200, 200, 200],
            'finance' => [403, 403, 403],
            'support' => [403, 403, 403],
            'content_author' => [403, 403, 403],
            'fulfilment' => [403, 403, 403],
        ];
        foreach ($matrix as $role => $expect) {
            $u = $this->makeStaff([$role]);
            $this->asStaff($u);
            $this->get('/admin/team')->assertStatus($expect[0]);
            $this->get('/admin/settings')->assertStatus($expect[1]);
            $this->get('/admin/audit')->assertStatus($expect[2]);
            $this->get('/admin')->assertOk();
        }
    }

    public function test_admin_navigation_only_shows_permitted_sections(): void
    {
        $this->asStaff($this->makeStaff(['support']))->get('/admin')
            ->assertDontSee('href="'.route('admin.team.index').'"', false)
            ->assertDontSee('href="'.route('admin.audit.index').'"', false);
    }

    public function test_sensitive_change_requires_fresh_step_up(): void
    {
        $owner = $this->makeStaff(['owner']);
        $payload = $this->settingsPayload(['brand__tagline' => 'New tagline']);

        $this->asStaff($owner, freshStepUp: false)->put('/admin/settings', $payload)->assertRedirect(route('staff.step-up'));
        $this->assertDatabaseMissing('settings', ['key' => 'brand.tagline']);

        $this->forgetTotpReplay($owner);
        $this->post('/staff/step-up', ['password' => 'Correct-Horse-9!', 'code' => $this->totp($owner->mfa_secret)])->assertRedirect();
        $this->put('/admin/settings', $payload)->assertSessionHasNoErrors();
        $this->assertSame('"New tagline"', DB::table('settings')->where('key', 'brand.tagline')->value('value'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_step_up_rejects_wrong_password(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner, false)->post('/staff/step-up', ['password' => 'nope', 'code' => $this->totp($owner->mfa_secret)])
            ->assertSessionHasErrors('code');
    }

    public function test_only_owner_can_grant_owner_and_last_owner_is_protected(): void
    {
        $owner = $this->makeStaff(['owner']);
        $admin = $this->makeStaff(['administrator']);
        $victim = $this->makeStaff(['support']);

        $this->asStaff($admin)->put("/admin/team/{$victim->ref}/roles", ['roles' => ['owner']])->assertForbidden();
        $this->assertFalse($victim->fresh()->hasRole('owner'));
        $this->asStaff($admin)->put("/admin/team/{$owner->ref}/roles", ['roles' => ['support']])->assertForbidden();
        $this->assertTrue($owner->fresh()->hasRole('owner'));

        $this->asStaff($owner)->put("/admin/team/{$owner->ref}/roles", ['roles' => ['administrator']])->assertSessionHasErrors('roles');
        $this->assertTrue($owner->fresh()->hasRole('owner'));
    }

    public function test_suspension_revokes_sessions_and_tokens(): void
    {
        $owner = $this->makeStaff(['owner']);
        $s = $this->makeStaff(['support']);
        $s->createToken('device');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $s->id, 'payload' => '', 'last_activity' => time()]);

        $this->asStaff($owner)->put("/admin/team/{$s->ref}/status", ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $s->fresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $s->id)->count());
        $this->assertSame(0, $s->tokens()->count());
    }

    public function test_invited_staff_get_exactly_the_invited_roles(): void
    {
        $owner = $this->makeStaff(['owner']);
        $res = $this->asStaff($owner)->post('/admin/team/invitations', ['name' => 'Fin', 'email' => 'fin@staff.test', 'roles' => ['finance']]);
        $link = session('invite_link');
        $this->assertNotNull($link);
        $token = basename($link);
        auth()->logout();
        session()->flush();

        $this->get("/staff/invitation/$token")->assertOk();
        $secret = session('invite_mfa_'.hash('sha256', $token));
        $this->post("/staff/invitation/$token", ['password' => 'Fin-Pass-12345!', 'password_confirmation' => 'Fin-Pass-12345!', 'code' => $this->totp($secret)]);
        $fin = User::where('email', 'fin@staff.test')->first();
        $this->assertSame(['finance'], $fin->getRoleNames()->all());
        $this->get('/admin/team')->assertForbidden();
    }

    public function test_user_route_keys_are_ulids_not_sequential_ids(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put("/admin/team/{$owner->id}/status", ['status' => 'active'])->assertNotFound();
    }

    private function settingsPayload(array $over): array
    {
        $p = [];
        foreach (\App\Http\Controllers\Admin\SettingsController::FIELDS as $k => $f) {
            $p[str_replace('.', '__', $k)] = config('brand')[$k] ?? null;
        }

        return array_merge($p, $over);
    }
}
