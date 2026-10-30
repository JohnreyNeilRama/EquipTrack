{{-- Due-soon / overdue reminder email (HTML). Same layout and colors as the
     password reset email: tables and inline styles, because email apps ignore
     <style> blocks and modern CSS. --}}
@php
    $accent = $overdue ? '#DC2626' : '#385585';
    $noteBg = $overdue ? '#FEF2F2' : '#EFF4FA';
    $noteText = $overdue ? '#991B1B' : '#2A3F63';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $overdue ? 'Overdue equipment' : 'Equipment due soon' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F0F2F5; -webkit-text-size-adjust:100%;">
    {{-- Preview text shown next to the subject in the inbox list --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        {{ $equipment }} is {{ $when }}. {{ $overdue ? 'Please return it as soon as possible.' : 'Please return it on time.' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F0F2F5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; overflow:hidden;">
                    {{-- Header --}}
                    <tr>
                        <td align="center" style="background-color:#2A3F63; padding:26px 24px;">
                            <img src="{{ asset('images/EquipTrack_logo.png') }}" width="190" alt="EquipTrack" style="display:block; width:190px; max-width:100%; height:auto; border:0; color:#FFFFFF; font-family:Arial, Helvetica, sans-serif; font-size:22px; font-weight:bold;">
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 32px 8px; font-family:Arial, Helvetica, sans-serif; color:#333333;">
                            <h1 style="margin:0 0 16px; font-size:20px; line-height:28px; font-weight:bold; color:{{ $accent }};">
                                {{ $overdue ? 'Your borrowed equipment is overdue' : 'Your borrowed equipment is due soon' }}
                            </h1>
                            <p style="margin:0 0 14px; font-size:15px; line-height:24px;">Hi {{ $name }},</p>
                            <p style="margin:0 0 20px; font-size:15px; line-height:24px;">
                                @if ($overdue)
                                    <strong>{{ $equipment }}</strong> was due on <strong>{{ $dueDate }}</strong> and is now
                                    <strong>{{ $when }}</strong>. Please return it to the laboratory as soon as possible.
                                @else
                                    This is a reminder that <strong>{{ $equipment }}</strong> is <strong>{{ $when }}</strong>
                                    (<strong>{{ $dueDate }}</strong>). Please return it to the laboratory on time.
                                @endif
                            </p>

                            {{-- Loan details --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px; border:1px solid #E2E8F0; border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; line-height:20px; color:#64748B; width:40%; border-bottom:1px solid #E2E8F0;">Equipment</td>
                                    <td style="padding:12px 16px; font-size:14px; line-height:20px; color:#333333; font-weight:bold; border-bottom:1px solid #E2E8F0;">{{ $equipment }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; line-height:20px; color:#64748B; border-bottom:1px solid #E2E8F0;">Borrowed on</td>
                                    <td style="padding:12px 16px; font-size:14px; line-height:20px; color:#333333; border-bottom:1px solid #E2E8F0;">{{ $borrowDate }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; line-height:20px; color:#64748B;">Due date</td>
                                    <td style="padding:12px 16px; font-size:14px; line-height:20px; color:{{ $accent }}; font-weight:bold;">{{ $dueDate }}{{ $overdue ? ' (' . $when . ')' : '' }}</td>
                                </tr>
                            </table>

                            {{-- Button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" style="border-radius:10px; background-color:#385585;">
                                        <a href="{{ $returnsUrl }}" target="_blank" style="display:inline-block; padding:14px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:10px;">View My Borrowed Items</a>
                                    </td>
                                </tr>
                            </table>

                            {{-- What to do --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px; background-color:{{ $noteBg }}; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:{{ $noteText }};">
                                        @if ($overdue)
                                            Late returns may be recorded as a penalty, and a pending penalty stops you from making new borrow requests.
                                            After handing the item back, submit the return on the <strong>Return Item</strong> page so the laboratory can confirm it.
                                        @else
                                            When you hand the item back, submit the return on the <strong>Return Item</strong> page so the laboratory can confirm it.
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px; font-size:14px; line-height:22px; color:#666666;">
                                Already handed it back? Submit the return on the Return Item page. Reminders stop once you have submitted it.
                            </p>

                            {{-- Fallback link --}}
                            <p style="margin:0 0 6px; font-size:12px; line-height:18px; color:#666666;">If the button doesn't work, copy and paste this link into your browser:</p>
                            <p style="margin:0 0 24px; font-size:12px; line-height:18px; word-break:break-all;">
                                <a href="{{ $returnsUrl }}" target="_blank" style="color:#385585; text-decoration:underline;">{{ $returnsUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
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
