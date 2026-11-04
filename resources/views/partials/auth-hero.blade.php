{{-- Branded photo panel beside the Login, Forgot Password and Reset Password forms --}}
<aside class="auth-hero" aria-hidden="true">
    <a href="{{ route('landing') }}" class="auth-hero-brand" tabindex="-1">
        <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack">
    </a>

    <div class="auth-hero-copy">
        <span class="auth-hero-eyebrow">Laboratory Equipment System</span>
        <h2>Borrow, track and return lab equipment in one place.</h2>
        <p>Reserve what you need, follow your requests and get reminded before items are due.</p>

        <ul class="auth-hero-features">
            <li><i class="fa-solid fa-magnifying-glass"></i> Browse equipment and check availability</li>
            <li><i class="fa-solid fa-calendar-check"></i> Request and track reservations</li>
            <li><i class="fa-solid fa-bell"></i> Get reminders before items are due</li>
        </ul>
    </div>
</aside>
