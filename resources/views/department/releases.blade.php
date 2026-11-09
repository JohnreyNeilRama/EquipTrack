@extends('layouts.department')

@section('title', 'EquipTrack - Release Equipment')

@push('css')
{{-- Same table, badge and modal design as the Borrow Requests / Return Confirmation pages --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}?v={{ filemtime(public_path('admin/css/admindashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}?v={{ filemtime(public_path('admin/css/adminrequests.css')) }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}">
<style>
    .releases-table th,
    .releases-table td.col-date,
    .releases-table .status-badge {
        white-space: nowrap;
    }
    /* Seven columns: tighter cells (same as the Return Confirmation table) so
       the Action column stays in view on laptop screens. */
    .admin-table-card .releases-table th,
    .admin-table-card .releases-table td {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    .admin-table-card .releases-table th:first-child,
    .admin-table-card .releases-table td:first-child {
        padding-left: 20px !important;
    }
    /* Fixed column widths (set in the <colgroup>): the columns keep the same
       size whatever the rows hold, even when the table is empty. The short,
       predictable columns have set widths (Status fits "Ready for Pickup",
       Action fits Release + View side by side); Borrower and Equipment share
       the rest equally. Text that doesn't fit ends in "…" (the full value is
       in the tooltip and in View). Below the minimum width the table scrolls
       sideways inside its card. */
    /* .admin-table-card in the selector: it must beat the shared phone rule
       ".admin-table-card table { min-width: 500px }", which would otherwise
       squeeze Borrower and Equipment to nothing on small screens. */
    .admin-table-card .releases-table {
        table-layout: fixed;
        min-width: 1060px;
    }
    .releases-table col.col-w-id { width: 115px; }
    .releases-table col.col-w-pickup { width: 135px; }
    .releases-table col.col-w-due { width: 125px; }
    .releases-table col.col-w-status { width: 185px; }
    .releases-table col.col-w-action { width: 240px; }
    .releases-table th,
    .releases-table td {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .releases-table td {
        vertical-align: middle;
    }    .releases-table td.col-date {
        white-space: nowrap;
    }
    .releases-table .status-badge {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    /* The empty-table message spans every column and keeps its own layout */
    .releases-table td.no-requests-message {
        white-space: normal;
        overflow: visible;
    }
    /* When / by whom it was handled: wraps under the badge instead of widening the column */
    .releases-table td.col-status .cell-sub {
        margin-top: 6px;
        white-space: normal;
        line-height: 1.4;
    }
    /* Status filter: the box itself is wide enough for "Ready for Pickup", so
       the arrow (placed inside the box) never sits on the text; a longer
       option ends in "…" before the arrow. */
    .filter-select-item.rel-filter {
        min-width: 200px;
    }
    .releases-table .cell-main {
        display: block;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .releases-table .cell-sub {
        display: block;
        margin-top: 2px;
        font-size: var(--fs-small);
        color: var(--text-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .releases-table .cell-sub.is-due {
        color: #b45309;
        font-weight: 600;
    }
    .releases-table .cell-sub.is-late {
        color: #ef4444;
        font-weight: 600;
    }
    /* Release statuses, in the shared badge design */
    .status-badge.status-ready {
        background-color: rgba(245, 158, 11, 0.12);
        color: #b45309;
    }
    .status-badge.status-released {
        background-color: rgba(16, 185, 129, 0.08);
        color: #10b981;
    }
    .status-badge.status-not-claimed {
        background-color: rgba(71, 85, 105, 0.08);
        color: #475569;
    }
    .dark-theme .status-badge.status-ready { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    .dark-theme .status-badge.status-released { background-color: rgba(16, 185, 129, 0.15); color: #34d399; }
    .dark-theme .status-badge.status-not-claimed { background-color: rgba(226, 232, 240, 0.1); color: #cbd5e1; }

    .summary-card-icon.icon-orange { background-color: #fef3c7; color: #d97706; }
    .dark-theme .summary-card-icon.icon-orange { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }

    /* Release / Not Claimed window */
    .release-summary {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 8px 16px;
        margin-bottom: 20px;
        padding: 16px;
        font-size: var(--fs-body);
        background: var(--primary-light);
        border-radius: 12px;
    }
    .release-summary dt {
        color: var(--text-muted);
        font-weight: 500;
    }
    .release-summary dd {
        margin: 0;
        color: var(--text-main);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .id-check {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 16px;
        font-size: var(--fs-body);
        color: var(--text-main);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
    }
    .id-check input {
        margin-top: 3px;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        accent-color: #385585;
    }
    .release-help {
        margin: 0 0 16px;
        font-size: var(--fs-label);
        color: var(--text-muted);
        line-height: 1.5;
    }
    .btn-submit-request:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    /* Action column: at most two buttons, the main action (Release, or a
       muted chip saying when it can be released) and View, which holds the
       details and the rarer Not Claimed / Cancel reservation. Release and View
       are the shared btn-approve / btn-view-only buttons of the other tables;
       on a narrow screen the two stack instead of overflowing the column. */
    .releases-table .rel-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .releases-table .rel-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 32px;
        padding: 0 12px;
        border: 1px solid transparent;
        border-radius: 8px;
        font-family: inherit;
        font-size: var(--fs-label);
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color 0.2s, border-color 0.2s, color 0.2s;
    }
    .releases-table .rel-btn i { font-size: 0.9em; }
    .releases-table .rel-btn:focus-visible { outline: 2px solid #385585; outline-offset: 2px; }
    .releases-table .rel-btn--wait { max-width: 100%; overflow: hidden; text-overflow: ellipsis; background-color: #f1f5f9; color: #64748b; border-color: #e2e8f0; cursor: help; font-weight: 500; }
    .releases-table .btn-approve:focus-visible,
    .releases-table .btn-view-only:focus-visible { outline: 2px solid #385585; outline-offset: 2px; }
    /* View button: the bordered style of the Department Dashboard and the
       other Department tables (Return Confirmation, Penalties), so it stays
       visible on the dark table too. */
    .releases-table .btn-view-only { background-color: rgba(56, 85, 133, 0.08); border: 1px solid rgba(56, 85, 133, 0.35); color: #385585; }
    .releases-table .btn-view-only:hover { background-color: #385585; border-color: #385585; color: #ffffff; }
    .dark-theme .releases-table .btn-view-only { background-color: #334155; border-color: #475569; color: #93c5fd; }
    .dark-theme .releases-table .btn-view-only:hover { background-color: #2563eb; border-color: #2563eb; color: #ffffff; }
    .dark-theme .releases-table .btn-view-only:focus-visible { outline-color: #60a5fa; }
    /* Keyboard focus on a row (Enter opens it, like a click) */
    .releases-table tr.admin-table-row:focus-visible { outline: 2px solid #385585; outline-offset: -2px; }

    /* View window: the loan's details, then its actions in the footer */
    .view-section + .view-section { margin-top: 16px; }
    .view-section-title {
        margin: 0 0 8px;
        font-size: var(--fs-small);
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .view-footer {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }
    .view-footer .view-footer-end { display: flex; gap: 10px; margin-left: auto; }
    .view-footer .view-note { font-size: var(--fs-label); color: var(--text-muted); }
    .view-footer button {
        height: 38px;
        padding: 0 16px;
        border-radius: 8px;
        font-family: inherit;
        font-size: var(--fs-label);
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
    }
    .view-footer .view-btn-primary { background-color: #385585; color: #ffffff; }
    .view-footer .view-btn-primary:hover { background-color: #2f477a; }
    .view-footer .view-btn-outline { background-color: transparent; color: #dc2626; border-color: #fca5a5; }
    .view-footer .view-btn-outline:hover { background-color: #fef2f2; border-color: #dc2626; }
    .view-footer .view-btn-text { padding: 0 4px; background: none; color: #dc2626; text-decoration: underline; text-underline-offset: 3px; font-weight: 500; }
    .view-footer .view-btn-text:hover { color: #b91c1c; }
    .view-footer .view-btn-close { background-color: transparent; color: var(--text-main); border-color: var(--border-color); }
    .dark-theme .view-footer .view-btn-primary { background-color: #3b82f6; }
    .dark-theme .view-footer .view-btn-outline,
    .dark-theme .view-footer .view-btn-text { color: #f87171; }
    @media (max-width: 576px) {
        .view-footer { flex-wrap: wrap; }
        .view-footer .view-footer-end { width: 100%; margin-left: 0; }
        .view-footer .view-footer-end button { flex: 1; }
    }
    .dark-theme .releases-table .rel-btn--wait { background-color: rgba(148, 163, 184, 0.12); color: #94a3b8; border-color: rgba(148, 163, 184, 0.25); }
</style>
@endpush

@section('content')
    <div class="requests-container">
        <div class="equipment-header-section">
            <h2>Release Equipment</h2>
            <p>Release approved equipment to borrowers after checking their school ID.</p>
        </div>

        <!-- Summary Cards -->
        <div class="requests-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumReady">0</span>
                        <div class="summary-card-icon icon-orange">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Ready for Pickup</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumReleased">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-hand-holding"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Released (last 30 days)</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumNotClaimed">0</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Not Claimed / Cancelled (last 30 days)</span>
                </div>
            </div>
        </div>

        <!-- Search & filter -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchReleases" placeholder="Search user, equipment or ID..." aria-label="Search releases">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <div class="filter-select-item rel-filter">
                <select id="filterStatus" aria-label="Filter by status">
                    <option value="all">All Status</option>
                    <option value="Ready for Pickup" selected>Ready for Pickup</option>
                    <option value="Released">Released</option>
                    <option value="Not Claimed">Not Claimed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="filter-select-item">
                <select id="filterRole" aria-label="Filter by role">
                    <option value="all">All Roles</option>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty Member</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>

        <div class="table-container card admin-table-card">
            <table class="releases-table" style="width: 100%;">
                <colgroup>
                    <col class="col-w-id">
                    <col class="col-w-user">
                    <col class="col-w-equipment">
                    <col class="col-w-pickup">
                    <col class="col-w-due">
                    <col class="col-w-status">
                    <col class="col-w-action">
                </colgroup>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th class="col-user">Borrower</th>
                        <th class="col-equipment">Equipment</th>
                        <th>Pickup Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="action-column" style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="releasesTableBody">
                    {{-- Rendered by the script below --}}
                </tbody>
            </table>

            {{-- Pagination Footer (same as the Borrow Requests and Return Confirmation tables) --}}
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons">
                    {{-- Buttons added by the script below --}}
                </div>
            </div>
        </div>
    </div>

    {{-- View window: the loan's details, and its actions in the footer --}}
    <div class="modal-overlay" id="viewModal">
        <div class="modal-card eq-modal-card" style="max-width: 560px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top" id="viewSubtitle">Reservation details</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeViewBtn" aria-label="Close">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;" id="viewTitle">Request #—</h3>

                <section class="view-section">
                    <h4 class="view-section-title">Borrower</h4>
                    <dl class="release-summary">
                        <dt>Name</dt><dd id="viewUser">—</dd>
                        <dt>ID Number</dt><dd id="viewIdNumber">—</dd>
                        <dt>Department</dt><dd id="viewDepartment">—</dd>
                    </dl>
                </section>

                <section class="view-section">
                    <h4 class="view-section-title">Equipment</h4>
                    <dl class="release-summary">
                        <dt>Item</dt><dd id="viewEquipment">—</dd>
                        <dt>Serial No.</dt><dd id="viewSerial">—</dd>
                        <dt>Quantity</dt><dd id="viewQuantity">—</dd>
                    </dl>
                </section>

                <section class="view-section">
                    <h4 class="view-section-title">Reservation</h4>
                    <dl class="release-summary">
                        <dt>Pickup Date</dt><dd id="viewPickup">—</dd>
                        <dt>Due Date</dt><dd id="viewDue">—</dd>
                        <dt>Purpose</dt><dd id="viewPurpose">—</dd>
                        <dt>Instructor</dt><dd id="viewInstructor">—</dd>
                        <dt>Notes</dt><dd id="viewNotes">—</dd>
                        <dt>Requested</dt><dd id="viewRequested">—</dd>
                        <dt>Approved by</dt><dd id="viewApproval">—</dd>
                    </dl>
                </section>

                <section class="view-section">
                    <h4 class="view-section-title">Status</h4>
                    <dl class="release-summary">
                        <dt>Status</dt><dd id="viewStatus">—</dd>
                        <dt id="viewHistoryLabel">Details</dt><dd id="viewHistory">—</dd>
                    </dl>
                </section>

                <div class="view-footer" id="viewFooter"></div>
            </div>
        </div>
    </div>

    {{-- Release / Not Claimed window --}}
    <div class="modal-overlay" id="releaseModal">
        <div class="modal-card eq-modal-card" style="max-width: 500px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top" id="releaseModalSubtitle">Hand the equipment to the borrower</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeReleaseBtn" aria-label="Close">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;" id="releaseModalTitle">Release Equipment</h3>

                <dl class="release-summary">
                    <dt>Borrower</dt><dd id="relUser">—</dd>
                    <dt>ID Number</dt><dd id="relIdNumber">—</dd>
                    <dt>Equipment</dt><dd id="relEquipment">—</dd>
                    <dt>Pickup Date</dt><dd id="relPickup">—</dd>
                    <dt>Due Date</dt><dd id="relDue">—</dd>
                </dl>

                <form id="releaseForm">
                    <input type="hidden" id="relTransactionId">

                    {{-- Release --}}
                    <div id="releaseFields">
                        <label class="id-check">
                            <input type="checkbox" id="relIdVerified">
                            <span>I checked the borrower's school ID and it matches the name and ID number above.</span>
                        </label>
                        <div class="form-submit-container" style="margin-top: 20px;">
                            <button type="submit" class="btn-submit-request" id="relSubmitRelease" disabled>Release Equipment</button>
                        </div>
                    </div>

                    {{-- Cancel Reservation --}}
                    <div id="cancelFields" style="display: none;">
                        <p class="release-help">The reservation is closed and the dates are free again. The borrower is not penalized, sees your reason and can request the item again.</p>
                        <label class="detail-form-label" for="relCancelReason" style="text-align: left;">Reason (shown to the borrower)</label>
                        <textarea id="relCancelReason" class="detail-form-control textarea-control" rows="3" maxlength="255" placeholder="e.g. The projector is being repaired. Please request another unit."></textarea>
                        <div class="form-submit-container" style="margin-top: 20px;">
                            <button type="submit" class="btn-submit-reject" id="relSubmitCancel" disabled>Cancel Reservation</button>
                        </div>
                    </div>

                    {{-- Not Claimed --}}
                    <div id="notClaimedFields" style="display: none;">
                        <p class="release-help">The held unit goes back to stock. The borrower is not penalized and can request the item again.</p>
                        <label class="detail-form-label" for="relNote" style="text-align: left;">Note (optional)</label>
                        <textarea id="relNote" class="detail-form-control textarea-control" rows="3" maxlength="255" placeholder="e.g. Borrower did not come on the pickup date."></textarea>
                        <div class="form-submit-container" style="margin-top: 20px;">
                            <button type="submit" class="btn-submit-reject">Mark as Not Claimed</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
    (() => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_DATA = @json(route('department.releases.data'));
        const URL_RELEASE = @json(route('department.releases.release'));
        const URL_NOT_CLAIMED = @json(route('department.releases.not-claimed'));
        const URL_CANCEL = @json(route('department.releases.cancel'));
        const REFRESH_MS = 15000;

        const PAGE_SIZE = 10;

        let releases = @json($releases);
        let mode = 'release';
        let currentPage = 1;

        const tableBody = document.getElementById('releasesTableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationButtons = document.getElementById('paginationButtons');
        const searchInput = document.getElementById('searchReleases');
        const filterSelect = document.getElementById('filterStatus');
        const filterRole = document.getElementById('filterRole');
        const modal = document.getElementById('releaseModal');
        const viewModal = document.getElementById('viewModal');
        const form = document.getElementById('releaseForm');
        const idCheck = document.getElementById('relIdVerified');
        const releaseBtn = document.getElementById('relSubmitRelease');
        const cancelReason = document.getElementById('relCancelReason');
        const cancelBtn = document.getElementById('relSubmitCancel');

        function escapeHTML(value) {
            if (value === null || value === undefined) return '';
            return String(value).replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        const badgeClass = status => ({
            'Ready for Pickup': 'status-ready',
            'Released': 'status-released',
            'Not Claimed': 'status-not-claimed',
            'Cancelled': 'status-not-claimed',
        }[status] || 'status-pending');

        function renderSummary() {
            document.getElementById('sumReady').textContent = releases.filter(r => r.status === 'Ready for Pickup').length;
            document.getElementById('sumReleased').textContent = releases.filter(r => r.status === 'Released').length;
            document.getElementById('sumNotClaimed').textContent = releases.filter(r => r.status === 'Not Claimed' || r.status === 'Cancelled').length;
        }

        // Second line of the Status / date cells: when it was handled, or how soon pickup is.
        function statusDetail(r) {
            if (r.status === 'Released') return `${escapeHTML(r.releasedAt)}<br>by ${escapeHTML(r.releasedBy)}`;
            if (r.status === 'Not Claimed') return escapeHTML(r.unclaimedAt);
            if (r.status === 'Cancelled') return `${escapeHTML(r.cancelledAt)}<br>by ${escapeHTML(r.cancelledBy)}`;
            return '';
        }

        // Action column, at most two buttons: Release (or, when it can't be
        // released yet, a muted chip saying why, with the full reason on
        // hover), and View, which holds the details and the rarer actions.
        function actionButtons(r) {
            const view = `<button type="button" class="btn-view-only" data-view="${r.id}"><i class="fa-solid fa-eye" aria-hidden="true"></i> View</button>`;

            if (!r.canAct) {
                return `<div class="rel-actions">${view}</div>`;
            }

            const release = r.releaseBlocked
                ? `<span class="rel-btn rel-btn--wait" tabindex="0" title="${escapeHTML(r.releaseBlocked)}" aria-label="${escapeHTML(r.releaseBlocked)}"><i class="fa-regular fa-clock" aria-hidden="true"></i>${escapeHTML(r.releaseBlockedLabel || 'Not yet')}</span>`
                : `<button type="button" class="btn-approve" data-release="${r.id}">Release</button>`;

            return `<div class="rel-actions">${release}${view}</div>`;
        }

        function renderTable() {
            const query = searchInput.value.toLowerCase().trim();
            const filter = filterSelect.value;
            const role = filterRole.value;

            const rows = releases.filter(r => {
                const text = [r.user, r.idNumber, r.equipment, r.serial, '#' + r.requestId, String(r.requestId)].join(' ').toLowerCase();
                return (filter === 'all' || r.status === filter)
                    && (role === 'all' || (r.role || '').toLowerCase().includes(role))
                    && (query === '' || text.includes(query));
            });

            if (!rows.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-requests-message">
                            <i class="fa-solid fa-hand-holding"></i>
                            ${filter === 'Ready for Pickup' && query === '' ? 'No equipment is waiting for pickup.' : 'No records match your search.'}
                        </td>
                    </tr>`;
                paginationContainer.style.display = 'none';
                return;
            }

            // Pages of PAGE_SIZE rows (same as the other tables)
            const totalPages = Math.ceil(rows.length / PAGE_SIZE);
            if (currentPage > totalPages) currentPage = totalPages;
            const start = (currentPage - 1) * PAGE_SIZE;
            const end = Math.min(start + PAGE_SIZE, rows.length);

            tableBody.innerHTML = rows.slice(start, end).map(r => `
                <tr class="admin-table-row" data-row="${r.id}" tabindex="0" aria-label="View request #${r.requestId}">
                    <td style="font-weight: 600;">#${r.requestId}</td>
                    <td class="col-user">
                        <span class="cell-main" title="${escapeHTML(r.user)}">${escapeHTML(r.user)}</span>
                        <span class="cell-sub">${escapeHTML(r.role)} · ${escapeHTML(r.idNumber)}</span>
                    </td>
                    <td class="col-equipment">
                        <span class="cell-main" title="${escapeHTML(r.equipment)}">${escapeHTML(r.equipment)}</span>
                        <span class="cell-sub">${escapeHTML(r.serial)}${r.requiresBothApprovals ? ' · Expensive' : ''}</span>
                    </td>
                    <td class="col-date">
                        ${escapeHTML(r.pickupDate)}
                        ${r.pickupNote ? `<span class="cell-sub ${r.pickupPassed ? 'is-late' : (r.pickupNote === 'Pickup today' ? 'is-due' : '')}">${escapeHTML(r.pickupNote)}</span>` : ''}
                    </td>
                    <td class="col-date">${escapeHTML(r.dueDate)}</td>
                    <td class="col-status">
                        <span class="status-badge ${badgeClass(r.status)}">${escapeHTML(r.status)}</span>
                        ${statusDetail(r) ? `<span class="cell-sub">${statusDetail(r)}</span>` : ''}
                    </td>
                    <td class="action-cell">
                        ${actionButtons(r)}
                    </td>
                </tr>`).join('');

            paginationContainer.style.display = 'flex';
            paginationInfo.textContent = `Showing ${start + 1} to ${end} of ${rows.length} entries`;
            renderPaginationButtons(totalPages);
        }

        // Previous, page numbers, Next (same buttons as the other tables)
        function renderPaginationButtons(totalPages) {
            paginationButtons.innerHTML = '';

            const addButton = (html, page, disabled, active = false) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `btn-page ${active ? 'active' : ''}`;
                button.innerHTML = html;
                button.disabled = disabled;
                button.addEventListener('click', () => {
                    currentPage = page;
                    renderTable();
                });
                paginationButtons.appendChild(button);
            };

            addButton('<i class="fa-solid fa-angle-left"></i>', currentPage - 1, currentPage === 1);
            for (let i = 1; i <= totalPages; i++) {
                addButton(String(i), i, false, i === currentPage);
            }
            addButton('<i class="fa-solid fa-angle-right"></i>', currentPage + 1, currentPage === totalPages);
        }

        function render() {
            renderSummary();
            renderTable();
        }

        // ------------------------------------------------------------------
        // View window: details, and the actions this loan allows right now.
        // Its buttons open the same confirmation windows as before.
        // ------------------------------------------------------------------
        const setText = (id, value) => { document.getElementById(id).textContent = value || '—'; };

        function openView(id) {
            const r = releases.find(item => item.id === id);
            if (!r) return;

            setText('viewTitle', 'Request #' + r.requestId);
            setText('viewSubtitle', r.status === 'Ready for Pickup' ? 'Reservation details' : 'Loan details');
            setText('viewUser', `${r.user} (${r.role})`);
            setText('viewIdNumber', r.idNumber);
            setText('viewDepartment', r.department);
            setText('viewEquipment', `${r.equipment} · ${r.category}${r.requiresBothApprovals ? ' · Expensive' : ''}`);
            setText('viewSerial', r.serial);
            setText('viewQuantity', r.quantity > 1 ? r.quantity + ' units' : '1 unit');
            setText('viewPickup', r.pickupDate + (r.pickupNote ? ` (${r.pickupNote})` : ''));
            setText('viewDue', r.dueDate);
            setText('viewPurpose', r.purpose);
            setText('viewInstructor', r.instructor);
            setText('viewNotes', r.notes);
            setText('viewRequested', r.requestedAt);
            setText('viewApproval', [r.deptApproval !== '—' ? 'Lab Personnel: ' + r.deptApproval : '', r.adminApproval ? 'Admin: ' + r.adminApproval : ''].filter(Boolean).join(' / '));
            setText('viewStatus', r.status);

            const history = {
                'Released': ['Released', `${r.releasedAt} by ${r.releasedBy}`],
                'Not Claimed': ['Marked', r.unclaimedAt],
                'Cancelled': ['Cancelled', `${r.cancelledAt} by ${r.cancelledBy}${r.cancelReason ? ' · Reason: ' + r.cancelReason : ''}`],
            }[r.status] || ['Release', r.releaseBlocked || 'Can be released now, after the school ID check.'];
            setText('viewHistoryLabel', history[0]);
            setText('viewHistory', history[1]);

            // Footer: Cancel reservation (quiet, on the left); Not Claimed and
            // Release on the right, each only when its rules allow it.
            const footer = document.getElementById('viewFooter');
            if (!r.canAct) {
                footer.innerHTML = `<div class="view-footer-end"><button type="button" class="view-btn-close" data-view-close>Close</button></div>`;
            } else {
                const right = [];
                if (!r.notClaimedBlocked) right.push(`<button type="button" class="view-btn-outline" data-view-action="not-claimed">Not Claimed</button>`);
                if (!r.releaseBlocked) right.push(`<button type="button" class="view-btn-primary" data-view-action="release">Release</button>`);
                if (!right.length) right.push(`<button type="button" class="view-btn-close" data-view-close>Close</button>`);
                footer.innerHTML = `<button type="button" class="view-btn-text" data-view-action="cancel">Cancel reservation</button>`
                    + `<div class="view-footer-end">${right.join('')}</div>`;
            }
            footer.dataset.id = r.id;

            viewModal.classList.add('show');
        }

        function closeView() {
            viewModal.classList.remove('show');
        }

        document.getElementById('viewFooter').addEventListener('click', event => {
            const footer = event.currentTarget;
            if (event.target.closest('[data-view-close]')) {
                closeView();
                return;
            }
            const action = event.target.closest('[data-view-action]');
            if (!action) return;
            closeView();
            openModal(parseInt(footer.dataset.id, 10), action.dataset.viewAction);
        });
        document.getElementById('closeViewBtn').addEventListener('click', closeView);
        viewModal.addEventListener('click', event => {
            if (event.target === viewModal) closeView();
        });

        function openModal(id, nextMode) {
            const r = releases.find(item => item.id === id);
            if (!r) return;

            mode = nextMode;
            const releasing = mode === 'release';

            document.getElementById('relTransactionId').value = r.id;
            document.getElementById('relUser').textContent = `${r.user} (${r.role})`;
            document.getElementById('relIdNumber').textContent = r.idNumber;
            document.getElementById('relEquipment').textContent = `${r.equipment} · ${r.serial}${r.quantity > 1 ? ' · ×' + r.quantity : ''}`;
            document.getElementById('relPickup').textContent = r.pickupDate + (r.pickupNote ? ` (${r.pickupNote})` : '');
            document.getElementById('relDue').textContent = r.dueDate;
            const cancelling = mode === 'cancel';
            document.getElementById('releaseModalTitle').textContent = releasing
                ? 'Release Equipment'
                : (cancelling ? 'Cancel Reservation' : 'Mark as Not Claimed');
            document.getElementById('releaseModalSubtitle').textContent = releasing
                ? 'Hand the equipment to the borrower'
                : (cancelling ? 'The reservation can\'t go ahead' : 'The borrower did not pick up the equipment');
            document.getElementById('releaseFields').style.display = releasing ? 'block' : 'none';
            document.getElementById('cancelFields').style.display = cancelling ? 'block' : 'none';
            document.getElementById('notClaimedFields').style.display = mode === 'not-claimed' ? 'block' : 'none';
            idCheck.checked = false;
            releaseBtn.disabled = true;
            cancelReason.value = '';
            cancelBtn.disabled = true;
            document.getElementById('relNote').value = '';

            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        // Release and View buttons do their own action. A click anywhere else
        // on a row opens View (like the other tables); clicks inside the
        // Action buttons area (e.g. the "not yet" chip) open nothing.
        tableBody.addEventListener('click', event => {
            const release = event.target.closest('[data-release]');
            const view = event.target.closest('[data-view]');
            if (release) {
                openModal(parseInt(release.dataset.release, 10), 'release');
                return;
            }
            if (view) {
                openView(parseInt(view.dataset.view, 10));
                return;
            }
            if (event.target.closest('.rel-actions')) return;
            // Selecting text in a row is not a click on it
            if (window.getSelection && String(window.getSelection()).trim() !== '') return;

            const row = event.target.closest('tr[data-row]');
            if (row) openView(parseInt(row.dataset.row, 10));
        });

        // Enter on a focused row opens it too
        tableBody.addEventListener('keydown', event => {
            if (event.key !== 'Enter' || !event.target.matches('tr[data-row]')) return;
            event.preventDefault();
            openView(parseInt(event.target.dataset.row, 10));
        });

        idCheck.addEventListener('change', () => {
            releaseBtn.disabled = !idCheck.checked;
        });

        // A reason is required: the borrower sees it.
        cancelReason.addEventListener('input', () => {
            cancelBtn.disabled = cancelReason.value.trim() === '';
        });

        form.addEventListener('submit', event => {
            event.preventDefault();

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('transaction_id', document.getElementById('relTransactionId').value);

            if (mode === 'release') {
                if (!idCheck.checked) return;
                formData.append('id_verified', '1');
            } else if (mode === 'cancel') {
                if (cancelReason.value.trim() === '') return;
                formData.append('reason', cancelReason.value.trim());
            } else {
                formData.append('note', document.getElementById('relNote').value.trim());
            }

            const url = { release: URL_RELEASE, cancel: URL_CANCEL }[mode] || URL_NOT_CLAIMED;
            const doneTitle = { release: 'Equipment Released', cancel: 'Reservation Cancelled' }[mode] || 'Marked Not Claimed';

            fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotification(doneTitle, data.message, mode === 'release' ? 'success' : 'error');
                        releases = data.releases || releases;
                        closeModal();
                        render();
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                        showNotification('Action Failed', firstError || data.message || 'The action could not be completed.', 'error');
                        refresh();
                    }
                })
                .catch(() => showNotification('Error', 'Network error occurred.', 'error'));
        });

        document.getElementById('closeReleaseBtn').addEventListener('click', closeModal);
        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });
        // A new search or filter starts again from page 1
        searchInput.addEventListener('input', () => {
            currentPage = 1;
            renderTable();
        });
        filterSelect.addEventListener('change', () => {
            currentPage = 1;
            renderTable();
        });
        filterRole.addEventListener('change', () => {
            currentPage = 1;
            renderTable();
        });

        function refresh() {
            return fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        releases = data.releases;
                        render();
                    }
                })
                .catch(err => console.error('Error loading releases:', err));
        }

        render();
        // Refresh while the tab is visible (a hidden tab catches up when shown),
        // and never redraw the table under an open window.
        const refreshIfIdle = () => {
            if (document.hidden) return;
            if (!modal.classList.contains('show') && !viewModal.classList.contains('show')) refresh();
        };
        setInterval(refreshIfIdle, REFRESH_MS);
        document.addEventListener('visibilitychange', refreshIfIdle);
    })();
</script>
@endpush
