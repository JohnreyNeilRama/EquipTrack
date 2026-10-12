{{-- Shared Requests table styles (Admin Requests + Department Borrow Requests). --}}
<style>
    /* Custom overrides to match the exact mockup screenshot */
    .requests-page-title {
        font-size: 28px;
        font-weight: 700;
        color: #385585; /* Specific blue for title */
        margin-bottom: 24px;
    }
    .dark-theme .requests-page-title {
        color: #60a5fa;
    }

    /* Actions container spacing */
    .action-cell {
        padding-right: 20px !important;
    }
    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* Row clickable cursor for viewing details */
    .admin-table-row {
        cursor: pointer;
    }
    .admin-table-row:hover td {
        background-color: rgba(56, 85, 133, 0.03) !important;
    }

    /* Reject/View buttons styling */
    .btn-approve, .btn-reject, .btn-view-only {
        border: none;
        border-radius: 6px;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 32px;
    }

    .btn-approve {
        background-color: #3b5998;
        color: #ffffff;
    }
    .btn-approve:hover {
        background-color: #2f477a;
        transform: translateY(-1px);
    }

    .btn-reject {
        background-color: #ff0000;
        color: #ffffff;
    }
    .btn-reject:hover {
        background-color: #cc0000;
        transform: translateY(-1px);
    }

    .btn-view-only {
        background-color: rgba(79, 107, 156, 0.1);
        color: #4f6b9c;
    }
    .btn-view-only:hover {
        background-color: #4f6b9c;
        color: #ffffff;
        transform: translateY(-1px);
    }

    .dark-theme .btn-approve {
        background-color: #3b82f6;
    }
    .dark-theme .btn-approve:hover {
        background-color: #2563eb;
    }
    .dark-theme .btn-view-only {
        background-color: rgba(129, 140, 248, 0.1);
        color: #818cf8;
    }
    .dark-theme .btn-view-only:hover {
        background-color: #818cf8;
        color: #0f172a;
    }

    /* Modal Details Form styling */
    .eq-modal-card.request-details-card {
        max-width: 680px !important;
    }

    .detail-main-content {
        display: flex;
        gap: 28px;
        margin-bottom: 20px;
        align-items: stretch;
    }
    .detail-left-side {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .detail-img-container {
        width: 100%;
        height: 190px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        background-color: var(--bg-color);
        border: 1px solid var(--border-color);
    }
    .detail-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .detail-right-side {
        flex: 1.3;
        min-width: 0;
    }

    .detail-form-grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .detail-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        text-align: left;
        width: 100%;
    }
    .detail-form-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .detail-form-control {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-main);
        background-color: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        outline: none;
        font-family: inherit;
        box-sizing: border-box;
    }
    .detail-form-control[readonly] {
        cursor: default;
    }

    .detail-lower-section {
        border-top: 1px solid var(--border-color);
        padding-top: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    @media (max-width: 768px) {
        .detail-main-content {
            flex-direction: column;
            gap: 16px;
        }
        .detail-img-container {
            height: 150px;
        }
    }

    .textarea-control {
        resize: none;
        font-family: inherit;
        line-height: 1.5;
    }
    .text-danger-control {
        border-color: rgba(239, 68, 68, 0.3);
        background-color: rgba(239, 68, 68, 0.03);
        color: #ef4444;
    }
    .dark-theme .text-danger-control {
        border-color: rgba(248, 113, 113, 0.3);
        background-color: rgba(248, 113, 113, 0.05);
        color: #f87171;
    }

    /* Adjust detail status badge align */
    .status-badge-wrapper {
        display: flex;
        justify-content: flex-start;
    }

    .no-requests-message {
        text-align: center;
        color: var(--text-muted);
        padding: 40px;
    }

    /* Two-party approval (Expensive equipment) */
    .class-tag-expensive {
        display: inline-block;
        margin-left: 6px;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        background-color: #ede9fe;
        color: #6d28d9;
        vertical-align: middle;
    }
    .dark-theme .class-tag-expensive {
        background-color: rgba(139, 92, 246, 0.18);
        color: #c4b5fd;
    }
    .approval-parties {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 10px;
        margin-top: 6px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }
    .approval-party .party-approved { color: #10b981; }
    .approval-party .party-rejected { color: #ef4444; }
    .approval-party .party-pending { color: var(--text-muted); }
    .awaiting-note {
        display: inline-block;
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #b45309;
    }
    .dark-theme .awaiting-note { color: #fbbf24; }
    .no-requests-message i {
        display: block;
        font-size: 32px;
        color: var(--primary-color);
        margin-bottom: 12px;
        opacity: 0.7;
    }
</style>
