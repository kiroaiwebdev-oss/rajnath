<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const KIND_CUSTOMER = 'customer';

    public const KIND_STAFF = 'staff';

    public const KIND_PRACTITIONER = 'practitioner';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $guard_name = 'web';

    protected $attributes = [
        'kind' => self::KIND_CUSTOMER,
        'status' => self::STATUS_ACTIVE,
        'locale' => 'en',
        'display_timezone' => 'Asia/Kolkata',
    ];

    protected $fillable = ['name', 'email', 'phone_e164', 'locale', 'display_timezone'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->ref ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ref';
    }

    public function isStaff(): bool
    {
        return $this->kind === self::KIND_STAFF;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasMfa(): bool
    {
        return $this->mfa_confirmed_at !== null && $this->mfa_secret !== null;
    }
}
