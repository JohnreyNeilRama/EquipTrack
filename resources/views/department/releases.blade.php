@extends('layouts.department')

@section('title', 'EquipTrack')

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
    /* Borrower and Equipment share the remaining width; their text ends in "…" */
    .releases-table .col-user,
    .releases-table .col-equipment {
        width: 24%;
        max-width: 0;
        min-width: 120px;
    }
    /* When / by whom it was handled: wraps under the badge instead of widening the column */
    .releases-table td.col-status .cell-sub {
        margin-top: 6px;
        white-space: normal;
        line-height: 1.4;
    }
    #filterStatus {
        min-width: 180px;
        padding-right: 36px;
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
    /* Action column (this page only): at most two buttons, the main action
       (Release, or a muted chip saying when it can be released) and View,
       which holds the details and the rarer Not Claimed / Cancel reservation. */
    .releases-table .rel-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex-wrap: nowrap;
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
    .releases-table .rel-btn--primary { background-color: #385585; color: #ffffff; }
    .releases-table .rel-btn--primary:hover { background-color: #2f477a; }
    .releases-table .rel-btn--wait { background-color: #f1f5f9; color: #64748b; border-color: #e2e8f0; cursor: help; font-weight: 500; }
    .releases-table .rel-btn--view { background-color: #ffffff; color: #385585; border-color: #cbd5e1; }
    .releases-table .rel-btn--view:hover { background-color: #f1f5f9; border-color: #385585; }
    .dark-theme .releases-table .rel-btn--primary { background-color: #3b82f6; }
    .dark-theme .releases-table .rel-btn--primary:hover { background-color: #2563eb; }
    .dark-theme .releases-table .rel-btn--view { background-color: transparent; color: #93c5fd; border-color: rgba(148, 163, 184, 0.35); }
    .dark-theme .releases-table .rel-btn--view:hover { background-color: rgba(148, 163, 184, 0.12); border-color: #93c5fd; }

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
            <p>Hand approved equipment to borrowers when they come to the lab. Check their school ID, then release the item. Open View for the full details, to mark it Not Claimed if they never came, or to cancel the reservation (with a reason the borrower will see).</p>
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
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchReleases" placeholder="Search by user, ID number, equipment or request ID...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterStatus">
                        <option value="all">All Status</option>
                        <option value="Ready for Pickup" selected>Ready for Pickup</option>
                        <option value="Released">Released</option>
                        <option value="Not Claimed">Not Claimed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
        </div>

        <div class="table-container card admin-table-card">
            <table class="releases-table" style="width: 100%;">
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

        let releases = @json($releases);
        let mode = 'release';

        const tableBody = document.getElementById('releasesTableBody');
        const searchInput = document.getElementById('searchReleases');
        const filterSelect = document.getElementById('filterStatus');
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
            const view = `<button type="button" class="rel-btn rel-btn--view" data-view="${r.id}"><i class="fa-regular fa-eye" aria-hidden="true"></i>View</button>`;

            if (!r.canAct) {
                return `<div class="rel-actions">${view}</div>`;
            }

            const release = r.releaseBlocked
                ? `<span class="rel-btn rel-btn--wait" tabindex="0" title="${escapeHTML(r.releaseBlocked)}" aria-label="${escapeHTML(r.releaseBlocked)}"><i class="fa-regular fa-clock" aria-hidden="true"></i>${escapeHTML(r.releaseBlockedLabel || 'Not yet')}</span>`
                : `<button type="button" class="rel-btn rel-btn--primary" data-release="${r.id}">Release</button>`;

            return `<div class="rel-actions">${release}${view}</div>`;
        }

        function renderTable() {
            const query = searchInput.value.toLowerCase().trim();
            const filter = filterSelect.value;

            const rows = releases.filter(r => {
                const text = [r.user, r.idNumber, r.equipment, r.serial, '#' + r.requestId, String(r.requestId)].join(' ').toLowerCase();
                return (filter === 'all' || r.status === filter) && (query === '' || text.includes(query));
            });

            if (!rows.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-requests-message">
                            <i class="fa-solid fa-hand-holding"></i>
                            ${filter === 'Ready for Pickup' && query === '' ? 'No equipment is waiting for pickup.' : 'No records match your search.'}
                        </td>
                    </tr>`;
                return;
            }

            tableBody.innerHTML = rows.map(r => `
                <tr class="admin-table-row">
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

        tableBody.addEventListener('click', event => {
            const release = event.target.closest('[data-release]');
            const view = event.target.closest('[data-view]');
            if (release) openModal(parseInt(release.dataset.release, 10), 'release');
            if (view) openView(parseInt(view.dataset.view, 10));
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
        searchInput.addEventListener('input', renderTable);
        filterSelect.addEventListener('change', renderTable);

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
        setInterval(() => {
            // Don't redraw the table under an open window.
            if (!modal.classList.contains('show') && !viewModal.classList.contains('show')) refresh();
        }, REFRESH_MS);
    })();
</script>
@endpush
