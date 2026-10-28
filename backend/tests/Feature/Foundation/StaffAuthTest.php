<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncRbac();
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/staff/login');
    }

    public function test_password_alone_does_not_grant_access(): void
    {
        $u = $this->makeStaff(['support'], ['email' => 's@staff.test']);
        $this->post('/staff/login', ['email' => 's@staff.test', 'password' => 'Correct-Horse-9!'])->assertRedirect('/staff/mfa');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/staff/login');

        $this->post('/staff/mfa', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/staff/mfa', ['code' => $this->totp($u->mfa_secret)])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->get('/admin')->assertOk();
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $u = $this->makeStaff(['support'], ['email' => 'r@staff.test']);
        $code = $this->totp($u->mfa_secret);
        $this->post('/staff/login', ['email' => 'r@staff.test', 'password' => 'Correct-Horse-9!']);
        $this->post('/staff/mfa', ['code' => $code])->assertRedirect();
        $this->post('/staff/logout');

        $this->post('/staff/login', ['email' => 'r@staff.test', 'password' => 'Correct-Horse-9!']);
        $this->post('/staff/mfa', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_wrong_password_suspended_and_roleless_users_are_rejected_identically(): void
    {
        $this->makeStaff(['support'], ['email' => 'a@staff.test']);
        $this->makeStaff(['support'], ['email' => 'b@staff.test', 'status' => User::STATUS_SUSPENDED]);
        $this->makeStaff([], ['email' => 'c@staff.test']);

        foreach ([['a@staff.test', 'wrong-Password-1!'], ['b@staff.test', 'Correct-Horse-9!'], ['c@staff.test', 'Correct-Horse-9!']] as [$e, $p]) {
            $this->post('/staff/login', ['email' => $e, 'password' => $p])
                ->assertSessionHasErrors(['email' => 'These credentials do not match an active staff account.']);
        }
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/staff/login', ['email' => 'nobody@staff.test', 'password' => 'x']);
        }
        $this->post('/staff/login', ['email' => 'nobody@staff.test', 'password' => 'x'])->assertStatus(429);
    }

    public function test_suspended_user_with_live_session_is_logged_out(): void
    {
        $u = $this->makeStaff(['support']);
        $this->asStaff($u)->get('/admin')->assertOk();
        $u->forceFill(['status' => User::STATUS_SUSPENDED])->save();
        $this->get('/admin')->assertRedirect('/staff/login');
        $this->assertGuest();
    }

    public function test_customer_cannot_enter_admin_even_when_signed_in(): void
    {
        $c = new User(['name' => 'C', 'email' => 'c@cust.test']);
        $c->save();
        $this->actingAs($c)->withSession(['staff_mfa_at' => now()->timestamp])->get('/admin')->assertRedirect('/staff/login');
    }
}
