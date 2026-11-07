<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\DepartmentAccount;
use App\Models\LoanManualReminder;
use App\Notifications\LoanReminderNotification;
use App\Support\LoanReminders;
use App\Support\LoanTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The "Remind" button on Equipment Monitoring (Admin and Lab Personnel): sends
 * the borrower a return reminder by hand, on top of the automatic schedule.
 *
 *  - Only for items still out (released, return not submitted).
 *  - At most once per loan per day (Philippine date), whoever sends it.
 *  - The borrower gets it in their bell (UserNotificationController) and by
 *    email when they have an active account with an email address.
 *  - Recorded in loan_manual_reminders and in the Audit Trail.
 */
class ManualLoanReminder
{
    /**
     * @return array{ok: bool, status: int, message: string}
     */
    public function send(BorrowTransaction $loan, Admin|DepartmentAccount $sender): array
    {
        $loan->loadMissing(['request.equipment', 'request.user.student', 'request.user.facultyMember']);
        $user = $loan->request?->user;

        if (!$user || !LoanReminders::isOut($loan)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This item is no longer out on loan (returned, or its return is being confirmed), so there is nothing to remind.',
            ];
        }

        if (self::remindedToday($loan)) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => 'A reminder was already sent for this loan today. You can send another one tomorrow.',
            ];
        }

        $isAdmin = $sender instanceof Admin;

        $reminder = LoanManualReminder::create([
            'transaction_id' => $loan->transaction_id,
            'sent_by_type' => $isAdmin ? 'admin' : 'dept',
            'sent_by_id' => $isAdmin ? $sender->admin_id : $sender->dept_acc_id,
            'sent_at' => now(),
        ]);
        $reminder->setRelation($isAdmin ? 'admin' : 'departmentAccount', $sender);

        $emailed = false;
        $canEmail = filled($user->email) && strcasecmp((string) ($user->status ?: 'Active'), 'Active') === 0;

        if ($canEmail) {
            try {
                $user->notify(new LoanReminderNotification(
                    $loan,
                    LoanReminderNotification::MANUAL,
                    BorrowTransaction::today(),
                    0,
                    $reminder->senderLabel(),
                ));
                $emailed = true;
            } catch (Throwable $e) {
                // The bell notification still reaches them.
                Log::warning('Manual loan reminder email failed', ['transaction_id' => $loan->transaction_id, 'error' => $e->getMessage()]);
            }
        }

        $borrower = $user->fullName() ?: $user->email;
        $equipment = $loan->request?->equipment?->name ?? 'equipment';
        $daysLate = LoanReminders::daysLate($loan);

        AuditLogger::log(
            'Sent return reminder for borrow request #' . $loan->request_id . ' to ' . $borrower,
            'borrow_request:' . $loan->request_id,
            $equipment . ', due ' . LoanTime::dueBy($loan->due_date)
                . ($daysLate > 0 ? ' (' . LoanReminders::daysLabel($daysLate) . ' late)' : '')
                . '. ' . ($emailed ? 'Sent by email and notification.' : 'Sent as a notification (no email).'),
            AuditLogger::actorFor($isAdmin ? 'admin' : 'dept', $sender),
        );

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Reminder sent to ' . $borrower . ($emailed ? ' by email and notification.' : ' as a notification.'),
        ];
    }

    /** Whether anyone already reminded this loan by hand today (Philippine date). */
    public static function remindedToday(BorrowTransaction $loan): bool
    {
        return LoanManualReminder::where('transaction_id', $loan->transaction_id)
            ->where('sent_at', '>=', self::startOfToday())
            ->exists();
    }

    /**
     * IDs of the given loans that were reminded by hand today. If the table
     * has not been migrated yet, the monitoring pages still load (nothing
     * counts as reminded).
     */
    public static function remindedTodayIds(array $transactionIds): array
    {
        if (!$transactionIds) {
            return [];
        }

        try {
            return LoanManualReminder::whereIn('transaction_id', $transactionIds)
                ->where('sent_at', '>=', self::startOfToday())
                ->pluck('transaction_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } catch (QueryException $e) {
            Log::warning('loan_manual_reminders is unavailable (run "php artisan migrate"): ' . $e->getMessage());

            return [];
        }
    }

    /** Midnight today in the Philippines, on the app (UTC) clock. */
    private static function startOfToday(): Carbon
    {
        return Carbon::now(config('app.display_timezone'))->startOfDay()->timezone(config('app.timezone'));
    }
}
