@extends('layouts.guest')

@section('title', 'EquipTrack - Login')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}">
@endpush

@section('content')
    <div class="login-container">
        <div class="login-panel">
            <div class="login-header">
                <h1>Welcome back!</h1>
                <p>Please login to your account.</p>
            </div>

            @if ($registered)
                <div style="color: #065f46; background: #d1fae5; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #6ee7b7; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-circle-check" style="color: #059669;"></i> Account created successfully! Please log in below.
                </div>
            @endif

            @if ($deactivated)
                <div style="color: #ef4444; background: #fee2e2; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #fca5a5;">
                    <i class="fa-solid fa-circle-exclamation"></i> Your account has been deactivated by the Administrator. Please contact support.
                </div>
            @endif

            @error('email')
                <div style="color: #ef4444; background: #fee2e2; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #fca5a5;">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ $message }}
                </div>
            @enderror

            <form action="/login" method="POST" class="login-form">
                @csrf
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" value="{{ old('email') }}" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required>
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" value="1" checked>
                        <span class="checkmark"></span>
                        Remember Me
                    </label>
                </div>

                <button type="submit" class="login-btn">Login</button>

                <div class="signup-link">
                    Don't have an account? <a href="/register" id="registerLink">Sign up here</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    if (togglePassword && password) {
        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye / eye-slash icon
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Smooth transition to Registration page
    const registerLink = document.getElementById('registerLink');
    if (registerLink) {
        registerLink.addEventListener('click', function(e) {
            e.preventDefault();
            const panel = document.querySelector('.login-panel');
            if (panel) {
                panel.style.animation = 'slideOutRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            }

            setTimeout(() => {
                window.location.href = this.href;
            }, 400);
        });
    }

    // Fix missing panel when using browser back button (bfcache restore)
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            const panel = document.querySelector('.login-panel');
            if (panel) {
                panel.style.animation = ''; // Reset to default CSS entrance animation
            }
        }
    });
</script>
@endpush
