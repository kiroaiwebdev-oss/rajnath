<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** API: token owner must be an active customer; records last IP for the device list. */
class EnsureActiveCustomerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u || $u->isStaff() || ! $u->isActive()) {
            $t = $u?->currentAccessToken();
            if ($t instanceof PersonalAccessToken) {
                $t->delete();
            }

            return response()->json(['error' => ['code' => 'account_inactive', 'message' => 'This account is not active.']], 401);
        }
        $t = $u->currentAccessToken();
        if ($t instanceof PersonalAccessToken && $t->ip !== $request->ip()) {
            $t->forceFill(['ip' => $request->ip()])->save();
        }

        return $next($request);
    }
}
