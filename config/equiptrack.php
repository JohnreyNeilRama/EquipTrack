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
    | block_borrowing_with_overdue_item: students and faculty who still have
    | an overdue item (not returned, or its return was rejected) cannot submit
    | new borrow requests until they return it. A return waiting for the
    | department's confirmation no longer blocks.
    */
    'block_borrowing_with_overdue_item' => (bool) env('BLOCK_BORROWING_WITH_OVERDUE_ITEM', true),

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

    /*
    |--------------------------------------------------------------------------
    | Return reminders (App\Support\LoanReminders)
    |--------------------------------------------------------------------------
    |
    | Every reminder is sent automatically. Emails go out only on office days,
    | during office hours (lab_opens until lab_closes); the notification bells
    | update by themselves. Days late are counted in office days.
    |
    | Borrower (bell + email):
    |   early_reminder_days: a bell reminder this many days before the due
    |     date, for loans of at least long_loan_days days.
    |   The day before the due date, and on the due date itself: always
    |     (one email, on the first of those two days the borrower has it).
    |   same_day_hours_before: Within the Day loans also get a reminder this
    |     many hours before the return deadline, unless they were released
    |     less than same_day_min_lead_hours before it.
    |   overdue_steps / overdue_repeat_days: overdue reminders on these days
    |     late, then every overdue_repeat_days days until the item is returned.
    |
    | Lab Personnel (bell): a "due back today" summary each due date, an item
    |   per loan from the moment it is overdue, flagged again on
    |   department_follow_up_days.
    |
    | Admin (bell): loans still overdue after admin_escalation_days, and one
    |   daily summary of Expensive requests waiting more than
    |   admin_stale_request_days for review and penalty proofs waiting more
    |   than admin_stale_proof_days for verification.
    |
    */

    'reminders' => [
        'early_reminder_days' => (int) env('REMINDER_EARLY_DAYS', 3),
        'long_loan_days' => (int) env('REMINDER_LONG_LOAN_DAYS', 5),
        'same_day_hours_before' => (int) env('REMINDER_SAME_DAY_HOURS_BEFORE', 2),
        'same_day_min_lead_hours' => (int) env('REMINDER_SAME_DAY_MIN_LEAD_HOURS', 3),
        'overdue_steps' => [1, 3, 7],
        'overdue_repeat_days' => (int) env('REMINDER_OVERDUE_REPEAT_DAYS', 7),
        'department_follow_up_days' => (int) env('REMINDER_DEPARTMENT_FOLLOW_UP_DAYS', 3),
        'admin_escalation_days' => (int) env('REMINDER_ADMIN_ESCALATION_DAYS', 7),
        'admin_stale_request_days' => (int) env('REMINDER_ADMIN_STALE_REQUEST_DAYS', 2),
        'admin_stale_proof_days' => (int) env('REMINDER_ADMIN_STALE_PROOF_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups (php artisan equiptrack:backup)
    |--------------------------------------------------------------------------
    |
    | Each backup is a folder named equiptrack-YYYYMMDD-HHMMSS holding
    | database.sql (the whole database) and files/ (uploaded pictures and
    | penalty proofs from storage/app/public and storage/app/private).
    |
    | path: where backups are saved. Point it at another drive, a USB drive or
    |   a cloud-synced folder (Google Drive, OneDrive): a backup kept only on
    |   the same disk is lost together with it.
    | keep_days: backups older than this are deleted after each new backup
    |   (0 = keep everything).
    | time: when the daily backup runs (HH:MM, Philippine time). The computer
    |   must be on and the scheduler running at that time.
    | mysqldump: the mysqldump program. On Laragon it is usually not on the
    |   PATH, e.g. C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
    |
    */

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'time' => env('BACKUP_TIME', '19:30'),
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        // How the database is saved: "auto" uses mysqldump where PHP may start
        // other programs, and PHP itself where it may not (shared hosting such
        // as Hostinger switches that off); "php" always uses PHP.
        'method' => env('BACKUP_METHOD', 'auto'),
    ],

    /*
    | Admin Reports "Export PDF" lists at most this many rows: the PDF is built
    | in memory and a very large one can crash. The totals still count every
    | row; Export CSV and Print have no limit. 0 = no limit.
    */
    'report_pdf_limit' => (int) env('REPORT_PDF_LIMIT', 1000),

    /*
    | Two-step sign-in for Admin accounts: after the right password, a 6-digit
    | code is emailed to the admin and must be entered (App\Support\AdminTwoFactor).
    | Turn it off only if email can't be sent, or no admin could sign in.
    */
    'admin_two_factor' => (bool) env('ADMIN_TWO_FACTOR', true),

    /*
    |--------------------------------------------------------------------------
    | Privacy Notice (/privacy, Data Privacy Act of 2012, RA 10173)
    |--------------------------------------------------------------------------
    |
    | version / updated: raise the version (and the date) whenever the
    |   notice's wording changes in a way people should agree to again: every
    |   account is then asked once more at its next sign-in.
    | organization: the school responsible for the information, as named in
    |   the notice.
    | contact_name / contact_email: the school's Data Protection Officer (or
    |   the office handling privacy requests). When empty, the notice tells
    |   people to ask through the EquipTrack Administrator.
    |
    */

    'privacy' => [
        'version' => 1,
        'updated' => '2026-11-16',
        'organization' => env('PRIVACY_ORGANIZATION', 'University of Cebu, Main Campus'),
        'contact_name' => env('PRIVACY_CONTACT_NAME'),
        'contact_email' => env('PRIVACY_CONTACT_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Keeping old records (php artisan equiptrack:prune-old-records)
    |--------------------------------------------------------------------------
    |
    | So the database and storage don't grow forever, a daily job clears out
    | records nobody needs on screen any more.
    |
    | audit_keep_days: audit trail entries older than this are first saved to
    |   a CSV file in archive_path, then removed from the database. The CSV
    |   opens in Excel and keeps who did what, when and from where.
    |   0 = keep every entry in the database.
    | proof_keep_days: photos and receipts uploaded as penalty proof are
    |   deleted this many days after the penalty was Settled or Waived. The
    |   proof details (type, reference number, amount, who reviewed it) stay.
    |   Pending penalties are never touched. 0 = keep every file.
    | archive_path: where the audit CSV files go. Inside storage/app/private,
    |   so the daily backup copies them and the website never serves them.
    | time: when the job runs (HH:MM, Philippine time); after the backup, so
    |   everything removed is still in that night's backup.
    |
    */

    'retention' => [
        'audit_keep_days' => (int) env('AUDIT_KEEP_DAYS', 730),
        'proof_keep_days' => (int) env('PROOF_KEEP_DAYS', 365),
        'archive_path' => env('ARCHIVE_PATH', storage_path('app/private/archives')),
        'time' => env('PRUNE_TIME', '20:00'),
    ],

];
