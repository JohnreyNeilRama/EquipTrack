@extends('layouts.guest')

@section('title', 'EquipTrack - Set Your Password')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

{{-- Set Your Password: the page the invite email links to (Auth\AccountSetupController).
     Same look and password rules as Reset Password. --}}
@section('content')
    <div class="login-container">
        @include('partials.auth-hero')

        <main class="login-panel">
            <div class="login-topbar">
                <a href="{{ route('login') }}" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to Login</span>
                </a>
            </div>

            <div class="login-card">
            @if (!$linkValid || session('link_invalid'))
                <div class="login-header">
                    <h1>Link expired</h1>
                    <p>This invite link can't be used anymore.</p>
                </div>

                <div class="auth-alert is-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Invite links work only once and expire after 7 days. Ask your laboratory or the Admin to send a new invite.</span>
                </div>

                <div class="signup-link">
                    Already set your password? <a href="{{ route('login') }}">Log in</a>
                </div>
            @else
                <div class="login-header">
                    <h1>Welcome{{ $name ? ', ' . $name : '' }}!</h1>
                    <p>Choose a password for your EquipTrack account. This also confirms your email address.</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form action="{{ route('account.setup.store') }}" method="POST" class="login-form" id="setupForm" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">

                    <div class="form-group">
                        <label for="accountEmail">Account</label>
                        <input type="text" id="accountEmail" value="{{ $email }}" readonly class="is-readonly" aria-readonly="true">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input-container">
                            <input type="password" id="password" name="password" autocomplete="new-password" required aria-describedby="passwordRules" autofocus>
                            <i class="fa-regular fa-eye toggle-password" data-target="password" role="button" tabindex="0" aria-label="Show password"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <div class="password-input-container">
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required aria-describedby="passwordRules">
                            <i class="fa-regular fa-eye toggle-password" data-target="password_confirmation" role="button" tabindex="0" aria-label="Show password"></i>
                        </div>
                    </div>

                    <ul class="password-rules" id="passwordRules" aria-live="polite">
                        <li data-rule="length"><i class="fa-solid fa-circle-check"></i> At least 8 characters</li>
                        <li data-rule="letter"><i class="fa-solid fa-circle-check"></i> Includes a letter</li>
                        <li data-rule="number"><i class="fa-solid fa-circle-check"></i> Includes a number</li>
                        <li data-rule="match"><i class="fa-solid fa-circle-check"></i> Passwords match</li>
                    </ul>

                    <p class="auth-alert is-error" id="clientError" role="alert" hidden></p>

                    <button type="submit" class="login-btn" id="setupBtn">Set Password</button>
                </form>
            @endif
            </div>
        </main>
    </div>
@endsection

@push('scripts')
<script>
    const setupForm = document.getElementById('setupForm');
    if (setupForm) {
        const password = document.getElementById('password');
        const confirmation = document.getElementById('password_confirmation');
        const rules = document.getElementById('passwordRules');
        const clientError = document.getElementById('clientError');
        const setupBtn = document.getElementById('setupBtn');

        // Same policy as the server (Password::defaults in AppServiceProvider).
        const RULES = {
            length: (pw) => pw.length >= 8,
            letter: (pw) => /[A-Za-z]/.test(pw),
            number: (pw) => /\d/.test(pw),
            match: (pw, confirm) => pw !== '' && pw === confirm,
        };

        function updateRules() {
            rules.querySelectorAll('li').forEach(li => {
                li.classList.toggle('is-met', RULES[li.dataset.rule](password.value, confirmation.value));
            });
            clientError.hidden = true;
        }
        password.addEventListener('input', updateRules);
        confirmation.addEventListener('input', updateRules);

        document.querySelectorAll('.toggle-password').forEach(icon => {
            const toggle = () => {
                const input = document.getElementById(icon.dataset.target);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('fa-eye-slash', show);
                icon.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            };
            icon.addEventListener('click', toggle);
            icon.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
        });

        setupForm.addEventListener('submit', (e) => {
            const pw = password.value;
            let message = '';
            if (!RULES.length(pw) || !RULES.letter(pw) || !RULES.number(pw)) {
                message = 'Your password must be at least 8 characters and include a letter and a number.';
                password.focus();
            } else if (!RULES.match(pw, confirmation.value)) {
                message = 'Password and confirm password do not match.';
                confirmation.focus();
            }
            if (message) {
                e.preventDefault();
                clientError.textContent = message;
                clientError.hidden = false;
                return;
            }
            setupBtn.disabled = true;
            setupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        });

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                setupBtn.disabled = false;
                setupBtn.textContent = 'Set Password';
            }
        });
    }
</script>
@endpush
