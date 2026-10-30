<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\CustomerAuth;
use App\Domain\Identity\Otp\OtpException;
use App\Domain\Identity\Otp\OtpService;
use App\Domain\Privacy\PrivacyService;
use App\Domain\Support\Tickets;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * App parity for the website account: support tickets, contact change with OTP, sign out
 * everywhere, privacy export and deletion. Export and deletion need a fresh OTP sent to the
 * customer's own email or mobile (the app equivalent of the website's "sign in again").
 */
class AccountApiController extends Controller
{
    public function __construct(private OtpService $otp, private AuditLogger $audit) {}

    private static function iso(?string $t): ?string
    {
        return $t ? CarbonImmutable::parse($t, 'UTC')->toIso8601ZuluString() : null;
    }

    private function ticket(Request $r, string $ref): object
    {
        $t = DB::table('tickets')->where('ref', $ref)->where('user_id', $r->user()->id)->first();
        abort_unless($t, 404);

        return $t;
    }

    private static function ticketToApi(object $t): array
    {
        return ['ref' => $t->ref, 'subject' => $t->subject, 'category' => $t->category, 'category_label' => Tickets::CATEGORIES[$t->category] ?? $t->category,
            'status' => $t->status, 'status_label' => Tickets::STATUSES[$t->status] ?? $t->status, 'created_at' => self::iso($t->created_at), 'updated_at' => self::iso($t->updated_at)];
    }

    // ---------- Support ----------
    public function tickets(Request $r): JsonResponse
    {
        return response()->json(['data' => DB::table('tickets')->where('user_id', $r->user()->id)->orderByDesc('updated_at')->get()->map(fn ($t) => self::ticketToApi($t)),
            'meta' => ['categories' => collect(Tickets::CATEGORIES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()]]);
    }

    public function openTicket(Request $r, Tickets $tickets): JsonResponse
    {
        $d = $r->validate(['category' => ['required', Rule::in(array_keys(Tickets::CATEGORIES))], 'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'], 'order_ref' => ['nullable', 'string', 'size:26']]);

        return response()->json(['data' => self::ticketToApi($tickets->open($r->user(), $d))], 201);
    }

    public function showTicket(Request $r, string $ref): JsonResponse
    {
        $t = $this->ticket($r, $ref);
        $messages = DB::table('ticket_messages as m')->join('users as u', 'u.id', '=', 'm.author_id')->where('m.ticket_id', $t->id)->where('m.internal', false)
            ->orderBy('m.id')->get(['m.body', 'm.created_at', 'u.kind', 'u.name'])
            ->map(fn ($m) => ['body' => $m->body, 'from' => $m->kind === User::KIND_STAFF ? 'team' : 'you', 'name' => $m->kind === User::KIND_STAFF ? $m->name : null, 'at' => self::iso($m->created_at)]);

        return response()->json(['data' => self::ticketToApi($t) + ['messages' => $messages]]);
    }

    public function replyTicket(Request $r, string $ref, Tickets $tickets): JsonResponse
    {
        $t = $this->ticket($r, $ref);
        $tickets->reply($t, $r->user(), $r->validate(['body' => ['required', 'string', 'max:5000']])['body']);

        return $this->showTicket($r, $ref);
    }

    public function closeTicket(Request $r, string $ref, Tickets $tickets): JsonResponse
    {
        $tickets->setStatus($this->ticket($r, $ref), $r->user(), 'closed');

        return $this->showTicket($r, $ref);
    }

