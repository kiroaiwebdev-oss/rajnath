<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\CustomerAuth;
use App\Domain\Identity\Otp\OtpException;
use App\Domain\Identity\Otp\OtpService;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mobile app sign-in. Flow: POST otp/start → POST otp/verify → Bearer token (per device, 90 days).
 * Error responses use {"error": {"code": ..., "message": ...}}.
 */
class AuthController extends Controller
{
    public const TOKEN_DAYS = 90;

    public function __construct(private CustomerAuth $auth, private OtpService $otp) {}

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:190']]);
        try {
            $ref = $this->auth->start($data['identifier'], $request->ip());
        } catch (OtpException $e) {
            return self::error($e->reason, $e->getMessage(), $e->reason === 'rate_limited' ? 429 : 422);
        }
        $c = DB::table('otp_challenges')->where('ref', $ref)->first();

        return response()->json([
            'challenge' => $ref, 'channel' => $c->channel, 'sent_to' => $this->otp->maskedDestination($ref),
            'is_new_account' => ! $this->auth->findByContact($c->channel, Crypt::decryptString($c->destination)),
            'expires_in' => OtpService::TTL_MINUTES * 60, 'resend_in' => OtpService::RESEND_SECONDS,
        ], 201);
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge' => ['required', 'string', 'size:26']]);
        try {
            $this->otp->resend($data['challenge']);
        } catch (OtpException $e) {
            return self::error($e->reason, $e->getMessage(), in_array($e->reason, ['rate_limited', 'too_soon'], true) ? 429 : 422);
        }

        return response()->json(['resend_in' => OtpService::RESEND_SECONDS]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:26'],
            'code' => ['required', 'string', 'max:10'],
            'device_name' => ['required', 'string', 'max:80'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'name' => ['nullable', 'string', 'max:120'],
            'accept_terms' => ['nullable', 'boolean'],
        ]);
        try {
            $r = $this->auth->complete($data['challenge'], $data['code'], (bool) ($data['accept_terms'] ?? false), $data['name'] ?? null, $request->ip());
        } catch (OtpException $e) {
            return self::error($e->reason, $e->getMessage(), 422);
        }
        $token = $r['user']->createToken(mb_substr($data['device_name'], 0, 80), ['customer'], now()->addDays(self::TOKEN_DAYS));
        $token->accessToken->forceFill(['platform' => $data['platform'], 'ip' => $request->ip()])->save();

        return response()->json([
            'token' => $token->plainTextToken, 'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601ZuluString(),
            'created' => $r['created'], 'user' => MeController::present($r['user']),
        ], $r['created'] ? 201 : 200);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $audit->record('customer.logout', $request->user(), ['via' => 'api'], $request->user());

        return response()->json(null, 204);
    }

    public static function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
