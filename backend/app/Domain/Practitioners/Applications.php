<?php

namespace App\Domain\Practitioners;

use App\Domain\Identity\StaffInvitations;
use App\Domain\Notify\Notifier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Astrologer applications (AN-12): a signed-in customer applies from the app or website;
 * staff with practitioners.manage accept (which sends a practitioner staff invitation to the
 * email given, so the astrologer then sets a password and TOTP and completes the usual profile
 * review) or reject with a note. One open application per customer.
 * submitted → accepted | rejected | withdrawn
 */
class Applications
{
    public function __construct(private StaffInvitations $invitations, private AuditLogger $audit) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?91|0)?[6-9]\d{9}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:70'],
            'languages' => ['required', 'string', 'max:160'],
            'specialties' => ['required', 'string', 'max:255'],
            'about' => ['required', 'string', 'min:60', 'max:3000'],
            'consent' => ['accepted'],
        ];
    }

    public static function messages(): array
    {
        return ['phone.regex' => 'Enter a 10-digit Indian mobile number.', 'about.min' => 'Tell us a little more (at least 60 characters).',
            'consent.accepted' => 'Please confirm that your details are true.'];
    }

    public static function toApi(object $a): array
    {
        return ['ref' => $a->ref, 'status' => $a->status, 'name' => $a->name, 'email' => $a->email, 'experience_years' => (int) $a->experience_years,
            'languages' => $a->languages, 'specialties' => $a->specialties, 'decision_note' => $a->status === 'rejected' ? $a->decision_note : null,
            'created_at' => CarbonImmutable::parse($a->created_at, 'UTC')->toIso8601ZuluString()];
    }

    public function submit(User $u, array $d): object
    {
        if (DB::table('practitioner_applications')->where('user_id', $u->id)->where('status', 'submitted')->exists()) {
            throw ValidationException::withMessages(['name' => 'Your application is already with our team.']);
        }
        $email = strtolower(trim($d['email']));
        if (DB::table('users')->where('email', $email)->where('kind', User::KIND_STAFF)->exists()
            || DB::table('staff_invitations')->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has a team account or invitation.']);
        }
        if (DB::table('users')->where('email', $email)->where('id', '!=', $u->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email belongs to another account.']);
        }
        if ($email === strtolower((string) $u->email)) {
            throw ValidationException::withMessages(['email' => 'Use a different email for your astrologer account (your customer account already uses this one).']);
        }
        $digits = preg_replace('/\D/', '', $d['phone']);
        $id = DB::table('practitioner_applications')->insertGetId([
            'ref' => (string) Str::ulid(), 'user_id' => $u->id, 'name' => trim($d['name']), 'email' => $email, 'phone_e164' => '+91'.substr($digits, -10),
            'city' => $d['city'] ?? null, 'experience_years' => (int) $d['experience_years'], 'languages' => trim($d['languages']), 'specialties' => trim($d['specialties']),
            'about' => trim($d['about']), 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $a = DB::table('practitioner_applications')->find($id);
        $this->audit->record('practitioner.applied', null, ['application' => $a->ref], $u);
        app(Notifier::class)->send($u, 'support', 'Application received', 'Thank you for applying to join as an astrologer. Our team will review it and reply within a few working days.', $a->ref);

        return $a;
    }

    public function withdraw(object $a, User $u): void
    {
        abort_unless((int) $a->user_id === $u->id, 404);
        if (! DB::table('practitioner_applications')->where('id', $a->id)->where('status', 'submitted')->update(['status' => 'withdrawn', 'updated_at' => now()])) {
            throw ValidationException::withMessages(['application' => 'This application can no longer be withdrawn.']);
        }
        $this->audit->record('practitioner.application_withdrawn', null, ['application' => $a->ref], $u);
    }

    /** @return array{0: object, 1: string} the updated application and the invitation link */
    public function accept(object $a, User $by): array
    {
        if ($a->status !== 'submitted') {
            throw ValidationException::withMessages(['application' => 'This application was already decided.']);
        }
        if ((int) $a->user_id === $by->id) {
            throw ValidationException::withMessages(['application' => 'You cannot decide your own application.']);
        }
        [, $token] = $this->invitations->create($a->email, $a->name, ['practitioner'], $by);
        $link = url('/staff/invitation/'.$token);
        DB::table('practitioner_applications')->where('id', $a->id)->update(['status' => 'accepted', 'decided_by' => $by->id, 'decided_at' => now(), 'updated_at' => now()]);
        $this->audit->record('practitioner.application_accepted', null, ['application' => $a->ref], $by);
        try {
            Mail::raw("Namaste {$a->name},\n\nYour application to join ".config('brand')['brand.name']." as an astrologer has been accepted.\n\nOpen this link within 48 hours to create your team account (password and authenticator app):\n$link\n\nAfter signing in, complete your profile and payout details under My practice; our team then reviews it before bookings open.",
                fn ($m) => $m->to($a->email)->subject('Your astrologer account invitation'));
        } catch (Throwable $e) {
            report($e);
        }
        app(Notifier::class)->send((int) $a->user_id, 'support', 'Application accepted', 'Your astrologer application was accepted. We have emailed an invitation link to '.$a->email.'.', $a->ref);

        return [DB::table('practitioner_applications')->find($a->id), $link];
    }

    public function reject(object $a, string $note, User $by): void
    {
        if ($a->status !== 'submitted') {
            throw ValidationException::withMessages(['application' => 'This application was already decided.']);
        }
        DB::table('practitioner_applications')->where('id', $a->id)->update(['status' => 'rejected', 'decision_note' => $note, 'decided_by' => $by->id, 'decided_at' => now(), 'updated_at' => now()]);
        $this->audit->record('practitioner.application_rejected', null, ['application' => $a->ref], $by);
        app(Notifier::class)->send((int) $a->user_id, 'support', 'About your astrologer application', 'Thank you for applying. We cannot take your application forward at this time. '.$note, $a->ref);
    }
}
