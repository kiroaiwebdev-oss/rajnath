<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\CustomerAuth;
use App\Domain\Identity\Otp\OtpException;
use App\Domain\Identity\Otp\OtpService;
use App\Domain\Ledger\Wallet;
use App\Http\Controllers\Controller;
use App\Models\BirthProfile;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public const NOTIFICATION_PREFS = [
        'booking_reminders' => 'Booking reminders and changes',
        'reports_ready' => 'Report ready notices',
        'payments' => 'Payment and refund updates',
        'offers' => 'Festival guidance and offers (optional)',
    ];

    public function __construct(private AuditLogger $audit, private OtpService $otp) {}

    public function dashboard(Request $request): View
    {
        $u = $request->user();

        return view('account.dashboard', [
            'profiles' => BirthProfile::where('user_id', $u->id)->orderByDesc('is_primary')->orderBy('name')->get(),
            'enquiries' => Enquiry::with('offering.translations')->where('user_id', $u->id)->latest()->limit(5)->get(),
            'wallet' => app(Wallet::class)->balances($u),
        ]);
    }

    public function editProfile(Request $request): View
    {
        $u = $request->user();

        return view('account.profile', ['user' => $u, 'prefs' => self::NOTIFICATION_PREFS, 'current' => $u->notification_prefs ? json_decode($u->notification_prefs, true) : self::defaultPrefs()]);
    }

    public static function defaultPrefs(): array
    {
        return ['booking_reminders' => true, 'reports_ready' => true, 'payments' => true, 'offers' => false];
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $u = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'locale' => ['required', Rule::in(['en', 'hi'])],
            'prefs' => ['array'],
            'prefs.*' => ['in:1'],
        ]);
        $prefs = [];
        foreach (array_keys(self::NOTIFICATION_PREFS) as $k) {
            $prefs[$k] = isset($data['prefs'][$k]);
        }
        $u->forceFill(['name' => $data['name'], 'locale' => $data['locale'], 'notification_prefs' => json_encode($prefs)])->save();
        $this->audit->record('customer.profile_updated', $u, ['prefs' => $prefs, 'locale' => $data['locale']], $u);

        return back()->with('status', 'Profile saved.');
    }

    // ---- Add or change email / mobile (verified by OTP to the new address) ----

    public function startContactChange(Request $request): RedirectResponse
    {
        $data = $request->validate(['contact' => ['required', 'string', 'max:190']]);
        ['channel' => $ch, 'destination' => $dest] = CustomerAuth::parseIdentifier($data['contact']);
        $other = User::where($ch === 'email' ? 'email' : 'phone_e164', $dest)->where('id', '!=', $request->user()->id)->exists();
        if ($other) {
            throw ValidationException::withMessages(['contact' => 'This '.($ch === 'email' ? 'email' : 'number').' is already used by another account.']);
        }
        try {
            $ref = $this->otp->issue($ch, $dest, 'change_contact', $request->ip(), $request->user()->id);
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['contact' => $e->getMessage()]);
        }
        $request->session()->put('contact_otp_ref', $ref);

        return back()->with('contact_pending', $this->otp->maskedDestination($ref));
    }

    public function confirmContactChange(Request $request): RedirectResponse
    {
        $ref = $request->session()->get('contact_otp_ref');
        $code = $request->validate(['contact_code' => ['required', 'string', 'max:10']])['contact_code'];
        if (! $ref) {
            throw ValidationException::withMessages(['contact_code' => 'Please start again.']);
        }
        try {
            $c = $this->otp->verify($ref, $code, 'change_contact');
        } catch (OtpException $e) {
            return back()->withErrors(['contact_code' => $e->getMessage()])->with('contact_pending', $this->otp->maskedDestination($ref));
        }
        $u = $request->user();
        abort_unless((int) $c['user_id'] === $u->id, 403);
        DB::transaction(function () use ($u, $c) {
            $col = $c['channel'] === 'email' ? 'email' : 'phone_e164';
            if (User::where($col, $c['destination'])->where('id', '!=', $u->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['contact_code' => 'This address was just taken by another account.']);
            }
            $u->forceFill([$col => $c['destination'], ($c['channel'] === 'email' ? 'email_verified_at' : 'phone_verified_at') => now()])->save();
        });
        $request->session()->forget('contact_otp_ref');
        $this->audit->record('customer.contact_changed', $u, ['channel' => $c['channel']], $u);

        return back()->with('status', ($c['channel'] === 'email' ? 'Email' : 'Mobile number').' verified and saved. You can now sign in with it.');
    }

    // ---- Sessions & devices ----

    public function devices(Request $request): View
    {
        $u = $request->user();
        $sessions = DB::table('sessions')->where('user_id', $u->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return view('account.devices', [
            'sessions' => $sessions, 'currentId' => $request->session()->getId(),
            'tokens' => $u->tokens()->orderByDesc('last_used_at')->get(['id', 'name', 'platform', 'ip', 'last_used_at', 'created_at']),
        ]);
    }

    public function revokeSession(Request $request, string $id): RedirectResponse
    {
        abort_if($id === $request->session()->getId(), 422, 'Use Sign out to end this session.');
        $n = DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $id)->delete();
        abort_unless($n, 404);
        $this->audit->record('customer.session_revoked', $request->user(), [], $request->user());

        return back()->with('status', 'That browser has been signed out.');
    }

    public function revokeToken(Request $request, int $id): RedirectResponse
    {
        $n = $request->user()->tokens()->whereKey($id)->delete();
        abort_unless($n, 404);
        $this->audit->record('customer.device_revoked', $request->user(), ['token_id' => $id], $request->user());

        return back()->with('status', 'That device has been signed out.');
    }

    public function revokeAllOthers(Request $request): RedirectResponse
    {
        $u = $request->user();
        DB::table('sessions')->where('user_id', $u->id)->where('id', '!=', $request->session()->getId())->delete();
        $u->tokens()->delete();
        $this->audit->record('customer.signed_out_everywhere', $u, [], $u);

        return back()->with('status', 'All other browsers and app devices have been signed out.');
    }
}
