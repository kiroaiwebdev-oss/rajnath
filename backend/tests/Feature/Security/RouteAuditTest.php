<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Static audit of every registered route's protection (fails if a new route forgets a guard). */
class RouteAuditTest extends TestCase
{
    private function mw($route): array
    {
        return Route::gatherRouteMiddleware($route);
    }

    public function test_every_admin_route_requires_staff_session(): void
    {
        $n = 0;
        foreach (Route::getRoutes() as $r) {
            if (str_starts_with($r->uri(), 'admin')) {
                $mw = implode(' ', array_map(fn ($m) => is_string($m) ? $m : '', $this->mw($r)));
                $this->assertStringContainsString('EnsureStaff', $mw, $r->uri());
                $this->assertMatchesRegularExpression('/Authenticate/', $mw, $r->uri());
                $n++;
            }
        }
        $this->assertGreaterThan(40, $n);
    }

    public function test_sensitive_admin_mutations_require_step_up(): void
    {
        $mustStepUp = ['admin/team/', 'admin/settings', 'admin/providers', 'admin/payments', 'admin/catalogue/{ref}/variants', 'admin/finance/refunds',
            'admin/push', 'admin/calling', 'admin/commission', 'admin/payouts', 'admin/practitioners/invite', 'admin/coupons', 'admin/practitioner-applications/',
            'admin/privacy/{ref}/delete', 'admin/privacy/{ref}/hold'];
        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            if (array_intersect($r->methods(), ['POST', 'PUT', 'DELETE']) && collect($mustStepUp)->contains(fn ($p) => str_starts_with($uri, $p))) {
                $mw = implode(' ', array_filter($this->mw($r), 'is_string'));
                $this->assertStringContainsString('RequireStepUp', $mw, $uri);
            }
        }
    }

    public function test_account_and_checkout_routes_require_customer(): void
    {
        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            $public = in_array($uri, ['account/sign-in', 'account/verify', 'account/verify/resend'], true);
            if (! $public && (str_starts_with($uri, 'account') || str_starts_with($uri, 'checkout') || $uri === 'gems/buy')) {
                $mw = implode(' ', array_filter($this->mw($r), 'is_string'));
                $this->assertStringContainsString('EnsureCustomer', $mw, $uri);
            }
        }
    }

    public function test_csrf_applies_to_all_web_mutations_except_the_signed_webhook(): void
    {
        foreach (Route::getRoutes() as $r) {
            if (str_starts_with($r->uri(), 'api/') || ! array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }
            $excluded = in_array(ValidateCsrfToken::class, $r->excludedMiddleware(), true);
            $this->assertSame(in_array($r->uri(), ['webhooks/razorpay', 'webhooks/razorpayx', 'webhooks/courier-tracking'], true), $excluded, $r->uri());
        }
    }

    public function test_api_customer_routes_require_token_and_ability(): void
    {
        $open = ['api/v1/meta', 'api/v1/auth/otp/start', 'api/v1/auth/otp/resend', 'api/v1/auth/otp/verify', 'api/v1/catalogue/families', 'api/v1/catalogue/offerings',
            'api/v1/catalogue/offerings/{slug}', 'api/v1/places', 'api/v1/tools', 'api/v1/tools/{slug}', 'api/v1/panchang', 'api/v1/enquiries',
            'api/v1/store/products', 'api/v1/store/products/{slug}', 'api/v1/practitioners', 'api/v1/horoscopes', 'api/v1/horoscopes/types',
            'api/v1/gochar', 'api/v1/gochar/{planet}', 'api/v1/muhurat', 'api/v1/muhurat/{event}', 'api/v1/festivals',
            'api/v1/calendar', 'api/v1/rashi/{sign}', 'api/v1/navagraha', 'api/v1/faq', 'api/v1/site'];
        foreach (Route::getRoutes() as $r) {
            if (! str_starts_with($r->uri(), 'api/') || in_array($r->uri(), $open, true)) {
                continue;
            }
            $mw = implode(' ', array_filter($this->mw($r), 'is_string'));
            $this->assertStringContainsString('auth:sanctum', implode(' ', $r->gatherMiddleware()), $r->uri());
            $this->assertStringContainsString('EnsureActiveCustomerToken', $mw, $r->uri());
        }
    }

    public function test_every_route_is_rate_limited_where_it_sends_or_charges(): void
    {
        foreach (['account/sign-in', 'account/verify', 'api/v1/auth/otp/start', 'api/v1/auth/otp/verify', 'enquiries', 'checkout', 'gems/buy', 'staff/login', 'staff/mfa', 'tools/{slug}',
            'assistant/chat', 'api/v1/assistant/chat', 'api/v1/checkout/service', 'api/v1/checkout/booking', 'api/v1/checkout/product', 'api/v1/checkout/recharge', 'api/v1/palmistry/{ref}/photos'] as $uri) {
            $route = collect(Route::getRoutes())->first(fn ($r) => $r->uri() === $uri && array_intersect($r->methods(), ['POST']));
            $this->assertNotNull($route, $uri);
            $this->assertMatchesRegularExpression('/throttle/', implode(' ', $route->gatherMiddleware()), $uri);
        }
    }

    public function test_debug_mode_and_dev_helpers_are_not_reachable(): void
    {
        $this->assertFalse(collect(Route::getRoutes())->contains(fn ($r) => str_contains($r->uri(), 'telescope') || str_contains($r->uri(), '_ignition')));
        $example = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('APP_DEBUG=false', $example);
        $this->assertStringContainsString('APP_ENV=production', $example);
    }
}
