@extends('layouts.guest')

@section('title', 'EquipTrack - Login')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

@section('content')
    <div class="login-container">
        @include('partials.auth-hero')

        <main class="login-panel">
            <div class="login-topbar">
                <a href="{{ route('landing') }}" class="back-link" id="backToLanding">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
            </div>

            <div class="login-card">
                <div class="login-header">
                    <h1>Welcome back!</h1>
                    <p>Log in to your EquipTrack account to continue.</p>
                </div>

                @if ($registered)
                    <div class="auth-alert is-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Account created successfully! Please log in below.</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="auth-alert is-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($deactivated)
                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Your account has been deactivated by the Administrator. Please contact support.</span>
                    </div>
                @endif

                @error('email')
                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <form action="/login" method="POST" class="login-form">
                    @csrf
                    <div class="form-group">
                        <label for="email">Email or Username</label>
                        <div class="input-icon">
                            <i class="fa-regular fa-envelope field-icon"></i>
                            <input type="text" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-icon password-input-container">
                            <i class="fa-solid fa-lock field-icon"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <i class="fa-regular fa-eye toggle-password" id="togglePassword" role="button" tabindex="0" aria-label="Show password"></i>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" value="1" checked>
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="{{ route('password.request') }}" class="forgot-password" id="forgotLink">Forgot password?</a>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <span>Log In</span>
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    </button>

                    <div class="signup-link">
                        Don't have an account? <a href="/register" id="registerLink">Sign up here</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
@endsection

@push('scripts')
<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    if (togglePassword && password) {
        const toggle = function () {
            // toggle the type attribute
            const show = password.getAttribute('type') === 'password';
            password.setAttribute('type', show ? 'text' : 'password');
            // toggle the eye / eye-slash icon
            togglePassword.classList.toggle('fa-eye-slash', show);
            togglePassword.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        };

        togglePassword.addEventListener('click', toggle);
        togglePassword.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    }

    // Show progress and block double submits while signing in
    const loginForm = document.querySelector('.login-form');
    const loginBtn = document.querySelector('#loginBtn');
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function () {
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Logging in...</span>';
        });
    }

    // Smooth transition to the Landing, Registration and Forgot Password pages
    ['backToLanding', 'registerLink', 'forgotLink'].map(id => document.getElementById(id)).filter(Boolean).forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const panel = document.querySelector('.login-panel');
            if (panel) {
                panel.style.animation = 'slideOutRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            }

            setTimeout(() => {
                window.location.href = this.href;
            }, 400);
        });
    });

    // Fix missing panel and stuck button when using browser back button (bfcache restore)
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            const panel = document.querySelector('.login-panel');
            if (panel) {
                panel.style.animation = ''; // Reset to default CSS entrance animation
            }
            if (loginBtn) {
                loginBtn.disabled = false;
                loginBtn.innerHTML = '<span>Log In</span> <i class="fa-solid fa-arrow-right-to-bracket"></i>';
            }
        }
    });
</script>
@endpush
