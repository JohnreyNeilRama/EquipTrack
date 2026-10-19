@extends('layouts.admin')

@section('title', 'EquipTrack - Equipment Requests')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
@include('partials.requests.styles')
@endpush

@section('content')
    {{-- Requests Content Container --}}
    <div class="requests-container">
        {{-- Header Section --}}
        <div class="equipment-header-section">
            <h2>Requests</h2>
            <p>Review, approve, or reject student and faculty borrowing requests.</p>
        </div>

        @include('partials.requests.table', ['showRequestId' => true, 'combineUserRole' => true, 'tableClass' => 'requests-table-compact'])
    </div>

    @include('partials.requests.modals')
@endsection

@push('scripts')
@include('partials.requests.script', [
    'party' => 'admin',
    'dataUrl' => route('admin.requests.data'),
    'updateUrl' => route('admin.requests.update'),
    'showRequestId' => true,
    'combineUserRole' => true,
    'stackEquipmentTag' => true,
])
@endpush
