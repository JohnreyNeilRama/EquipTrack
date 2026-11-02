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

];
