{{-- Account emails for Students and Faculty (HTML): verify your email, invite
     to set a password, Faculty approval decision. Same look as the password
     reset email: tables and inline styles, because email apps ignore <style>
     blocks. Colors follow EquipTrack's theme: sidebar #2A3F63, primary #385585.

     Variables: $title, $preview, $heading, $name, $paragraphs (list of plain
     strings), optional $buttonLabel + $buttonUrl, optional $note (shaded box),
     optional $footnote (small grey line). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F0F2F5; -webkit-text-size-adjust:100%;">
    {{-- Preview text shown next to the subject in the inbox list --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">{{ $preview }}</div>

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
                            <h1 style="margin:0 0 16px; font-size:20px; line-height:28px; font-weight:bold; color:#2A3F63;">{{ $heading }}</h1>
                            <p style="margin:0 0 14px; font-size:15px; line-height:24px;">Hi {{ $name }},</p>
                            @foreach ($paragraphs as $paragraph)
                                <p style="margin:0 0 {{ $loop->last ? '24' : '14' }}px; font-size:15px; line-height:24px;">{{ $paragraph }}</p>
                            @endforeach

                            @if (!empty($buttonUrl))
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 24px;">
                                    <tr>
                                        <td align="center" style="border-radius:10px; background-color:#385585;">
                                            <a href="{{ $buttonUrl }}" target="_blank" style="display:inline-block; padding:14px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:10px;">{{ $buttonLabel }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if (!empty($note))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px; background-color:#EFF4FA; border-radius:10px;">
                                    <tr>
                                        <td style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#2A3F63;">{{ $note }}</td>
                                    </tr>
                                </table>
                            @endif

                            @if (!empty($footnote))
                                <p style="margin:0 0 24px; font-size:14px; line-height:22px; color:#666666;">{{ $footnote }}</p>
                            @endif

                            @if (!empty($buttonUrl))
                                <p style="margin:0 0 6px; font-size:12px; line-height:18px; color:#666666;">If the button doesn't work, copy and paste this link into your browser:</p>
                                <p style="margin:0 0 24px; font-size:12px; line-height:18px; word-break:break-all;">
                                    <a href="{{ $buttonUrl }}" target="_blank" style="color:#385585; text-decoration:underline;">{{ $buttonUrl }}</a>
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px 24px; border-top:1px solid #E2E8F0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94A3B8; text-align:center;">
                            This is an automated message from EquipTrack. Please don't reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
