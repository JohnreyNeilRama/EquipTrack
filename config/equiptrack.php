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

];
