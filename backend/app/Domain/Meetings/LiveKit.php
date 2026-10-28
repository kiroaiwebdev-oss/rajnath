<?php

namespace App\Domain\Meetings;

use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * In-app calls on the owner's LiveKit server. A room per booking ("booking-<ref>"); only the
 * customer, the assigned astrologer (or office staff for Acharya Ji's bookings) get a token, and
 * only from 15 minutes before the start until 30 minutes after the end. Tokens last 2 hours.
 */
class LiveKit
{
    public const EARLY_MINUTES = 15;
    public const LATE_MINUTES = 30;

    public function __construct(private SettingsRepository $settings) {}

    public function url(): ?string
    {
        return $this->settings->get('calling.livekit.url') ?: null;
    }

    /** Who may join, as [identity, display name, role] — or null. */
    public static function participant(object $b, User $u): ?array
    {
        if ($b->user_id === $u->id) {
            return ['c-'.$u->ref, $u->name ?: 'Customer', 'customer'];
        }
        if ($u->isStaff()) {
            $p = $b->practitioner_id ? DB::table('practitioners')->find($b->practitioner_id) : null;
            if ($p && $p->user_id === $u->id) {
                return ['p-'.$p->ref, $p->display_name, 'astrologer'];
            }
            if (! $p && $u->can('bookings.manage')) {
                return ['s-'.$u->ref, config('brand')['brand.practitioner_name'], 'astrologer'];
            }
        }

        return null;
    }

    /** @return array{url: string, token: string, room: string, audio_only: bool, identity: string} */
    public function tokenFor(object $b, User $u): array
    {
        $who = self::participant($b, $u);
        abort_unless($who, 404);
        if ($b->status !== 'confirmed' || $b->meeting_provider !== 'livekit') {
            throw ValidationException::withMessages(['call' => 'This consultation does not use the in-app call.']);
        }
        $start = CarbonImmutable::parse($b->start_utc, 'UTC');
        $end = CarbonImmutable::parse($b->end_utc, 'UTC');
        if (now()->lt($start->subMinutes(self::EARLY_MINUTES))) {
            throw ValidationException::withMessages(['call' => 'You can join from '.self::EARLY_MINUTES.' minutes before the start time.']);
        }
        if (now()->gt($end->addMinutes(self::LATE_MINUTES))) {
            throw ValidationException::withMessages(['call' => 'This consultation time is over.']);
        }
        if (! $this->url() || ! $this->settings->get('calling.livekit.key')) {
            throw ValidationException::withMessages(['call' => 'In-app calling is not set up yet. Please use the phone number in your booking.']);
        }
        [$identity, $name, $role] = $who;
        $room = 'booking-'.$b->ref;

        return ['url' => $this->url(), 'room' => $room, 'identity' => $identity, 'audio_only' => $b->mode === 'audio',
            'token' => $this->jwt($identity, $name, $room, ['role' => $role])];
    }

    public function jwt(string $identity, string $name, string $room, array $metadata = [], int $ttl = 7200): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $claims = ['iss' => (string) $this->settings->get('calling.livekit.key'), 'sub' => $identity, 'name' => $name, 'nbf' => $now - 10, 'exp' => $now + $ttl,
            'metadata' => json_encode($metadata), 'video' => ['room' => $room, 'roomJoin' => true, 'canPublish' => true, 'canSubscribe' => true, 'canPublishData' => true]];
        $unsigned = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])).'.'.$b64(json_encode($claims));

        return $unsigned.'.'.$b64(hash_hmac('sha256', $unsigned, (string) $this->settings->get('calling.livekit.secret'), true));
    }
}
