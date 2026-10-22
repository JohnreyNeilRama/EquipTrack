{{-- Shared Penalties page styles (Admin + Lab Personnel). Same table, badge and
     modal design as the Requests / Return Confirmation pages. --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}">
{{-- Damage & Penalty Record view (same design as the Return Confirmation form) --}}
<link rel="stylesheet" href="{{ asset('ccs/penalty-record.css') }}?v={{ filemtime(public_path('ccs/penalty-record.css')) }}">
<style>
    .penalties-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .btn-add-penalty {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 40px;
        padding: 0 18px;
        border: none;
        border-radius: 10px;
        background-color: #385585;
        color: #ffffff;
        font-family: inherit;
        font-size: var(--fs-label);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-add-penalty:hover { background-color: #2f477a; }
    .btn-add-penalty:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(56, 85, 133, 0.35); }

    .summary-card-icon.icon-orange { background-color: #fef3c7; color: #d97706; }
    .dark-theme .summary-card-icon.icon-orange { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }

    /* Search + up to three filters on one line: search takes the free space
       (never narrower than 240px), filters keep their width, and the bar
       wraps instead of squeezing on narrow screens. */
    .controls-left.penalties-controls {
        max-width: none;
        flex-wrap: wrap;
        gap: 12px;
    }
    .penalties-controls .search-box-wrapper { flex: 1 1 240px; }
    .penalties-controls .filter-select-wrapper { flex: 0 0 160px; }
    .penalties-controls .filter-select-wrapper:has(#filterDepartment) { flex-basis: 220px; }
    @media (max-width: 576px) {
        .penalties-controls .filter-select-wrapper,
        .penalties-controls .filter-select-wrapper:has(#filterDepartment) { flex: 1 1 140px; }
    }

    /* Table layout: User and Equipment share the free width and shorten with
       "…" (max-width: 0 lets the text shrink instead of widening the table;
       the full text is in a hover tooltip and the View form). Every other
       column holds a short one-line value and shrinks to fit it. */
    .admin-table-card .penalties-table th,
    .admin-table-card .penalties-table td {
        padding-left: 10px !important;
        padding-right: 10px !important;
        vertical-align: middle;
    }
    .admin-table-card .penalties-table th:first-child,
    .admin-table-card .penalties-table td:first-child {
        padding-left: 20px !important;
    }
    .admin-table-card .penalties-table th:last-child,
    .admin-table-card .penalties-table td:last-child {
        padding-right: 20px !important;
    }
    .penalties-table .col-fit {
        width: 1%;
        white-space: nowrap;
    }
    .penalties-table .col-user {
        width: 26%;
        min-width: 120px;
        max-width: 0;
    }
    .penalties-table .col-equipment {
        width: 34%;
        min-width: 130px;
        max-width: 0;
    }
    .penalties-table .col-amount { text-align: right; }
    .penalties-table .action-column,
    .penalties-table .action-cell { text-align: center; }
    /* Same buttons as the other tables, a little narrower so the text columns keep their room */
    .penalties-table .action-cell .btn-approve,
    .penalties-table .action-cell .btn-view-only { padding: 6px 12px; }
    .penalties-table .cell-main,
    .penalties-table .cell-sub {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .penalties-table .cell-main { font-weight: 500; color: var(--text-main); }
    .penalties-table .cell-sub { margin-top: 2px; font-size: var(--fs-small); color: var(--text-muted); }

    .penalty-id { font-weight: 600; color: var(--text-main); font-variant-numeric: tabular-nums; }
    .penalty-amount { font-weight: 700; color: var(--text-main); font-variant-numeric: tabular-nums; }
    .penalty-amount.none { font-weight: 500; color: var(--text-muted); }

    .type-tag {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: var(--fs-badge);
        font-weight: 600;
        white-space: nowrap;
    }
    .type-late-return { background-color: #dbeafe; color: #1d4ed8; }
    .type-damaged { background-color: #fee2e2; color: #b91c1c; }
    .type-incomplete { background-color: #fef3c7; color: #b45309; }
    .type-lost { background-color: #ede9fe; color: #6d28d9; }
    .dark-theme .type-late-return { background-color: rgba(59, 130, 246, 0.15); color: #93c5fd; }
    .dark-theme .type-damaged { background-color: rgba(239, 68, 68, 0.15); color: #f87171; }
    .dark-theme .type-incomplete { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    .dark-theme .type-lost { background-color: rgba(139, 92, 246, 0.18); color: #c4b5fd; }

    .status-badge.status-waived { background-color: #e2e8f0; color: #475569; }
    .dark-theme .status-badge.status-waived { background-color: rgba(148, 163, 184, 0.15); color: #cbd5e1; }

    /* ------------------------------------------------------------------
       Edit / Settle / Waive / Add form. Same modal shell as the rest of the
       admin (modal-overlay, modal-inner-card); inside it: a header, a
       scrolling body and a footer that stays in view. The record view itself
       is styled by public/ccs/penalty-record.css.
       ------------------------------------------------------------------ */
    .eq-modal-card.penalty-form-card { max-width: 520px !important; width: calc(100% - 32px) !important; }

    .modal-inner-card.penalty-sheet {
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 110px);
        padding: 28px 28px 0;
    }
    .penalty-sheet > .modal-close { top: 22px; right: 22px; }

    .penalty-sheet-head {
        padding: 0 44px 18px 0;
        border-bottom: 1px solid var(--border-color);
    }
    .penalty-sheet-title {
        margin: 0;
        font-size: var(--fs-modal-title);
        font-weight: 700;
        color: var(--section-title-color);
        font-variant-numeric: tabular-nums;
    }
    .penalty-sheet-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px 6px;
        margin: 6px 0 0;
        font-size: var(--fs-label);
        color: var(--text-muted);
    }
    .penalty-sheet-meta[hidden] { display: none; }
    .penalty-sheet-meta strong { color: var(--text-main); font-weight: 600; }
    /* Form context: "PEN-0001 · [Damaged] · ₱2,500.00 · Name…" */
    .penalty-sheet-meta .ctx-part { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
    .penalty-sheet-meta .ctx-part::before { content: "·"; color: var(--text-muted); }
    /* The borrower gets its own line under the ID / type / amount */
    .penalty-sheet-meta .ctx-user {
        display: block;
        flex-basis: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .penalty-sheet-meta .ctx-user::before { content: none; }

    /* Long records scroll here; the header and the buttons stay put. */
    .penalty-sheet-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 20px 2px 22px;
        margin: 0 -2px;
    }
    /* Sections keep their height; the body scrolls instead of squeezing them. */
    .penalty-sheet-body > * { flex-shrink: 0; }

    /* Footer: Cancel, then the primary action */
    .penalty-sheet-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 -28px;
        padding: 16px 28px 22px;
        border-top: 1px solid var(--border-color);
        background-color: var(--card-bg);
        border-radius: 0 0 24px 24px;
    }
    .penalty-form .penalty-sheet-footer { justify-content: flex-end; }

    .btn-sheet {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 40px;
        padding: 0 18px;
        border-radius: 10px;
        border: 1px solid transparent;
        font-family: inherit;
        font-size: var(--fs-label);
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s;
    }
    .btn-sheet:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(56, 85, 133, 0.35); }
    .btn-sheet:disabled { opacity: 0.65; cursor: progress; }
    .btn-sheet-secondary { background-color: var(--bg-color); border-color: var(--border-color); color: var(--text-main); }
    .btn-sheet-secondary:hover { background-color: var(--primary-light); }
    .btn-sheet-primary { min-width: 140px; background-color: #385585; color: #ffffff; }
    .btn-sheet-primary:hover { background-color: #2f477a; }
    .btn-sheet-primary.is-danger { background-color: #dc2626; }
    .btn-sheet-primary.is-danger:hover { background-color: #b91c1c; }
    .btn-sheet-primary.is-success { background-color: #10b981; }
    .btn-sheet-primary.is-success:hover { background-color: #059669; }

    /* Form fields */
    .penalty-form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    .penalty-form [hidden] { display: none !important; }
    .penalty-form-body { gap: 18px; }
    .penalty-field-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }
    .penalty-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; text-align: left; }
    .penalty-form .detail-form-label {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin: 0;
        font-size: var(--fs-label);
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        color: var(--text-main);
    }
    .penalty-field .req { color: #ef4444; }
    .penalty-field .optional { font-size: var(--fs-small); font-weight: 500; color: var(--text-muted); }
    .penalty-form .detail-form-control {
        height: 42px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .penalty-form textarea.detail-form-control { height: auto; min-height: 96px; resize: vertical; line-height: 1.5; }
    .penalty-form select.detail-form-control {
        appearance: none;
        -webkit-appearance: none;
        padding-right: 38px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        cursor: pointer;
        text-overflow: ellipsis;
    }
    .penalty-form .detail-form-control:hover { border-color: #94a3b8; }
    .penalty-form .detail-form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(56, 85, 133, 0.15);
    }
    .penalty-form .detail-form-control.is-invalid { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12); }

    /* ₱ inside the amount box */
    .penalty-input-prefix { position: relative; }
    .penalty-input-prefix > span {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-weight: 600;
        color: var(--text-muted);
        pointer-events: none;
    }
    .penalty-input-prefix .detail-form-control { padding-left: 30px; font-variant-numeric: tabular-nums; }

    .penalty-hint { margin: 0; font-size: var(--fs-small); color: var(--text-muted); text-align: left; }
    .penalty-note {
        display: flex;
        gap: 10px;
        margin: 0;
        padding: 12px 14px;
        border-radius: 10px;
        background-color: var(--primary-light);
        font-size: var(--fs-small);
        line-height: 1.5;
        color: var(--text-main);
    }
    .penalty-note i { margin-top: 2px; color: var(--primary-color); }

    @media (max-width: 640px) {
        .modal-inner-card.penalty-sheet { padding: 22px 18px 0; }
        .penalty-sheet-footer { margin: 0 -18px; padding: 14px 18px 18px; }
        /* Buttons stack full-width, primary action on top */
        .penalty-sheet-footer { flex-direction: column-reverse; align-items: stretch; }
        .penalty-sheet-footer .btn-sheet { width: 100%; }
    }
</style>
