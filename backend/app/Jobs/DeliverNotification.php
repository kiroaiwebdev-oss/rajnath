<?php

namespace App\Jobs;

use App\Domain\Messaging\DeliveryLog;
use App\Domain\Notify\Fcm;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Delivers one stored notification by email and push. Each channel is attempted once per job run. */
class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId) {}

    public function handle(Fcm $fcm, DeliveryLog $log): void
    {
        $n = DB::table('user_notifications')->find($this->notificationId);
        $u = $n ? User::find($n->user_id) : null;
        if (! $n || ! $u) {
            return;
        }
        $done = json_decode($n->channels ?? '{}', true) ?: [];
        $text = $n->body.($n->link ? "\n\n".$n->link : '');

        if (! isset($done['email'])) {
            if (! $u->email) {
                $done['email'] = 'no_address';
            } else {
                $started = hrtime(true);
                try {
                    Mail::raw($text, fn ($m) => $m->to($u->email)->subject($n->title));
                    $done['email'] = 'sent';
                    $log->record('email', 'smtp', 'notice:'.$n->kind, $u->email, true, null, null, $started);
                } catch (Throwable $e) {
                    $done['email'] = 'failed';
                    $log->record('email', 'smtp', 'notice:'.$n->kind, $u->email, false, null, mb_substr($e->getMessage(), 0, 200), $started);
                }
            }
        }

        if (! isset($done['push'])) {
            $tokens = DB::table('push_tokens')->where('user_id', $u->id)->whereNull('failed_at')->get();
            if (! $fcm->configured()) {
                $done['push'] = 'not_configured';
            } elseif ($tokens->isEmpty()) {
                $done['push'] = 'no_device';
            } else {
                $sent = 0;
                foreach ($tokens as $t) {
                    try {
                        $r = $fcm->send($t->token, $n->title, $n->body, ['kind' => $n->kind, 'ref' => (string) $n->ref, 'link' => (string) $n->link, 'notification_id' => (string) $n->id]);
                    } catch (Throwable $e) {
                        report($e);
                        $r = 'error:exception';
                    }
                    if ($r === 'sent') {
                        $sent++;
                    } elseif ($r === 'invalid_token') {
                        DB::table('push_tokens')->where('id', $t->id)->delete();
                    }
                }
                $done['push'] = $sent ? 'sent:'.$sent : 'failed';
            }
        }
        DB::table('user_notifications')->where('id', $n->id)->update(['channels' => json_encode($done), 'updated_at' => now()]);
    }
}
