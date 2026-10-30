<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Slots;
use App\Domain\Meetings\LiveKit;
use App\Domain\Palmistry\PalmReadings;
use App\Domain\Puja\PujaBookings;
use App\Domain\Reports\ReportJobs;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FileLinkController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** App: the customer's bookings, palmistry readings, reports and puja bookings (own records only). */
class ActivityController extends Controller
{
    private function own(string $table, string $ref, Request $r): object
    {
        $row = DB::table($table)->where('ref', $ref)->where('user_id', $r->user()->id)->first();
        abort_unless($row, 404);

        return $row;
    }

    private static function sections(array $defs, ?string $json): array
    {
        $s = $json ? json_decode($json, true) : [];

        return collect($defs)->map(fn ($title, $k) => ['title' => $title, 'body' => (string) ($s[$k] ?? '')])->filter(fn ($x) => $x['body'] !== '')->values()->all();
    }

    // ---------- bookings ----------
    private function booking(object $b): array
    {
        $start = CarbonImmutable::parse($b->start_utc, 'UTC');
        $changeable = $b->status === 'confirmed' && $start->subHours(config('booking.change_hours'))->isFuture();
        $p = $b->practitioner_id ? DB::table('practitioners')->find($b->practitioner_id) : null;
        $v = DB::table('offering_variants as v')->join('offering_translations as t', fn ($j) => $j->on('t.offering_id', '=', 'v.offering_id')->where('t.locale', 'en'))->where('v.id', $b->variant_id)->first(['v.ref', 'v.name', 't.title']);

        return ['ref' => $b->ref, 'title' => $v->title ?? 'Consultation', 'variant' => $v->ref ?? null, 'variant_name' => $v->name ?? null, 'mode' => $b->mode,
            'start_utc' => $start->toIso8601ZuluString(), 'end_utc' => CarbonImmutable::parse($b->end_utc, 'UTC')->toIso8601ZuluString(), 'status' => $b->status,
            'meeting_link' => $b->status === 'confirmed' && $b->meeting_provider !== 'livekit' ? $b->meeting_link : null,
            'meeting_provider' => $b->status === 'confirmed' ? $b->meeting_provider : null,
            'call_open' => $b->status === 'confirmed' && now()->between($start->subMinutes(LiveKit::EARLY_MINUTES), CarbonImmutable::parse($b->end_utc, 'UTC')->addMinutes(LiveKit::LATE_MINUTES)), 'practitioner' => $p?->display_name, 'practitioner_ref' => $p?->ref,
            'can_cancel' => $changeable, 'can_reschedule' => $changeable && $b->reschedules < config('booking.max_reschedules')];
    }

    public function bookings(Request $r): JsonResponse
    {
        return response()->json(['data' => DB::table('bookings')->where('user_id', $r->user()->id)->whereIn('status', ['confirmed', 'completed', 'cancelled', 'no_show'])
            ->orderByDesc('start_utc')->limit(100)->get()->map(fn ($b) => $this->booking($b))]);
    }

    public function cancelBooking(Request $r, string $ref, Slots $slots): JsonResponse
    {
        $b = $this->own('bookings', $ref, $r);
        $slots->cancelByCustomer($b, $r->user());

        return response()->json(['data' => $this->booking(DB::table('bookings')->find($b->id))]);
    }

