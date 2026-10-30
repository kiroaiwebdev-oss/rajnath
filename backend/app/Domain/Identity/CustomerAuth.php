<?php

namespace App\Domain\Identity;

use App\Domain\Identity\Otp\OtpException;
use App\Domain\Identity\Otp\OtpService;
use App\Domain\Legal\LegalDocuments;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Passwordless customer sign-in with an email or mobile OTP. The same flow registers new
 * customers (after terms acceptance) and serves as account recovery: a customer who loses one
 * channel signs in with the other verified channel.
 */
class CustomerAuth
{
    public const TERMS_VERSION = '2026-10-28';

    public function __construct(private OtpService $otp, private AuditLogger $audit) {}

    /** @return array{channel: string, destination: string} */
    public static function parseIdentifier(string $raw): array
    {
        $raw = trim($raw);
        if (str_contains($raw, '@')) {
            $email = strtolower($raw);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                throw ValidationException::withMessages(['identifier' => 'Enter a valid email address.']);
            }

            return ['channel' => 'email', 'destination' => $email];
        }
        $digits = preg_replace('/[\s\-()]/', '', $raw);
        if (preg_match('/^(?:\+?91|0)?([6-9]\d{9})$/', $digits, $m)) {
            return ['channel' => 'sms', 'destination' => '+91'.$m[1]];
        }
        if (preg_match('/^\+[1-9]\d{7,14}$/', $digits)) {
            return ['channel' => 'sms', 'destination' => $digits];
        }
        throw ValidationException::withMessages(['identifier' => 'Enter an email address or a mobile number (10 digits, or with country code like +44…).']);
    }

    /** @throws OtpException */
    public function start(string $identifier, ?string $ip): string
    {
        ['channel' => $ch, 'destination' => $dest] = self::parseIdentifier($identifier);

        // Same behaviour whether or not an account exists (no account enumeration).
        return $this->otp->issue($ch, $dest, 'login', $ip);
    }

    /**
     * @return array{user: User, created: bool}
     *
     * @throws OtpException|ValidationException
     */
    public function complete(string $ref, string $code, bool $acceptedTerms, ?string $name, ?string $ip): array
    {
        // Pre-check before consuming the code, so a new customer who forgot to tick the terms
        // box does not burn their OTP.
        $peek = DB::table('otp_challenges')->where('ref', $ref)->first();
        if ($peek) {
            $dest = Crypt::decryptString($peek->destination);
            $exists = $this->findByContact($peek->channel, $dest);
            if (! $exists && ! $acceptedTerms) {
                throw ValidationException::withMessages(['terms' => 'Please accept the Terms and Privacy Policy to create your account.']);
            }
        }

        $c = $this->otp->verify($ref, $code, 'login');

        return DB::transaction(function () use ($c, $acceptedTerms, $name, $ip) {
            $user = $this->findByContact($c['channel'], $c['destination'], lock: true);
            $created = false;
            if ($user) {
                if ($user->kind === User::KIND_STAFF) {
                    throw ValidationException::withMessages(['code' => 'This address belongs to a staff account. Please use staff sign in.']);
                }
                if (! $user->isActive()) {
                    throw ValidationException::withMessages(['code' => 'This account is not active. Please contact support.']);
                }
            } else {
                if (! $acceptedTerms) {
                    throw ValidationException::withMessages(['terms' => 'Please accept the Terms and Privacy Policy to create your account.']);
                }
                $user = new User(['name' => $name ? mb_substr(trim($name), 0, 120) : 'Customer']);
                $user->kind = User::KIND_CUSTOMER;
                $c['channel'] === 'email' ? $user->email = $c['destination'] : $user->phone_e164 = $c['destination'];
                $user->terms_accepted_at = now();
                $user->save();
                DB::table('consents')->insert(['user_id' => $user->id, 'type' => 'terms_privacy', 'version' => LegalDocuments::consentVersion(), 'granted_at' => now(), 'ip' => $ip]);
                $created = true;
            }
            if ($c['channel'] === 'email') {
                $user->email_verified_at ??= now();
            } else {
                $user->phone_verified_at ??= now();
            }
            $user->last_login_at = now();
            $user->save();
            $this->audit->record($created ? 'customer.registered' : 'customer.login', $user, ['channel' => $c['channel']], $user);

            return ['user' => $user, 'created' => $created];
        });
    }

    public function findByContact(string $channel, string $destination, bool $lock = false): ?User
    {
        $q = User::query()->where($channel === 'email' ? 'email' : 'phone_e164', $destination);

        return ($lock ? $q->lockForUpdate() : $q)->first();
    }
}
