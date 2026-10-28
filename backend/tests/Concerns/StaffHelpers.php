<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

trait StaffHelpers
{
    protected function syncRbac(): void
    {
        $this->artisan('arj:rbac-sync')->assertSuccessful();
    }

    protected function makeStaff(array $roles, array $attrs = []): User
    {
        $user = new User(['name' => $attrs['name'] ?? 'Staff '.uniqid(), 'email' => $attrs['email'] ?? uniqid().'@staff.test']);
        $user->kind = User::KIND_STAFF;
        $user->status = $attrs['status'] ?? User::STATUS_ACTIVE;
        $user->password = $attrs['password'] ?? 'Correct-Horse-9!';
        $user->mfa_secret = (new Google2FA)->generateSecretKey(32);
        $user->mfa_confirmed_at = now();
        $user->save();
        $user->syncRoles($roles);

        return $user;
    }

    /** Signed in, MFA passed, step-up fresh. */
    protected function asStaff(User $user, bool $freshStepUp = true): static
    {
        return $this->actingAs($user)->withSession([
            'staff_mfa_at' => now()->timestamp,
            'step_up_at' => $freshStepUp ? now()->timestamp : 0,
        ]);
    }

    protected function totp(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    /** Lets a test reuse the current TOTP step (replay protection is per user). */
    protected function forgetTotpReplay(User $user): void
    {
        Cache::forget('mfa:last:u'.$user->id);
    }
}
