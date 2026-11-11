{{-- Email-change alert (HTML), sent to an Administrator's OLD address with an
     undo link. Email apps ignore <style> blocks and modern CSS, so this uses
     tables and inline styles, like the password reset email. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Your EquipTrack Admin email was changed</title>
</head>
<body style="margin:0; padding:0; background-color:#F0F2F5; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        The email on your EquipTrack Admin account was changed. If this wasn't you, undo it within {{ $days }} days.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F0F2F5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#2A3F63; padding:26px 24px;">
                            <img src="{{ asset('images/EquipTrack_logo.png') }}" width="190" alt="EquipTrack" style="display:block; width:190px; max-width:100%; height:auto; border:0; color:#FFFFFF; font-family:Arial, Helvetica, sans-serif; font-size:22px; font-weight:bold;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 8px; font-family:Arial, Helvetica, sans-serif; color:#333333;">
                            <h1 style="margin:0 0 16px; font-size:20px; line-height:28px; font-weight:bold; color:#2A3F63;">Your Admin email was changed</h1>
                            <p style="margin:0 0 14px; font-size:15px; line-height:24px;">Hi {{ $name }},</p>
                            <p style="margin:0 0 20px; font-size:15px; line-height:24px;">
                                The email on your EquipTrack Administrator account was changed from
                                <strong>{{ $oldEmail }}</strong> to <strong>{{ $newEmail }}</strong>.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px; background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:22px; color:#334155;">
                                        <strong>When:</strong> {{ $changedAt }} (Philippine time)<br>
                                        <strong>Changed by:</strong> {{ $changedBy }}<br>
                                        <strong>From IP address:</strong> {{ $ipAddress }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px; font-size:15px; line-height:24px;">
                                <strong>If you made this change,</strong> you don't need to do anything.
                            </p>
                            <p style="margin:0 0 20px; font-size:15px; line-height:24px;">
                                <strong>If this wasn't you,</strong> undo it now. Undoing puts this email back on your account,
                                signs out every device using it, and lets you set a new password with Forgot Password.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" style="border-radius:10px; background-color:#B91C1C;">
                                        <a href="{{ $undoUrl }}" target="_blank" style="display:inline-block; padding:14px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:10px;">This wasn't me: undo this change</a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px; background-color:#EFF4FA; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#2A3F63;">
                                        This link works for <strong>{{ $days }} days</strong>. You can also ask another administrator to restore your account.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 6px; font-size:12px; line-height:18px; color:#666666;">If the button doesn't work, copy and paste this link into your browser:</p>
                            <p style="margin:0 0 24px; font-size:12px; line-height:18px; word-break:break-all;">
                                <a href="{{ $undoUrl }}" target="_blank" style="color:#385585; text-decoration:underline;">{{ $undoUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px 24px; border-top:1px solid #E2E8F0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94A3B8; text-align:center;">
                            This is an automated security message from EquipTrack. Please don't reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
