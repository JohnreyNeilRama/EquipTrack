@extends('layouts.guest')

@section('title', 'EquipTrack - Reset Password')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

@section('content')
    <div class="login-container">
        <div class="login-panel">
            @if (!$linkValid || session('link_invalid'))
                {{-- Expired, already used or wrong link --}}
                <div class="login-header">
                    <h1>Link expired</h1>
                    <p>This password reset link can't be used anymore.</p>
                </div>

                <div class="auth-alert is-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Reset links work only once and expire after 30 minutes. Request a new link to reset your password.</span>
                </div>

                <a href="{{ route('password.request', $email !== '' ? ['email' => $email] : []) }}" class="login-btn login-btn-link">Request a New Link</a>

                <div class="signup-link">
                    Remembered your password? <a href="{{ route('login') }}">Back to Login</a>
                </div>
            @else
                <div class="login-header">
                    <h1>Set a new password</h1>
                    <p>Choose a new password for your EquipTrack account.</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form action="{{ route('password.store') }}" method="POST" class="login-form" id="resetForm" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">

                    <div class="form-group">
                        <label for="accountEmail">Account</label>
                        <input type="text" id="accountEmail" value="{{ $email }}" readonly class="is-readonly" aria-readonly="true">
                    </div>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="password" name="password" autocomplete="new-password" required aria-describedby="passwordRules" autofocus>
                            <i class="fa-regular fa-eye toggle-password" data-target="password" role="button" tabindex="0" aria-label="Show password"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm New Password</label>
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

                    <button type="submit" class="login-btn" id="resetBtn">Reset Password</button>

                    <div class="signup-link">
                        Remembered your password? <a href="{{ route('login') }}">Back to Login</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
        const password = document.getElementById('password');
        const confirmation = document.getElementById('password_confirmation');
        const rules = document.getElementById('passwordRules');
        const clientError = document.getElementById('clientError');
        const resetBtn = document.getElementById('resetBtn');

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

        // Show / hide password (click or keyboard).
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

        resetForm.addEventListener('submit', (e) => {
            const pw = password.value;
            let message = '';
            if (!RULES.length(pw) || !RULES.letter(pw) || !RULES.number(pw)) {
                message = 'Your new password must be at least 8 characters and include a letter and a number.';
                password.focus();
            } else if (!RULES.match(pw, confirmation.value)) {
                message = 'New password and confirm password do not match.';
                confirmation.focus();
            }
            if (message) {
                e.preventDefault();
                clientError.textContent = message;
                clientError.hidden = false;
                return;
            }
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Resetting...';
        });

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                resetBtn.disabled = false;
                resetBtn.textContent = 'Reset Password';
            }
        });
    }
</script>
@endpush
