@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
{{-- Same table design as the Admin Requests page --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}">
@endpush

@section('content')
    {{-- Requests Content Container --}}
    <div class="requests-container">
        {{-- Header Section --}}
        <div class="equipment-header-section">
            <h2>Borrow Requests</h2>
            <p>Review and manage equipment borrowing requests for your department.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="requests-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Pending Request -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumPending">0</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Pending Request</span>
                </div>

                <!-- Card 2: Approved -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumApproved">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Approved</span>
                </div>

                <!-- Card 3: Rejected -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumRejected">0</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Rejected</span>
                </div>
            </div>
        </div>

        @include('partials.requests.table', ['showRequestId' => true, 'combineUserRole' => true, 'tableClass' => 'requests-table-compact'])
    </div>

    @include('partials.requests.modals')

    {{-- Toast Notification (the department layout has none; used by showNotification) --}}
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarScrim = document.getElementById('sidebarScrim');
    const topbarMenuBtn = document.getElementById('topbarMenuBtn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (sidebarScrim) sidebarScrim.classList.add('show');
    }
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (sidebarScrim) sidebarScrim.classList.remove('show');
    }

    if (topbarMenuBtn) topbarMenuBtn.addEventListener('click', openSidebar);
    if (sidebarScrim) sidebarScrim.addEventListener('click', closeSidebar);

    // Summary cards follow the rows the shared table renders (initial load,
    // live polling and after every approve / reject).
    document.addEventListener('requests:updated', (e) => {
        const rows = e.detail.requests || [];
        const count = (status) => rows.filter(r => r.status === status).length;

        document.getElementById('sumPending').textContent = count('Pending');
        document.getElementById('sumApproved').textContent = count('Approved');
        document.getElementById('sumRejected').textContent = count('Rejected');
    });
</script>
@include('partials.requests.script', [
    'party' => 'dept',
    'dataUrl' => route('department.requests.data'),
    'updateUrl' => route('department.requests.update'),
    'showRequestId' => true,
    'combineUserRole' => true,
])
@endpush