    public function rescheduleBooking(Request $r, string $ref, Slots $slots): JsonResponse
    {
        $b = $this->own('bookings', $ref, $r);
        $d = $r->validate(['start' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}Z$/']]);
        $slots->reschedule($b, $r->user(), $d['start']);

        return response()->json(['data' => $this->booking(DB::table('bookings')->find($b->id))]);
    }

    // ---------- palmistry ----------
    private function palm(object $p): array
    {
        return ['ref' => $p->ref, 'status' => $p->status, 'staff_note' => $p->status === 'needs_new_photos' ? $p->staff_note : null,
            'can_upload' => in_array($p->status, ['awaiting_photos', 'needs_new_photos'], true),
            'sections' => $p->status === 'completed' ? self::sections(PalmReadings::SECTIONS, $p->report) : [],
            'completed_at' => CommerceController::iso($p->completed_at),
            'pdf_url' => $p->status === 'completed' ? FileLinkController::url('palm', $p->ref, (int) $p->user_id) : null];
    }

    public function palms(Request $r): JsonResponse
    {
        return response()->json(['data' => DB::table('palm_readings')->where('user_id', $r->user()->id)->orderByDesc('id')->get()->map(fn ($p) => $this->palm($p))]);
    }

    public function palmShow(Request $r, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->palm($this->own('palm_readings', $ref, $r))]);
    }

    public function palmUpload(Request $r, string $ref, PalmReadings $palm): JsonResponse
    {
        $p = $this->own('palm_readings', $ref, $r);
        $d = $r->validate([
            'left' => ['required', 'file', 'max:8192'], 'right' => ['required', 'file', 'max:8192'], 'dominant_hand' => ['required', Rule::in(['left', 'right'])],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'], 'gender' => ['nullable', Rule::in(['male', 'female', 'other'])], 'language' => ['nullable', Rule::in(['en', 'hi'])],
            'question' => ['nullable', 'string', 'max:1000'], 'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please agree to the use of your palm photos for this report.']);
        $palm->submitPhotos($p, $r->user(), $r->file('left'), $r->file('right'), $d);

        return response()->json(['data' => $this->palm(DB::table('palm_readings')->find($p->id))]);
    }

    // ---------- reports ----------
    private function report(object $j): array
    {
        $title = DB::table('offerings as o')->join('offering_translations as t', fn ($x) => $x->on('t.offering_id', '=', 'o.id')->where('t.locale', 'en'))->where('o.slug', $j->offering_slug)->value('t.title');

        return ['ref' => $j->ref, 'title' => $title, 'status' => $j->status, 'needs_two_profiles' => ReportJobs::needsTwo($j->offering_slug),
            'sections' => $j->status === 'completed' ? self::sections(ReportJobs::sections($j->offering_slug), $j->sections) : [], 'completed_at' => CommerceController::iso($j->completed_at),
            'pdf_url' => $j->status === 'completed' ? FileLinkController::url('report', $j->ref, (int) $j->user_id) : null];
    }

    public function reports(Request $r): JsonResponse
    {
        return response()->json(['data' => DB::table('report_jobs')->where('user_id', $r->user()->id)->orderByDesc('id')->get()->map(fn ($j) => $this->report($j))]);
    }

    public function reportShow(Request $r, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->report($this->own('report_jobs', $ref, $r))]);
    }

    public function reportDetails(Request $r, string $ref, ReportJobs $jobs): JsonResponse
    {
        $j = $this->own('report_jobs', $ref, $r);
        $two = ReportJobs::needsTwo($j->offering_slug);
        $d = $r->validate(['profile_a' => ['required', 'string', 'size:26'], 'profile_b' => [$two ? 'required' : 'nullable', 'string', 'size:26', 'different:profile_a'],
            'language' => ['required', Rule::in(['en', 'hi'])], 'question' => ['nullable', 'string', 'max:2000']]);
        $jobs->submitDetails($j, $r->user(), array_values(array_filter([$d['profile_a'], $two ? $d['profile_b'] : null])), $d['language'], $d['question'] ?? null);

        return response()->json(['data' => $this->report(DB::table('report_jobs')->find($j->id))]);
    }

    // ---------- puja ----------
    private function puja(object $b): array
    {
        $ev = $b->evidence ? json_decode($b->evidence, true) : [];

        return ['ref' => $b->ref, 'title' => DB::table('offering_translations')->where('offering_id', $b->offering_id)->where('locale', 'en')->value('title'), 'status' => $b->status,
            'scheduled_at' => CommerceController::iso($b->scheduled_at), 'live_link' => $b->live_link, 'customer_note' => $b->customer_note,
            'video_url' => $ev['video_url'] ?? null, 'photo_count' => count($ev['photos'] ?? []),
            'photo_urls' => array_map(fn ($i) => FileLinkController::url('puja-photo', $b->ref, (int) $b->user_id, $i), array_keys($ev['photos'] ?? []))];
    }

    public function pujas(Request $r): JsonResponse
    {
        return response()->json(['data' => DB::table('puja_bookings')->where('user_id', $r->user()->id)->orderByDesc('id')->get()->map(fn ($b) => $this->puja($b))]);
    }

    public function pujaShow(Request $r, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->puja($this->own('puja_bookings', $ref, $r))]);
    }

    public function pujaDetails(Request $r, string $ref, PujaBookings $puja): JsonResponse
    {
        $b = $this->own('puja_bookings', $ref, $r);
        $puja->submitSankalp($b, $r->user(), $r->validate(PujaBookings::sankalpRules(), ['phone.regex' => 'Enter a valid mobile number.']));

        return response()->json(['data' => $this->puja(DB::table('puja_bookings')->find($b->id))]);
    }
}
