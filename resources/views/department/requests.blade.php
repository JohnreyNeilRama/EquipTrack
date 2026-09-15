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
                        <th style="width: 14%;">ID</th>
                        <th style="width: 18%;">User</th>
                        <th style="width: 14%;">Roles</th>
                        <th style="width: 18%;">Equipment</th>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 12%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>
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

        // Notifications bell
        const notifBtn = document.getElementById('notifBtn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                alert('No new notifications.');
            });
        }
    

        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('requestsTableBody');
            const searchInput = document.getElementById('searchRequests');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');
            const emptyStateContainer = document.getElementById('emptyStateContainer');

            // Summary metrics elements
            const sumPending = document.getElementById('sumPending');
            const sumApproved = document.getElementById('sumApproved');
            const sumRejected = document.getElementById('sumRejected');

            // Toast elements
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Storage Management
            let requests = JSON.parse(localStorage.getItem('equip-track-borrow-requests'));
            if (!requests || !Array.isArray(requests)) {
                requests = [];
            } else {
                requests = requests.filter(r => !['21432132', 'FAC-2023', '20230456', '20230812', '20230944', '20230112', '20230554', '20230788'].includes(r.id));
            }
            localStorage.setItem('equip-track-borrow-requests', JSON.stringify(requests));

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
                
                requests.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-status', item.status.toLowerCase());
                    tr.setAttribute('data-category', (item.category || '').toLowerCase());
                    tr.setAttribute('data-search', `${item.id} ${item.user} ${item.equipment}`.toLowerCase());

                    let actionHtml = '';
                    if (item.status === 'Pending') {
                        actionHtml = `
                            <div class="action-buttons-cell">
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
                        <td>${actionHtml}</td>
                    `;

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();
                filterTable();
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
                const req = requests.find(r => r.id === id);
                if (req) {
                    req.status = newStatus;
                    localStorage.setItem('equip-track-borrow-requests', JSON.stringify(requests));
                    
                    if (newStatus === 'Approved') {
                        showNotification('Request Approved', `Approved borrow request for ${req.user}.`, 'success');
                    } else {
                        showNotification('Request Rejected', `Rejected borrow request for ${req.user}.`, 'error');
                    }

                    renderTable();
                }
            };

            function filterTable() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = filterStatusSelect.value.toLowerCase();
                const selectedCategory = filterCategorySelect.value.toLowerCase();

                const rows = tableBody.querySelectorAll('tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search');
                    const rowStatus = row.getAttribute('data-status');
                    const rowCategory = row.getAttribute('data-category');

                    const matchesSearch = searchData.includes(query);
                    const matchesStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
                    const matchesCategory = (selectedCategory === 'all' || rowCategory.includes(selectedCategory));

                    if (matchesSearch && matchesStatus && matchesCategory) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
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
