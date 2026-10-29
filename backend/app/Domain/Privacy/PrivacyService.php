<?php

namespace App\Domain\Privacy;

use App\Models\BirthProfile;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Self-service data export and account deletion.
 *
 * Deletion = anonymisation that actually removes personal data:
 *   - name/email/phone erased; birth profiles, palm photos, puja sankalp/proof, sessions, tokens deleted
 *   - enquiries, tickets and report texts stripped of personal content
 *   - orders, payments, refunds and ledger entries are KEPT (amounts and dates only) because tax and
 *     accounting law requires them; they no longer point to a named person.
 * Blocked while money or services are in progress, so nothing paid is lost.
 * Backups: deleted data disappears from backups as they rotate (14 days, deploy/backup.sh).
 */
class PrivacyService
{
    public function __construct(private AuditLogger $audit) {}

    public function export(User $u): array
    {
        $data = [
            'generated_at' => now()->toIso8601ZuluString(),
            'account' => ['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone_e164, 'locale' => $u->locale, 'created_at' => $u->created_at?->toIso8601ZuluString(), 'notification_prefs' => $u->notification_prefs ? json_decode($u->notification_prefs, true) : null],
            'consents' => DB::table('consents')->where('user_id', $u->id)->get(['type', 'version', 'granted_at', 'withdrawn_at']),
            'birth_profiles' => BirthProfile::where('user_id', $u->id)->get()->map->toApi(),
            'orders' => DB::table('orders')->where('user_id', $u->id)->get(['ref', 'kind', 'status', 'subtotal_minor', 'refunded_minor', 'snapshot', 'created_at', 'paid_at'])
                ->map(fn ($o) => array_merge((array) $o, ['snapshot' => json_decode($o->snapshot, true)])),
            'wallet_balance_minor' => (int) (DB::table('ledger_accounts')->where('code', 'customer_wallet:'.$u->id)->value('balance_minor') ?? 0),
            'bookings' => DB::table('bookings')->where('user_id', $u->id)->get(['ref', 'start_utc', 'end_utc', 'status', 'mode', 'topic']),
            'enquiries' => DB::table('enquiries')->where('user_id', $u->id)->get(['ref', 'name', 'phone_e164', 'email', 'message', 'status', 'created_at']),
            'tickets' => DB::table('tickets')->where('user_id', $u->id)->get()->map(fn ($t) => ['ref' => $t->ref, 'subject' => $t->subject, 'status' => $t->status,
                'messages' => DB::table('ticket_messages')->where('ticket_id', $t->id)->where('internal', false)->get(['body', 'created_at'])]),
            'reports' => DB::table('report_jobs')->where('user_id', $u->id)->get(['ref', 'offering_slug', 'status', 'sections', 'completed_at']),
            'palm_readings' => DB::table('palm_readings')->where('user_id', $u->id)->get(['ref', 'status', 'question', 'report', 'completed_at']),
            'notifications' => DB::table('user_notifications')->where('user_id', $u->id)->orderBy('id')->get(['kind', 'title', 'body', 'created_at', 'read_at']),
            'assistant_conversations' => DB::table('assistant_conversations')->where('user_id', $u->id)->get()->map(fn ($c) => ['ref' => $c->ref, 'channel' => $c->channel, 'created_at' => $c->created_at,
                'messages' => DB::table('assistant_messages')->where('conversation_id', $c->id)->orderBy('id')->get(['role', 'content', 'created_at'])]),
            'app_devices_for_push' => DB::table('push_tokens')->where('user_id', $u->id)->count(),
            'puja_bookings' => DB::table('puja_bookings')->where('user_id', $u->id)->get()->map(fn ($b) => ['ref' => $b->ref, 'status' => $b->status, 'sankalp' => $b->sankalp ? json_decode(Crypt::decryptString($b->sankalp), true) : null, 'scheduled_at' => $b->scheduled_at]),
        ];
        DB::table('privacy_requests')->insert(['ref' => (string) Str::ulid(), 'user_id' => $u->id, 'type' => 'export', 'status' => 'completed', 'case_status' => 'closed', 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('privacy.exported', $u, [], $u);

        return $data;
    }

    /** @return list<string> reasons deletion is not possible yet */
    public function blockers(User $u): array
    {
        $r = [];
        if ($u->privacy_hold_reason) {
            $r[] = 'Your account is on hold for a legal or accounting reason. Our privacy team will contact you.';
        }
        if (DB::table('orders')->where('user_id', $u->id)->where('status', 'pending_payment')->exists()) {
            $r[] = 'You have an unpaid order. Pay or cancel it first.';
        }
        if (DB::table('bookings')->where('user_id', $u->id)->whereIn('status', ['held', 'confirmed'])->where('end_utc', '>', now())->exists()) {
            $r[] = 'You have an upcoming consultation. Cancel it or wait until it is over.';
        }
        if (DB::table('puja_bookings')->where('user_id', $u->id)->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            $r[] = 'A puja booking is still in progress.';
        }
        if (DB::table('report_jobs')->where('user_id', $u->id)->where('status', '!=', 'completed')->exists() || DB::table('palm_readings')->where('user_id', $u->id)->where('status', '!=', 'completed')->exists()) {
            $r[] = 'A report you paid for is still being prepared.';
        }
        if (DB::table('shipments')->join('orders', 'orders.id', '=', 'shipments.order_id')->where('orders.user_id', $u->id)->whereIn('shipments.status', ['awaiting_dispatch', 'dispatched'])->exists()) {
            $r[] = 'A gem order is still being delivered.';
        }
        if ((int) (DB::table('ledger_accounts')->where('code', 'customer_wallet:'.$u->id)->value('balance_minor') ?? 0) > 0) {
            $r[] = 'Your wallet still has money. Use it, or ask support for a refund, before deleting.';
        }
        if (DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id')->where('orders.user_id', $u->id)->whereIn('refunds.status', ['requested', 'approved', 'processing'])->exists()) {
            $r[] = 'A refund is still being processed.';
        }

        return $r;
    }

    /**
     * Deletes (anonymises) the account. If something blocks it, the request is recorded and opens
     * a case in the staff privacy queue (Admin › Privacy requests); a repeat request updates the
     * open case. $by = staff member running the deletion for the customer.
     */
    public function delete(User $u, ?User $by = null): object
    {
        $ref = (string) Str::ulid();
        if ($b = $this->blockers($u)) {
            $open = DB::table('privacy_requests')->where('user_id', $u->id)->where('type', 'delete')->whereIn('case_status', ['open', 'in_progress'])->first();
            if ($open) {
                DB::table('privacy_requests')->where('id', $open->id)->update(['blocked_reason' => mb_substr(implode(' ', $b), 0, 255), 'updated_at' => now()]);

                return DB::table('privacy_requests')->find($open->id);
            }
            DB::table('privacy_requests')->insert(['ref' => $ref, 'user_id' => $u->id, 'type' => 'delete', 'status' => 'blocked', 'case_status' => 'open',
                'blocked_reason' => mb_substr(implode(' ', $b), 0, 255), 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('privacy_requests')->where('ref', $ref)->first();
        }
        $steps = [];
        DB::transaction(function () use ($u, &$steps) {
            $palm = DB::table('palm_readings')->where('user_id', $u->id)->get();
            foreach ($palm as $p) {
                Storage::disk('local')->deleteDirectory('palm/'.$p->ref);
            }
            DB::table('palm_readings')->where('user_id', $u->id)->update(['left_photo' => null, 'right_photo' => null, 'question' => null, 'report' => null, 'staff_note' => null, 'photos_purged_at' => now()]);
            $steps[] = 'palm photos and report text deleted ('.count($palm).')';
            foreach (DB::table('puja_bookings')->where('user_id', $u->id)->get() as $pb) {
                Storage::disk('local')->deleteDirectory('puja/'.$pb->ref);
            }
            DB::table('puja_bookings')->where('user_id', $u->id)->update(['sankalp' => null, 'evidence' => null, 'customer_note' => null, 'live_link' => null]);
            $steps[] = 'puja sankalp and proof deleted';
            $steps[] = 'birth profiles deleted ('.DB::table('birth_profiles')->where('user_id', $u->id)->delete().')';
            DB::table('report_jobs')->where('user_id', $u->id)->update(['subjects' => null, 'chart' => null, 'sections' => null, 'question' => null]);
            DB::table('bookings')->where('user_id', $u->id)->update(['topic' => null, 'birth_profile_id' => null, 'meeting_link' => null]);
            DB::table('enquiries')->where('user_id', $u->id)->update(['name' => 'Deleted', 'phone_e164' => '+0', 'email' => null, 'message' => null, 'birth_date' => null, 'birth_time' => null, 'birth_place' => null, 'staff_note' => null, 'ip' => null]);
            $ticketIds = DB::table('tickets')->where('user_id', $u->id)->pluck('id');
            DB::table('ticket_messages')->whereIn('ticket_id', $ticketIds)->update(['body' => '[deleted at customer request]']);
            DB::table('tickets')->whereIn('id', $ticketIds)->update(['subject' => '[deleted]', 'status' => 'closed']);
            foreach (DB::table('shipments')->join('orders', 'orders.id', '=', 'shipments.order_id')->where('orders.user_id', $u->id)->pluck('shipments.id') as $sid) {
                DB::table('shipments')->where('id', $sid)->update(['address' => Crypt::encryptString(json_encode(['name' => 'Deleted']))]);
            }
            $steps[] = 'enquiries, tickets, report and delivery details anonymised';
            DB::table('assistant_conversations')->where('user_id', $u->id)->delete();
            DB::table('user_notifications')->where('user_id', $u->id)->delete();
            DB::table('push_tokens')->where('user_id', $u->id)->delete();
            $steps[] = 'assistant chats, notifications and push registrations deleted';
            DB::table('otp_challenges')->where('user_id', $u->id)->delete();
            DB::table('sessions')->where('user_id', $u->id)->delete();
            $u->tokens()->delete();
            $steps[] = 'all sessions and app devices signed out';
            $u->forceFill(['name' => 'Deleted user', 'email' => null, 'phone_e164' => null, 'email_verified_at' => null, 'phone_verified_at' => null,
                'notification_prefs' => null, 'status' => 'anonymized', 'remember_token' => null])->save();
            $steps[] = 'name, email and mobile erased';
            $steps[] = 'kept for tax/accounting law: order amounts, dates and payment references (no name or contact)';
        });
        DB::table('privacy_requests')->insert(['ref' => $ref, 'user_id' => null, 'type' => 'delete', 'status' => 'completed', 'case_status' => 'closed', 'closed_at' => now(),
            'steps' => json_encode($steps), 'created_at' => now(), 'updated_at' => now()]);
        // an open case for this person is closed and unlinked (the person no longer exists)
        DB::table('privacy_requests')->where('user_id', $u->id)->whereIn('case_status', ['open', 'in_progress'])
            ->update(['user_id' => null, 'status' => 'completed', 'case_status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);
        $this->audit->record('privacy.deleted', null, ['request' => $ref, 'steps' => count($steps)], $by, $by ? null : 'system');

        return DB::table('privacy_requests')->where('ref', $ref)->first();
    }
}
