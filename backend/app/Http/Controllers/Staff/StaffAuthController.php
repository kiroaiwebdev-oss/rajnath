<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Identity\Mfa;
use App\Domain\Identity\StaffInvitations;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffAuthController extends Controller
{
    public function __construct(private AuditLogger $audit, private Mfa $mfa) {}

    public static function passwordRule(): Password
    {
        return Password::min(12)->letters()->mixedCase()->numbers()->symbols();
    }

    // ---- Invitation acceptance (owner bootstrap + staff invites) ----

    public function showInvitation(Request $request, string $token, StaffInvitations $inv): View
    {
        $invitation = $inv->findValid($token);
        abort_unless($invitation, 410, 'This invitation link has expired or was already used.');
        $key = 'invite_mfa_'.hash('sha256', $token);
        $secret = $request->session()->get($key) ?? $this->mfa->newSecret();
        $request->session()->put($key, $secret);

        return view('staff.auth.invitation', [
            'invitation' => $invitation, 'token' => $token,
            'qr' => $this->mfa->qrSvg($invitation->email, $secret), 'secret' => $secret,
        ]);
    }

    public function acceptInvitation(Request $request, string $token, StaffInvitations $inv): RedirectResponse
    {
        $key = 'invite_mfa_'.hash('sha256', $token);
        $secret = $request->session()->get($key);
        $data = $request->validate([
            'password' => ['required', 'confirmed', self::passwordRule()],
            'code' => ['required', 'string'],
        ]);
        if (! $secret || ! $this->mfa->verifySecret($secret, $data['code'], 'invite'.$key)) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is not valid. Check your phone clock and try again.']);
        }
        $user = $inv->accept($token, $data['password'], $secret);
        $request->session()->forget($key);
        $this->completeLogin($request, $user);

        return redirect()->route('admin.dashboard')->with('status', 'Your account is ready. MFA is active.');
    }

    // ---- Login with password + TOTP ----

    public function showLogin(): View
    {
        return view('staff.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', strtolower($data['email']))->first();
        $ok = $user && $user->password && Hash::check($data['password'], $user->password);
        if (! $ok || ! $user->isActive() || ! $user->hasMfa() || $user->roles()->doesntExist()) {
            $this->audit->record('staff.login_failed', null, ['email' => $data['email']], null, 'guest');
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active staff account.']);
        }
        $request->session()->regenerate();
        $request->session()->put('staff_pending', ['id' => $user->id, 'at' => now()->timestamp]);

        return redirect()->route('staff.mfa');
    }

    public function showMfa(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('staff.login');
        }

        return view('staff.auth.mfa');
    }

    public function verifyMfa(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('staff.login');
        }
        $request->validate(['code' => ['required', 'string']]);
        if (! $this->mfa->verify($user, $request->input('code'))) {
            $this->audit->record('staff.mfa_failed', $user, [], $user);
            throw ValidationException::withMessages(['code' => 'The authenticator code is not valid.']);
        }
        $request->session()->forget('staff_pending');
        $this->completeLogin($request, $user);

        return redirect()->intended(route('admin.dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $p = $request->session()->get('staff_pending');
        if (! $p || now()->timestamp - $p['at'] > 300) {
            return null;
        }

        return User::find($p['id']);
    }

    private function completeLogin(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('staff_mfa_at', now()->timestamp);
        $request->session()->put('step_up_at', now()->timestamp);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record('staff.login', $user, [], $user);
    }

    // ---- Step-up re-authentication for sensitive actions ----

    public function showStepUp(): View
    {
        return view('staff.auth.step-up');
    }

    public function stepUp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $user->password) || ! $this->mfa->verify($user, $request->input('code'))) {
            $this->audit->record('staff.step_up_failed', $user, [], $user);
            throw ValidationException::withMessages(['code' => 'Password or authenticator code is not valid.']);
        }
        $request->session()->put('step_up_at', now()->timestamp);
        $this->audit->record('staff.step_up', $user, [], $user);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($user) {
            $this->audit->record('staff.logout', $user, [], $user);
        }

        return redirect()->route('staff.login');
    }
}
