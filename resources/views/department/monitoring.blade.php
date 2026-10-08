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
                    <option value="unavailable">Unavailable</option>
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
                        <th style="width: 8%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="monitoringTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>

            <!-- Table Footer Pagination Matching Reference Image -->
            <div class="table-footer-pagination">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Buttons added dynamically -->
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
        <div class="eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Equipment monitoring record details</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeViewModalBtn">&times;</button>
                <h3 class="modal-title-center">Borrowing Details</h3>
            
                <!-- Borrower info block -->
                <div class="modal-requester-profile">
                    <img src="" alt="Avatar" class="modal-requester-avatar" id="viewBorrowerAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="viewBorrower">-</span>
                        <span class="modal-requester-details"><span id="viewRole">-</span> | ID: <span id="viewBorrowerId">-</span></span>
                    </div>
                </div>

                <!-- Main Fields Content -->
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="viewEquipmentImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="viewStatus">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="viewEquipmentName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="viewCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="viewBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="viewReturnDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions Footer -->
                <div class="modal-actions-footer">
                    <button type="button" class="btn-modal-close" id="closeViewModalFooterBtn">Close</button>
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
            const paginationControls = document.getElementById('paginationControls');
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

            // Monitoring rows loaded live from the database for this department
            // (one row per active loan, or one row per equipment with no active loan).
            let monitoringData = @json($dbMonitoring);

            // Pagination state
            let currentPage = 1;
            const pageSize = 10;

            function updateSummaryCards() {
                const totalCount = monitoringData.length;
                const availableCount = monitoringData.filter(m => m.status.toLowerCase() === 'available').length;
                // Items currently out on loan (including overdue ones)
                const borrowedCount = monitoringData.filter(m => ['borrowed', 'overdue'].includes(m.status.toLowerCase())).length;
                const reservedCount = monitoringData.filter(m => ['reserved', 'on hold'].includes(m.status.toLowerCase())).length;

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

                const query = searchInput.value.toLowerCase().trim();
                const selectedRole = filterRoleSelect.value.toLowerCase();
                const selectedStatus = filterStatusSelect.value.toLowerCase();
                const selectedCategory = filterCategorySelect.value.toLowerCase();

                const filtered = monitoringData.filter(item => {
                    const status = item.status.toLowerCase();
                    const searchData = `${item.equipment} ${item.borrower} ${item.id_number} ${item.role} ${item.status}`.toLowerCase();

                    const matchesSearch = searchData.includes(query);
                    const matchesRole = (selectedRole === 'all' || item.role.toLowerCase().includes(selectedRole));
                    const matchesCategory = (selectedCategory === 'all' || item.category.toLowerCase().includes(selectedCategory));
                    const matchesStatus = (selectedStatus === 'all' ||
                        status === selectedStatus ||
                        (selectedStatus === 'maintenance' && status === 'under maintenance') ||
                        (selectedStatus === 'reserved' && status === 'on hold'));

                    return matchesSearch && matchesRole && matchesCategory && matchesStatus;
                });

                const totalEntries = filtered.length;
                const totalPages = Math.ceil(totalEntries / pageSize);

                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const pageItems = filtered.slice(startIndex, endIndex);

                pageItems.forEach(item => {
                    const tr = document.createElement('tr');

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
                        <td>${actionContent}</td>
                    `;

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();

                if (paginationInfo) {
                    paginationInfo.textContent = totalEntries > 0
                        ? `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`
                        : 'Showing 0 to 0 of 0 entries';
                }
                renderPaginationButtons(totalPages);

                emptyStateContainer.style.display = totalEntries === 0 ? 'flex' : 'none';
            }

            // Render pagination buttons dynamically (keeps the existing page-btn design)
            function renderPaginationButtons(totalPages) {
                paginationControls.innerHTML = '';

                const makeBtn = (html, disabled, active, onClick) => {
                    const btn = document.createElement('button');
                    btn.className = 'page-btn' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                    btn.innerHTML = html;
                    btn.disabled = disabled;
                    if (!disabled) btn.addEventListener('click', onClick);
                    return btn;
                };

                paginationControls.appendChild(makeBtn('<i class="fa-solid fa-chevron-left"></i>', currentPage <= 1, false, () => {
                    currentPage--;
                    renderTable();
                }));

                const pagesToShow = Math.max(1, totalPages);
                for (let i = 1; i <= pagesToShow; i++) {
                    paginationControls.appendChild(makeBtn(String(i), false, currentPage === i, () => {
                        currentPage = i;
                        renderTable();
                    }));
                }

                paginationControls.appendChild(makeBtn('<i class="fa-solid fa-chevron-right"></i>', currentPage >= totalPages, false, () => {
                    currentPage++;
                    renderTable();
                }));
            }

            function statusClass(status) {
                switch (status.toUpperCase()) {
                    case 'BORROWED': return 'borrowed';
                    case 'OVERDUE': return 'overdue';
                    case 'AVAILABLE': return 'available';
                    case 'RESERVED':
                    case 'ON HOLD': return 'reserved';
                    case 'MAINTENANCE':
                    case 'UNDER MAINTENANCE':
                    case 'UNAVAILABLE': return 'maintenance';
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
                    document.getElementById('viewBorrowerAvatar').src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.borrower || 'N/A') + '&background=385585&color=fff&bold=true';
                    document.getElementById('viewBorrower').textContent = item.borrower || '—';
                    document.getElementById('viewRole').textContent = item.role || '—';
                    document.getElementById('viewBorrowerId').textContent = item.id_number || 'N/A';

                    document.getElementById('viewEquipmentName').value = item.equipment || '—';
                    document.getElementById('viewCategory').value = item.category || '—';
                    document.getElementById('viewBorrowDate').value = item.borrowDate || '—';
                    document.getElementById('viewReturnDate').value = item.returnDate || '—';

                    // Equipment picture from the equipment record; falls back to the logo if missing or broken
                    const fallbackImg = '{{ asset('images/EquipTrack_logo.png') }}';
                    const eqImg = document.getElementById('viewEquipmentImg');
                    eqImg.onerror = function () {
                        this.onerror = null;
                        this.src = fallbackImg;
                    };
                    eqImg.src = item.img || fallbackImg;

                    const statusBadge = document.getElementById('viewStatus');
                    statusBadge.textContent = item.status;
                    statusBadge.className = 'status-badge ' + statusClass(item.status);

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

            const closeViewModalFooterBtn = document.getElementById('closeViewModalFooterBtn');
            if (closeViewModalFooterBtn) {
                closeViewModalFooterBtn.addEventListener('click', () => {
                    viewMonitoringModal.classList.remove('show');
                });
            }

            viewMonitoringModal.addEventListener('click', (e) => {
                if (e.target === viewMonitoringModal) {
                    viewMonitoringModal.classList.remove('show');
                }
            });

            // Search/filters changed: go back to the first page, then re-render
            function filterTable() {
                currentPage = 1;
                renderTable();
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
