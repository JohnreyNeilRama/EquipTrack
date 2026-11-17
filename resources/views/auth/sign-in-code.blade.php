@extends('layouts.guest')

@section('title', 'EquipTrack - Sign-in Code')

@push('css')
<link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
@endpush

{{-- Second sign-in step for Admin and Lab Personnel accounts: the 6-digit
     code emailed after the right password (LoginController::codeForm,
     App\Support\SignInCode). --}}
@section('content')
    <div class="login-container">
        @include('partials.auth-hero')

        <main class="login-panel">
            <div class="login-card">
                <div class="login-header">
                    <h1>Check your email</h1>
                    <p>We sent a 6-digit sign-in code to <strong>{{ $email }}</strong>. Enter it below to finish signing in.</p>
                </div>

                @if (session('status'))
                    <div class="auth-alert is-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @error('code')
                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <form action="{{ route('login.code.verify') }}" method="POST" class="login-form">
                    @csrf
                    <div class="form-group">
                        <label for="code">Sign-in code</label>
                        <div class="input-icon">
                            <i class="fa-solid fa-key field-icon"></i>
                            {{-- one-time-code lets phones offer the code from the email --}}
                            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7"
                                   autocomplete="one-time-code" placeholder="123456" required autofocus
                                   style="letter-spacing: 0.3em; font-variant-numeric: tabular-nums;">
                        </div>
                    </div>

                    <button type="submit" class="login-btn">Verify and Sign In</button>
                </form>

                <p class="auth-help">The code expires in {{ $minutes }} minutes. Can't find the email? Check your spam folder.</p>

                <div class="signup-link">
                    <form action="{{ route('login.code.resend') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="link-button" id="resendCode" @if ($resendWait > 0) data-wait="{{ $resendWait }}" @endif>Send a new code</button>
                    </form>
                    &middot;
                    <form action="{{ route('login.code.cancel') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="link-button">Use a different account</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
@endsection

@push('scripts')
<script>
    // "Send a new code" is allowed once a minute; show the wait on the link.
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('resendCode');
        let wait = parseInt(button.dataset.wait || '0', 10);
        if (wait <= 0) return;

        const label = button.textContent;
        button.disabled = true;
        const tick = () => {
            if (wait <= 0) {
                button.disabled = false;
                button.textContent = label;
                return;
            }
            button.textContent = `${label} (${wait}s)`;
            wait--;
            setTimeout(tick, 1000);
        };
        tick();
    });
</script>
@endpush
