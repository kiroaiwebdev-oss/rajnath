<?php

namespace App\Domain\Practitioners;

use App\Models\OfferingVariant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Uploads\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Team astrologers. A practitioner is a staff account with the `practitioner` role (password + TOTP)
 * and a public profile. Lifecycle: draft → submitted (by the practitioner) → approved (by a reviewer,
 * not themself) ; suspended. They are bookable only when approved AND an approved commission rule is
 * in effect. Team-consultation offerings can be published only when at least one bookable
 * practitioner of that tier exists. No practitioner is ever invented by the system.
 */
class Practitioners
{
    /** Team-consultation offering slug → required practitioner tier. */
    public const TIER_OFFERINGS = [
        'team-astrologer-consultation' => 'team',
        'premium-astrologer-consultation' => 'premium',
        'elite-astrologer-consultation' => 'elite',
    ];
    public const TIERS = ['team' => 'Team astrologer', 'premium' => 'Premium astrologer', 'elite' => 'Elite astrologer'];

    public function __construct(private AuditLogger $audit) {}

    public static function currentShareBps(int $practitionerId): ?int
    {
        $v = DB::table('commission_rules')->where('practitioner_id', $practitionerId)->where('status', 'approved')
            ->where('effective_from', '<=', now('Asia/Kolkata')->toDateString())->orderByDesc('effective_from')->orderByDesc('id')->value('share_bps');

        return $v === null ? null : (int) $v;
    }

    public static function bookable(object $p): bool
    {
        return $p->status === 'approved' && self::currentShareBps($p->id) !== null;
    }

    public static function forTier(string $tier)
    {
        return DB::table('practitioners')->where('tier', $tier)->where('status', 'approved')->orderBy('display_name')->get()->filter(fn ($p) => self::bookable($p))->values();
    }

    public static function canPublish(string $slug): bool
    {
        $tier = self::TIER_OFFERINGS[$slug] ?? null;

        return $tier === null || self::forTier($tier)->isNotEmpty();
    }

    /** Resolves and checks the practitioner chosen for a booking. Null for Acharya Ji's own offerings. */
    public static function forBooking(OfferingVariant $v, ?string $ref): ?object
    {
        $slug = DB::table('offerings')->where('id', $v->offering_id)->value('slug');
        $tier = self::TIER_OFFERINGS[$slug] ?? null;
        if ($tier === null) {
            return null;
        }
        $p = $ref ? DB::table('practitioners')->where('ref', $ref)->first() : null;
        if (! $p || $p->tier !== $tier || ! self::bookable($p)) {
            throw ValidationException::withMessages(['practitioner' => 'Choose an available astrologer for this consultation.']);
        }

        return $p;
    }

    public static function toApi(object $p): array
    {
        return ['ref' => $p->ref, 'display_name' => $p->display_name, 'bio' => $p->bio, 'tier' => $p->tier,
            'languages' => array_values(array_filter(array_map('trim', explode(',', (string) $p->languages)))),
            'specialties' => array_values(array_filter(array_map('trim', explode(',', (string) $p->specialties)))),
            'experience_years' => $p->experience_years, 'photo_url' => $p->photo_path ? Storage::disk('uploads')->url($p->photo_path) : null];
    }

    public static function mine(User $u): ?object
    {
        return DB::table('practitioners')->where('user_id', $u->id)->first();
    }

