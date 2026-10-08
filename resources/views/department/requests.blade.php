@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
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

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchRequests" placeholder="Search by user or Equipment...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterStatus">
                    <option value="all">Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterCategory">
                    <option value="all">Equipment Category</option>
                    @foreach ($dbCategories as $cat)
                        <option value="{{ strtolower($cat) }}">{{ $cat }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>

        <!-- Borrow Requests Data Table Card -->
        <div class="requests-table-container">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">ID</th>
                        <th style="width: 18%;">User</th>
                        <th style="width: 12%;">Roles</th>
                        <th style="width: 20%;">Equipment</th>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 14%;">Status</th>
                        <th style="width: 16%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>

            <!-- Pagination Footer -->
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons">
                    <!-- Buttons added dynamically -->
                </div>
            </div>
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-clipboard-list empty-state-icon"></i>
            <h4>No requests found</h4>
            <p>No borrow requests match your current search query or filter settings.</p>
        </div>
<!-- Toast Notification -->
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- Interactivity & Data Logic -->
    

    <!-- Table Management & Action Script -->
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
    

        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('requestsTableBody');
            const searchInput = document.getElementById('searchRequests');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');
            const emptyStateContainer = document.getElementById('emptyStateContainer');

            // Pagination elements & state (same behavior as the Admin tables)
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');
            let currentPage = 1;
            const pageSize = 10;

            // Summary metrics elements
            const sumPending = document.getElementById('sumPending');
            const sumApproved = document.getElementById('sumApproved');
            const sumRejected = document.getElementById('sumRejected');

            // Toast elements
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Requests loaded live from the database (equipment owned by this
            // department), so approvals and stock changes stay in sync.
            const URL_UPDATE = @json(route('department.requests.update'));
            const CSRF_TOKEN = @json(csrf_token());
            let requests = @json($dbRequests);

            function updateSummaryCards() {
                const pendingCount = requests.filter(r => r.status === 'Pending').length;
                const approvedCount = requests.filter(r => r.status === 'Approved').length;
                const rejectedCount = requests.filter(r => r.status === 'Rejected').length;

                sumPending.textContent = pendingCount;
                sumApproved.textContent = approvedCount;
                sumRejected.textContent = rejectedCount;
            }

            function renderTable() {
                tableBody.innerHTML = '';
                
                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = filterStatusSelect.value.toLowerCase();
                const selectedCategory = filterCategorySelect.value.toLowerCase();

                const filtered = requests.filter(item => {
                    const searchData = `${item.id} ${item.user} ${item.equipment}`.toLowerCase();
                    const itemStatus = item.status.toLowerCase();
                    const itemCategory = (item.category || '').toLowerCase();

                    const matchesSearch = searchData.includes(query);
                    const matchesStatus = (selectedStatus === 'all' || itemStatus === selectedStatus);
                    const matchesCategory = (selectedCategory === 'all' || itemCategory.includes(selectedCategory));
                    return matchesSearch && matchesStatus && matchesCategory;
                });

                const totalEntries = filtered.length;
                const totalPages = Math.ceil(totalEntries / pageSize);

                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const paginatedRequests = filtered.slice(startIndex, endIndex);

                paginatedRequests.forEach(item => {
                    const tr = document.createElement('tr');

                    let actionHtml = '';
                    if (item.status === 'Pending') {
                        actionHtml = `
                            <div class="action-buttons-cell" style="justify-content: center; gap: 8px;">
                                <button class="btn-action-approve" onclick="updateRequestStatus('${item.id}', 'Approved')">Approve</button>
                                <button class="btn-action-reject" onclick="updateRequestStatus('${item.id}', 'Rejected')">Reject</button>
                            </div>
                        `;
                    } else if (item.status === 'Approved') {
                        actionHtml = `<span class="status-badge approved"><i class="fa-solid fa-circle-check"></i> Approved</span>`;
                    } else {
                        actionHtml = `<span class="status-badge rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>`;
                    }

                    let statusBadgeHtml = '';
                    if (item.status === 'Pending') {
                        statusBadgeHtml = `<span class="status-badge pending">Pending</span>`;
                    } else if (item.status === 'Approved') {
                        statusBadgeHtml = `<span class="status-badge approved">Approved</span>`;
                    } else {
                        statusBadgeHtml = `<span class="status-badge rejected">Rejected</span>`;
                    }

                    tr.innerHTML = `
                        <td class="col-id">${escapeHTML(item.id)}</td>
                        <td class="col-user">${escapeHTML(item.user)}</td>
                        <td class="col-roles">${escapeHTML(item.role)}</td>
                        <td>${escapeHTML(item.equipment)}</td>
                        <td>${escapeHTML(item.date)}</td>
                        <td>${statusBadgeHtml}</td>
                        <td style="text-align: center;">${actionHtml}</td>
                    `;

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();

                // Mark the last rendered row so its bottom line doesn't double up
                // against the card border.
                const renderedRows = tableBody.querySelectorAll('tr');
                if (renderedRows.length) {
                    renderedRows[renderedRows.length - 1].classList.add('last-visible-row');
                }

                if (totalEntries === 0) {
                    emptyStateContainer.style.display = 'flex';
                    paginationContainer.style.display = 'none';
                } else {
                    emptyStateContainer.style.display = 'none';
                    paginationContainer.style.display = 'flex';
                    paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                    renderPaginationButtons(totalPages);
                }
            }

            // Render pagination buttons dynamically (same behavior as Admin Users)
            function renderPaginationButtons(totalPages) {
                paginationButtons.innerHTML = '';

                const prevBtn = document.createElement('button');
                prevBtn.className = 'btn-page';
                prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
                prevBtn.disabled = currentPage === 1;
                prevBtn.addEventListener('click', () => {
                    currentPage--;
                    renderTable();
                });
                paginationButtons.appendChild(prevBtn);

                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderTable();
                    });
                    paginationButtons.appendChild(pageBtn);
                }

                const nextBtn = document.createElement('button');
                nextBtn.className = 'btn-page';
                nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.addEventListener('click', () => {
                    currentPage++;
                    renderTable();
                });
                paginationButtons.appendChild(nextBtn);
            }

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

            window.updateRequestStatus = function(id, newStatus) {
                const req = requests.find(r => String(r.id) === String(id));
                if (!req) return;

                const formData = new FormData();
                formData.append('request_id', req.id);
                formData.append('status', newStatus);
                if (newStatus === 'Rejected') {
                    // The page has no reason field, so a default reason is recorded.
                    formData.append('reject_reason', 'Rejected by the department.');
                }

                fetch(URL_UPDATE, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        // Approval also decreases the equipment's available quantity.
                        req.status = newStatus;
                        showNotification(
                            newStatus === 'Approved' ? 'Request Approved' : 'Request Rejected',
                            data.message || `Borrow request for ${req.user} has been ${newStatus.toLowerCase()}.`,
                            newStatus === 'Approved' ? 'success' : 'error'
                        );
                        renderTable();
                    } else {
                        showNotification('Update Failed', (data && data.message) || 'Unable to update the request.', 'error');
                    }
                })
                .catch(() => {
                    showNotification('System Error', 'An unexpected error occurred while updating the request.', 'error');
                });
            };

            // Filters/search changed: go back to the first page, then re-render
            function filterTable() {
                currentPage = 1;
                renderTable();
            }

            function showNotification(title, message, type = 'success') {
                toastTitle.textContent = title;
                toastMsg.textContent = message;
                
                if (type === 'success') {
                    toastIcon.className = 'fa-solid fa-circle-check toast-icon';
                    toastIcon.style.color = '#10b981';
                    document.querySelector('.toast-content').style.borderLeft = '4px solid #10b981';
                } else {
                    toastIcon.className = 'fa-solid fa-circle-xmark toast-icon';
                    toastIcon.style.color = '#ef4444';
                    document.querySelector('.toast-content').style.borderLeft = '4px solid #ef4444';
                }
                
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3500);
            }

            searchInput.addEventListener('input', filterTable);
            filterStatusSelect.addEventListener('change', filterTable);
            filterCategorySelect.addEventListener('change', filterTable);

            // Initial render
            renderTable();
        });
    
</script>
@endpush
