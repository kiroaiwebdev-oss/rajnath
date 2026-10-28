<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Staff area gate: signed in, active, has at least one role, MFA passed in this session. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $request->session()->has('staff_mfa_at')) {
            return redirect()->guest(route('staff.login'));
        }
        if (! $user->isActive() || ! $user->hasMfa() || $user->roles()->doesntExist()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('staff.login')->withErrors(['email' => 'Your staff access is not active.']);
        }

        return $next($request);
    }
}
