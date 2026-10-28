<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Requires password + TOTP re-verification within the configured window. */
class RequireStepUp
{
    public function handle(Request $request, Closure $next): Response
    {
        $at = (int) $request->session()->get('step_up_at', 0);
        if (now()->timestamp - $at > config('rbac.step_up_minutes') * 60) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            } else {
                $request->session()->put('url.intended', url()->previous());
            }

            return redirect()->route('staff.step-up')->with('status', 'Please confirm your identity to continue.');
        }

        return $next($request);
    }
}
