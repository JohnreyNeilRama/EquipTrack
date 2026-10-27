{{-- Plain-text version of the password reset email. Not HTML, so values are
     printed as-is ({!! !!}) to keep characters like ' and & readable. --}}
Reset your EquipTrack password

Hi {!! $name !!},

We received a request to reset the password for your EquipTrack {!! $accountType !!} account ({!! $email !!}).

Open this link to choose a new password:
{!! $resetUrl !!}

This link expires in {{ $minutes }} minutes (at {{ $expiresAt }}, Philippine time) and can be used only once.

If you didn't ask to reset your password, you can ignore this email. Your password will stay the same.

--
This is an automated message from EquipTrack. Please don't reply to this email.
