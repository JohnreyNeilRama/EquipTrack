@extends('layouts.guest')

@section('title', 'EquipTrack - Waiting for Approval')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

{{-- Self-registered Faculty with a verified email, waiting for their
     department's approval (Auth\PendingApprovalController). --}}
@section('content')
    <div class="login-container">
        @include('partials.auth-hero')

        <main class="login-panel">
            <div class="login-card">
                <div class="login-header">
                    <h1>Waiting for approval</h1>
                    <p>Your email is verified. {{ $department }} will review your Faculty account.</p>
                </div>

                @if (session('status'))
                    <div class="auth-alert is-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <div class="auth-alert is-info" role="note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>We'll email <strong>{{ $email }}</strong> as soon as your account is approved. Then you can sign in and request equipment.</span>
                </div>

                <p class="auth-help">Taking long? Contact your department's laboratory and ask them to approve your account on their Users page.</p>

                <form action="{{ route('logout') }}" method="POST" class="login-form">
                    @csrf
                    <button type="submit" class="login-btn">Log Out</button>
                </form>
            </div>
        </main>
    </div>
@endsection
