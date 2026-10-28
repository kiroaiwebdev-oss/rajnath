<?php

namespace App\Domain\Support;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Customer support tickets with public replies and staff-only internal notes. */
class Tickets
{
    public const CATEGORIES = ['booking' => 'Booking or consultation', 'payment' => 'Payment or refund', 'report' => 'Report', 'puja' => 'Puja / Chadhava', 'order' => 'Gem order / delivery', 'account' => 'Account or privacy', 'other' => 'Something else'];
    public const STATUSES = ['open' => 'Open', 'waiting_customer' => 'Waiting for you', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public function __construct(private AuditLogger $audit) {}

    public function open(User $u, array $d): object
    {
        $orderId = null;
        if (! empty($d['order_ref'])) {
            $orderId = DB::table('orders')->where('ref', $d['order_ref'])->where('user_id', $u->id)->value('id');
            if (! $orderId) {
                throw ValidationException::withMessages(['order_ref' => 'Order not found in your account.']);
            }
        }
        if (DB::table('tickets')->where('user_id', $u->id)->where('created_at', '>', now()->subDay())->count() >= 10) {
            throw ValidationException::withMessages(['subject' => 'Too many new requests today. Please reply on an existing one.']);
        }
        $id = DB::transaction(function () use ($u, $d, $orderId) {
            $id = DB::table('tickets')->insertGetId(['ref' => (string) Str::ulid(), 'user_id' => $u->id, 'category' => $d['category'], 'subject' => $d['subject'],
                'priority' => $d['category'] === 'payment' ? 'high' : 'normal', 'status' => 'open', 'order_id' => $orderId, 'last_customer_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ticket_messages')->insert(['ticket_id' => $id, 'author_id' => $u->id, 'internal' => false, 'body' => $d['body'], 'created_at' => now()]);

            return $id;
        });
        $t = DB::table('tickets')->find($id);
        $this->audit->record('ticket.opened', null, ['ticket' => $t->ref, 'category' => $d['category']], $u);

        return $t;
    }

    public function reply(object $t, User $by, string $body, bool $internal = false, ?string $status = null): void
    {
        $isStaff = $by->isStaff();
        if (! $isStaff && $t->user_id !== $by->id) {
            abort(404);
        }
        if (! $isStaff && $t->status === 'closed') {
            throw ValidationException::withMessages(['body' => 'This request is closed. Please open a new one.']);
        }
        DB::transaction(function () use ($t, $by, $body, $internal, $isStaff, $status) {
            DB::table('ticket_messages')->insert(['ticket_id' => $t->id, 'author_id' => $by->id, 'internal' => $isStaff && $internal, 'body' => $body, 'created_at' => now()]);
            $new = $isStaff ? ($status ?? ($internal ? $t->status : 'waiting_customer')) : 'open';
            DB::table('tickets')->where('id', $t->id)->update(['status' => $new, 'updated_at' => now()] + ($isStaff ? ['assigned_to' => $t->assigned_to ?? $by->id] : ['last_customer_at' => now()]));
        });
        $this->audit->record($internal ? 'ticket.note' : 'ticket.reply', null, ['ticket' => $t->ref], $by);
        if ($isStaff && ! $internal && ($u = User::find($t->user_id))?->email) {
            try {
                Mail::raw("We replied to your request \"{$t->subject}\".\n\n".route('account.support.show', $t->ref), fn ($m) => $m->to($u->email)->subject('Reply to your support request'));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function setStatus(object $t, User $by, string $status): void
    {
        if (! $by->isStaff() && ($t->user_id !== $by->id || ! in_array($status, ['closed', 'open'], true))) {
            abort(404);
        }
        DB::table('tickets')->where('id', $t->id)->update(['status' => $status, 'updated_at' => now()]);
        $this->audit->record('ticket.status', null, ['ticket' => $t->ref, 'status' => $status], $by);
    }
}
