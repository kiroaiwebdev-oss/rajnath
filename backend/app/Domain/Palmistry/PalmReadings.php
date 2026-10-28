<?php

namespace App\Domain\Palmistry;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Uploads\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paid, expert-prepared palmistry report from photos of both palms.
 *
 * States: awaiting_photos → submitted → in_review → completed
 *                              ↑______ needs_new_photos ______|
 * Photos are stored on the PRIVATE disk (never a public URL), re-encoded without EXIF/GPS, and
 * deleted automatically N days after the report is completed (setting palm.photo_retention_days).
 */
class PalmReadings
{
    public const OFFERING_SLUG = 'palmistry-photo-report';
    public const DISK = 'local';
    public const SECTIONS = [
        'overview' => 'Overview of your hands',
        'life_line' => 'Life line (Jeevan rekha)',
        'head_line' => 'Head line (Mastishk rekha)',
        'heart_line' => 'Heart line (Hriday rekha)',
        'fate_line' => 'Fate line (Bhagya rekha)',
        'sun_line' => 'Sun line (Surya rekha)',
        'mounts' => 'Mounts (Parvat)',
        'marriage' => 'Relationship lines',
        'signs' => 'Special signs',
        'guidance' => 'Guidance and simple remedies',
    ];

    public function __construct(private AuditLogger $audit) {}

    /** Called by Checkout when a palmistry order is paid. Idempotent. */
    public function onOrderPaid(object $order): void
    {
        DB::table('palm_readings')->insertOrIgnore([
            'ref' => (string) Str::ulid(), 'user_id' => $order->user_id, 'order_id' => $order->id, 'status' => 'awaiting_photos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function submitPhotos(object $r, User $u, ?UploadedFile $left, ?UploadedFile $right, array $d): void
    {
        if ($r->user_id !== $u->id) {
            abort(404);
        }
        if (! in_array($r->status, ['awaiting_photos', 'needs_new_photos'], true)) {
            throw ValidationException::withMessages(['left' => 'Photos can no longer be changed for this reading.']);
        }
        if (! $left || ! $right) {
            throw ValidationException::withMessages(['left' => 'Please upload clear photos of both palms.']);
        }
        $dir = 'palm/'.$r->ref;
        $l = SafeImage::store($left, self::DISK, $dir, 'left');
        $rt = SafeImage::store($right, self::DISK, $dir, 'right');
        $old = array_filter([$r->left_photo, $r->right_photo]);
        DB::table('palm_readings')->where('id', $r->id)->update([
            'left_photo' => $l, 'right_photo' => $rt, 'dominant_hand' => $d['dominant_hand'], 'age' => $d['age'] ?? null, 'gender' => $d['gender'] ?? null,
            'question' => $d['question'] ?? null, 'report_language' => $d['language'] ?? 'en', 'status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now(),
        ]);
        Storage::disk(self::DISK)->delete($old);
        $this->audit->record('palm.photos_submitted', null, ['reading' => $r->ref], $u);
    }

    public function claim(object $r, User $staff): void
    {
        DB::table('palm_readings')->where('id', $r->id)->whereIn('status', ['submitted', 'in_review'])->update(['status' => 'in_review', 'assigned_to' => $staff->id, 'updated_at' => now()]);
        $this->audit->record('palm.claimed', null, ['reading' => $r->ref], $staff);
    }

    public function requestNewPhotos(object $r, string $note, User $staff): void
    {
        if (! in_array($r->status, ['submitted', 'in_review'], true)) {
            throw ValidationException::withMessages(['note' => 'Not possible in the current state.']);
        }
        DB::table('palm_readings')->where('id', $r->id)->update(['status' => 'needs_new_photos', 'staff_note' => $note, 'updated_at' => now()]);
        $this->audit->record('palm.new_photos_requested', null, ['reading' => $r->ref], $staff);
        $this->notify($r, 'We need clearer photos of your palms', 'Please upload new photos for your palmistry report. Note from our team: '.$note);
    }

    public function saveReport(object $r, array $sections, bool $complete, User $staff): void
    {
        if (! in_array($r->status, ['submitted', 'in_review'], true)) {
            throw ValidationException::withMessages(['overview' => 'This reading is not open for writing.']);
        }
        $clean = [];
        foreach (self::SECTIONS as $k => $_) {
            $clean[$k] = trim((string) ($sections[$k] ?? ''));
        }
        if ($complete && (mb_strlen($clean['overview']) < 50 || mb_strlen($clean['guidance']) < 30)) {
            throw ValidationException::withMessages(['overview' => 'Write at least the overview and guidance sections before completing.']);
        }
        DB::table('palm_readings')->where('id', $r->id)->update([
            'report' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'status' => $complete ? 'completed' : 'in_review',
            'assigned_to' => $r->assigned_to ?? $staff->id, 'completed_at' => $complete ? now() : null, 'updated_at' => now(),
        ]);
        $this->audit->record($complete ? 'palm.completed' : 'palm.draft_saved', null, ['reading' => $r->ref], $staff);
        if ($complete) {
            $this->notify($r, 'Your palmistry report is ready', 'Your palmistry report from '.config('brand')['brand.name'].' is ready. Sign in to your account to read and download it.');
        }
    }

    /** Scheduled daily: deletes palm photos of completed readings older than the retention period. */
    public function purgeOldPhotos(): int
    {
        $days = (int) (app(\App\Support\Settings\SettingsRepository::class)->get('palm.photo_retention_days') ?? 180);
        $n = 0;
        foreach (DB::table('palm_readings')->where('status', 'completed')->whereNull('photos_purged_at')->where('completed_at', '<', now()->subDays($days))->get() as $r) {
            Storage::disk(self::DISK)->deleteDirectory('palm/'.$r->ref);
            DB::table('palm_readings')->where('id', $r->id)->update(['left_photo' => null, 'right_photo' => null, 'photos_purged_at' => now(), 'updated_at' => now()]);
            $n++;
        }
        if ($n) {
            $this->audit->record('palm.photos_purged', null, ['count' => $n, 'retention_days' => $days], null, 'system');
        }

        return $n;
    }

    private function notify(object $r, string $subject, string $body): void
    {
        $u = User::find($r->user_id);
        if (! $u?->email) {
            return;
        }
        try {
            Mail::raw($body."\n\n".route('account.palm.show', $r->ref), fn ($m) => $m->to($u->email)->subject($subject));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
