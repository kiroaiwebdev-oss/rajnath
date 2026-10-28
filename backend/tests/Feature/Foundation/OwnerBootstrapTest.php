<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class OwnerBootstrapTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private function bootstrapToken(): string
    {
        Artisan::call('arj:bootstrap-owner', ['email' => 'owner@example.com', 'name' => 'Owner']);
        preg_match('#/staff/invitation/([A-Za-z0-9]+)#', Artisan::output(), $m);
        $this->assertNotEmpty($m[1] ?? null, 'bootstrap must print a one-time link');

        return $m[1];
    }

    public function test_no_users_or_credentials_exist_after_migration_and_rbac_sync(): void
    {
        $this->syncRbac();
        $this->assertSame(0, User::count());
        $this->assertSame(9, DB::table('roles')->count());
    }

    public function test_owner_sets_password_and_mfa_via_one_time_link(): void
    {
        $token = $this->bootstrapToken();
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $token]); // only the hash is stored

        $this->get("/staff/invitation/$token")->assertOk()->assertSee('Two-factor authentication');
        $secret = session()->get('invite_mfa_'.hash('sha256', $token));

        $this->post("/staff/invitation/$token", [
            'password' => 'Str0ng-Owner-Pass!', 'password_confirmation' => 'Str0ng-Owner-Pass!',
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('admin.dashboard'));

        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertTrue($owner->hasMfa());
        $this->assertNotSame($secret, DB::table('users')->where('id', $owner->id)->value('mfa_secret'), 'MFA secret encrypted at rest');
        $this->assertAuthenticatedAs($owner);
        $this->get('/admin')->assertOk();

        // Token is single-use.
        auth()->logout();
        $this->get("/staff/invitation/$token")->assertStatus(410);
    }

    public function test_invalid_totp_or_weak_password_is_rejected(): void
    {
        $token = $this->bootstrapToken();
        $this->get("/staff/invitation/$token");
        $this->post("/staff/invitation/$token", ['password' => 'Str0ng-Owner-Pass!', 'password_confirmation' => 'Str0ng-Owner-Pass!', 'code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->post("/staff/invitation/$token", ['password' => 'short', 'password_confirmation' => 'short', 'code' => '000000'])
            ->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
    }

    public function test_second_bootstrap_is_refused_once_owner_exists(): void
    {
        $this->syncRbac();
        $this->makeStaff(['owner']);
        $this->assertSame(1, Artisan::call('arj:bootstrap-owner', ['email' => 'x@example.com', 'name' => 'X']));
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $token = $this->bootstrapToken();
        $this->travel(31)->minutes();
        $this->get("/staff/invitation/$token")->assertStatus(410);
    }
}
