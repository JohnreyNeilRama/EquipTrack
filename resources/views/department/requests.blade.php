@extends('layouts.department')

@section('title', 'EquipTrack - Borrow Requests')

@push('css')
{{-- Same table design as the Admin Requests page --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}?v={{ filemtime(public_path('admin/css/adminrequests.css')) }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}?v={{ filemtime(public_path('departments/css/requests.css')) }}">
<style>
    /* Fixed column widths (same approach as the Release Equipment, Return
       Confirmation and Penalties tables): the columns keep the same size
       whatever the rows hold, even when the table is empty. The short,
       predictable columns have set widths (Status fits the badge and the
       "Admin: … / Department: …" lines, Action fits Approve + Reject side by
       side); User and Equipment share the rest equally. Text that doesn't fit
       ends in "…" (full value on hover and in the details window). Below the
       minimum width the table scrolls sideways inside its card.
       .admin-table-card in the selector beats the shared phone rule
       ".admin-table-card table { min-width: 500px }". */
    .admin-table-card .dept-requests-table {
        table-layout: fixed;
        min-width: 1000px;
    }
    .dept-requests-table th:nth-child(1) { width: 115px; }   /* Request ID */
    .dept-requests-table th:nth-child(2),
    .dept-requests-table th:nth-child(3) { width: auto; }    /* User, Equipment */
    .dept-requests-table th:nth-child(4) { width: 135px; }   /* Date */
    .dept-requests-table th:nth-child(5) { width: 190px; }   /* Status */
    .dept-requests-table th:nth-child(6) { width: 200px; }   /* Action */
    .dept-requests-table th,
    .dept-requests-table td {
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    .dept-requests-table td.col-user,
    .dept-requests-table td.col-equipment-truncate,
    .dept-requests-table td:nth-child(5) {
        min-width: 0;
        max-width: none;
    }
    .dept-requests-table .status-badge {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    /* The empty-table message spans every column and keeps its own layout */
    .dept-requests-table td.no-requests-message {
        white-space: normal;
        overflow: visible;
    }
    /* Keyboard focus on a row (Enter opens it, like a click) */
    .dept-requests-table tr.admin-table-row:focus-visible {
        outline: 2px solid #385585;
        outline-offset: -2px;
    }

</style>
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

        @include('partials.requests.table', ['showRequestId' => true, 'combineUserRole' => true, 'tableClass' => 'requests-table-compact dept-requests-table'])
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
    'stackEquipmentTag' => true,
    'truncateEquipment' => true,
])
<script>
    // Rows, as on the other Department tables: a click anywhere opens the
    // request (the shared script handles that); selecting text in a row does
    // not, and Enter on a focused row does.
    (() => {
        const tableBody = document.getElementById('requestsTableBody');
        if (!tableBody) return;

        const makeFocusable = () => tableBody.querySelectorAll('tr.admin-table-row').forEach(tr => {
            if (tr.tabIndex !== 0) tr.tabIndex = 0;
        });
        new MutationObserver(makeFocusable).observe(tableBody, { childList: true });
        makeFocusable();

        // Runs before the row's own click handler, and stops it while text is selected.
        tableBody.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            if (window.getSelection && String(window.getSelection()).trim() !== '') e.stopPropagation();
        }, true);

        tableBody.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || !e.target.matches('tr.admin-table-row')) return;
            e.preventDefault();
            e.target.click();
        });
    })();
</script>
@endpush
