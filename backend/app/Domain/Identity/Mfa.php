<?php

namespace App\Domain\Identity;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/** TOTP (RFC 6238) with replay protection: each accepted time-step can be used only once per user. */
class Mfa
{
    public function __construct(private Google2FA $g = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->g->generateSecretKey(32);
    }

    public function qrSvg(string $email, string $secret): string
    {
        $uri = $this->g->getQRCodeUrl(config('app.name'), $email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }

    public function verifySecret(string $secret, string $code, string $replayScope): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $ts = $this->g->verifyKeyNewer($secret, $code, Cache::get("mfa:last:$replayScope"), 1);
        if ($ts === false) {
            return false;
        }
        Cache::put("mfa:last:$replayScope", $ts === true ? $this->g->getTimestamp() : $ts, now()->addMinutes(5));

        return true;
    }

    public function verify(User $user, string $code): bool
    {
        return $user->hasMfa() && $this->verifySecret($user->mfa_secret, $code, 'u'.$user->id);
    }
}
