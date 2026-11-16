{{-- The Privacy Notice text (Data Privacy Act of 2012, RA 10173), shared by
     the public page (/privacy) and the one-time "please agree" page.
     Keeping periods come from config, so the notice always matches what
     EquipTrack really does. Change config equiptrack.privacy.version when the
     wording changes in a way people should agree to again. --}}
@php
    $privacy = config('equiptrack.privacy');
    $organization = $privacy['organization'] ?: 'The school that operates this EquipTrack system';
    $orgShort = $privacy['organization'] ?: 'the school';
    $span = function (int $days): string {
        if ($days <= 0) {
            return 'until it is no longer needed';
        }
        if ($days % 365 === 0) {
            $years = intdiv($days, 365);

            return $years . ' ' . ($years === 1 ? 'year' : 'years');
        }

        return $days . ' days';
    };
    $auditKeep = (int) config('equiptrack.retention.audit_keep_days');
    $proofKeep = (int) config('equiptrack.retention.proof_keep_days');
    $backupKeep = (int) config('equiptrack.backup.keep_days');
    $logKeep = (int) config('logging.channels.daily.max_files', 14);
@endphp

<div class="privacy-notice">
    <p class="privacy-meta">Version {{ $privacy['version'] }} &middot; Last updated {{ \Illuminate\Support\Carbon::parse($privacy['updated'])->format('F j, Y') }}</p>

    <p>{{ $organization }} uses EquipTrack to lend laboratory equipment to students and faculty. This notice explains what personal information EquipTrack collects, why, who can see it, how long it is kept, and your rights under the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>.</p>

    <h2>1. What we collect</h2>
    <table class="privacy-table">
        <thead>
            <tr><th scope="col">Who</th><th scope="col">Information</th></tr>
        </thead>
        <tbody>
            <tr>
                <th scope="row">Students</th>
                <td>First and last name, email address, student ID number, year level, home address and department</td>
            </tr>
            <tr>
                <th scope="row">Faculty</th>
                <td>First and last name, email address, faculty ID number, teaching license number, highest educational attainment (when given), home address and department</td>
            </tr>
            <tr>
                <th scope="row">Lab Personnel and Administrators</th>
                <td>Name, email address, employee ID number, role, department, and for Administrators a username</td>
            </tr>
            <tr>
                <th scope="row">Everyone</th>
                <td>
                    Your password (stored only in scrambled form, which nobody can read back),
                    an optional profile photo,
                    your borrowing requests and history (equipment, dates, purpose, instructor),
                    returns and the condition of returned equipment,
                    penalties and the proof you upload to settle them (receipts or photos, reference numbers, amounts paid),
                    the notifications you have read,
                    and a record of your activity in EquipTrack, including sign-in times, the IP address you connected from and your browser type
                </td>
            </tr>
        </tbody>
    </table>

    <h2>2. Why we use it</h2>
    <ul>
        <li>To create and manage your account, confirm your email address and sign you in safely.</li>
        <li>To let you borrow equipment, and to let your department and the Administrator review, release and receive it.</li>
        <li>To send you notices and emails about your requests, items due back, overdue items and penalties.</li>
        <li>To record damage, loss and late returns, and the settlement of penalties, so the school's property is accounted for.</li>
        <li>To keep EquipTrack secure: stopping password guessing, spotting misuse, and keeping an audit trail of who did what.</li>
        <li>To produce reports on how equipment is used.</li>
    </ul>
    <p>We use your information because you agree to it, and because {{ $orgShort }} needs it to lend and account for its equipment. It is <strong>never sold</strong> and never used for advertising.</p>

    <h2>3. Who can see it</h2>
    <ul>
        <li><strong>You</strong> can see your own account, requests, history and penalties.</li>
        <li><strong>Lab Personnel of your department</strong> see the students and faculty of their department, and the requests, loans and penalties for their department's equipment.</li>
        <li><strong>The Administrator</strong> sees all accounts, requests, reports and the audit trail.</li>
        <li><strong>Service providers</strong> that run EquipTrack for {{ $orgShort }}: the web host whose servers store the system, and the email service that delivers its emails. They may use the information only to provide that service.</li>
    </ul>
    <p>We do not share your information with anyone else, unless the law requires it.</p>

    <h2>4. How long we keep it</h2>
    <ul>
        <li><strong>Your account and borrowing history</strong> are kept while you have an account. If you have ever borrowed equipment, your account can be deactivated (you can no longer sign in) but its borrowing records are kept for the school's records and reports. An account that never borrowed anything is removed completely when it is deleted.</li>
        <li><strong>Penalty proof files</strong> (receipts and photos) are deleted {{ $span($proofKeep) }} after the penalty is settled or waived. The record that it was settled stays.</li>
        <li><strong>The audit trail</strong> is kept in EquipTrack for {{ $span($auditKeep) }}. Older entries are moved to a private archive file kept by {{ $orgShort }}.</li>
        <li><strong>Backups</strong> of the whole system are kept for {{ $span($backupKeep) }}, and <strong>error logs</strong> for {{ $span($logKeep) }}.</li>
    </ul>

    <h2>5. How we protect it</h2>
    <ul>
        <li>The site is only reachable through a secure, encrypted connection (HTTPS).</li>
        <li>Passwords are stored scrambled. Repeated wrong passwords lock the account for a while, and Administrators also confirm each sign-in with a code sent to their email.</li>
        <li>Each person sees only what their role needs, and Lab Personnel only their own department's records.</li>
        <li>Penalty proofs are stored privately and shown only to the people allowed to see them.</li>
        <li>Important actions are recorded in an audit trail, and the system is backed up daily.</li>
    </ul>

    <h2>6. Your rights</h2>
    <p>Under the Data Privacy Act, you have the right to:</p>
    <ul>
        <li><strong>be informed</strong> about how your information is used (this notice);</li>
        <li><strong>access</strong> your information and get a copy of it;</li>
        <li><strong>correct</strong> information that is wrong or out of date (you can edit most of it on your Profile page);</li>
        <li><strong>object</strong> to its use, and to have it <strong>deleted or blocked</strong> when it is no longer needed or was used unlawfully;</li>
        <li><strong>receive your information</strong> in a common electronic format (data portability);</li>
        <li><strong>be compensated</strong> for damages caused by its misuse; and</li>
        <li><strong>file a complaint</strong> with the National Privacy Commission (<a href="https://privacy.gov.ph" target="_blank" rel="noopener noreferrer">privacy.gov.ph</a>).</li>
    </ul>
    <p>Some records, such as unsettled penalties or the history of equipment you borrowed, may have to be kept for the school to account for its property, even if you ask for them to be deleted.</p>

    <h2>7. Questions and requests</h2>
    @if ($privacy['contact_email'])
        <p>For questions about this notice or to use any of your rights, contact {{ $privacy['contact_name'] ?: 'the Data Protection Officer' }} at <a href="mailto:{{ $privacy['contact_email'] }}">{{ $privacy['contact_email'] }}</a>.</p>
    @else
        <p>For questions about this notice or to use any of your rights, contact the EquipTrack Administrator through your department's laboratory office. They will pass your request to {{ $orgShort }}'s Data Protection Officer.</p>
    @endif

    <h2>8. Changes to this notice</h2>
    <p>If this notice changes in an important way, its version number goes up and you will be asked to read and agree to it again the next time you sign in.</p>
</div>
