@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
{{-- Same table, badge and modal design as the Borrow Requests page --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}">
<style>
    /* Ten columns: tighter cells than the six-column requests table so the
       Action column stays in view on laptop screens. */
    .admin-table-card .returns-table th,
    .admin-table-card .returns-table td {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    .admin-table-card .returns-table th:first-child,
    .admin-table-card .returns-table td:first-child {
        padding-left: 20px !important;
    }
    .returns-table .status-badge {
        text-align: center;
        line-height: 1.3;
    }

    /* Condition indicators */
    .condition-tag {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    .condition-good { background-color: #d1fae5; color: #047857; }
    .condition-damaged, .condition-lost { background-color: #fee2e2; color: #b91c1c; }
    .condition-incomplete { background-color: #fef3c7; color: #b45309; }
    .condition-none { color: var(--text-muted); }
    .dark-theme .condition-good { background-color: rgba(16, 185, 129, 0.15); color: #34d399; }
    .dark-theme .condition-damaged, .dark-theme .condition-lost { background-color: rgba(239, 68, 68, 0.15); color: #f87171; }
    .dark-theme .condition-incomplete { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    .condition-source {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .equipment-id {
        font-size: 13px;
        color: var(--text-muted);
        white-space: nowrap;
    }

    .return-id {
        font-weight: 600;
        white-space: nowrap;
    }

    .photo-indicator {
        margin-left: 4px;
        font-size: 11px;
        color: var(--text-muted);
    }

    /* ------------------------------------------------------------------
       Return Review form
       ------------------------------------------------------------------ */
    .eq-modal-card.return-review-card {
        max-width: 720px !important;
        width: calc(100% - 32px);
    }
    .return-review-card .modal-inner-card {
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 110px);
        padding-bottom: 0;
    }
    /* Long forms scroll inside the card; the title and the buttons stay put. */
    .review-scroll {
        overflow-y: auto;
        flex: 1 1 auto;
        min-height: 0;
        margin: 0 -4px;
        padding: 0 4px 4px;
    }

    .review-heading {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 32px 18px;
    }
    .review-title {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main);
    }
    .review-requester {
        margin-bottom: 18px;
    }

    .review-section {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 18px 0;
        border-top: 1px solid var(--border-color);
    }
    .review-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 14.5px;
        font-weight: 700;
        color: var(--text-main);
    }
    .review-step {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #ffffff;
        background-color: #385585;
        flex-shrink: 0;
    }
    .dark-theme .review-step { background-color: #3b82f6; }
    .review-hint {
        margin: -4px 0 0;
        font-size: 12.5px;
        color: var(--text-muted);
    }

    /* Original equipment photo (from Equipment Management) */
    .equipment-photo {
        position: relative;
        margin: 0;
        height: 200px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        background-color: var(--bg-color);
    }
    .equipment-photo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    .equipment-photo-placeholder {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 600;
    }
    .equipment-photo-placeholder i {
        font-size: 34px;
        opacity: 0.6;
    }
    .equipment-photo figcaption {
        position: absolute;
        left: 10px;
        bottom: 10px;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        color: #ffffff;
        background-color: rgba(15, 23, 42, 0.7);
    }
    @media (max-width: 640px) {
        .equipment-photo { height: 160px; }
    }

    /* Read-only facts as compact tiles instead of disabled inputs */
    .review-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin: 0;
    }
    .review-info-item {
        background-color: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 10px 14px;
        min-width: 0;
    }
    .review-info-wide { grid-column: 1 / -1; }
    .review-info-item dt {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 4px;
    }
    .review-info-item dd {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
        overflow-wrap: anywhere;
        white-space: pre-line;
    }
    .review-info-danger {
        border-color: rgba(239, 68, 68, 0.3);
        background-color: rgba(239, 68, 68, 0.04);
    }
    .review-info-danger dt, .review-info-danger dd { color: #ef4444; }

    .reported-box {
        border-left: 3px solid #385585;
        background-color: var(--bg-color);
        border-radius: 0 10px 10px 0;
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .dark-theme .reported-box { border-left-color: #3b82f6; }
    .reported-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .reported-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
    }
    .reported-notes {
        margin: 0;
        font-size: 13.5px;
        line-height: 1.5;
        color: var(--text-main);
        white-space: pre-line;
        overflow-wrap: anywhere;
    }

    .review-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .review-field-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
    }
    .review-field-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        padding: 0;
    }
    .review-field-label .req { color: #ef4444; text-transform: none; letter-spacing: 0; }
    .review-field-label .optional { font-weight: 500; text-transform: none; letter-spacing: 0; }
    .char-counter {
        font-size: 11.5px;
        color: var(--text-muted);
        white-space: nowrap;
    }
    .review-field textarea.is-invalid {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    }

    /* Condition as selectable cards */
    .condition-options {
        border: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }
    .condition-options legend {
        margin-bottom: 8px;
    }
    .condition-option {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px;
        border: 1.5px solid var(--border-color);
        border-radius: 12px;
        background-color: var(--card-bg);
        cursor: pointer;
        transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
    }
    .condition-option:hover { border-color: #94a3b8; }
    .condition-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .condition-option input:focus-visible + .condition-option-icon {
        outline: 2px solid #385585;
        outline-offset: 2px;
    }
    .condition-option-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }
    .condition-option[data-tone="good"] .condition-option-icon { background-color: #d1fae5; color: #047857; }
    .condition-option[data-tone="damaged"] .condition-option-icon { background-color: #fee2e2; color: #b91c1c; }
    .condition-option[data-tone="incomplete"] .condition-option-icon { background-color: #fef3c7; color: #b45309; }
    .dark-theme .condition-option[data-tone="good"] .condition-option-icon { background-color: rgba(16, 185, 129, 0.15); color: #34d399; }
    .dark-theme .condition-option[data-tone="damaged"] .condition-option-icon { background-color: rgba(239, 68, 68, 0.15); color: #f87171; }
    .dark-theme .condition-option[data-tone="incomplete"] .condition-option-icon { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    .condition-option-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .condition-option-name {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--text-main);
    }
    .condition-option-desc {
        font-size: 11.5px;
        line-height: 1.35;
        color: var(--text-muted);
    }
    .condition-option.is-selected[data-tone="good"] { border-color: #10b981; background-color: rgba(16, 185, 129, 0.06); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12); }
    .condition-option.is-selected[data-tone="damaged"] { border-color: #ef4444; background-color: rgba(239, 68, 68, 0.05); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12); }
    .condition-option.is-selected[data-tone="incomplete"] { border-color: #f59e0b; background-color: rgba(245, 158, 11, 0.06); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.14); }

    .result-photo-link {
        display: block;
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        background-color: #0f172a;
        margin-top: 4px;
    }
    .result-photo-link img {
        display: block;
        width: 100%;
        max-height: 260px;
        object-fit: contain;
    }
    .result-photo-hint {
        position: absolute;
        right: 10px;
        bottom: 10px;
        padding: 5px 10px;
        border-radius: 14px;
        font-size: 11.5px;
        font-weight: 600;
        color: #ffffff;
        background-color: rgba(15, 23, 42, 0.75);
    }

    .review-footer {
        flex-shrink: 0;
        margin: 0 -4px;
        padding: 14px 4px 22px;
        border-top: 1px solid var(--border-color);
        background-color: var(--card-bg);
    }
    .review-footer button[disabled] {
        opacity: 0.65;
        cursor: progress;
    }

    .reject-hint {
        margin: 10px 0 0;
        font-size: 12.5px;
        color: var(--text-muted);
        text-align: left;
    }

    @media (max-width: 640px) {
        .condition-options,
        .review-info-grid {
            grid-template-columns: 1fr;
        }
        .review-footer {
            flex-direction: column-reverse;
            gap: 8px;
        }
        .review-footer button {
            width: 100%;
        }
        .return-review-card .modal-inner-card {
            max-height: calc(100vh - 80px);
        }
    }
</style>
@endpush

@section('content')
    <div class="requests-container">
        {{-- Header Section --}}
        <div class="equipment-header-section">
            <h2>Return Confirmation</h2>
            <p>Verify that returned equipment was physically handed back, inspect its condition, and confirm or reject the return.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="requests-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumPending">0</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Pending Confirmation</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumConfirmed">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Confirmed</span>
                </div>
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

        {{-- Controls bar (Search & Filters) --}}
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchReturns" placeholder="Search by user, equipment or return ID...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterStatus">
                        <option value="all">All Status</option>
                        <option value="pending confirmation">Pending Confirmation</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
        </div>

        {{-- Returns Table Card --}}
        <div class="table-container card admin-table-card">
            <table class="returns-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Return ID</th>
                        <th>User</th>
                        <th>Department</th>
                        <th>Equipment</th>
                        <th>Equipment ID</th>
                        <th>Qty</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Condition</th>
                        <th class="action-column" style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="returnsTableBody">
                    {{-- Loaded dynamically via JavaScript --}}
                </tbody>
            </table>

            {{-- Pagination Footer --}}
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons"></div>
            </div>
        </div>
    </div>

    {{-- Return Review / Inspection Modal --}}
    <div class="modal-overlay" id="reviewModal">
        <div class="modal-card eq-modal-card return-review-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Verify and inspect the returned equipment</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeReviewBtn" aria-label="Close">&times;</button>

                {{-- Title + status --}}
                <div class="review-heading">
                    <h3 class="review-title" id="reviewTitle">Return Review</h3>
                    <span class="status-badge" id="reviewStatusBadge">Pending Confirmation</span>
                </div>

                <div class="review-scroll">
                    {{-- Borrower --}}
                    <div class="modal-requester-profile review-requester">
                        <img src="" alt="User Avatar" class="modal-requester-avatar" id="reviewUserAvatar">
                        <div class="modal-requester-meta">
                            <span class="modal-requester-name" id="reviewUserName">—</span>
                            <span class="modal-requester-details" id="reviewUserMeta">—</span>
                        </div>
                    </div>

                    {{-- 1. Return information --}}
                    <section class="review-section">
                        <h4 class="review-section-title"><span class="review-step">1</span> Return Information</h4>

                        {{-- Original photo from Equipment Management, to compare with the returned unit --}}
                        <figure class="equipment-photo">
                            <img src="" alt="" id="reviewEquipmentImg" style="display: none;">
                            <div class="equipment-photo-placeholder" id="reviewEquipmentPlaceholder">
                                <i class="fa-regular fa-image"></i>
                                <span>No equipment photo available</span>
                            </div>
                            <figcaption>Original equipment photo</figcaption>
                        </figure>

                        <dl class="review-info-grid">
                            <div class="review-info-item review-info-wide">
                                <dt>Equipment</dt>
                                <dd id="reviewEquipment">—</dd>
                            </div>
                            <div class="review-info-item">
                                <dt>Equipment ID / Serial No.</dt>
                                <dd id="reviewEquipmentId">—</dd>
                            </div>
                            <div class="review-info-item">
                                <dt>Quantity Borrowed</dt>
                                <dd id="reviewQty">—</dd>
                            </div>
                            <div class="review-info-item">
                                <dt>Borrow Date</dt>
                                <dd id="reviewBorrowDate">—</dd>
                            </div>
                            <div class="review-info-item">
                                <dt>Due Date</dt>
                                <dd id="reviewDueDate">—</dd>
                            </div>
                            <div class="review-info-item review-info-wide">
                                <dt>Return Submitted</dt>
                                <dd id="reviewSubmitted">—</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- 2. What the user reported --}}
                    <section class="review-section">
                        <h4 class="review-section-title"><span class="review-step">2</span> User's Report</h4>
                        <div class="reported-box">
                            <div class="reported-row">
                                <span class="reported-label">Condition reported</span>
                                <span class="condition-tag" id="reviewReportedCondition">—</span>
                            </div>
                            <p class="reported-notes" id="reviewReportedRemarks">None</p>
                        </div>
                    </section>

                    {{-- 3a. Pending: the inspection form --}}
                    <section class="review-section" id="inspectionForm">
                        <h4 class="review-section-title"><span class="review-step">3</span> Inspection</h4>
                        <p class="review-hint">Check that the equipment is physically here, then record its actual condition.</p>

                        <fieldset class="condition-options">
                            <legend class="review-field-label">Actual Condition <span class="req">*</span></legend>
                            <label class="condition-option" data-tone="good">
                                <input type="radio" name="inspectCondition" value="Good" checked>
                                <span class="condition-option-icon"><i class="fa-solid fa-circle-check"></i></span>
                                <span class="condition-option-text">
                                    <span class="condition-option-name">Good Condition</span>
                                    <span class="condition-option-desc">Returned and in acceptable working condition</span>
                                </span>
                            </label>
                            <label class="condition-option" data-tone="damaged">
                                <input type="radio" name="inspectCondition" value="Damaged">
                                <span class="condition-option-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                <span class="condition-option-text">
                                    <span class="condition-option-name">Damaged</span>
                                    <span class="condition-option-desc">Visible damage or functional issues</span>
                                </span>
                            </label>
                            <label class="condition-option" data-tone="incomplete">
                                <input type="radio" name="inspectCondition" value="Incomplete">
                                <span class="condition-option-icon"><i class="fa-solid fa-puzzle-piece"></i></span>
                                <span class="condition-option-text">
                                    <span class="condition-option-name">Incomplete</span>
                                    <span class="condition-option-desc">Missing parts or accessories</span>
                                </span>
                            </label>
                        </fieldset>

                        <div class="review-field">
                            <div class="review-field-head">
                                <label class="review-field-label" for="inspectRemarks">
                                    Inspection Remarks <span class="req" id="remarksRequired" style="display: none;">* required for this condition</span>
                                </label>
                                <span class="char-counter" id="remarksCounter">0 / 1000</span>
                            </div>
                            <textarea id="inspectRemarks" class="detail-form-control textarea-control" rows="3" maxlength="1000" placeholder="Describe any damage, missing components, or other issues found..."></textarea>
                        </div>
                    </section>

                    {{-- 3b. Reviewed: the recorded outcome --}}
                    <section class="review-section" id="inspectionResult" style="display: none;">
                        <h4 class="review-section-title"><span class="review-step">3</span> Review Result</h4>
                        <dl class="review-info-grid">
                            <div class="review-info-item" id="resultConditionGroup">
                                <dt>Inspected Condition</dt>
                                <dd><span class="condition-tag" id="resultCondition">—</span></dd>
                            </div>
                            <div class="review-info-item">
                                <dt>Reviewed By</dt>
                                <dd id="resultReviewer">—</dd>
                            </div>
                            <div class="review-info-item review-info-wide" id="resultRemarksGroup">
                                <dt>Inspection Remarks</dt>
                                <dd id="resultRemarks">None</dd>
                            </div>
                            <div class="review-info-item review-info-wide review-info-danger" id="resultReasonGroup">
                                <dt>Reason for Rejection</dt>
                                <dd id="resultReason">—</dd>
                            </div>
                            <div class="review-info-item review-info-wide" id="resultPhotoGroup">
                                <dt>Equipment Photo</dt>
                                <dd>
                                    <a href="#" target="_blank" rel="noopener" class="result-photo-link" id="resultPhotoLink" title="Open the full-size photo">
                                        <img src="" alt="Photo of the returned equipment" id="resultPhoto">
                                        <span class="result-photo-hint"><i class="fa-solid fa-up-right-from-square"></i> Open full size</span>
                                    </a>
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <div class="modal-actions-footer review-footer" id="reviewActionsFooter"></div>
            </div>
        </div>
    </div>

    {{-- Reject Return Modal --}}
    <div class="modal-overlay" id="rejectReturnModal">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">The equipment was not returned or the information is wrong</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeRejectReturnBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Reject Return</h3>

                <form id="rejectReturnForm">
                    <input type="hidden" id="rejectReturnId">
                    <div class="reject-reason-input-group">
                        <label class="detail-form-label text-danger" style="text-align: left;">Reason for Rejection</label>
                        <textarea id="rejectReturnReason" class="detail-form-control textarea-control" rows="4" maxlength="1000" placeholder="e.g. The equipment has not been handed over to the department yet." required style="border-color: rgba(239, 68, 68, 0.3);"></textarea>
                    </div>
                    <p class="reject-hint">The item stays on loan to the user, who can submit the return again.</p>
                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-reject">Reject Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
    document.addEventListener('DOMContentLoaded', () => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_DATA = @json(route('department.returns.data'));
        const URL_CONFIRM = @json(route('department.returns.confirm'));
        const URL_REJECT = @json(route('department.returns.reject'));

        let returns = @json($dbReturns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        const tableBody = document.getElementById('returnsTableBody');
        const searchInput = document.getElementById('searchReturns');
        const filterSelect = document.getElementById('filterStatus');
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationButtons = document.getElementById('paginationButtons');

        const reviewModal = document.getElementById('reviewModal');
        const rejectModal = document.getElementById('rejectReturnModal');
        const rejectForm = document.getElementById('rejectReturnForm');
        const inspectRemarks = document.getElementById('inspectRemarks');

        let currentPage = 1;
        const pageSize = 10;
        let busy = false;

        const STATUS_CLASS = {
            'Pending Confirmation': 'status-pending',
            'Confirmed': 'status-approved',
            'Rejected': 'status-rejected',
        };

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        function conditionClass(condition) {
            const c = String(condition || '').toLowerCase();
            return ['good', 'damaged', 'incomplete', 'lost'].includes(c) ? 'condition-' + c : 'condition-none';
        }

        function conditionTag(condition) {
            if (!condition || condition === '—') return '<span class="condition-none">—</span>';
            return `<span class="condition-tag ${conditionClass(condition)}">${escapeHTML(condition)}</span>`;
        }

        function loadReturns() {
            fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.returns)) {
                        returns = data.returns;
                        renderTable();
                    }
                })
                .catch(err => console.error('Error fetching returns:', err));
        }

        // New returns appear automatically.
        setInterval(() => { if (!busy) loadReturns(); }, 5000);

        function updateSummaryCards() {
            const count = status => returns.filter(r => r.status === status).length;
            document.getElementById('sumPending').textContent = count('Pending Confirmation');
            document.getElementById('sumConfirmed').textContent = count('Confirmed');
            document.getElementById('sumRejected').textContent = count('Rejected');
        }

        function renderTable() {
            const query = searchInput.value.toLowerCase().trim();
            const filter = filterSelect.value;
            tableBody.innerHTML = '';
            updateSummaryCards();

            const filtered = returns.filter(r => {
                const haystack = `${r.returnId} ${r.user} ${r.equipment} ${r.equipmentId} ${r.department}`.toLowerCase();
                return haystack.includes(query) && (filter === 'all' || r.status.toLowerCase() === filter);
            });

            if (filtered.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="10" class="no-requests-message">
                            <i class="fa-solid fa-folder-open"></i>
                            No returns found matching your query.
                        </td>
                    </tr>
                `;
                paginationContainer.style.display = 'none';
                return;
            }

            const totalEntries = filtered.length;
            const totalPages = Math.ceil(totalEntries / pageSize);
            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalEntries);
            paginationContainer.style.display = 'flex';

            filtered.slice(startIndex, endIndex).forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'admin-table-row';
                tr.addEventListener('click', (e) => {
                    if (!e.target.closest('button')) openReviewModal(r.id);
                });

                const actionHtml = r.canReview
                    ? `<div class="action-buttons" style="justify-content: center; gap: 8px;">
                           <button class="btn-approve" onclick="openReviewModal(${r.id})"><i class="fa-solid fa-magnifying-glass"></i> Review</button>
                       </div>`
                    : `<button class="btn-view-only" onclick="openReviewModal(${r.id})"><i class="fa-solid fa-eye"></i> View</button>`;

                tr.innerHTML = `
                    <td><span class="return-id">${escapeHTML(r.returnId)}</span></td>
                    <td>
                        <div class="requester-info">
                            <span class="requester-name">${escapeHTML(r.user)}</span>
                        </div>
                    </td>
                    <td>${escapeHTML(r.department)}</td>
                    <td>${escapeHTML(r.equipment)}</td>
                    <td><span class="equipment-id">${escapeHTML(r.equipmentId)}</span></td>
                    <td>${escapeHTML(r.quantity)}</td>
                    <td>${escapeHTML(r.submitted)}</td>
                    <td><span class="status-badge ${STATUS_CLASS[r.status] || 'status-pending'}">${escapeHTML(r.status)}</span></td>
                    <td>
                        ${conditionTag(r.condition)}
                        <span class="condition-source">${r.conditionSource === 'inspected' ? 'Inspected' : 'Reported by user'}${r.photo ? ' <i class="fa-solid fa-camera photo-indicator" title="Photo attached"></i>' : ''}</span>
                    </td>
                    <td class="action-cell" style="text-align: center;">${actionHtml}</td>
                `;
                tableBody.appendChild(tr);
            });

            paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
            renderPaginationButtons(totalPages);
        }

        function renderPaginationButtons(totalPages) {
            paginationButtons.innerHTML = '';

            const makeBtn = (html, disabled, onClick, active = false) => {
                const btn = document.createElement('button');
                btn.className = `btn-page ${active ? 'active' : ''}`;
                btn.innerHTML = html;
                btn.disabled = disabled;
                btn.addEventListener('click', onClick);
                paginationButtons.appendChild(btn);
            };

            makeBtn('<i class="fa-solid fa-angle-left"></i>', currentPage === 1, () => { currentPage--; renderTable(); });
            for (let i = 1; i <= totalPages; i++) {
                makeBtn(String(i), false, () => { currentPage = i; renderTable(); }, currentPage === i);
            }
            makeBtn('<i class="fa-solid fa-angle-right"></i>', currentPage === totalPages, () => { currentPage++; renderTable(); });
        }

        // ------------------------------------------------------------------
        // Inspection form: condition cards and remarks
        // ------------------------------------------------------------------
        const remarksCounter = document.getElementById('remarksCounter');

        function selectedCondition() {
            const checked = document.querySelector('input[name="inspectCondition"]:checked');
            return checked ? checked.value : 'Good';
        }

        function setCondition(value) {
            document.querySelectorAll('input[name="inspectCondition"]').forEach(input => {
                input.checked = input.value === value;
            });
            syncConditionUi();
        }

        function syncConditionUi() {
            const condition = selectedCondition();
            document.querySelectorAll('.condition-option').forEach(option => {
                option.classList.toggle('is-selected', option.querySelector('input').value === condition);
            });
            document.getElementById('remarksRequired').style.display = condition !== 'Good' ? 'inline' : 'none';
            if (condition === 'Good') inspectRemarks.classList.remove('is-invalid');
        }

        function syncRemarksCounter() {
            remarksCounter.textContent = `${inspectRemarks.value.length} / 1000`;
            if (inspectRemarks.value.trim()) inspectRemarks.classList.remove('is-invalid');
        }

        document.querySelectorAll('input[name="inspectCondition"]').forEach(input => {
            input.addEventListener('change', syncConditionUi);
        });
        inspectRemarks.addEventListener('input', syncRemarksCounter);

        // The photo the admin uploaded for this equipment; a placeholder when
        // there is none or the file cannot be loaded.
        const equipmentImg = document.getElementById('reviewEquipmentImg');
        const equipmentPlaceholder = document.getElementById('reviewEquipmentPlaceholder');

        function showEquipmentPlaceholder() {
            equipmentImg.style.display = 'none';
            equipmentImg.removeAttribute('src');
            equipmentPlaceholder.style.display = 'flex';
        }

        function showEquipmentPhoto(url, name) {
            if (!url) {
                showEquipmentPlaceholder();
                return;
            }
            equipmentImg.alt = `Photo of ${name}`;
            equipmentImg.src = url;
            equipmentImg.style.display = 'block';
            equipmentPlaceholder.style.display = 'none';
        }

        equipmentImg.addEventListener('error', showEquipmentPlaceholder);

        window.openReviewModal = function (id) {
            const r = returns.find(item => item.id === id);
            if (!r) return;

            document.getElementById('reviewTitle').textContent = `Return ${r.returnId}`;
            document.getElementById('reviewUserAvatar').src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(r.user) + '&background=385585&color=fff';
            document.getElementById('reviewUserName').textContent = r.user;
            document.getElementById('reviewUserMeta').textContent = `${r.role} · ${r.department}`;

            const badge = document.getElementById('reviewStatusBadge');
            badge.textContent = r.status;
            badge.className = 'status-badge ' + (STATUS_CLASS[r.status] || 'status-pending');

            const reported = document.getElementById('reviewReportedCondition');
            reported.textContent = r.reportedCondition;
            reported.className = 'condition-tag ' + conditionClass(r.reportedCondition);
            document.getElementById('reviewReportedRemarks').textContent = r.reportedRemarks || 'No notes from the user.';

            showEquipmentPhoto(r.equipmentImage, r.equipment);
            document.getElementById('reviewEquipment').textContent = `${r.equipment} (${r.category})`;
            document.getElementById('reviewEquipmentId').textContent = r.equipmentId;
            document.getElementById('reviewQty').textContent = r.quantity + ' pc(s)';
            document.getElementById('reviewBorrowDate').textContent = r.borrowDate;
            document.getElementById('reviewDueDate').textContent = r.dueDate;
            document.getElementById('reviewSubmitted').textContent = r.submittedFull;

            const footer = document.getElementById('reviewActionsFooter');
            document.getElementById('inspectionForm').style.display = r.canReview ? 'flex' : 'none';
            document.getElementById('inspectionResult').style.display = r.canReview ? 'none' : 'flex';

            if (r.canReview) {
                // Start from what the user reported when it is one of the inspection options.
                setCondition(['Good', 'Damaged'].includes(r.reportedCondition) ? r.reportedCondition : 'Good');
                inspectRemarks.value = '';
                inspectRemarks.classList.remove('is-invalid');
                syncRemarksCounter();

                footer.innerHTML = `
                    <button type="button" class="btn-modal-close" id="reviewCancelBtn">Close</button>
                    <button type="button" class="btn-modal-reject-trigger" id="rejectReturnBtn">Reject Return</button>
                    <button type="button" class="btn-modal-approve" id="confirmReturnBtn"><i class="fa-solid fa-check"></i> Confirm Return</button>
                `;
                document.getElementById('rejectReturnBtn').addEventListener('click', () => triggerRejectReturn(r.id));
                document.getElementById('confirmReturnBtn').addEventListener('click', () => confirmReturn(r.id));
            } else {
                const confirmed = r.status === 'Confirmed';
                document.getElementById('resultConditionGroup').style.display = confirmed ? 'block' : 'none';
                document.getElementById('resultRemarksGroup').style.display = confirmed ? 'block' : 'none';
                document.getElementById('resultReasonGroup').style.display = confirmed ? 'none' : 'block';

                const result = document.getElementById('resultCondition');
                result.textContent = r.condition;
                result.className = 'condition-tag ' + conditionClass(r.condition);
                document.getElementById('resultRemarks').textContent = r.inspectionRemarks || 'None';
                document.getElementById('resultReason').textContent = r.rejectReason || 'No reason provided.';
                document.getElementById('resultReviewer').textContent = r.reviewedBy !== '—' ? `${r.reviewedBy} · ${r.reviewedAt}` : '—';

                const photoGroup = document.getElementById('resultPhotoGroup');
                if (confirmed && r.photo) {
                    document.getElementById('resultPhoto').src = r.photo;
                    document.getElementById('resultPhotoLink').href = r.photo;
                    photoGroup.style.display = 'block';
                } else {
                    document.getElementById('resultPhoto').src = '';
                    photoGroup.style.display = 'none';
                }

                footer.innerHTML = `<button type="button" class="btn-modal-close" style="width: 100%;" id="reviewCancelBtn">Close</button>`;
            }

            document.getElementById('reviewCancelBtn').addEventListener('click', closeReviewModal);
            document.querySelector('#reviewModal .review-scroll').scrollTop = 0;
            reviewModal.classList.add('show');
        };

        function closeReviewModal() {
            reviewModal.classList.remove('show');
        }

        function post(url, payload) {
            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            Object.entries(payload).forEach(([key, value]) => formData.append(key, value));

            busy = true;
            return fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(res => res.json()
                    .catch(() => ({ message: 'Unexpected response from the server (HTTP ' + res.status + ').' }))
                    .then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        if (Array.isArray(data.returns)) {
                            returns = data.returns;
                            renderTable();
                        }
                        return data;
                    }
                    let message = data && data.message;
                    if (data && data.errors) {
                        const first = Object.values(data.errors)[0];
                        message = Array.isArray(first) ? first[0] : first;
                    }
                    throw new Error(message || 'Unable to update the return.');
                })
                .finally(() => { busy = false; });
        }

        function confirmReturn(id) {
            const condition = selectedCondition();
            const remarks = inspectRemarks.value.trim();

            if (condition !== 'Good' && !remarks) {
                inspectRemarks.classList.add('is-invalid');
                inspectRemarks.focus();
                showNotification('Remarks Required', 'Please describe the damage or the missing parts before confirming.', 'error');
                return;
            }

            if (!confirm(`Confirm that this equipment was physically returned in ${condition} condition?`)) return;

            const button = document.getElementById('confirmReturnBtn');
            const buttons = document.querySelectorAll('#reviewActionsFooter button');
            buttons.forEach(b => { b.disabled = true; });
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            post(URL_CONFIRM, { transaction_id: id, condition, remarks })
                .then(data => {
                    closeReviewModal();
                    showNotification('Return Confirmed', data.message, 'success');
                })
                .catch(err => {
                    showNotification('Action Failed', err.message, 'error');
                    buttons.forEach(b => { b.disabled = false; });
                    button.innerHTML = '<i class="fa-solid fa-check"></i> Confirm Return';
                });
        }

        window.triggerRejectReturn = function (id) {
            document.getElementById('rejectReturnId').value = id;
            document.getElementById('rejectReturnReason').value = '';
            rejectModal.classList.add('show');
        };

        rejectForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const id = parseInt(document.getElementById('rejectReturnId').value, 10);
            const reason = document.getElementById('rejectReturnReason').value.trim();

            if (!id || !reason) {
                showNotification('Input Error', 'Please state why the return is being rejected.', 'error');
                return;
            }

            post(URL_REJECT, { transaction_id: id, reason })
                .then(data => {
                    rejectModal.classList.remove('show');
                    closeReviewModal();
                    showNotification('Return Rejected', data.message, 'error');
                })
                .catch(err => showNotification('Action Failed', err.message, 'error'));
        });

        // Close modals
        document.getElementById('closeReviewBtn').addEventListener('click', closeReviewModal);
        document.getElementById('closeRejectReturnBtn').addEventListener('click', () => rejectModal.classList.remove('show'));
        reviewModal.addEventListener('click', (e) => {
            if (e.target === reviewModal) closeReviewModal();
        });
        rejectModal.addEventListener('click', (e) => {
            if (e.target === rejectModal) rejectModal.classList.remove('show');
        });

        searchInput.addEventListener('input', () => { currentPage = 1; renderTable(); });
        filterSelect.addEventListener('change', () => { currentPage = 1; renderTable(); });

        renderTable();
    });
</script>
@endpush