    /** First visit of an invited practitioner creates an empty draft profile. */
    public function ensureProfile(User $u): object
    {
        abort_unless($u->hasRole('practitioner'), 403);
        DB::table('practitioners')->insertOrIgnore(['ref' => (string) Str::ulid(), 'user_id' => $u->id, 'display_name' => $u->name, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        return self::mine($u);
    }

    /** Practitioner edits their own profile. Any change to an approved profile sends it back for review. */
    public function saveProfile(object $p, User $u, array $d, ?UploadedFile $photo, bool $submit): void
    {
        abort_unless($p->user_id === $u->id, 404);
        if ($p->status === 'suspended') {
            throw ValidationException::withMessages(['display_name' => 'Your profile is suspended. Please contact the office.']);
        }
        $row = ['display_name' => $d['display_name'], 'bio' => $d['bio'], 'languages' => $d['languages'] ?? null, 'specialties' => $d['specialties'] ?? null,
            'experience_years' => $d['experience_years'] ?? null, 'updated_at' => now()];
        if ($photo) {
            $row['photo_path'] = SafeImage::store($photo, 'uploads', 'practitioners', 'photo');
        }
        $public = ['display_name', 'bio', 'languages', 'specialties', 'experience_years'];
        $changed = $photo || collect($public)->contains(fn ($k) => (string) $p->$k !== (string) ($row[$k] ?? ''));
        if ($submit || ($p->status === 'approved' && $changed)) {
            $row['status'] = 'submitted';
            $row['approved_by'] = null;
            $row['approved_at'] = null;
        }
        DB::table('practitioners')->where('id', $p->id)->update($row);
        $this->audit->record('practitioner.profile_saved', null, ['practitioner' => $p->ref, 'submitted' => isset($row['status'])], $u);
    }

    public function savePayoutDetails(object $p, User $u, array $d): void
    {
        abort_unless($p->user_id === $u->id, 404);
        $details = $d['method'] === 'upi' ? ['method' => 'upi', 'upi' => strtolower($d['upi'])] : ['method' => 'bank', 'account_name' => $d['account_name'], 'account_number' => $d['account_number'], 'ifsc' => strtoupper($d['ifsc'])];
        DB::table('practitioners')->where('id', $p->id)->update(['payout_details' => Crypt::encryptString(json_encode($details)), 'updated_at' => now()]);
        // Payout destination changes are a fraud signal: always audited, and shown to finance on the next payout.
        $this->audit->record('practitioner.payout_details_changed', null, ['practitioner' => $p->ref, 'method' => $details['method']], $u);
    }

    public static function payoutDetails(object $p): ?array
    {
        return $p->payout_details ? json_decode(Crypt::decryptString($p->payout_details), true) : null;
    }

    public static function maskedPayout(?array $d): string
    {
        if (! $d) {
            return 'Not given';
        }

        return $d['method'] === 'upi' ? 'UPI '.preg_replace('/^(.{2}).*(@.*)$/', '$1•••$2', $d['upi']) : 'Bank ••••'.substr($d['account_number'], -4).' · '.$d['ifsc'];
    }

    public function review(object $p, string $decision, string $tier, User $by): void
    {
        if ($p->user_id === $by->id) {
            throw ValidationException::withMessages(['decision' => 'You cannot approve your own profile.']);
        }
        if ($decision === 'approve' && $p->status !== 'submitted') {
            throw ValidationException::withMessages(['decision' => 'Only submitted profiles can be approved.']);
        }
        $status = ['approve' => 'approved', 'return' => 'draft', 'suspend' => 'suspended'][$decision];
        DB::table('practitioners')->where('id', $p->id)->update(['status' => $status, 'tier' => $tier, 'approved_by' => $status === 'approved' ? $by->id : null,
            'approved_at' => $status === 'approved' ? now() : null, 'updated_at' => now()]);
        $this->audit->record('practitioner.'.$decision, null, ['practitioner' => $p->ref, 'tier' => $tier], $by);
    }

    public function proposeCommission(object $p, int $shareBps, string $from, ?string $note, User $by): void
    {
        if ($shareBps < 1000 || $shareBps > 9000) {
            throw ValidationException::withMessages(['share' => 'The practitioner share must be between 10% and 90%.']);
        }
        DB::table('commission_rules')->insert(['practitioner_id' => $p->id, 'share_bps' => $shareBps, 'effective_from' => $from, 'status' => 'proposed',
            'proposed_by' => $by->id, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('commission.proposed', null, ['practitioner' => $p->ref, 'share_bps' => $shareBps, 'from' => $from], $by);
    }

    public function decideCommission(int $ruleId, bool $approve, User $by): void
    {
        DB::transaction(function () use ($ruleId, $approve, $by) {
            $r = DB::table('commission_rules')->where('id', $ruleId)->lockForUpdate()->first();
            if (! $r || $r->status !== 'proposed') {
                throw ValidationException::withMessages(['decision' => 'This commission is not awaiting a decision.']);
            }
            if ((int) $r->proposed_by === $by->id || DB::table('practitioners')->where('id', $r->practitioner_id)->value('user_id') === $by->id) {
                throw ValidationException::withMessages(['decision' => 'A different person must approve this commission.']);
            }
            DB::table('commission_rules')->where('id', $r->id)->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'updated_at' => now()]);
        });
        $this->audit->record($approve ? 'commission.approved' : 'commission.rejected', null, ['rule' => $ruleId], $by);
    }
}
