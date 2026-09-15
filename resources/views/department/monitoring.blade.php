@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/monitoring.css') }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
            <h2>Equipment Monitoring</h2>
            <p>Monitor borrowed equipment, return requests, and overdue records.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="monitoring-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Total Monitored -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Total Monitored</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-desktop"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="sumTotalMonitored">0</span>
                </div>

                <!-- Card 2: Available -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Available</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="sumAvailable">0</span>
                </div>

                <!-- Card 3: Borrowed -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Borrowed</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="sumBorrowed">0</span>
                </div>

                <!-- Card 4: Reserved -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Reserved</span>
                        <div class="summary-card-icon icon-amber">
                            <i class="fa-solid fa-bookmark"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="sumReserved">0</span>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchMonitoring" placeholder="Search by name or ID...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterRole">
                    <option value="all">All Roles</option>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty Member</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterStatus">
                    <option value="all">Status</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="overdue">Overdue</option>
                    <option value="available">Available</option>
                    <option value="reserved">Reserved</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterCategory">
                    <option value="all">All Categories</option>
                    @foreach ($dbCategories as $cat)
                        <option value="{{ strtolower($cat) }}">{{ $cat }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>

        <!-- Monitoring Data Table Card -->
        <div class="monitoring-table-container">
            <table class="monitoring-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">Equipment</th>
                        <th style="width: 18%;">Borrower</th>
                        <th style="width: 12%;">Role</th>
                        <th style="width: 14%;">Borrow Date</th>
                        <th style="width: 14%;">Return Date</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 8%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="monitoringTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>

            <!-- Table Footer Pagination Matching Reference Image -->
            <div class="table-footer-pagination">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-controls">
                    <button class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-desktop empty-state-icon"></i>
            <h4>No records found</h4>
            <p>No monitoring records match your search or filter parameters.</p>
        </div>
<!-- View Details Modal -->
    <div class="modal-overlay" id="viewMonitoringModal">
        <div class="modal-card">
            <button class="modal-close" id="closeViewModalBtn">&times;</button>
            <h3 class="modal-title-center">Monitoring Details</h3>
            <p class="modal-subtitle-top">Equipment monitoring and borrower information</p>
            
            <div class="view-details-card">
                <div class="view-detail-item">
                    <span class="label">Equipment Name</span>
                    <span class="val" id="viewEquipmentName">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Borrower</span>
                    <span class="val" id="viewBorrower">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Role</span>
                    <span class="val" id="viewRole">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Borrow Date</span>
                    <span class="val" id="viewBorrowDate">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Return Date</span>
                    <span class="val" id="viewReturnDate">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Status</span>
                    <span class="val" id="viewStatus">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotif">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
        <span id="toastMessage">Notification sent successfully.</span>
    </div>

    <!-- Interactivity Script -->
    

    <!-- Data & Table Script -->
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
            const tableBody = document.getElementById('monitoringTableBody');
            const searchInput = document.getElementById('searchMonitoring');
            const filterRoleSelect = document.getElementById('filterRole');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');
            const emptyStateContainer = document.getElementById('emptyStateContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const toastNotif = document.getElementById('toastNotif');
            const toastMessage = document.getElementById('toastMessage');

            // Summary metrics
            const sumTotalMonitored = document.getElementById('sumTotalMonitored');
            const sumAvailable = document.getElementById('sumAvailable');
            const sumBorrowed = document.getElementById('sumBorrowed');
            const sumReserved = document.getElementById('sumReserved');

            // Modal elements
            const viewMonitoringModal = document.getElementById('viewMonitoringModal');
            const closeViewModalBtn = document.getElementById('closeViewModalBtn');

            // Storage Management
            let monitoringData = JSON.parse(localStorage.getItem('equip-track-monitoring-data'));
            if (!monitoringData || !Array.isArray(monitoringData)) {
                monitoringData = [];
            } else {
                monitoringData = monitoringData.filter(m => !['MON-101', 'MON-102', 'MON-103', 'MON-104', 'MON-105'].includes(m.id));
            }
            localStorage.setItem('equip-track-monitoring-data', JSON.stringify(monitoringData));

            function updateSummaryCards() {
                const totalCount = monitoringData.length;
                const availableCount = monitoringData.filter(m => m.status === 'AVAILABLE').length;
                const borrowedCount = monitoringData.filter(m => m.status === 'BORROWED').length;
                const reservedCount = monitoringData.filter(m => m.status === 'RESERVED').length;

                if (sumTotalMonitored) sumTotalMonitored.textContent = totalCount;
                if (sumAvailable) sumAvailable.textContent = availableCount;
                if (sumBorrowed) sumBorrowed.textContent = borrowedCount;
                if (sumReserved) sumReserved.textContent = reservedCount;
            }

            function showToast(msg) {
                toastMessage.textContent = msg;
                toastNotif.classList.add('show');
                setTimeout(() => {
                    toastNotif.classList.remove('show');
                }, 3000);
            }

            function renderTable() {
                tableBody.innerHTML = '';

                monitoringData.forEach(item => {
                    const tr = document.createElement('tr');
                    const statusLower = item.status.toLowerCase();

                    tr.setAttribute('data-role', item.role.toLowerCase());
                    tr.setAttribute('data-status', statusLower);
                    tr.setAttribute('data-category', item.category.toLowerCase());
                    tr.setAttribute('data-search', `${item.equipment} ${item.borrower} ${item.role} ${item.status}`.toLowerCase());

                    let actionContent = `
                        <button class="btn-action-view" onclick="openViewModal('${item.id}')">
                            <i class="fa-solid fa-eye"></i> View
                        </button>
                    `;

                    if (item.status === 'OVERDUE') {
                        actionContent = `
                            <button class="btn-action-alert" title="Send Overdue Notice" onclick="sendOverdueNotice('${item.borrower}')">
                                <i class="fa-solid fa-bell"></i>
                            </button>
                        `;
                    }

                    tr.innerHTML = `
                        <td class="col-equipment">${escapeHTML(item.equipment)}</td>
                        <td class="col-borrower">${escapeHTML(item.borrower)}</td>
                        <td class="col-role">${escapeHTML(item.role)}</td>
                        <td class="col-date">${escapeHTML(item.borrowDate)}</td>
                        <td class="col-date">${escapeHTML(item.returnDate)}</td>
                        <td><span class="status-badge ${statusClass(item.status)}">${escapeHTML(item.status)}</span></td>
                        <td style="text-align: center;">${actionContent}</td>
                    `;

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();
                filterTable();
            }

            function statusClass(status) {
                switch (status.toUpperCase()) {
                    case 'BORROWED': return 'borrowed';
                    case 'OVERDUE': return 'overdue';
                    case 'AVAILABLE': return 'available';
                    case 'RESERVED': return 'reserved';
                    case 'MAINTENANCE': return 'maintenance';
                    default: return 'available';
                }
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

            window.openViewModal = function(id) {
                const item = monitoringData.find(m => m.id === id);
                if (item) {
                    document.getElementById('viewEquipmentName').textContent = item.equipment;
                    document.getElementById('viewBorrower').textContent = item.borrower;
                    document.getElementById('viewRole').textContent = item.role;
                    document.getElementById('viewBorrowDate').textContent = item.borrowDate;
                    document.getElementById('viewReturnDate').textContent = item.returnDate;
                    document.getElementById('viewStatus').textContent = item.status;
                    viewMonitoringModal.classList.add('show');
                }
            };

            window.sendOverdueNotice = function(borrowerName) {
                showToast(`Overdue return notification sent to ${borrowerName}.`);
            };

            if (closeViewModalBtn) {
                closeViewModalBtn.addEventListener('click', () => {
                    viewMonitoringModal.classList.remove('show');
                });
            }

            viewMonitoringModal.addEventListener('click', (e) => {
                if (e.target === viewMonitoringModal) {
                    viewMonitoringModal.classList.remove('show');
                }
            });

            function filterTable() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedRole = filterRoleSelect.value.toLowerCase();
                const selectedStatus = filterStatusSelect.value.toLowerCase();
                const selectedCategory = filterCategorySelect.value.toLowerCase();

                const rows = tableBody.querySelectorAll('tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search');
                    const rowRole = row.getAttribute('data-role');
                    const rowStatus = row.getAttribute('data-status');
                    const rowCategory = row.getAttribute('data-category');

                    const matchesSearch = searchData.includes(query);
                    const matchesRole = (selectedRole === 'all' || rowRole.includes(selectedRole));
                    const matchesStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
                    const matchesCategory = (selectedCategory === 'all' || rowCategory.includes(selectedCategory));

                    if (matchesSearch && matchesRole && matchesStatus && matchesCategory) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (paginationInfo) {
                    paginationInfo.textContent = `Showing ${visibleCount > 0 ? 1 : 0} to ${visibleCount} of ${visibleCount} entries`;
                }

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            searchInput.addEventListener('input', filterTable);
            filterRoleSelect.addEventListener('change', filterTable);
            filterStatusSelect.addEventListener('change', filterTable);
            filterCategorySelect.addEventListener('change', filterTable);

            // Initial render
            renderTable();
        });
    
</script>
@endpush
