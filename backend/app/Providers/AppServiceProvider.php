<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Settings\SettingsRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Default-deny: only explicit permissions pass. Owners pass everything, and only while
        // their account is active. Non-staff users never pass staff abilities.
        Gate::before(function (User $user, string $ability) {
            if (! $user->isActive()) {
                return false;
            }
            if ($user->hasRole('owner')) {
                return true;
            }

            return null;
        });

        RateLimiter::for('staff-login', fn (Request $r) => [
            Limit::perMinute(5)->by('ip:'.$r->ip()),
            Limit::perMinute(5)->by('email:'.strtolower((string) $r->input('email'))),
        ]);
        RateLimiter::for('staff-mfa', fn (Request $r) => Limit::perMinute(6)->by('sess:'.$r->session()->getId()));
        // OTP endpoints: per IP and per identifier; the OTP service adds per-destination hourly caps.
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute(10)->by('otp-ip:'.$r->ip()),
            Limit::perMinute(5)->by('otp-id:'.strtolower((string) ($r->input('identifier') ?? $r->input('challenge') ?? ($r->hasSession() ? $r->session()->getId() : $r->ip())))),
        ]);
        RateLimiter::for('tools', fn (Request $r) => Limit::perMinute(30)->by('tl:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('places', fn (Request $r) => Limit::perMinute(60)->by('pl:'.$r->ip()));
        RateLimiter::for('enquiry', fn (Request $r) => [Limit::perMinute(3)->by('enq:'.$r->ip()), Limit::perDay(30)->by('enqd:'.$r->ip())]);
        RateLimiter::for('provider-test', fn (Request $r) => Limit::perMinute(5)->by('u:'.$r->user()?->id));

        // Apply SMTP settings saved in admin (falls back to .env MAIL_* when none are saved).
        $this->app->booted(fn () => rescue(fn () => app(\App\Domain\Messaging\MailConfigurator::class)->apply(), null, false));

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));

        View::composer('*', function ($view) {
            static $brand;
            $brand ??= rescue(fn () => app(SettingsRepository::class)->all(), [], false);
            $view->with('site', fn (string $k, $d = null) => $brand[$k] ?? (config('brand')[$k] ?? $d));
        });
    }
}
