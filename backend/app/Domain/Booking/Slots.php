<?php

namespace App\Domain\Booking;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Models\OfferingVariant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Consultation slot booking for the principal practitioner.
 * - Availability: weekly rules in office time (Asia/Kolkata) minus leave dates.
 * - Policy (config/booking.php): minimum lead time, 15-minute payment hold, cancel/reschedule
 *   allowed until N hours before start; cancellations refund in full to the wallet.
 * - Double booking is prevented by a UNIQUE index on booking_units.unit_start.
 */
class Slots
{
    public const TZ = 'Asia/Kolkata';
    public const SLOT_MODES = ['audio', 'video', 'in_person', 'chat'];

    public function __construct(private Checkout $checkout, private Ledger $ledger, private AuditLogger $audit) {}

    public static function bookable(OfferingVariant $v): bool
    {
        return in_array($v->mode, self::SLOT_MODES, true) && $v->duration_min > 0;
    }

    /** @return array<string, list<array{start: string, label: string}>> keyed by local date */
    public function available(int $durationMin, int $days = 21): array
    {
        $rules = DB::table('availability_rules')->where('active', true)->get()->groupBy('weekday');
        $leave = DB::table('availability_exceptions')->pluck('date')->map(fn ($d) => substr($d, 0, 10))->flip();
        $taken = DB::table('booking_units')->where('unit_start', '>=', now('UTC')->subDay())->pluck('unit_start')->map(fn ($t) => substr($t, 0, 16))->flip();
        $earliest = CarbonImmutable::now('UTC')->addMinutes(config('booking.lead_minutes'));
        $out = [];
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        for ($d = 0; $d < $days; $d++) {
            $day = $today->addDays($d);
            if (isset($leave[$day->toDateString()])) {
                continue;
            }
            foreach ($rules[$day->dayOfWeek] ?? [] as $rule) {
                $step = (int) $rule->slot_minutes;
                $units = (int) ceil($durationMin / $step);
                $cursor = CarbonImmutable::parse($day->toDateString().' '.$rule->start_local, self::TZ);
                $end = CarbonImmutable::parse($day->toDateString().' '.$rule->end_local, self::TZ);
                while ($cursor->addMinutes($units * $step)->lte($end)) {
                    $utc = $cursor->utc();
                    $free = $utc->gte($earliest);
                    for ($u = 0; $free && $u < $units; $u++) {
                        $free = ! isset($taken[$utc->addMinutes($u * $step)->format('Y-m-d H:i')]);
                    }
                    if ($free) {
                        $out[$day->toDateString()][] = ['start' => $utc->format('Y-m-d\TH:i\Z'), 'label' => $cursor->format('g:i A')];
                    }
                    $cursor = $cursor->addMinutes($step);
                }
            }
        }

        return $out;
    }

