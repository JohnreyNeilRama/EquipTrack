@extends('layouts.guest')

@section('title', 'EquipTrack - Privacy Notice')

@push('css')
<link rel="stylesheet" href="{{ asset('css/privacy.css') }}?v={{ filemtime(public_path('css/privacy.css')) }}">
@endpush

{{-- Shown once to a signed-in account that hasn't agreed to the current
     Privacy Notice (accounts created before it existed, or after a new
     version): App\Http\Middleware\EnsurePrivacyAccepted. --}}
@section('content')
    <div class="privacy-page privacy-accept">
        <article class="privacy-card">
            <header class="privacy-banner">
                <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack">
                <h1>{{ $isUpdate ? 'Our Privacy Notice has changed' : 'Please review our Privacy Notice' }}</h1>
                <p>Hi {{ $name }}, before you continue, please read how EquipTrack uses your personal information.</p>
            </header>

            <div class="privacy-body" tabindex="0" aria-label="Privacy Notice">
                @include('partials.privacy-notice')
            </div>

            <form action="{{ route('privacy.accept.store') }}" method="POST" class="privacy-accept-form">
                @csrf
                <p class="privacy-accept-intro">You need to agree once to keep using EquipTrack.</p>

                <label class="privacy-check" for="agree">
                    <input type="checkbox" id="agree" name="agree" value="1" required>
                    <span>I have read the EquipTrack Privacy Notice and agree to the collection and use of my personal information as described.</span>
                </label>

                @error('agree')
                    <p class="privacy-error" role="alert">{{ $message }}</p>
                @enderror

                <div class="privacy-actions">
                    <button type="submit" class="privacy-btn">I Agree and Continue</button>
                    <button type="submit" form="privacyLogout" class="privacy-link-btn">Not now, sign me out</button>
                </div>
            </form>

            <form id="privacyLogout" action="{{ route('logout') }}" method="POST" hidden>@csrf</form>
        </article>
    </div>
@endsection
