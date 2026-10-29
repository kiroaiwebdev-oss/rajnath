<?php

use App\Http\Middleware\EnsureActiveCustomerToken;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\RequireStepUp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetLocale::class]);
        $middleware->api(append: [SetLocale::class]);
        $middleware->alias([
            'staff' => EnsureStaff::class,
            'step.up' => RequireStepUp::class,
            'customer' => EnsureCustomer::class,
            'customer.api' => EnsureActiveCustomerToken::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('admin', 'admin/*', 'staff/*') ? route('staff.login') : route('account.sign-in'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
