@extends('layouts.guest')

@section('title', 'EquipTrack - Verify Your Email')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

{{-- "Check your email": signed-in Students & Faculty whose email isn't
     verified yet (Auth\EmailVerificationController::notice). --}}
@section('content')
    <div class="login-container">
        @include('partials.auth-hero')

        <main class="login-panel">
            <div class="login-card">
                <div class="login-header">
                    <h1>Check your email</h1>
                    <p>We sent a verification link to <strong>{{ $email }}</strong>. Open it to start using EquipTrack.</p>
                </div>

                @if (session('status'))
                    <div class="auth-alert is-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if ($faculty)
                    <div class="auth-alert is-info" role="note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>After you verify, your department will review your Faculty account. You can borrow equipment once they approve it.</span>
                    </div>
                @endif

                <p class="auth-help">Can't find it? Check your spam or promotions folder. The link expires in 24 hours.</p>

                <form action="{{ route('verification.send') }}" method="POST" class="login-form">
                    @csrf
                    <button type="submit" class="login-btn">Resend Verification Email</button>
                </form>

                <div class="signup-link">
                    Wrong email address? Sign in and change it on your Profile after verifying, or
                    <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="link-button">log out</button>
                    </form>
                    and register again.
                </div>
            </div>
        </main>
    </div>
@endsection
