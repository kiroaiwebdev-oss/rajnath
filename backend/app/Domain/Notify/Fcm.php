<?php

namespace App\Domain\Notify;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 API. Server credentials = a Firebase service-account JSON
 * (Project settings › Service accounts › Generate new private key), stored encrypted in settings.
 * An OAuth access token is minted with a self-signed RS256 JWT and cached for 50 minutes.
 * The Android app config (api key, app id, sender id) is public and is handed to the app via /meta.
 */
class Fcm
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    public const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public function __construct(private SettingsRepository $settings) {}

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    public function account(): ?array
    {
        $raw = (string) $this->settings->get('push.fcm.service_account', '');
        $j = $raw !== '' ? json_decode($raw, true) : null;

        return is_array($j) && isset($j['project_id'], $j['client_email'], $j['private_key']) ? $j : null;
    }

    public function configured(): bool
    {
        return $this->account() !== null;
    }

    /** Public Android client config for the app, or null until fully configured. */
    public function clientConfig(): ?array
    {
        $acct = $this->account();
        $c = ['api_key' => $this->settings->get('push.android.api_key'), 'app_id' => $this->settings->get('push.android.app_id'),
            'messaging_sender_id' => $this->settings->get('push.android.sender_id'), 'project_id' => $acct['project_id'] ?? null];

        return $acct && ! in_array(null, $c, true) && ! in_array('', $c, true) ? $c : null;
    }

    /** Returns an error message, or null when the JSON is a usable service account. */
    public static function validateServiceAccount(string $json): ?string
    {
        $j = json_decode($json, true);
        if (! is_array($j) || ($j['type'] ?? null) !== 'service_account') {
            return 'Paste the full service-account JSON file (it contains "type": "service_account").';
        }
        foreach (['project_id', 'client_email', 'private_key'] as $k) {
            if (empty($j[$k]) || ! is_string($j[$k])) {
                return "The JSON is missing \"$k\".";
            }
        }
        if (! preg_match('/^[a-z0-9-]{4,40}$/', $j['project_id'])) {
            return 'The project_id in the JSON looks wrong.';
        }
        if (! openssl_pkey_get_private($j['private_key'])) {
            return 'The private key in the JSON could not be read.';
        }

        return null;
    }

    public function accessToken(bool $fresh = false): string
    {
        $acct = $this->account() ?? throw new RuntimeException('Firebase is not configured.');
        $cacheKey = 'fcm:token:'.hash('sha256', $acct['client_email'].$acct['private_key']);
        if (! $fresh && ($t = Cache::get($cacheKey))) {
            return $t;
        }
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'iss' => $acct['client_email'], 'scope' => self::SCOPE, 'aud' => self::TOKEN_URI, 'iat' => $now, 'exp' => $now + 3600,
        ]));
        if (! openssl_sign($unsigned, $sig, $acct['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Firebase token request.');
        }
        $res = Http::asForm()->timeout(10)->post(self::TOKEN_URI, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$b64($sig)]);
        if (! $res->successful() || ! $res->json('access_token')) {
            throw new RuntimeException('Google rejected the service account: '.mb_substr((string) ($res->json('error_description') ?? $res->json('error') ?? $res->status()), 0, 160));
        }
        Cache::put($cacheKey, $res->json('access_token'), now()->addMinutes(50));

        return $res->json('access_token');
    }

    /**
     * Sends one message. Returns 'sent', 'invalid_token' (token must be deleted) or 'error:<reason>'.
     *
     * @param  array<string, string>  $data
     */
    public function send(string $token, string $title, string $body, array $data = [], bool $validateOnly = false): string
    {
        $acct = $this->account() ?? throw new RuntimeException('Firebase is not configured.');
        $msg = ['token' => $token, 'notification' => ['title' => $title, 'body' => $body], 'data' => array_map('strval', $data),
            'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'updates']]];
        $res = Http::withToken($this->accessToken())->timeout(10)
            ->post('https://fcm.googleapis.com/v1/projects/'.$acct['project_id'].'/messages:send', ['message' => $msg, 'validate_only' => $validateOnly]);
        if ($res->successful()) {
            return 'sent';
        }
        $status = (string) $res->json('error.status');
        $code = collect($res->json('error.details') ?? [])->pluck('errorCode')->filter()->first();
        if ($res->status() === 404 || $code === 'UNREGISTERED' || ($code === 'INVALID_ARGUMENT' && str_contains((string) $res->json('error.message'), 'token'))) {
            return 'invalid_token';
        }
        if ($res->status() === 401) {
            Cache::forget('fcm:token:'.hash('sha256', $acct['client_email'].$acct['private_key']));
        }

        return 'error:'.mb_substr($code ?: $status ?: (string) $res->status(), 0, 40);
    }
}
