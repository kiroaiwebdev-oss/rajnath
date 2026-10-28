<?php

namespace App\Domain\Reports;

use App\Domain\Astro\KundliService;
use App\Domain\Astro\Matching;
use App\Models\BirthProfile;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Expert-prepared personalised reports. After payment the customer chooses birth profile(s);
 * the system calculates the chart(s) (Swiss Ephemeris) and attaches them; Acharya Ji's team writes
 * each report-specific section; the customer reads and prints/saves it as PDF.
 * Reports are labelled "prepared by Acharya Ji's team" — they are not auto-generated text.
 */
class ReportJobs
{
    /** Report-specific sections. Unlisted reports use DEFAULT. */
    public const SECTIONS = [
        'premium-personalized-kundli' => ['personality' => 'Personality and nature', 'career' => 'Career and work', 'finance' => 'Money and wealth', 'marriage' => 'Marriage and relationships', 'health' => 'Health tendencies', 'dasha' => 'Your current and coming dashas', 'doshas' => 'Doshas and yogas', 'remedies' => 'Remedies'],
        'couple-kundli-compatibility-report' => ['guna' => 'Guna milan explained', 'manglik' => 'Manglik assessment for both', 'seventh' => '7th house and Venus', 'navamsa' => 'Navamsa comparison', 'timing' => 'Favourable timing', 'remedies' => 'Remedies and advice'],
        'career-report' => ['tenth' => '10th house and its lord', 'strengths' => 'Strengths for work', 'fields' => 'Suitable fields', 'periods' => 'Strong and weak periods', 'remedies' => 'Remedies'],
        'dhanyog-finance-report' => ['dhana' => 'Dhana yogas in your chart', 'second_eleventh' => '2nd and 11th houses', 'periods' => 'Financial periods ahead', 'cautions' => 'Cautions', 'remedies' => 'Remedies'],
        'love-report' => ['nature' => 'How you love', 'fifth_seventh' => '5th and 7th houses', 'venus' => 'Venus and the Moon', 'timing' => 'Relationship timing', 'advice' => 'Advice'],
        'sade-sati-report' => ['status' => 'Your Sade Sati status', 'phases' => 'Phase-by-phase effects', 'saturn' => 'Saturn in your chart', 'remedies' => 'Remedies'],
        'kaal-sarp-and-manglik-dosh-report' => ['kaal_sarp' => 'Kaal Sarp assessment', 'manglik' => 'Manglik assessment', 'cancellations' => 'Cancellations and severity', 'remedies' => 'Remedies'],
        'varshphal-yearly-report' => ['overview' => 'The year ahead', 'months' => 'Month by month', 'career' => 'Career and money', 'family' => 'Family and health', 'remedies' => 'Remedies'],
        'ask-one-question' => ['answer' => 'Answer to your question', 'reasoning' => 'What the chart shows', 'advice' => 'Advice'],
    ];
    public const DEFAULT = ['overview' => 'Overview', 'analysis' => 'Detailed analysis', 'timing' => 'Timing', 'remedies' => 'Remedies and guidance'];

    public function __construct(private KundliService $kundli, private AuditLogger $audit) {}

    public static function sections(string $slug): array
    {
        return self::SECTIONS[$slug] ?? self::DEFAULT;
    }

    public static function needsTwo(string $slug): bool
    {
        return (bool) preg_match('/couple|compatib|love-compatibility/', $slug);
    }

    public function onOrderPaid(object $order, array $snap): void
    {
        DB::table('report_jobs')->insertOrIgnore([
            'ref' => (string) Str::ulid(), 'user_id' => $order->user_id, 'order_id' => $order->id, 'offering_slug' => $snap['offering']['slug'],
            'status' => 'awaiting_details', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function submitDetails(object $job, User $u, array $profileRefs, string $language, ?string $question): void
    {
        abort_unless($job->user_id === $u->id, 404);
        if ($job->status !== 'awaiting_details') {
            throw ValidationException::withMessages(['profile_a' => 'Details were already submitted.']);
        }
        $profiles = [];
        foreach ($profileRefs as $ref) {
            $p = BirthProfile::where('user_id', $u->id)->where('ref', $ref)->first();
            if (! $p) {
                throw ValidationException::withMessages(['profile_a' => 'Choose your saved birth profile.']);
            }
            if (! $p->birth_utc) {
                throw ValidationException::withMessages(['profile_a' => $p->name.': a birth time is needed for this report. Edit the profile and add the time.']);
            }
            $profiles[] = $p;
        }
        $charts = [];
        foreach ($profiles as $p) {
            $charts[] = $this->kundli->chart(\DateTimeImmutable::createFromMutable($p->birth_utc->toDateTime()), $p->lat, $p->lng, $p->tz_name);
        }
        $extra = [];
        if (count($charts) === 2) {
            $pick = fn ($c) => ['nak' => $c['nakshatra']['index'], 'sign' => $c['moon_sign']['sign'], 'moon_lon' => $c['planets']['moon']['lon']];
            $extra['matching'] = Matching::ashtakoota($pick($charts[0]), $pick($charts[1]));
        }
        DB::table('report_jobs')->where('id', $job->id)->update([
            'subjects' => json_encode(array_map(fn ($p) => $p->toApi(), $profiles), JSON_UNESCAPED_UNICODE),
            'chart' => json_encode(['charts' => $charts] + $extra, JSON_UNESCAPED_UNICODE),
            'language' => $language, 'question' => $question, 'status' => 'in_preparation', 'updated_at' => now(),
        ]);
        $this->audit->record('report.details_submitted', null, ['job' => $job->ref], $u);
    }

    public function save(object $job, array $sections, bool $complete, User $staff): void
    {
        if ($job->status !== 'in_preparation') {
            throw ValidationException::withMessages(['sections' => 'This report is not open for writing.']);
        }
        $defs = self::sections($job->offering_slug);
        $clean = [];
        foreach ($defs as $k => $_) {
            $clean[$k] = trim((string) ($sections[$k] ?? ''));
        }
        if ($complete && count(array_filter($clean, fn ($t) => mb_strlen($t) >= 40)) < min(2, count($defs))) {
            throw ValidationException::withMessages(['sections' => 'Write at least two sections (40+ characters each) before publishing.']);
        }
        DB::table('report_jobs')->where('id', $job->id)->update(['sections' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'assigned_to' => $job->assigned_to ?? $staff->id,
            'status' => $complete ? 'completed' : 'in_preparation', 'completed_at' => $complete ? now() : null, 'updated_at' => now()]);
        $this->audit->record($complete ? 'report.completed' : 'report.draft_saved', null, ['job' => $job->ref], $staff);
        if ($complete && ($u = User::find($job->user_id))?->email) {
            try {
                Mail::raw('Your report from '.config('brand')['brand.name'].' is ready: '.route('account.reports.show', $job->ref), fn ($m) => $m->to($u->email)->subject('Your report is ready'));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