    /** Holds the slot and creates the order. Returns [booking, checkout payload]. */
    public function hold(User $user, OfferingVariant $v, string $startIso, ?int $birthProfileId, ?string $topic, string $key): array
    {
        if (! self::bookable($v)) {
            throw ValidationException::withMessages(['start' => 'This option is not booked by time slot.']);
        }
        $start = CarbonImmutable::parse($startIso)->utc();
        $slotsForDay = $this->available((int) $v->duration_min, 31)[$start->setTimezone(self::TZ)->toDateString()] ?? [];
        if (! collect($slotsForDay)->contains('start', $start->format('Y-m-d\TH:i\Z'))) {
            throw ValidationException::withMessages(['start' => 'This time is no longer available. Please choose another slot.']);
        }
        $step = (int) (DB::table('availability_rules')->where('weekday', $start->setTimezone(self::TZ)->dayOfWeek)->value('slot_minutes') ?: 30);
        $units = (int) ceil($v->duration_min / $step);
        try {
            $booking = DB::transaction(function () use ($user, $v, $start, $birthProfileId, $topic, $units, $step) {
                $id = DB::table('bookings')->insertGetId([
                    'ref' => (string) Str::ulid(), 'user_id' => $user->id, 'variant_id' => $v->id, 'start_utc' => $start, 'end_utc' => $start->addMinutes($v->duration_min),
                    'status' => 'held', 'hold_expires_at' => now()->addMinutes(config('booking.hold_minutes')), 'mode' => $v->mode,
                    'birth_profile_id' => $birthProfileId, 'topic' => $topic, 'created_at' => now(), 'updated_at' => now(),
                ]);
                for ($u = 0; $u < $units; $u++) {
                    DB::table('booking_units')->insert(['booking_id' => $id, 'unit_start' => $start->addMinutes($u * $step)]);
                }

                return DB::table('bookings')->find($id);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                throw ValidationException::withMessages(['start' => 'Someone just booked this time. Please choose another slot.']);
            }
            throw $e;
        }
        try {
            $r = $this->checkout->startServiceOrder($user, $v, false, $key, ['booking_ref' => $booking->ref, 'start_utc' => $start->format('Y-m-d\TH:i\Z')]);
        } catch (\Throwable $e) {
            $this->release($booking->id, 'cancelled');
            throw $e;
        }
        DB::table('bookings')->where('id', $booking->id)->update(['order_id' => $r['order']->id]);
        $this->audit->record('booking.held', null, ['booking' => $booking->ref], $user);

        return [DB::table('bookings')->find($booking->id), $r];
    }

    public function onOrderPaid(object $order): void
    {
        DB::table('bookings')->where('order_id', $order->id)->where('status', 'held')->update(['status' => 'confirmed', 'hold_expires_at' => null, 'updated_at' => now()]);
    }

    public function onOrderCancelled(object $order): void
    {
        $b = DB::table('bookings')->where('order_id', $order->id)->where('status', 'held')->first();
        if ($b) {
            $this->release($b->id, 'cancelled');
        }
    }

    /** Scheduled every minute: unpaid holds past their expiry free the slot and cancel the order. */
    public function expireHolds(): int
    {
        $n = 0;
        foreach (DB::table('bookings')->where('status', 'held')->where('hold_expires_at', '<', now())->get() as $b) {
            if ($b->order_id) {
                $this->checkout->cancel(DB::table('orders')->find($b->order_id));
            }
            $this->release($b->id, 'cancelled');
            $n++;
        }

        return $n;
    }

    private function release(int $bookingId, string $status): void
    {
        DB::transaction(function () use ($bookingId, $status) {
            DB::table('booking_units')->where('booking_id', $bookingId)->delete();
            DB::table('bookings')->where('id', $bookingId)->update(['status' => $status, 'updated_at' => now()]);
        });
    }

    private function changeable(object $b): bool
    {
        return $b->status === 'confirmed' && CarbonImmutable::parse($b->start_utc, 'UTC')->subHours(config('booking.change_hours'))->isFuture();
    }

    public function cancelByCustomer(object $b, User $u): void
    {
        abort_unless($b->user_id === $u->id, 404);
        if (! $this->changeable($b)) {
            throw ValidationException::withMessages(['booking' => 'Bookings can be cancelled up to '.config('booking.change_hours').' hours before the start time. Please contact support.']);
        }
        $order = DB::table('orders')->find($b->order_id);
        $this->release($b->id, 'cancelled');
        app(\App\Domain\Payments\Refunds::class)->policyRefundToWallet($order, (int) $order->subtotal_minor - (int) $order->refunded_minor, 'Cancelled by customer within policy');
        $this->audit->record('booking.cancelled', null, ['booking' => $b->ref], $u);
    }

    public function reschedule(object $b, User $u, string $newStartIso): void
    {
        abort_unless($b->user_id === $u->id, 404);
        if (! $this->changeable($b) || $b->reschedules >= config('booking.max_reschedules')) {
            throw ValidationException::withMessages(['start' => 'This booking can no longer be rescheduled online. Please contact support.']);
        }
        $v = OfferingVariant::findOrFail($b->variant_id);
        $start = CarbonImmutable::parse($newStartIso)->utc();
        if (! collect($this->available((int) $v->duration_min, 31)[$start->setTimezone(self::TZ)->toDateString()] ?? [])->contains('start', $start->format('Y-m-d\TH:i\Z'))) {
            throw ValidationException::withMessages(['start' => 'This time is no longer available.']);
        }
        $step = (int) (DB::table('availability_rules')->where('weekday', $start->setTimezone(self::TZ)->dayOfWeek)->value('slot_minutes') ?: 30);
        try {
            DB::transaction(function () use ($b, $start, $v, $step) {
                DB::table('booking_units')->where('booking_id', $b->id)->delete();
                for ($u = 0; $u < (int) ceil($v->duration_min / $step); $u++) {
                    DB::table('booking_units')->insert(['booking_id' => $b->id, 'unit_start' => $start->addMinutes($u * $step)]);
                }
                DB::table('bookings')->where('id', $b->id)->update(['start_utc' => $start, 'end_utc' => $start->addMinutes($v->duration_min), 'reschedules' => $b->reschedules + 1, 'updated_at' => now()]);
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['start' => 'Someone just booked this time. Please choose another slot.']);
        }
        $this->audit->record('booking.rescheduled', null, ['booking' => $b->ref], $u);
    }

    public function setOutcome(object $b, string $status, ?string $link, User $staff): void
    {
        $data = ['updated_at' => now()];
        if ($link !== null) {
            $data['meeting_link'] = $link;
        }
        if (in_array($status, ['completed', 'no_show'], true)) {
            if ($b->status !== 'confirmed') {
                throw ValidationException::withMessages(['status' => 'Only confirmed bookings can be closed.']);
            }
            $data['status'] = $status;
        }
        DB::table('bookings')->where('id', $b->id)->update($data);
        $this->audit->record('booking.updated', null, ['booking' => $b->ref, 'status' => $status], $staff);
    }
}
