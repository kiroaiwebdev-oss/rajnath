<?php

namespace App\Domain\Identity;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffInvitations
{
    public function __construct(private AuditLogger $audit) {}

    /** @return array{0: object, 1: string} invitation row and the plain token (shown once) */
    public function create(string $email, string $name, array $roles, ?User $by, int $minutes = 2880): array
    {
        $allowed = array_keys(config('rbac.roles'));
        $roles = array_values(array_intersect($roles, $allowed));
        if ($roles === []) {
            throw ValidationException::withMessages(['roles' => 'Select at least one valid role.']);
        }
        if (in_array('owner', $roles, true) && $by && ! $by->hasRole('owner')) {
            throw ValidationException::withMessages(['roles' => 'Only an owner can invite an owner.']);
        }
        $token = Str::random(48);
        $ref = (string) Str::ulid();
        DB::table('staff_invitations')->insert([
            'ref' => $ref, 'email' => strtolower($email), 'name' => $name, 'roles' => json_encode($roles),
            'token_hash' => hash('sha256', $token), 'invited_by' => $by?->id,
            'expires_at' => now()->addMinutes($minutes), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('staff_invitations')->where('ref', $ref)->first();
        $this->audit->record('staff.invited', null, ['invitation' => $ref, 'email' => $email, 'roles' => $roles], $by, $by ? null : 'cli');

        return [$row, $token];
    }

    public function findValid(string $token): ?object
    {
        return DB::table('staff_invitations')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /** Accepts the invitation atomically; the token cannot be reused. */
    public function accept(string $token, string $password, string $mfaSecret): User
    {
        return DB::transaction(function () use ($token, $password, $mfaSecret) {
            $inv = DB::table('staff_invitations')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $inv || $inv->accepted_at || $inv->revoked_at || now()->greaterThan($inv->expires_at)) {
                throw ValidationException::withMessages(['token' => 'This invitation is no longer valid.']);
            }
            $user = User::where('email', $inv->email)->lockForUpdate()->first() ?? new User(['email' => $inv->email]);
            $user->name = $user->exists ? $user->name : $inv->name;
            $user->kind = $user->kind === User::KIND_PRACTITIONER ? User::KIND_PRACTITIONER : User::KIND_STAFF;
            $user->status = User::STATUS_ACTIVE;
            $user->password = $password;
            $user->password_changed_at = now();
            $user->email_verified_at ??= now();
            $user->mfa_secret = $mfaSecret;
            $user->mfa_confirmed_at = now();
            $user->save();
            $user->syncRoles(array_unique(array_merge($user->getRoleNames()->all(), json_decode($inv->roles, true))));
            DB::table('staff_invitations')->where('id', $inv->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
            $this->audit->record('staff.invitation_accepted', $user, ['invitation' => $inv->ref, 'roles' => json_decode($inv->roles, true)], $user);

            return $user;
        });
    }

    public function revoke(string $ref, User $by): void
    {
        DB::table('staff_invitations')->where('ref', $ref)->whereNull('accepted_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        $this->audit->record('staff.invitation_revoked', null, ['invitation' => $ref], $by);
    }
}
