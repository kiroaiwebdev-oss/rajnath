<?php

namespace App\Domain\Identity\Otp;

use App\Domain\Messaging\DeliveryLog;
use App\Domain\Messaging\Sms\SmsManager;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues and verifies one-time codes sent by email or SMS.
 *
 * Rules: 6 digits, valid 10 minutes, 5 wrong attempts per code, resend after 30 s, at most
 * 5 sends per code, 5 new codes per destination per hour, 30 per IP per hour. Only an HMAC of
 * the code is stored. Verification is atomic (row lock), so a code works exactly once.
 */
class OtpService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 30;

    public const MAX_SENDS = 5;

    public const PER_DESTINATION_HOUR = 5;

    public const PER_IP_HOUR = 30;

    public function __construct(private SmsManager $sms, private DeliveryLog $log) {}

    public static function destinationHash(string $channel, string $destination): string
    {
        return hash_hmac('sha256', $channel.'|'.strtolower($destination), (string) config('app.key'));
    }

    private static function codeHash(string $ref, string $code): string
    {
        return hash_hmac('sha256', $ref.'|'.$code, (string) config('app.key'));
    }

    /** @throws OtpException */
    public function issue(string $channel, string $destination, string $purpose, ?string $ip, ?int $userId = null): string
    {
        $dh = self::destinationHash($channel, $destination);
        $hourAgo = now()->subHour();
        if (DB::table('otp_challenges')->where('destination_hash', $dh)->where('created_at', '>', $hourAgo)->count() >= self::PER_DESTINATION_HOUR) {
            throw new OtpException('Too many codes requested for this address. Please try again in an hour.', 'rate_limited');
        }
        if ($ip && DB::table('otp_challenges')->where('ip', $ip)->where('created_at', '>', $hourAgo)->count() >= self::PER_IP_HOUR) {
            throw new OtpException('Too many codes requested from this network. Please try again later.', 'rate_limited');
        }
        $ref = (string) Str::ulid();
        $code = $this->newCode();
        DB::table('otp_challenges')->insert([
            'ref' => $ref, 'channel' => $channel, 'purpose' => $purpose, 'destination_hash' => $dh,
            'destination' => Crypt::encryptString($destination), 'user_id' => $userId,
            'code_hash' => self::codeHash($ref, $code), 'attempts' => 0, 'sends' => 1,
            'last_sent_at' => now(), 'expires_at' => now()->addMinutes(self::TTL_MINUTES), 'ip' => $ip,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->deliver($channel, $destination, $code, $purpose);

        return $ref;
    }

    /** Re-sends a fresh code for an existing challenge. @throws OtpException */
    public function resend(string $ref): void
    {
        $code = $this->newCode();
        [$channel, $destination, $purpose] = DB::transaction(function () use ($ref, $code) {
            $c = DB::table('otp_challenges')->where('ref', $ref)->lockForUpdate()->first();
            if (! $c || $c->consumed_at || now()->greaterThan($c->expires_at)) {
                throw new OtpException('This code has expired. Please start again.', 'expired');
            }
            if ($c->sends >= self::MAX_SENDS) {
                throw new OtpException('Too many resends. Please start again later.', 'rate_limited');
            }
            if (now()->diffInSeconds($c->last_sent_at, true) < self::RESEND_SECONDS) {
                throw new OtpException('Please wait a few seconds before asking for a new code.', 'too_soon');
            }
            DB::table('otp_challenges')->where('id', $c->id)->update([
                'code_hash' => self::codeHash($ref, $code), 'attempts' => 0, 'sends' => $c->sends + 1,
                'last_sent_at' => now(), 'expires_at' => now()->addMinutes(self::TTL_MINUTES), 'updated_at' => now(),
            ]);

            return [$c->channel, Crypt::decryptString($c->destination), $c->purpose];
        });
        $this->deliver($channel, $destination, $code, $purpose);
    }

    /**
     * Verifies and consumes the code. Returns the challenge (channel, destination, purpose, user_id).
     *
     * @return array{channel: string, destination: string, purpose: string, user_id: ?int}
     *
     * @throws OtpException
     */
    public function verify(string $ref, string $code, string $expectedPurpose): array
    {
        $code = preg_replace('/\D/', '', $code);

        // The transaction returns an outcome instead of throwing inside it, so a failed-attempt
        // increment is committed (throwing inside would roll the counter back).
        $out = DB::transaction(function () use ($ref, $code, $expectedPurpose) {
            $c = DB::table('otp_challenges')->where('ref', $ref)->lockForUpdate()->first();
            if (! $c || $c->purpose !== $expectedPurpose || $c->consumed_at || now()->greaterThan($c->expires_at)) {
                return ['error' => new OtpException('This code has expired or was already used. Please request a new one.', 'expired')];
            }
            if ($c->attempts >= self::MAX_ATTEMPTS) {
                return ['error' => new OtpException('Too many wrong attempts. Please request a new code.', 'locked')];
            }
            if (strlen($code) !== 6 || ! hash_equals($c->code_hash, self::codeHash($ref, $code))) {
                DB::table('otp_challenges')->where('id', $c->id)->increment('attempts');
                $left = self::MAX_ATTEMPTS - $c->attempts - 1;

                return ['error' => new OtpException($left > 0 ? "That code is not correct. $left attempt(s) left." : 'Too many wrong attempts. Please request a new code.', 'invalid')];
            }
            DB::table('otp_challenges')->where('id', $c->id)->update(['consumed_at' => now(), 'updated_at' => now()]);

            return ['ok' => ['channel' => $c->channel, 'destination' => Crypt::decryptString($c->destination), 'purpose' => $c->purpose, 'user_id' => $c->user_id]];
        });
        if (isset($out['error'])) {
            throw $out['error'];
        }

        return $out['ok'];
    }

    /** Masked destination for display ("Code sent to 98******03"). */
    public function maskedDestination(string $ref): ?string
    {
        $c = DB::table('otp_challenges')->where('ref', $ref)->first();
        if (! $c) {
            return null;
        }
        $d = Crypt::decryptString($c->destination);
        if ($c->channel === 'email') {
            [$u, $dom] = explode('@', $d, 2) + [1 => ''];

            return mb_substr($u, 0, 2).str_repeat('•', max(2, mb_strlen($u) - 2)).'@'.$dom;
        }

        return substr($d, 0, 3).' '.str_repeat('•', max(0, strlen($d) - 5)).substr($d, -2);
    }

    public function secondsUntilResend(string $ref): int
    {
        $c = DB::table('otp_challenges')->where('ref', $ref)->first();

        return $c ? max(0, self::RESEND_SECONDS - (int) now()->diffInSeconds($c->last_sent_at, true)) : 0;
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** @throws OtpException */
    private function deliver(string $channel, string $destination, string $code, string $purpose): void
    {
        if ($channel === 'sms') {
            $r = $this->sms->sendOtp($destination, $code, self::TTL_MINUTES, $purpose);
            if (! $r->accepted) {
                report(new \RuntimeException('OTP SMS failed: '.$r->error));
                throw new OtpException('We could not send the SMS right now. Please try email instead, or try again shortly.', 'delivery_failed');
            }

            return;
        }
        $started = hrtime(true);
        try {
            Mail::to($destination)->send(new OtpMail($code, self::TTL_MINUTES));
            $this->log->record('email', (string) config('mail.default'), $purpose, $destination, true, null, null, $started);
        } catch (Throwable $e) {
            $this->log->record('email', (string) config('mail.default'), $purpose, $destination, false, null, mb_substr($e->getMessage(), 0, 200), $started);
            report($e);
            throw new OtpException('We could not send the email right now. Please try again shortly.', 'delivery_failed');
        }
    }
}
