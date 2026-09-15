@extends('layouts.admin')

@section('title', 'EquipTrack - Equipment Requests')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
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
    .no-requests-message i {
        display: block;
        font-size: 32px;
        color: var(--primary-color);
        margin-bottom: 12px;
        opacity: 0.7;
    }
</style>
@endpush

@section('content')
    {{-- Requests Content Container --}}
    <div class="requests-container">
        {{-- Header Section --}}
        <div class="equipment-header-section">
            <h2>Requests</h2>
            <p>Review, approve, or reject student and faculty borrowing requests.</p>
        </div>

        {{-- Controls bar (Search & Filters) --}}
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchRequests" placeholder="Search by user or equipment...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterStatus">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
        </div>

        {{-- Requests Table Card --}}
        <div class="table-container card admin-table-card">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Equipment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="action-column">Action</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    {{-- Loaded dynamically via JavaScript --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- Request Details Modal --}}
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card eq-modal-card request-details-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user request</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Request Details</h3>

                {{-- Requester Profile Block --}}
                <div class="modal-requester-profile">
                    <img src="" alt="User Avatar" class="modal-requester-avatar" id="modalUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalUserName">John Doe</span>
                        <span class="modal-requester-details" id="modalUserRole">Student</span>
                    </div>
                </div>

                {{-- Main Fields Section --}}
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="modalEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">Pending</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="modalEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="modalEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Quantity Requested</label>
                                <input type="text" id="modalEqQty" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Request Date</label>
                                <input type="text" id="modalReqDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="modalBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="modalDueDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Purpose & Notes Section --}}
                <div class="detail-text-grid">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Purpose of Borrowing</label>
                        <textarea id="modalPurpose" class="detail-form-control textarea-control" rows="3" readonly></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Additional Notes</label>
                        <textarea id="modalNotes" class="detail-form-control textarea-control" rows="3" readonly></textarea>
                    </div>
                </div>
                <div class="detail-form-group" id="modalReasonGroup" style="display: none; margin-bottom: 16px;">
                    <label class="detail-form-label text-danger">Reason for Rejection</label>
                    <textarea id="modalReason" class="detail-form-control textarea-control text-danger-control" rows="2" readonly></textarea>
                </div>

                {{-- Modal Actions Footer --}}
                <div class="modal-actions-footer" id="modalActionsFooter">
                    {{-- Loaded dynamically based on status --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Rejection Reason Input Modal --}}
    <div class="modal-overlay" id="rejectReasonModal">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Provide rejection feedback</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeRejectReasonBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Reject Request</h3>

                <form id="rejectReasonForm">
                    <input type="hidden" id="rejectRequestId">
                    <div class="reject-reason-input-group">
                        <label class="detail-form-label text-danger" style="text-align: left;">Reason for Rejection</label>
                        <textarea id="rejectReasonInput" class="detail-form-control textarea-control" rows="4" placeholder="Please provide a brief explanation for rejecting this request..." required style="border-color: rgba(239, 68, 68, 0.3);"></textarea>
                    </div>
                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-reject">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_DATA = @json(route('admin.requests.data'));
        const URL_UPDATE = @json(route('admin.requests.update'));

        // Initial requests dataset loaded directly from the database
        let requests = @json($dbRequests, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // DOM Elements
        const tableBody = document.getElementById('requestsTableBody');
        const searchInput = document.getElementById('searchRequests');
        const filterSelect = document.getElementById('filterStatus');

        // Modals
        const detailsModal = document.getElementById('detailsModal');
        const closeDetailsBtn = document.getElementById('closeDetailsBtn');
        const rejectReasonModal = document.getElementById('rejectReasonModal');
        const closeRejectReasonBtn = document.getElementById('closeRejectReasonBtn');
        const rejectReasonForm = document.getElementById('rejectReasonForm');
        const rejectRequestIdInput = document.getElementById('rejectRequestId');
        const rejectReasonInput = document.getElementById('rejectReasonInput');

        // Function to fetch latest requests from backend (live polling & after updates)
        function loadRequests() {
            fetch(URL_DATA)
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.requests)) {
                        requests = data.requests;
                        renderTable();
                        updateDashboardStats();
                    }
                })
                .catch(err => console.error('Error fetching requests:', err));
        }

        // Auto-refresh requests every 5 seconds for seamless automatic updating
        setInterval(loadRequests, 5000);

        // Render Table function
        function renderTable() {
            const query = searchInput.value.toLowerCase().trim();
            const filter = filterSelect.value;
            tableBody.innerHTML = '';

            const filtered = requests.filter(req => {
                const matchesSearch = (req.user || '').toLowerCase().includes(query) ||
                                      (req.equipment || '').toLowerCase().includes(query) ||
                                      (req.role || '').toLowerCase().includes(query);
                const matchesFilter = filter === 'all' || (req.status || '').toLowerCase() === filter;
                return matchesSearch && matchesFilter;
            });

            if (filtered.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="no-requests-message">
                            <i class="fa-solid fa-folder-open"></i>
                            No requests found matching your query.
                        </td>
                    </tr>
                `;
                return;
            }

            filtered.forEach(req => {
                const tr = document.createElement('tr');
                tr.className = 'admin-table-row';

                // Click row opens details modal (excluding clicking action buttons)
                tr.addEventListener('click', (e) => {
                    if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                        openDetailsModal(req.id);
                    }
                });

                // Action buttons cells
                let actionHtml = '';
                if (req.status.toLowerCase() === 'pending') {
                    actionHtml = `
                        <div class="action-buttons">
                            <button class="btn-approve" onclick="approveRequest(${req.id})">Approve</button>
                            <button class="btn-reject" onclick="triggerRejectReason(${req.id})">Reject</button>
                        </div>
                    `;
                } else {
                    actionHtml = `
                        <button class="btn-view-only" onclick="openDetailsModal(${req.id})">
                            <i class="fa-solid fa-eye"></i> View
                        </button>
                    `;
                }

                // Format status text
                const statusClass = 'status-' + req.status.toLowerCase();
                const qtyBadge = req.quantity > 1 ? ` <span style="font-size: 11px; opacity: 0.8;">(${req.quantity} pcs)</span>` : '';

                tr.innerHTML = `
                    <td>
                        <div class="requester-info">
                            <span class="requester-name">${escapeHTML(req.user)}</span>
                        </div>
                    </td>
                    <td>${escapeHTML(req.role)}</td>
                    <td>${escapeHTML(req.equipment)}${qtyBadge}</td>
                    <td>${escapeHTML(req.date)}</td>
                    <td>
                        <span class="status-badge ${statusClass}">${escapeHTML(req.status)}</span>
                    </td>
                    <td class="action-cell">${actionHtml}</td>
                `;
                tableBody.appendChild(tr);
            });
        }

        // Approve Request function
        window.approveRequest = function(id) {
            if (!confirm('Are you sure you want to approve this equipment request?')) return;

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('request_id', id);
            formData.append('status', 'Approved');

            fetch(URL_UPDATE, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Request Approved', data.message || 'Request approved successfully!', 'success');
                    detailsModal.classList.remove('show');
                    loadRequests();
                } else {
                    showNotification('Action Failed', data.message || 'Failed to approve request.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showNotification('Error', 'Network error occurred.', 'error');
            });
        };

        // Trigger Rejection Reason modal
        window.triggerRejectReason = function(id) {
            rejectRequestIdInput.value = id;
            rejectReasonInput.value = '';
            rejectReasonModal.classList.add('show');
        };

        // Rejection reason form submission
        rejectReasonForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const id = parseInt(rejectRequestIdInput.value);
            const reason = rejectReasonInput.value.trim();

            if (!id || !reason) {
                showNotification('Input Error', 'Please provide a valid rejection reason.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('request_id', id);
            formData.append('status', 'Rejected');
            formData.append('reject_reason', reason);

            fetch(URL_UPDATE, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Request Rejected', data.message || 'Request rejected.', 'error');
                    rejectReasonModal.classList.remove('show');
                    detailsModal.classList.remove('show');
                    loadRequests();
                } else {
                    showNotification('Action Failed', data.message || 'Failed to reject request.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showNotification('Error', 'Network error occurred.', 'error');
            });
        });

        // Open Details Modal
        window.openDetailsModal = function(id) {
            const req = requests.find(r => r.id === id);
            if (!req) return;

            // Populate modal user details
            document.getElementById('modalUserAvatar').src = req.avatar || "https://ui-avatars.com/api/?name=" + encodeURIComponent(req.user);
            document.getElementById('modalUserName').textContent = req.user;
            document.getElementById('modalUserRole').textContent = req.role;

            document.getElementById('modalEqImg').src = req.img;

            const statusBadge = document.getElementById('modalStatusBadge');
            statusBadge.textContent = req.status;
            statusBadge.className = 'status-badge status-' + req.status.toLowerCase();

            document.getElementById('modalEqName').value = req.equipment;
            document.getElementById('modalEqCategory').value = req.category;
            document.getElementById('modalEqQty').value = req.quantity + ' pc(s)';
            document.getElementById('modalReqDate').value = req.fullDate;
            document.getElementById('modalBorrowDate').value = req.borrowDate;
            document.getElementById('modalDueDate').value = req.dueDate;
            document.getElementById('modalPurpose').value = req.purpose;
            document.getElementById('modalNotes').value = req.notes || 'None';

            const reasonGroup = document.getElementById('modalReasonGroup');
            if (req.status.toLowerCase() === 'rejected') {
                document.getElementById('modalReason').value = req.rejectReason || 'No reason provided.';
                reasonGroup.style.display = 'block';
            } else {
                reasonGroup.style.display = 'none';
            }

            // Populate action buttons in footer
            const footer = document.getElementById('modalActionsFooter');
            if (req.status.toLowerCase() === 'pending') {
                footer.innerHTML = `
                    <button class="btn-modal-close" id="modalCancelBtn">Close</button>
                    <button class="btn-modal-reject-trigger" onclick="triggerRejectReason(${req.id})">Reject</button>
                    <button class="btn-modal-approve" onclick="approveRequest(${req.id})">Approve</button>
                `;
            } else {
                footer.innerHTML = `
                    <button class="btn-modal-close" style="width: 100%;" id="modalCancelBtn">Close</button>
                `;
            }

            // Add close listener for modal close button
            document.getElementById('modalCancelBtn').addEventListener('click', () => {
                detailsModal.classList.remove('show');
            });

            detailsModal.classList.add('show');
        };

        // Close modals functions
        closeDetailsBtn.addEventListener('click', () => {
            detailsModal.classList.remove('show');
        });
        detailsModal.addEventListener('click', (e) => {
            if (e.target === detailsModal) {
                detailsModal.classList.remove('show');
            }
        });

        closeRejectReasonBtn.addEventListener('click', () => {
            rejectReasonModal.classList.remove('show');
        });
        rejectReasonModal.addEventListener('click', (e) => {
            if (e.target === rejectReasonModal) {
                rejectReasonModal.classList.remove('show');
            }
        });

        // Helper to escape HTML values
        function escapeHTML(str) {
            if (!str) return '';
            return String(str).replace(/[&<>'"]/g,
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        // Sync stats to dashboard in localStorage
        function updateDashboardStats() {
            const pendingCount = requests.filter(r => r.status.toLowerCase() === 'pending').length;
            localStorage.setItem('dashboard-pending-count', pendingCount);
        }

        // Event Listeners for search & filters
        searchInput.addEventListener('input', renderTable);
        filterSelect.addEventListener('change', renderTable);

        // Initial render
        renderTable();
        updateDashboardStats();
    });
</script>
@endpush
