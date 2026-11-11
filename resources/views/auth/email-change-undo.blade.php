@extends('layouts.guest')

@section('title', 'EquipTrack - Undo Email Change')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
@endpush

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
                @if ($state === 'confirm')
                    <div class="login-header">
                        <h1>Undo the email change?</h1>
                        <p>The email on your Administrator account was changed to <strong>{{ $newEmail }}</strong>.</p>
                    </div>

                    <div class="auth-alert is-error" role="note">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Undoing will put <strong>{{ $oldEmail }}</strong> back on your account, cancel its current password and sign out every device using it.</span>
                    </div>

                    <form action="{{ $action }}" method="POST" class="login-form" id="undoForm">
                        @csrf
                        <button type="submit" class="login-btn" id="undoBtn">Undo the change</button>
                        <div class="signup-link">
                            Made this change yourself? You can close this page.
                        </div>
                    </form>
                @elseif ($state === 'done')
                    <div class="login-header">
                        <h1>Your email is back</h1>
                        <p><strong>{{ $oldEmail }}</strong> is the email on your Administrator account again, and every device using it has been signed out.</p>
                    </div>

                    <div class="auth-alert is-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Now set a new password: use Forgot Password with {{ $oldEmail }}. If the account was deactivated, ask another administrator to activate it first.</span>
                    </div>

                    <a href="{{ route('password.request', ['email' => $oldEmail]) }}" class="login-btn" style="display:flex; align-items:center; justify-content:center; text-decoration:none;">Set a new password</a>
                @else
                    <div class="login-header">
                        <h1>This link can't be used</h1>
                        <p>
                            @if ($state === 'stale')
                                This change was already undone, or the email has been changed again since.
                            @elseif ($state === 'failed')
                                {{ $message }}
                            @else
                                The link has expired or is incomplete. Undo links work for {{ \App\Services\AdminEmailChange::UNDO_DAYS }} days.
                            @endif
                        </p>
                    </div>

                    <div class="auth-alert is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>If someone else controls your account, ask another EquipTrack administrator to restore it.</span>
                    </div>

                    <a href="{{ route('login') }}" class="login-btn" style="display:flex; align-items:center; justify-content:center; text-decoration:none;">Back to Login</a>
                @endif
            </div>
        </main>
    </div>
@endsection

@push('scripts')
<script>
    const undoForm = document.getElementById('undoForm');
    if (undoForm) {
        const undoBtn = document.getElementById('undoBtn');
        undoForm.addEventListener('submit', () => {
            undoBtn.disabled = true;
            undoBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Undoing...';
        });
    }
</script>
@endpush
