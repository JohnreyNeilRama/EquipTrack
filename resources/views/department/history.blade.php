@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/history.css') }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
            <h2>Borrowing History</h2>
            <p>View completed borrowing transactions for your department.</p>
        </div>

        <!-- Summary Cards Section (Quick Stats) -->
        <div class="history-summary-section">
            <h4 class="section-subtitle">Quick Stats</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Total Transactions -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Total Transactions</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="statTotal">0</span>
                </div>

                <!-- Card 2: Items Returned -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Items Returned</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="statReturned">0</span>
                </div>

                <!-- Card 3: Late Returns -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Late Returns</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="statLate">0</span>
                </div>

                <!-- Card 4: Damaged Items -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-label">Damaged Items</span>
                        <div class="summary-card-icon icon-gray">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                    </div>
                    <span class="summary-card-val" id="statDamaged">0</span>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchHistory" placeholder="Search by name or ID...">
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
                    <option value="returned">Returned</option>
                    <option value="returned late">Returned Late</option>
                    <option value="damaged">Damaged</option>
                    <option value="lost">Lost</option>
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

        <!-- Borrowing History Data Table Card -->
        <div class="history-table-container">
            <table class="history-table">
                <thead>
                    <tr>
                        <th style="width: 13%;">Transaction ID</th>
                        <th style="width: 12%;">ID Number</th>
                        <th style="width: 14%;">Name</th>
                        <th style="width: 13%;">User Type</th>
                        <th style="width: 11%;">Year Level / Attainment</th>
                        <th style="width: 14%;">Equipment</th>
                        <th class="col-status" style="width: 16%;">Status</th>
                        <th class="col-action" style="width: 7%;">Action</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <!-- Rendered from the database records by the script below -->
                </tbody>
            </table>

            <!-- Table Footer Pagination -->
            <div class="table-footer-pagination">
                <span class="pagination-info" id="paginationInfo">Showing 0 of 0 transactions</span>
                <div class="pagination-controls" id="paginationControls">
                    <button type="button" class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="page-btn active">1</button>
                    <button type="button" class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-clock-rotate-left empty-state-icon"></i>
            <h4 id="emptyStateTitle">No records found</h4>
            <p id="emptyStateText">No borrowing history transactions match your search or filter parameters.</p>
        </div>