    // ---------- Contact change (OTP to the new email / mobile) ----------
    public function startContactChange(Request $r): JsonResponse
    {
        $d = $r->validate(['contact' => ['required', 'string', 'max:190']]);
        try {
            ['channel' => $ch, 'destination' => $dest] = CustomerAuth::parseIdentifier($d['contact']);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['contact' => 'Enter a valid email address or 10-digit mobile number.']);
        }
        if (User::where($ch === 'email' ? 'email' : 'phone_e164', $dest)->where('id', '!=', $r->user()->id)->exists()) {
            throw ValidationException::withMessages(['contact' => 'This '.($ch === 'email' ? 'email' : 'number').' is already used by another account.']);
        }
        try {
            $ref = $this->otp->issue($ch, $dest, 'change_contact', $r->ip(), $r->user()->id);
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['contact' => $e->getMessage()]);
        }

        return response()->json(['data' => ['challenge' => $ref, 'sent_to' => $this->otp->maskedDestination($ref)]]);
    }

    public function confirmContactChange(Request $r): JsonResponse
    {
        $d = $r->validate(['challenge' => ['required', 'string', 'size:26'], 'code' => ['required', 'string', 'max:10']]);
        try {
            $c = $this->otp->verify($d['challenge'], $d['code'], 'change_contact');
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }
        $u = $r->user();
        abort_unless((int) $c['user_id'] === $u->id, 403);
        DB::transaction(function () use ($u, $c) {
            $col = $c['channel'] === 'email' ? 'email' : 'phone_e164';
            if (User::where($col, $c['destination'])->where('id', '!=', $u->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['code' => 'This address was just taken by another account.']);
            }
            $u->forceFill([$col => $c['destination'], ($c['channel'] === 'email' ? 'email_verified_at' : 'phone_verified_at') => now()])->save();
        });
        $this->audit->record('customer.contact_changed', $u, ['channel' => $c['channel'], 'via' => 'api'], $u);

        return response()->json(['data' => MeController::present($u->fresh())]);
    }

    public function signOutOthers(Request $r): JsonResponse
    {
        $u = $r->user();
        $current = $u->currentAccessToken()->id;
        DB::table('sessions')->where('user_id', $u->id)->delete();
        $u->tokens()->where('id', '!=', $current)->delete();
        $this->audit->record('customer.signed_out_everywhere', $u, ['via' => 'api'], $u);

        return response()->json(['data' => ['status' => 'signed_out_others']]);
    }

    // ---------- Privacy ----------
    public function privacy(Request $r, PrivacyService $p): JsonResponse
    {
        return response()->json(['data' => ['blockers' => $p->blockers($r->user()),
            'kept' => 'Order amounts, dates and payment references are kept for tax law, without your name or contact.']]);
    }

    /** Sends a confirmation code to the customer's own email (or mobile) before export or deletion. */
    public function privacyCode(Request $r): JsonResponse
    {
        $u = $r->user();
        [$ch, $dest] = $u->email ? ['email', $u->email] : ['sms', $u->phone_e164];
        abort_unless($dest, 422, 'Add an email or mobile number first.');
        try {
            $ref = $this->otp->issue($ch, $dest, 'privacy', $r->ip(), $u->id);
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        return response()->json(['data' => ['challenge' => $ref, 'sent_to' => $this->otp->maskedDestination($ref)]]);
    }

    private function confirmPrivacyCode(Request $r): void
    {
        $d = $r->validate(['challenge' => ['required', 'string', 'size:26'], 'code' => ['required', 'string', 'max:10']]);
        try {
            $c = $this->otp->verify($d['challenge'], $d['code'], 'privacy');
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }
        abort_unless((int) $c['user_id'] === $r->user()->id, 403);
    }

    public function export(Request $r, PrivacyService $p): JsonResponse
    {
        $this->confirmPrivacyCode($r);

        return response()->json(['data' => $p->export($r->user())], 200, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $r, PrivacyService $p): JsonResponse
    {
        $r->validate(['confirm' => ['required', 'in:DELETE']], ['confirm.in' => 'Type DELETE in capital letters to confirm.']);
        $this->confirmPrivacyCode($r);
        $res = $p->delete($r->user());
        if ($res->status !== 'completed') {
            throw ValidationException::withMessages(['confirm' => $res->blocked_reason]);
        }

        return response()->json(['data' => ['status' => 'deleted', 'ref' => $res->ref, 'steps' => json_decode($res->steps, true)]]);
    }
}
