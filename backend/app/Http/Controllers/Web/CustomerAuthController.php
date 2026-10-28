<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\CustomerAuth;
use App\Domain\Identity\Otp\OtpException;
use App\Domain\Identity\Otp\OtpService;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function __construct(private CustomerAuth $auth, private OtpService $otp) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user() && ! $request->user()->isStaff()) {
            return redirect()->route('account.dashboard');
        }

        return view('account.auth.sign-in');
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:190']]);
        try {
            $ref = $this->auth->start($data['identifier'], $request->ip());
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['identifier' => $e->getMessage()]);
        }
        $request->session()->put('otp_ref', $ref);

        return redirect()->route('account.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $ref = $request->session()->get('otp_ref');
        if (! $ref || ! ($masked = $this->otp->maskedDestination($ref))) {
            return redirect()->route('account.sign-in');
        }
        $c = DB::table('otp_challenges')->where('ref', $ref)->first();
        $dest = \Illuminate\Support\Facades\Crypt::decryptString($c->destination);

        return view('account.auth.verify', [
            'masked' => $masked, 'channel' => $c->channel,
            'isNew' => ! $this->auth->findByContact($c->channel, $dest),
            'resendIn' => $this->otp->secondsUntilResend($ref),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $ref = $request->session()->get('otp_ref');
        abort_unless($ref, 419);
        try {
            $this->otp->resend($ref);
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', 'A new code has been sent.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $ref = $request->session()->get('otp_ref');
        if (! $ref) {
            return redirect()->route('account.sign-in');
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $r = $this->auth->complete($ref, $data['code'], $request->boolean('terms'), $data['name'] ?? null, $request->ip());
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }
        Auth::guard('web')->login($r['user'], remember: true);
        $request->session()->regenerate();
        $request->session()->forget('otp_ref');
        $request->session()->put('customer_reauth_at', now()->timestamp);

        return redirect()->intended(route('account.dashboard'))->with('status', $r['created'] ? 'Welcome! Your account has been created.' : 'You are signed in.');
    }

    public function logout(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($user) {
            $audit->record('customer.logout', $user, [], $user);
        }

        return redirect()->route('home');
    }
}
