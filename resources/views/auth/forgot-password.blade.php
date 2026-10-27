@extends('layouts.guest')

@section('title', 'EquipTrack - Forgot Password')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

@section('content')
    <div class="login-container">
        <div class="login-panel">
            <div class="login-header">
                <h1>Forgot password?</h1>
                <p>Enter the email address for your EquipTrack account and we'll email you a link to reset your password.</p>
            </div>

            @if (session('status'))
                <div class="auth-alert is-success" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @error('email')
                <div class="auth-alert is-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <form action="{{ route('password.email') }}" method="POST" class="login-form" id="forgotForm">
                @csrf
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', request()->query('email')) }}" autocomplete="email" maxlength="100" required autofocus>
                    <p class="field-hint">Use the email address you sign in with. Admins: use your email, not your username.</p>
                </div>

                <button type="submit" class="login-btn" id="sendLinkBtn">Send Reset Link</button>

                <div class="signup-link">
                    Remembered your password? <a href="{{ route('login') }}">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Show progress and prevent double submits while the email is being sent.
    const forgotForm = document.getElementById('forgotForm');
    const sendLinkBtn = document.getElementById('sendLinkBtn');
    forgotForm.addEventListener('submit', () => {
        if (!forgotForm.checkValidity()) return;
        sendLinkBtn.disabled = true;
        sendLinkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
    });

    // Re-enable the button when the page is restored from the back/forward cache.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            sendLinkBtn.disabled = false;
            sendLinkBtn.textContent = 'Send Reset Link';
        }
    });
</script>
@endpush
