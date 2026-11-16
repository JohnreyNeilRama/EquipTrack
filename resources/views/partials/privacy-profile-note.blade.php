{{-- Profile pages (every role): link to the Privacy Notice, and when this
     account agreed to it (App\Support\PrivacyConsent). --}}
@php
    $privacyAccount = \App\Support\PrivacyConsent::signedIn()[1] ?? null;
    $agreedAt = $privacyAccount?->privacy_accepted_at
        ? \Illuminate\Support\Carbon::parse($privacyAccount->privacy_accepted_at)->timezone(config('app.display_timezone'))
        : null;
@endphp
<p class="profile-privacy-note" style="margin: 24px 0 0; padding-top: 16px; border-top: 1px solid var(--border-color, #e2e8f0); font-size: 13px; color: var(--text-muted, #64748b); display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap;">
    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
    <span>
        See how EquipTrack uses your information:
        <a href="{{ route('privacy') }}" target="_blank" rel="noopener" style="font-weight: 600;">Privacy Notice</a>
        @if ($agreedAt)
            &middot; You agreed on {{ $agreedAt->format('M j, Y') }} (version {{ $privacyAccount->privacy_version }}).
        @endif
    </span>
</p>
