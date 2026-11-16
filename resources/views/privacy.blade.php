@extends('layouts.guest')

@section('title', 'EquipTrack - Privacy Notice')

@push('css')
<link rel="stylesheet" href="{{ asset('css/privacy.css') }}?v={{ filemtime(public_path('css/privacy.css')) }}">
@endpush

{{-- Public Privacy Notice (Data Privacy Act of 2012). Open to everyone,
     signed in or not; linked from Register, Login, the home page, the
     invite page and every Profile page. --}}
@section('content')
    <div class="privacy-page">
        <div class="privacy-top">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('landing') }}" class="privacy-back">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>Back</span>
            </a>
        </div>

        <article class="privacy-card">
            <header class="privacy-banner">
                <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack">
                <h1>Privacy Notice</h1>
                <p>How EquipTrack collects, uses and protects your personal information</p>
            </header>
            <div class="privacy-body">
                @include('partials.privacy-notice')
            </div>
        </article>
    </div>
@endsection
