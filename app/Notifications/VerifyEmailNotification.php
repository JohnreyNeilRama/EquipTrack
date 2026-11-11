<?php

namespace App\Notifications;

use App\Models\UserAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Verify your email" for a Student or Faculty member who registered
 * themselves. The link is signed and expires after LINK_MINUTES; opening it
 * confirms the address (Auth\EmailVerificationController::verify), with or
 * without being signed in.
 *
 * Sent right away (not queued), so no queue worker has to be running.
 */
class VerifyEmailNotification extends Notification
{
    public const LINK_MINUTES = 1440;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var UserAccount $notifiable */
        $url = self::verificationUrl($notifiable);
        $faculty = $notifiable->isPendingApproval();

        return (new MailMessage)
            ->subject('Verify your EquipTrack email address')
            ->view(['emails.account-message', 'emails.account-message-text'], [
                'title' => 'Verify your EquipTrack email address',
                'preview' => 'Confirm your email address to start using EquipTrack.',
                'heading' => 'Verify your email address',
                'name' => trim($notifiable->fullName()) ?: 'there',
                'paragraphs' => array_filter([
                    'Thanks for creating an EquipTrack account with ' . $notifiable->email . '. Please confirm that this email address is yours.',
                    $faculty ? 'After that, your department will review your Faculty account. You can borrow equipment once they approve it.' : null,
                ]),
                'buttonLabel' => 'Verify Email Address',
                'buttonUrl' => $url,
                'note' => 'This link expires in 24 hours. If it expires, sign in to EquipTrack and send a new one.',
                'footnote' => "If you didn't create an EquipTrack account, you can ignore this email.",
            ]);
    }

    /** Signed link to /email/verify/{id}/{hash}; the hash ties it to this exact address. */
    public static function verificationUrl(UserAccount $account): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(self::LINK_MINUTES),
            ['id' => $account->getKey(), 'hash' => sha1($account->getEmailForVerification())],
        );
    }
}
