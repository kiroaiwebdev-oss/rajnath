<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Customer area: signed-in, active, non-staff account. */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect()->guest(route('account.sign-in'));
        }
        if ($u->isStaff()) {
            return redirect()->route('admin.dashboard');
        }
        if (! $u->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('account.sign-in')->withErrors(['identifier' => 'This account is not active. Please contact support.']);
        }

        return $next($request);
    }
}
