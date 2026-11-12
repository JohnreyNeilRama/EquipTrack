{{-- Plain-text version of the email-change alert. Not HTML, so values are
     printed as-is ({!! !!}) to keep characters like ' and & readable. --}}
@php($accountType = $accountType ?? 'Administrator')
@php($helpFrom = $helpFrom ?? 'another EquipTrack administrator')
@php($short = $accountType === 'Administrator' ? 'Admin' : 'account')
Your EquipTrack {{ $short }} email was changed

Hi {!! $name !!},

The email on your EquipTrack {!! $accountType !!} account was changed from {!! $oldEmail !!} to {!! $newEmail !!}.

When: {{ $changedAt }} (Philippine time)
Changed by: {!! $changedBy !!}
From IP address: {{ $ipAddress }}

If you made this change, you don't need to do anything.

If this wasn't you, undo it now. Undoing puts this email back on your account, signs out every device using it, and lets you set a new password with Forgot Password:
{!! $undoUrl !!}

This link works for {{ $days }} days. You can also ask {!! $helpFrom !!} to restore your account.

--
This is an automated security message from EquipTrack. Please don't reply to this email.
