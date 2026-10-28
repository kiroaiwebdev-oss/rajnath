<?php

namespace App\Jobs;

use App\Domain\Booking\Slots;
use App\Domain\Meetings\Meetings;
use App\Domain\Notify\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Creates / moves / removes the meeting of a booking outside the payment transaction. */
class SyncMeeting implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $bookingId, public string $action) {}

    public function handle(Meetings $meetings, Notifier $notifier): void
    {
        $b = DB::table('bookings')->find($this->bookingId);
        if (! $b) {
            return;
        }
        if ($this->action === 'confirm' && $b->status === 'confirmed') {
            $meetings->onConfirmed($b);
            $b = DB::table('bookings')->find($b->id);
            if ($b->meeting_link) {
                $notifier->send((int) $b->user_id, 'booking', 'Your consultation link', 'Join your consultation on '.Slots::when($b).' here: '.$b->meeting_link, $b->ref, route('account.bookings'));
            }
        } elseif ($this->action === 'reschedule' && $b->status === 'confirmed' && $b->meeting_provider) {
            $meetings->onRescheduled($b);
        } elseif ($this->action === 'cancel') {
            $meetings->delete($b);
        }
    }
}
