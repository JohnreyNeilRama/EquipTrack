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
    /* Seven columns: slightly tighter cells than the six-column requests table
       so the Action column stays in view on laptop screens. */
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
        font-size: var(--fs-small);
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
        font-size: var(--fs-badge);
        font-weight: 600;
        color: var(--text-muted);
    }

    /* Long free text is shortened with "…" so one value can't stretch the
       row or squeeze the Status / Condition / Action columns. Each cell keeps
       its full text in a hover tooltip, and the review modal shows it in full. */

    /* User and Equipment share the table's width (max-width: 0 lets their
       text shorten instead of widening the table), with a floor so names stay
       readable when the table itself scrolls on small screens. */
    .returns-table .col-user {
        width: 22%;
        min-width: 120px;
        max-width: 0;
    }
    .returns-table .col-equipment {
        width: 30%;
        min-width: 140px;
        max-width: 0;
    }
    /* Return ID and Submitted hold short, one-line values, so they shrink to
       fit (same column widths as the Borrow Requests table). Status,
       Condition and Action keep their natural widths. */
    .returns-table th:nth-child(1),
    .returns-table th:nth-child(4) {
        width: 1%;
    }

    /* Borrower name: one line */
    .returns-table .requester-name {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Equipment cell: name (one line, ending in "…"), then the serial number and, only
       when more than one unit was borrowed, a "×N" marker. Department and
       quantity are in the review modal. */
    .returns-table .equipment-name {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }
    .equipment-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
        min-width: 0;
    }
    .equipment-id {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--fs-small);
        color: var(--text-muted);
        font-variant-numeric: tabular-nums;
    }
    /* Expensive tag sits in the line under the name, after the serial number;
       when there is not enough room it moves to its own line instead of
       shortening the serial number. */
    .equipment-meta:has(.class-tag-expensive) {
        flex-wrap: wrap;
        row-gap: 4px;
    }
    .equipment-meta .class-tag-expensive {
        flex-shrink: 0;
        margin-left: 0;
        line-height: 1.4;
    }
    .equipment-qty {
        flex-shrink: 0;
        padding: 0 6px;
        border-radius: 6px;
        font-size: var(--fs-small);
        font-weight: 600;
        line-height: 1.5;
        color: var(--text-muted);
        background-color: var(--bg-color);
        border: 1px solid var(--border-color);
        font-variant-numeric: tabular-nums;
    }

    /* Dates are short and fixed-format: kept on one line, never shortened */
    .returns-table .col-submitted {
        white-space: nowrap;
    }

    /* View button (reviewed returns). The shared dark style is a faint indigo
       tint with no border, which nearly disappears on the dark table; here it
       gets a solid slate fill, a visible border and EquipTrack blue text. Light
       mode gets the same treatment in the light palette. Same size and shape
       as the Review button. */
    .returns-table .btn-view-only {
        background-color: rgba(56, 85, 133, 0.08);
        border: 1px solid rgba(56, 85, 133, 0.35);
        color: #385585;
    }
    .returns-table .btn-view-only:hover {
        background-color: #385585;
        border-color: #385585;
        color: #ffffff;
    }
    .returns-table .btn-view-only:focus-visible {
        outline: 2px solid #385585;
        outline-offset: 2px;
    }
    .dark-theme .returns-table .btn-view-only {
        background-color: #334155;
        border-color: #475569;
        color: #93c5fd;
    }
    .dark-theme .returns-table .btn-view-only:hover {
        background-color: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }
    .dark-theme .returns-table .btn-view-only:focus-visible {
        outline-color: #60a5fa;
    }

    .return-id {
        font-weight: 600;
        white-space: nowrap;
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
        font-size: var(--fs-modal-title);
        font-weight: 600;
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
        font-size: var(--fs-body);
        font-weight: 600;
        color: var(--text-main);
    }
    .review-step {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: var(--fs-small);
        font-weight: 600;
        color: #ffffff;
        background-color: #385585;
        flex-shrink: 0;
    }
    .dark-theme .review-step { background-color: #3b82f6; }
    .review-hint {
        margin: -4px 0 0;
        font-size: var(--fs-small);
        color: var(--text-muted);
    }

    /* The returned item: reference photo beside its name and details */
    .return-item {
        display: grid;
        grid-template-columns: 200px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
        padding: 14px;
        border: 1px solid var(--border-color);
        border-radius: 14px;
        background-color: var(--bg-color);
    }

    /* Original equipment photo (from Equipment Management). Same treatment as
       the equipment cards: the whole item is shown, never stretched or
       cropped, with a blurred copy of the photo filling the spare space. */
    .return-photo {
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .return-photo-frame {
        position: relative;
        aspect-ratio: 4 / 3;
        border-radius: 12px;
        overflow: hidden;
        background-color: #f1f5f9;
        box-shadow: inset 0 0 0 1px var(--border-color);
        isolation: isolate;
    }
    .return-photo-link {
        position: absolute;
        inset: 0;
        display: block;
        cursor: zoom-in;
    }
    .return-photo-link:focus-visible {
        outline: 3px solid rgba(56, 85, 133, 0.45);
        outline-offset: -3px;
    }
    .return-photo-backdrop {
        position: absolute;
        top: -32px;
        left: -32px;
        width: calc(100% + 64px);
        height: calc(100% + 64px);
        max-width: none;
        object-fit: cover;
        filter: blur(16px);
    }
    .return-photo-img {
        position: relative;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }
    .return-photo-link:hover .return-photo-img { transform: scale(1.03); }
    .return-photo-zoom {
        position: absolute;
        right: 8px;
        bottom: 8px;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--fs-small);
        color: #ffffff;
        background-color: rgba(15, 23, 42, 0.6);
        opacity: 0.85;
        transition: opacity 0.2s, background-color 0.2s;
    }
    .return-photo-link:hover .return-photo-zoom,
    .return-photo-link:focus-visible .return-photo-zoom {
        opacity: 1;
        background-color: rgba(15, 23, 42, 0.8);
    }
    .return-photo-placeholder {
        position: absolute;
        inset: 0;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: #94a3b8;
        font-size: var(--fs-small);
        font-weight: 500;
    }
    .return-photo-placeholder i { font-size: 26px; }
    .return-photo-frame.is-missing .return-photo-link { display: none; }
    .return-photo-frame.is-missing .return-photo-placeholder { display: flex; }
    .return-photo figcaption {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: var(--fs-small);
        font-weight: 600;
        color: var(--text-muted);
    }
    .dark-theme .return-photo-frame { background-color: #0f172a; }
    .dark-theme .return-photo-placeholder {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #64748b;
    }
    @media (prefers-reduced-motion: reduce) {
        .return-photo-img { transition: none; }
        .return-photo-link:hover .return-photo-img { transform: none; }
    }

    .return-item-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        padding-top: 2px;
    }
    .return-item-label,
    .return-item-facts dt,
    .return-dates dt {
        font-size: var(--fs-badge);
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .return-item-name {
        margin: 2px 0 0;
        font-size: var(--fs-card-title);
        font-weight: 600;
        line-height: 1.35;
        color: var(--text-main);
        overflow-wrap: anywhere;
    }
    .return-item-category {
        margin: 2px 0 0;
        font-size: var(--fs-label);
        color: var(--text-muted);
    }
    .return-item-facts {
        margin: 14px 0 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }
    .return-item-facts dd,
    .return-dates dd {
        margin: 3px 0 0;
        font-size: var(--fs-body);
        font-weight: 600;
        color: var(--text-main);
        overflow-wrap: anywhere;
    }
    .return-item-facts dd.mono {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }
    .return-item-hint {
        margin: 12px 0 0;
        font-size: var(--fs-small);
        line-height: 1.4;
        color: var(--text-muted);
    }

    /* Borrow / due / submitted dates as one divided strip */
    .return-dates {
        margin: 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
    }
    .return-dates > div {
        padding: 10px 14px;
        min-width: 0;
    }
    .return-dates > div + div {
        border-left: 1px solid var(--border-color);
    }

    @media (max-width: 640px) {
        .return-item {
            grid-template-columns: minmax(0, 1fr);
            gap: 14px;
        }
        .return-photo-frame {
            aspect-ratio: 16 / 10;
            max-height: 220px;
        }
        .return-dates {
            grid-template-columns: minmax(0, 1fr);
        }
        .return-dates > div + div {
            border-left: none;
            border-top: 1px solid var(--border-color);
        }
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
        font-size: var(--fs-badge);
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 4px;
    }
    .review-info-item dd {
        margin: 0;
        font-size: var(--fs-body);
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
        font-size: var(--fs-small);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
    }
    .reported-notes {
        margin: 0;
        font-size: var(--fs-label);
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
        font-size: var(--fs-small);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        padding: 0;
    }
    .review-field-label .req { color: #ef4444; text-transform: none; letter-spacing: 0; }
    .review-field-label .optional { font-weight: 500; text-transform: none; letter-spacing: 0; }
    .char-counter {
        font-size: var(--fs-badge);
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
        font-size: var(--fs-label);
        font-weight: 600;
        color: var(--text-main);
    }
    .condition-option-desc {
        font-size: var(--fs-badge);
        line-height: 1.35;
        color: var(--text-muted);
    }
    .condition-option.is-selected[data-tone="good"] { border-color: #10b981; background-color: rgba(16, 185, 129, 0.06); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12); }
    .condition-option.is-selected[data-tone="damaged"] { border-color: #ef4444; background-color: rgba(239, 68, 68, 0.05); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12); }
    .condition-option.is-selected[data-tone="incomplete"] { border-color: #f59e0b; background-color: rgba(245, 158, 11, 0.06); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.14); }

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
        font-size: var(--fs-small);
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
                        <th class="col-user">User</th>
                        <th class="col-equipment">Equipment</th>
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

                        {{-- The returned item: its reference photo beside what it is --}}
                        <div class="return-item">
                            {{-- Original photo from Equipment Management, to compare with the returned unit --}}
                            <figure class="return-photo">
                                <div class="return-photo-frame" id="reviewEquipmentFrame">
                                    <a href="#" class="return-photo-link" id="reviewEquipmentLink" target="_blank" rel="noopener" title="Open the full-size photo">
                                        <img src="" alt="" class="return-photo-backdrop" id="reviewEquipmentBackdrop" aria-hidden="true">
                                        <img src="" alt="" class="return-photo-img" id="reviewEquipmentImg">
                                        <span class="return-photo-zoom" aria-hidden="true"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                    </a>
                                    <div class="return-photo-placeholder" id="reviewEquipmentPlaceholder">
                                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                                        <span>No equipment photo available</span>
                                    </div>
                                </div>
                                <figcaption>
                                    <i class="fa-regular fa-image" aria-hidden="true"></i> Reference photo
                                </figcaption>
                            </figure>

                            <div class="return-item-body">
                                <span class="return-item-label">Equipment</span>
                                <p class="return-item-name" id="reviewEquipment">—</p>
                                <p class="return-item-category" id="reviewEquipmentCategory">—</p>
                                <dl class="return-item-facts">
                                    <div>
                                        <dt>Equipment ID / Serial No.</dt>
                                        <dd class="mono" id="reviewEquipmentId">—</dd>
                                    </div>
                                    <div>
                                        <dt>Quantity Borrowed</dt>
                                        <dd id="reviewQty">—</dd>
                                    </div>
                                </dl>
                                <p class="return-item-hint" id="reviewEquipmentHint">Compare the returned unit with this photo from Equipment Management.</p>
                            </div>
                        </div>

                        <dl class="return-dates">
                            <div>
                                <dt>Borrow Date</dt>
                                <dd id="reviewBorrowDate">—</dd>
                            </div>
                            <div>
                                <dt>Due Date</dt>
                                <dd id="reviewDueDate">—</dd>
                            </div>
                            <div>
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
                        <td colspan="7" class="no-requests-message">
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
                    <td class="col-user">
                        <div class="requester-info">
                            <span class="requester-name" title="${escapeHTML(r.user)}">${escapeHTML(r.user)}</span>
                            <span class="requester-role">${escapeHTML(r.role)}</span>
                        </div>
                    </td>
                    <td class="col-equipment">
                        <span class="equipment-name" title="${escapeHTML(r.equipment)}">${escapeHTML(r.equipment)}</span>
                        <span class="equipment-meta">
                            <span class="equipment-id" title="${escapeHTML(r.equipmentId)}">${escapeHTML(r.equipmentId)}</span>
                            ${Number(r.quantity) > 1 ? `<span class="equipment-qty" title="Quantity borrowed">×${escapeHTML(r.quantity)}</span>` : ''}
                            ${r.requiresBothApprovals ? '<span class="class-tag-expensive">Expensive</span>' : ''}
                        </span>
                    </td>
                    <td class="col-submitted">${escapeHTML(r.submitted)}</td>
                    <td><span class="status-badge ${STATUS_CLASS[r.status] || 'status-pending'}">${escapeHTML(r.status)}</span></td>
                    <td>
                        ${conditionTag(r.condition)}
                        <span class="condition-source">${r.conditionSource === 'inspected' ? 'Inspected' : 'Reported by user'}</span>
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
        const equipmentFrame = document.getElementById('reviewEquipmentFrame');
        const equipmentLink = document.getElementById('reviewEquipmentLink');
        const equipmentBackdrop = document.getElementById('reviewEquipmentBackdrop');
        const equipmentHint = document.getElementById('reviewEquipmentHint');

        function showEquipmentPlaceholder() {
            equipmentFrame.classList.add('is-missing');
            equipmentHint.hidden = true;
            equipmentImg.removeAttribute('src');
            equipmentBackdrop.removeAttribute('src');
            equipmentLink.removeAttribute('href');
        }

        function showEquipmentPhoto(url, name) {
            if (!url) {
                showEquipmentPlaceholder();
                return;
            }
            equipmentImg.alt = `Photo of ${name}`;
            equipmentImg.src = url;
            equipmentBackdrop.src = url;
            equipmentLink.href = url;
            equipmentLink.setAttribute('aria-label', `Open the full-size photo of ${name}`);
            equipmentFrame.classList.remove('is-missing');
            equipmentHint.hidden = false;
        }

        equipmentImg.addEventListener('error', showEquipmentPlaceholder);

        // Borrower avatar: their own profile picture; otherwise (or if it can't
        // be loaded) their initials on the EquipTrack blue, drawn locally so it
        // never depends on an outside service.
        const borrowerAvatar = document.getElementById('reviewUserAvatar');

        function initialsAvatar(name) {
            const initials = String(name || '')
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map(word => Array.from(word)[0])
                .join('')
                .toUpperCase()
                .replace(/[&<>"']/g, '') || 'U';
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">`
                + `<rect width="96" height="96" fill="#385585"/>`
                + `<text x="48" y="48" dy="0.35em" text-anchor="middle" fill="#ffffff" `
                + `font-family="Inter, Segoe UI, Arial, sans-serif" font-size="36" font-weight="600">${initials}</text></svg>`;
            return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
        }

        function showBorrowerAvatar(url, name) {
            const fallback = initialsAvatar(name);
            borrowerAvatar.alt = `${name || 'Borrower'}'s profile picture`;
            borrowerAvatar.onerror = function () {
                this.onerror = null;
                this.src = fallback;
            };
            borrowerAvatar.src = url || fallback;
        }

        window.openReviewModal = function (id) {
            const r = returns.find(item => item.id === id);
            if (!r) return;

            document.getElementById('reviewTitle').textContent = `Return ${r.returnId}`;
            showBorrowerAvatar(r.avatar, r.user);
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
            document.getElementById('reviewEquipment').textContent = r.equipment;
            document.getElementById('reviewEquipmentCategory').textContent = r.category;
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
