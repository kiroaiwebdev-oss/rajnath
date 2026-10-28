<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\AccountController;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public static function present(User $u): array
    {
        return [
            'ref' => $u->ref, 'name' => $u->name,
            'email' => $u->email, 'email_verified' => $u->email_verified_at !== null,
            'phone' => $u->phone_e164, 'phone_verified' => $u->phone_verified_at !== null,
            'locale' => $u->locale,
            'notification_prefs' => $u->notification_prefs ? json_decode($u->notification_prefs, true) : AccountController::defaultPrefs(),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => self::present($request->user())]);
    }

    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'locale' => ['sometimes', Rule::in(['en', 'hi'])],
            'notification_prefs' => ['sometimes', 'array'],
            'notification_prefs.*' => ['boolean'],
        ]);
        $u = $request->user();
        if (isset($data['notification_prefs'])) {
            $current = $u->notification_prefs ? json_decode($u->notification_prefs, true) : AccountController::defaultPrefs();
            $data['notification_prefs'] = json_encode(array_merge($current, array_intersect_key($data['notification_prefs'], AccountController::NOTIFICATION_PREFS)));
        }
        $u->forceFill($data)->save();
        $audit->record('customer.profile_updated', $u, ['via' => 'api'], $u);

        return response()->json(['data' => self::present($u->fresh())]);
    }

    public function devices(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken()->id;

        return response()->json(['data' => $request->user()->tokens()->orderByDesc('last_used_at')->get()->map(fn ($t) => [
            'id' => $t->id, 'name' => $t->name, 'platform' => $t->platform, 'current' => $t->id === $current,
            'last_used_at' => $t->last_used_at?->toIso8601ZuluString(), 'created_at' => $t->created_at?->toIso8601ZuluString(),
        ])]);
    }

    public function revokeDevice(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $n = $request->user()->tokens()->whereKey($id)->delete();
        if (! $n) {
            return AuthController::error('not_found', 'Device not found.', 404);
        }
        $audit->record('customer.device_revoked', $request->user(), ['token_id' => $id, 'via' => 'api'], $request->user());

        return response()->json(null, 204);
    }
}
