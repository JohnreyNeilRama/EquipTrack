# EquipTrack

[![Tests](https://github.com/JohnreyNeilRama/EquipTrack/actions/workflows/tests.yml/badge.svg)](https://github.com/JohnreyNeilRama/EquipTrack/actions/workflows/tests.yml)

EquipTrack is a laboratory equipment borrowing system for a school. Students and teachers reserve equipment online, the owning department approves and hands it over, and the system follows each item until it is returned — with reminders, penalties and a full audit trail.

It is a Laravel 13 application (PHP 8.3+) with server-rendered Blade pages. There is no JavaScript build step: styles, scripts, Chart.js and Font Awesome are plain files under `public/`.

## Who uses it

| Account | Signs in to | What they do |
| --- | --- | --- |
| **User** (Student, Faculty, Personnel) | `/user` | Browse equipment, request to borrow, submit returns, see history, settle penalties |
| **Department Account** (one per department) | `/department` | Manage the department's equipment and users, approve requests, release items, confirm returns, record penalties |
| **Admin** | `/admin` | Manage departments, accounts and equipment, co-approve expensive equipment, reports, audit trail |

Everyone signs in on the same page (`/login`). Students verify their email after signing up. Faculty sign-ups wait for their department to approve them.

## How a loan works

1. A user requests equipment for a date and time range.
2. The department approves or rejects it. Equipment marked **Expensive** also needs the Admin's approval.
3. The department releases the item when the borrower collects it. Stock is held from this point.
4. The borrower submits a return; the department confirms the condition and can record a penalty.
5. Requests nobody acted on expire, and borrowers get email reminders before and after the due time.

## Requirements

- PHP 8.3 or newer, with the usual Laravel extensions
- Composer
- MySQL or MariaDB (SQLite also works and is what the tests use). The backup command uses `mysqldump`.
- A mail server for sign-up, password and reminder emails (locally, a catcher such as Mailpit)

## Setting up locally

```bash
composer install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Edit `.env`: set `APP_NAME=EquipTrack`, `APP_URL`, the `DB_*` values for your database, and the `MAIL_*` values. Then create the tables and the starting data:

```bash
php artisan migrate --seed
```

The seeder creates the first Admin account and prints its generated password **once** — copy it from the terminal. It also adds two starter equipment categories. Departments are not seeded; the Admin adds each department together with its account inside the app.

Uploaded photos need the storage link:

```bash
php artisan storage:link
```

Then open the site. With Laragon it is served at `http://equiptrack.test`; otherwise:

```bash
php artisan serve
```

## Background tasks

Reminder emails and the daily backup run through Laravel's scheduler. Locally:

```bash
php artisan schedule:work
```

On a server, add one cron entry that runs `php artisan schedule:run` every minute.

| Command | What it does | When it runs |
| --- | --- | --- |
| `php artisan equiptrack:send-loan-reminders` | Emails borrowers whose equipment is due soon, due today or overdue. Add `--dry-run` to list them without sending. | Every 15 minutes, office days and hours only |
| `php artisan equiptrack:backup` | Backs up the database and uploaded files | Daily at `BACKUP_TIME` (default 19:30) |
| `php artisan equiptrack:prune-old-records` | Saves audit entries older than `AUDIT_KEEP_DAYS` to a CSV in `storage/app/private/archives`, then removes them; deletes proof files `PROOF_KEEP_DAYS` after their penalty was settled or waived. Add `--dry-run` to only count. | Daily at `PRUNE_TIME` (default 20:00, after the backup) |
| `php artisan equiptrack:check-production` | Checks the live-server settings (debug off, HTTPS, cookies, mail, database, storage) | Run by hand before going live |

Emails are sent immediately, so no queue worker is needed.

## Settings

School rules live in [config/equiptrack.php](config/equiptrack.php), each one explained there and overridable from `.env`. The main ones:

| `.env` key | Meaning | Default |
| --- | --- | --- |
| `LAB_OPENS`, `LAB_CLOSES` | Hours when equipment can be collected and returned | 07:00 – 19:00 |
| `RETURN_DEADLINE` | Latest return time on the due day | same as `LAB_CLOSES` |
| `OFFICE_DAYS` | Days the laboratory is open | Mon–Sat |
| `MAX_ADVANCE_DAYS` | How far ahead a request can be made | 30 |
| `MAX_UNITS_PER_REQUEST` | Most units in one request (0 = no limit) | 0 |
| `PENALTY_LATE_PER_DAY` | Late fee per day (0 = none) | 0 |
| `BLOCK_BORROWING_WITH_PENDING_PENALTY`, `BLOCK_BORROWING_WITH_OVERDUE_ITEM` | Stop new requests until the borrower clears these | on |
| `BACKUP_TIME`, `BACKUP_KEEP_DAYS` | When the backup runs and how long copies are kept | 19:30, 14 days |
| `REPORT_PDF_LIMIT` | Most rows in an Admin Reports PDF (the totals still count every row; CSV has no limit) | 1000 |
| `AUDIT_KEEP_DAYS`, `PROOF_KEEP_DAYS` | How long audit entries stay in the database, and proof files after a penalty closes (0 = forever) | 730, 365 days |
| `APP_DISPLAY_TIMEZONE` | Time zone shown to people | Asia/Manila |

## Tests

```bash
php artisan test
```

The suite covers every role's pages and rules and runs against an in-memory SQLite database, so it never touches your real data.

GitHub also runs it on every push to `main` ([.github/workflows/tests.yml](.github/workflows/tests.yml)): the result shows as a green check or a red X next to each commit, and on the Tests badge above. Update the live site only after the check is green.

## Where things are

| Path | Contents |
| --- | --- |
| `app/Http/Controllers/{Admin,Department,User,Auth}` | One controller per page, grouped by who uses it |
| `app/Services` | Shared rules: availability, penalties, account creation, invites, audit logging |
| `app/Support` | Small helpers: loan times, reminders, images, login lockout, report PDFs |
| `app/Console/Commands` | The `equiptrack:*` commands above |
| `resources/views` | Blade pages, in the same Admin / Department / User / Auth groups |
| `public/css`, `public/js` | Styles and scripts shared by all roles |
| `public/admin`, `public/departments`, `public/user` | Page styles for each role |
| `public/vendor` | Self-hosted Chart.js and Font Awesome |
| `routes/web.php` | All routes; `routes/console.php` holds the schedule |
| `tests/Feature` | The test suite |

## Security notes

- Every response carries protective headers ([SecurityHeaders](app/Http/Middleware/SecurityHeaders.php)); script files only load from EquipTrack itself.
- Lab Personnel only see and change their own department's records. Which department a record belongs to is defined once ([BelongsToDepartment](app/Models/Concerns/BelongsToDepartment.php)); Department pages start their queries from [DepartmentScope](app/Support/DepartmentScope.php), and a test sweeps every Department page and action to check it.
- A Privacy Notice (`/privacy`, Data Privacy Act of 2012) explains what EquipTrack collects and why. Everyone agrees to it when registering or setting up an invited account, and accounts that haven't agreed to the current version are asked once at sign-in. Each agreement is saved (when, which version) and shown in the Audit Trail. Set `PRIVACY_ORGANIZATION` and `PRIVACY_CONTACT_EMAIL` (the school's Data Protection Officer) in `.env`.
- Admins and Lab Personnel sign in in two steps: password, then a 6-digit code emailed to them (valid 10 minutes, 5 tries). Students and Faculty use their password only. Turn it off per role with `ADMIN_TWO_FACTOR=false` or `DEPARTMENT_TWO_FACTOR=false`, only if email stops working.
- Sign-in locks after repeated failures, and sensitive Admin actions ask for the password again.
- Changing an account's email needs the current password and sends an undo link to the old address.
- Every important action is written to the audit trail through one class, [AuditLogger](app/Services/AuditLogger.php). Use it for any new action rather than writing audit rows directly.
- Penalty proof files are stored privately and served only to the people allowed to see them.

Run `php artisan equiptrack:check-production` before putting a server live.
