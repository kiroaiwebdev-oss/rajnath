<?php

// Consultation booking policy (shown to customers at checkout). Change here or via env.
return [
    'lead_minutes' => (int) env('BOOKING_LEAD_MINUTES', 180),       // earliest bookable slot from now
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),        // unpaid hold before the slot is freed
    'change_hours' => (int) env('BOOKING_CHANGE_HOURS', 24),        // cancel/reschedule allowed until this many hours before
    'max_reschedules' => (int) env('BOOKING_MAX_RESCHEDULES', 1),
];
