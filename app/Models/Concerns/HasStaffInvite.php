<?php

namespace App\Models\Concerns;

/**
 * Admin and Department (Lab Personnel) accounts added by an Admin are emailed
 * an invite to set their own password (App\Services\UserInvites). Until they
 * use it the account exists but nobody can sign in to it.
 *
 * Columns: invited_at (last invite sent), email_verified_at (password set
 * from the invite, which confirms the email). Accounts made before invites
 * existed count as set up.
 */
trait HasStaffInvite
{
    public function initializeHasStaffInvite(): void
    {
        $this->mergeFillable(['invited_at', 'email_verified_at']);
        $this->mergeCasts(['invited_at' => 'datetime', 'email_verified_at' => 'datetime']);
    }

    /** Invited, and the password hasn't been set from the invite yet. */
    public function hasPendingInvite(): bool
    {
        return $this->invited_at !== null && $this->email_verified_at === null;
    }

    /** The owner can sign in: not waiting for their invite. */
    public function isSetUp(): bool
    {
        return !$this->hasPendingInvite();
    }
}
