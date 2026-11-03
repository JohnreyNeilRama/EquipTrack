<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penalties
    |--------------------------------------------------------------------------
    |
    | EquipTrack only records penalties; payment happens outside the system.
    |
    | penalty_late_per_day: suggested amount per day late, pre-filled when staff
    | record a Late Return penalty (they can always change it). 0 means no
    | suggestion.
    |
    | block_borrowing_with_pending_penalty: students and faculty with a Pending
    | penalty cannot submit new borrow requests until it is settled or waived.
    |
    */

    'penalty_late_per_day' => (float) env('PENALTY_LATE_PER_DAY', 0),

    'block_borrowing_with_pending_penalty' => (bool) env('BLOCK_BORROWING_WITH_PENDING_PENALTY', true),

    /*
    |--------------------------------------------------------------------------
    | Borrowing times
    |--------------------------------------------------------------------------
    |
    | Borrowers choose dates only: Within the Day (returned the same day) or
    | several days. Equipment can be picked up any time during office hours.
    |
    | lab_opens / lab_closes: the equipment office's hours (24-hour HH:MM,
    | Philippine time), shown on the request form.
    |
    | return_deadline: the time on the due date by which equipment must be
    | back; a loan becomes overdue right after it. Defaults to closing time.
    |
    */

    'lab_opens' => env('LAB_OPENS', '07:00'),

    'lab_closes' => env('LAB_CLOSES', '19:00'),

    'return_deadline' => env('RETURN_DEADLINE', env('LAB_CLOSES', '19:00')),

    /*
    |--------------------------------------------------------------------------
    | Office days and booking window
    |--------------------------------------------------------------------------
    |
    | office_days: the days the equipment office is open (comma-separated,
    | Mon … Sun). Borrow and return dates must fall on them, late days count
    | only them, and reminder emails are sent only on them. Requests can still
    | be submitted any day; staff review them on the next office day.
    |
    | max_advance_days: how many days ahead a borrow date may be chosen.
    |
    */

    'office_days' => env('OFFICE_DAYS', 'Mon,Tue,Wed,Thu,Fri,Sat'),

    'max_advance_days' => (int) env('MAX_ADVANCE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Units per request
    |--------------------------------------------------------------------------
    |
    | Expensive equipment (equipment.classification, set by the admin) is lent
    | one unit per request. Other equipment may be requested in several units,
    | up to what the lab can lend; max_units_per_request caps that further
    | (0 = no cap beyond stock).
    |
    */

    'max_units_per_request' => (int) env('MAX_UNITS_PER_REQUEST', 0),

];