<!-- Transaction Details Modal -->
    <div class="modal-overlay" id="txnDetailsModal" role="dialog" aria-modal="true" aria-labelledby="txnModalTitle">
        <div class="eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of borrowing transaction information</p>
            </div>
            <div class="modal-inner-card">
                <button type="button" class="modal-close" id="drawerCloseBtn" aria-label="Close">&times;</button>
                <h3 class="modal-title-center" id="txnModalTitle">Transaction Details</h3>

                <div class="txn-summary-strip">
                    <div class="txn-summary-icon"><i class="fa-solid fa-receipt"></i></div>
                    <div class="txn-summary-meta">
                        <span class="txn-summary-label">Transaction ID</span>
                        <span class="txn-summary-id" id="drawerTxnId">TXN-0000</span>
                    </div>
                </div>

                <div class="txn-section">
                    <h4 class="txn-section-title"><i class="fa-solid fa-user"></i> Borrower Information</h4>
                    <div class="txn-grid">
                        <div class="txn-field"><span class="txn-label">Full Name</span><span class="txn-value" id="biFullName">—</span></div>
                        <div class="txn-field"><span class="txn-label">ID Number</span><span class="txn-value" id="biIdNumber">—</span></div>
                        <div class="txn-field"><span class="txn-label">User Type</span><span class="txn-value" id="biUserType">—</span></div>
                        <div class="txn-field"><span class="txn-label">Year Level / Attainment</span><span class="txn-value" id="biLevel">—</span></div>
                    </div>
                </div>

                <div class="txn-section">
                    <h4 class="txn-section-title"><i class="fa-solid fa-screwdriver-wrench"></i> Equipment Information</h4>
                    <div class="txn-grid">
                        <div class="txn-field"><span class="txn-label">Equipment Name</span><span class="txn-value" id="eqName">—</span></div>
                        <div class="txn-field"><span class="txn-label">Category</span><span class="txn-value" id="eqCategory">—</span></div>
                    </div>
                </div>

                <div class="txn-section">
                    <h4 class="txn-section-title"><i class="fa-solid fa-calendar-check"></i> Borrowing &amp; Return Details</h4>
                    <div class="txn-grid">
                        <div class="txn-field"><span class="txn-label">Borrow Date</span><span class="txn-value" id="bdBorrowDate">—</span></div>
                        <div class="txn-field"><span class="txn-label">Return Date</span><span class="txn-value" id="bdReturnDate">—</span></div>
                        <div class="txn-field txn-field-full"><span class="txn-label">Return Status</span><span class="txn-value"><span class="status-badge-dot returned" id="bdReturnStatus">—</span></span></div>
                        <div class="txn-field txn-field-full"><span class="txn-label">Return Remarks</span><span class="txn-value txn-value-remarks" id="bdRemarks">—</span></div>
                    </div>
                </div>

                <div class="modal-actions-footer">
                    <button type="button" class="btn-modal-close" id="txnCloseFooterBtn">Close</button>
                </div>
            </div>
        </div>
    </div>
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
            const URL_DATA = @json(route('department.history.data'));
            const REFRESH_MS = 10000;
            const PAGE_SIZE = 10;

            const tableBody = document.getElementById('historyTableBody');
            const searchInput = document.getElementById('searchHistory');
            const filterRoleSelect = document.getElementById('filterRole');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');
            const emptyStateContainer = document.getElementById('emptyStateContainer');
            const emptyStateTitle = document.getElementById('emptyStateTitle');
            const emptyStateText = document.getElementById('emptyStateText');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');

            // Transaction details modal elements
            const txnModal = document.getElementById('txnDetailsModal');
            const drawerCloseBtn = document.getElementById('drawerCloseBtn');
            const txnCloseFooterBtn = document.getElementById('txnCloseFooterBtn');

            // Borrowing history comes from the database (returned borrow
            // transactions for this department's equipment). It is embedded on
            // first load and refreshed from the JSON feed below.
            let historyData = @json($dbHistory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            let currentPage = 1;

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

            function statusClass(status) {
                switch (String(status).toUpperCase()) {
                    case 'RETURNED': return 'returned';
                    case 'RETURNED LATE': return 'returned-late';
                    case 'DAMAGED':
                    case 'LOST': return 'damaged';
                    default: return 'returned';
                }
            }

            function updateSummaryCards() {
                const count = status => historyData.filter(h => h.status.toLowerCase() === status).length;

                const set = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = value;
                };

                set('statTotal', historyData.length);
                set('statReturned', count('returned'));
                set('statLate', count('returned late'));
                set('statDamaged', count('damaged'));
            }

            // Search + role / status / category filters over the full dataset
            function getFilteredData() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedRole = filterRoleSelect.value.toLowerCase();
                const selectedStatus = filterStatusSelect.value.toLowerCase();
                const selectedCategory = filterCategorySelect.value.toLowerCase();

                return historyData.filter(item => {
                    const searchData = `${item.txnid} ${item.idnumber} ${item.name} ${item.equipment} ${item.userType}`.toLowerCase();

                    return searchData.includes(query)
                        && (selectedRole === 'all' || item.userType.toLowerCase().includes(selectedRole))
                        && (selectedStatus === 'all' || item.status.toLowerCase() === selectedStatus)
                        && (selectedCategory === 'all' || item.category.toLowerCase().includes(selectedCategory));
                });
            }

            function renderRows(rows) {
                tableBody.innerHTML = '';

                rows.forEach(item => {
                    const tr = document.createElement('tr');
                    const isFaculty = item.userType.toLowerCase() === 'faculty';

                    tr.innerHTML = `
                        <td class="col-txnid">${escapeHTML(item.txnid)}</td>
                        <td class="col-idnum">${escapeHTML(item.idnumber)}</td>
                        <td class="col-name" title="${escapeHTML(item.name)}">${escapeHTML(item.name)}</td>
                        <td><span class="type-badge ${isFaculty ? 'type-faculty' : 'type-student'}">${isFaculty ? '<i class="fa-solid fa-chalkboard-user"></i> Faculty' : '<i class="fa-solid fa-user-graduate"></i> Student'}</span></td>
                        <td>${escapeHTML(item.attainment)}</td>
                        <td>
                            <div class="equipment-meta-cell">
                                <span class="equipment-name-title">${escapeHTML(item.equipment)}</span>
                                <span class="equipment-category-sub">${escapeHTML(item.category)}</span>
                            </div>
                        </td>
                        <td class="col-status">
                            <span class="status-badge-dot ${statusClass(item.status)}">
                                <span class="dot"></span> ${escapeHTML(item.status)}
                            </span>
                        </td>
                        <td class="col-action">
                            <button class="btn-action-eye" data-txnid="${escapeHTML(item.txnid)}" title="View details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    `;

                    tableBody.appendChild(tr);
                });
            }

            function renderPagination(totalPages) {
                paginationControls.innerHTML = '';

                const makeBtn = (html, page, extraClass) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'page-btn' + (extraClass ? ' ' + extraClass : '');
                    btn.innerHTML = html;
                    if (page !== null && !(extraClass || '').includes('disabled')) {
                        btn.addEventListener('click', () => {
                            currentPage = page;
                            render();
                        });
                    }
                    paginationControls.appendChild(btn);
                };

                makeBtn('<i class="fa-solid fa-chevron-left"></i>', currentPage - 1, currentPage <= 1 ? 'disabled' : '');

                // Window of up to 5 page numbers around the current page
                let start = Math.max(1, currentPage - 2);
                let end = Math.min(totalPages, start + 4);
                start = Math.max(1, end - 4);

                for (let i = start; i <= end; i++) {
                    makeBtn(String(i), i, i === currentPage ? 'active' : '');
                }

                makeBtn('<i class="fa-solid fa-chevron-right"></i>', currentPage + 1, currentPage >= totalPages ? 'disabled' : '');
            }

            function render() {
                const filtered = getFilteredData();
                const totalEntries = filtered.length;
                const totalPages = Math.max(1, Math.ceil(totalEntries / PAGE_SIZE));

                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                const startIndex = (currentPage - 1) * PAGE_SIZE;
                const endIndex = Math.min(startIndex + PAGE_SIZE, totalEntries);

                renderRows(filtered.slice(startIndex, endIndex));
                renderPagination(totalPages);
                updateSummaryCards();

                if (paginationInfo) {
                    paginationInfo.textContent = totalEntries > 0
                        ? `Showing ${startIndex + 1}-${endIndex} of ${totalEntries} transactions`
                        : 'Showing 0 of 0 transactions';
                }

                if (totalEntries === 0) {
                    if (historyData.length === 0) {
                        emptyStateTitle.textContent = 'No borrowing history yet';
                        emptyStateText.textContent = 'Returned equipment from your department will appear here once borrowers return their items.';
                    } else {
                        emptyStateTitle.textContent = 'No records found';
                        emptyStateText.textContent = 'No borrowing history transactions match your search or filter parameters.';
                    }
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            window.openDrawer = function(txnid) {
                const item = historyData.find(h => h.txnid === txnid);
                if (item) {
                    document.getElementById('drawerTxnId').textContent = item.txnid;
                    document.getElementById('biFullName').textContent = item.name;
                    document.getElementById('biIdNumber').textContent = item.idnumber;
                    document.getElementById('biUserType').textContent = item.userType;
                    document.getElementById('biLevel').textContent = item.attainment;
                    document.getElementById('eqName').textContent = item.equipment;
                    document.getElementById('eqCategory').textContent = item.category;
                    document.getElementById('bdBorrowDate').textContent = item.borrowDate;
                    document.getElementById('bdReturnDate').textContent = item.returnDate;
                    const statusEl = document.getElementById('bdReturnStatus');
                    statusEl.className = 'status-badge-dot ' + statusClass(item.status);
                    statusEl.innerHTML = '<span class="dot"></span> ' + escapeHTML(item.status);
                    document.getElementById('bdRemarks').textContent = item.remarks || '—';

                    txnModal.classList.add('show');
                }
            };

            function closeDrawer() {
                txnModal.classList.remove('show');
            }

            tableBody.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-action-eye');
                if (btn) window.openDrawer(btn.getAttribute('data-txnid'));
            });

            if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);
            if (txnCloseFooterBtn) txnCloseFooterBtn.addEventListener('click', closeDrawer);
            if (txnModal) {
                // Clicking the dimmed backdrop (outside the card) closes the modal
                txnModal.addEventListener('click', (e) => {
                    if (e.target === txnModal) closeDrawer();
                });
            }
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && txnModal.classList.contains('show')) closeDrawer();
            });

            const onFilterChange = () => {
                currentPage = 1;
                render();
            };

            searchInput.addEventListener('input', onFilterChange);
            filterRoleSelect.addEventListener('change', onFilterChange);
            filterStatusSelect.addEventListener('change', onFilterChange);
            filterCategorySelect.addEventListener('change', onFilterChange);

            // Pull fresh records so new returns show up without a page reload
            function refreshHistory() {
                return fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success && Array.isArray(data.history)) {
                            historyData = data.history;
                            render();
                        }
                    })
                    .catch(err => console.error('Error loading borrowing history:', err));
            }

            // First paint from the server payload, then poll in the background
            render();

            setInterval(refreshHistory, REFRESH_MS);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    refreshHistory();
                }
            });
        });
</script>
@endpush
