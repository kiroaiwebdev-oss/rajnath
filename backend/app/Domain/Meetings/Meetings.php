<?php

namespace App\Domain\Meetings;

use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Meeting links for audio/video consultations. Providers:
 *  - livekit      in-app call (website + Android app) on your own LiveKit server
 *  - zoom         auto-created Zoom meeting (Server-to-Server OAuth app)
 *  - google_meet  auto-created Google Calendar event with a Meet link (OAuth, owner's Google account)
 *  - manual       staff paste any https link
 * The default comes from Admin › Calling; staff can switch any booking. Meetings are created when
 * a booking is confirmed, moved when it is rescheduled and deleted when it is cancelled.
 */
class Meetings
{
    public const PROVIDERS = ['livekit' => 'In-app call (LiveKit)', 'zoom' => 'Zoom', 'google_meet' => 'Google Meet', 'manual' => 'Manual link'];
    public const ONLINE_MODES = ['audio', 'video'];

    public function __construct(private SettingsRepository $settings) {}

    public function defaultProvider(): string
    {
        $p = (string) $this->settings->get('calling.default', 'livekit');

        return isset(self::PROVIDERS[$p]) ? $p : 'livekit';
    }

    public function configured(string $p): bool
    {
        return match ($p) {
            'livekit' => (bool) ($this->settings->get('calling.livekit.url') && $this->settings->get('calling.livekit.key') && $this->settings->get('calling.livekit.secret')),
            'zoom' => (bool) ($this->settings->get('calling.zoom.account_id') && $this->settings->get('calling.zoom.client_id') && $this->settings->get('calling.zoom.client_secret')),
            'google_meet' => (bool) ($this->settings->get('calling.google.client_id') && $this->settings->get('calling.google.client_secret') && $this->settings->get('calling.google.refresh_token')),
            default => true,
        };
    }

    /** On confirmation: create a meeting with the default provider (falls back to in-app, then manual). */
    public function onConfirmed(object $b): void
    {
        if (! in_array($b->mode, self::ONLINE_MODES, true) || $b->meeting_link) {
            return;
        }
        foreach (array_unique([$this->defaultProvider(), 'livekit']) as $p) {
            if ($p !== 'manual' && $this->configured($p) && $this->create($b, $p)) {
                return;
            }
        }
    }

    /** Creates (or replaces) the meeting for a booking. Returns false and records the error on failure. */
    public function create(object $b, string $provider): bool
    {
        $b = DB::table('bookings')->find($b->id);
        $this->delete($b);
        try {
            [$link, $ext] = match ($provider) {
                'livekit' => [route('call.join', $b->ref), 'booking-'.$b->ref],
                'zoom' => $this->zoomCreate($b),
                'google_meet' => $this->meetCreate($b),
            };
        } catch (Throwable $e) {
            report($e);
            DB::table('bookings')->where('id', $b->id)->update(['meeting_error' => mb_substr(self::PROVIDERS[$provider].': '.$e->getMessage(), 0, 255), 'updated_at' => now()]);

            return false;
        }
        DB::table('bookings')->where('id', $b->id)->update(['meeting_provider' => $provider, 'meeting_link' => $link, 'meeting_external_id' => $ext, 'meeting_error' => null, 'updated_at' => now()]);

        return true;
    }

    public function setManual(object $b, string $link): void
    {
        $this->delete(DB::table('bookings')->find($b->id));
        DB::table('bookings')->where('id', $b->id)->update(['meeting_provider' => 'manual', 'meeting_link' => $link, 'meeting_external_id' => null, 'meeting_error' => null, 'updated_at' => now()]);
    }

    /** After a reschedule: move the external meeting to the new time. */
    public function onRescheduled(object $b): void
    {
        $b = DB::table('bookings')->find($b->id);
        try {
            match ($b->meeting_provider) {
                'zoom' => $this->zoom('patch', '/meetings/'.$b->meeting_external_id, $this->zoomBody($b)),
                'google_meet' => $this->google('patch', 'events/'.$b->meeting_external_id, $this->meetBody($b)),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
            $this->create($b, $b->meeting_provider); // fall back to a fresh meeting
        }
    }

    /** Best effort: remove the external meeting (cancel, or switching provider). */
    public function delete(object $b): void
    {
        if (! $b->meeting_external_id) {
            return;
        }
        try {
            match ($b->meeting_provider) {
                'zoom' => $this->zoom('delete', '/meetings/'.$b->meeting_external_id),
                'google_meet' => $this->google('delete', 'events/'.$b->meeting_external_id),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
        }
        DB::table('bookings')->where('id', $b->id)->update(['meeting_external_id' => null]);
    }

    private static function topic(object $b): string
    {
        return config('brand')['brand.name'].' consultation '.substr($b->ref, -6);
    }

    // ---------------- Zoom (Server-to-Server OAuth) ----------------
    public function zoomToken(): string
    {
        return Cache::remember('zoom:token:'.md5((string) $this->settings->get('calling.zoom.client_id')), now()->addMinutes(50), function () {
            $res = Http::asForm()->withBasicAuth((string) $this->settings->get('calling.zoom.client_id'), (string) $this->settings->get('calling.zoom.client_secret'))->timeout(10)
                ->post('https://zoom.us/oauth/token', ['grant_type' => 'account_credentials', 'account_id' => (string) $this->settings->get('calling.zoom.account_id')]);
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new RuntimeException('Zoom rejected the app credentials ('.($res->json('reason') ?? $res->json('error') ?? $res->status()).').');
            }

            return $res->json('access_token');
        });
    }

    private function zoom(string $method, string $path, array $body = []): array
    {
        $res = Http::withToken($this->zoomToken())->acceptJson()->timeout(15)->$method('https://api.zoom.us/v2'.$path, $body);
        if (! $res->successful()) {
            throw new RuntimeException('Zoom API: '.mb_substr((string) ($res->json('message') ?? $res->status()), 0, 160));
        }

        return $res->json() ?? [];
    }

    private function zoomBody(object $b): array
    {
        $start = CarbonImmutable::parse($b->start_utc, 'UTC');

        return ['topic' => self::topic($b), 'type' => 2, 'start_time' => $start->format('Y-m-d\TH:i:s\Z'), 'timezone' => 'Asia/Kolkata',
            'duration' => (int) $start->diffInMinutes(CarbonImmutable::parse($b->end_utc, 'UTC')),
            'settings' => ['waiting_room' => true, 'join_before_host' => false, 'mute_upon_entry' => false, 'auto_recording' => 'none']];
    }

    private function zoomCreate(object $b): array
    {
        $m = $this->zoom('post', '/users/me/meetings', $this->zoomBody($b));
        if (empty($m['join_url']) || ! str_starts_with($m['join_url'], 'https://')) {
            throw new RuntimeException('Zoom did not return a join link.');
        }

        return [$m['join_url'], (string) $m['id']];
    }

    // ---------------- Google Meet (Calendar API) ----------------
    public static function googleAuthUrl(string $clientId, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id' => $clientId, 'redirect_uri' => route('admin.calling.google.callback'), 'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events', 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state]);
    }

    public function googleExchange(string $code): void
    {
        $res = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', ['code' => $code, 'client_id' => $this->settings->get('calling.google.client_id'),
            'client_secret' => $this->settings->get('calling.google.client_secret'), 'redirect_uri' => route('admin.calling.google.callback'), 'grant_type' => 'authorization_code']);
        if (! $res->successful() || ! $res->json('refresh_token')) {
            throw new RuntimeException('Google did not give access: '.($res->json('error_description') ?? $res->json('error') ?? $res->status()));
        }
        $this->settings->set('calling.google.refresh_token', $res->json('refresh_token'), secret: true, group: 'calling');
        Cache::forget('google:token');
    }

    public function googleToken(): string
    {
        return Cache::remember('google:token', now()->addMinutes(50), function () {
            $res = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', ['client_id' => $this->settings->get('calling.google.client_id'),
                'client_secret' => $this->settings->get('calling.google.client_secret'), 'refresh_token' => $this->settings->get('calling.google.refresh_token'), 'grant_type' => 'refresh_token']);
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new RuntimeException('Google access expired. Connect Google again in Admin › Calling.');
            }

            return $res->json('access_token');
        });
    }

    private function google(string $method, string $path, array $body = []): array
    {
        $cal = rawurlencode((string) ($this->settings->get('calling.google.calendar') ?: 'primary'));
        $url = "https://www.googleapis.com/calendar/v3/calendars/$cal/$path".($method === 'delete' ? '' : (str_contains($path, '?') ? '&' : '?').'conferenceDataVersion=1&sendUpdates=none');
        $res = Http::withToken($this->googleToken())->acceptJson()->timeout(15)->$method($url, $body);
        if (! $res->successful() && ! ($method === 'delete' && $res->status() === 410)) {
            throw new RuntimeException('Google Calendar: '.mb_substr((string) ($res->json('error.message') ?? $res->status()), 0, 160));
        }

        return $res->json() ?? [];
    }

    private function meetBody(object $b): array
    {
        return ['summary' => self::topic($b), 'start' => ['dateTime' => CarbonImmutable::parse($b->start_utc, 'UTC')->toIso8601ZuluString(), 'timeZone' => 'Asia/Kolkata'],
            'end' => ['dateTime' => CarbonImmutable::parse($b->end_utc, 'UTC')->toIso8601ZuluString(), 'timeZone' => 'Asia/Kolkata'], 'visibility' => 'private'];
    }

    private function meetCreate(object $b): array
    {
        $e = $this->google('post', 'events', $this->meetBody($b) + ['conferenceData' => ['createRequest' => ['requestId' => (string) Str::uuid(), 'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]]]);
        $link = $e['hangoutLink'] ?? collect($e['conferenceData']['entryPoints'] ?? [])->firstWhere('entryPointType', 'video')['uri'] ?? null;
        if (! $link || ! str_starts_with($link, 'https://meet.google.com/')) {
            throw new RuntimeException('Google did not return a Meet link (the account may not allow Meet).');
        }

        return [$link, (string) $e['id']];
    }
}
