@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/monitoring.css') }}?v={{ filemtime(public_path('departments/css/monitoring.css')) }}">
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
                        <th class="col-equipment">Equipment</th>
                        <th class="col-user">User</th>
                        <th class="col-date">Borrowed</th>
                        <th class="col-due">Due</th>
                        <th class="col-status">Status</th>
                        <th class="col-action">Action</th>
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
<!-- View Details Modal: same layout as the Department Equipment details window -->
    <div class="modal-overlay" id="viewMonitoringModal" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
        <div class="mon-modal-card">
            <header class="mon-modal-header">
                <div class="mon-modal-icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></div>
                <div class="mon-modal-heading">
                    <h3 class="mon-modal-title" id="viewModalTitle">Borrowing Details</h3>
                    <p class="mon-modal-subtitle">Equipment monitoring record</p>
                </div>
                <span class="status-badge" id="viewStatus">-</span>
                <button type="button" class="modal-close" id="closeViewModalBtn" aria-label="Close">&times;</button>
            </header>

            <div class="mon-modal-body">
                <section class="mon-section" aria-labelledby="viewBorrowerHeading">
                    <h4 class="mon-section-title" id="viewBorrowerHeading">Borrower</h4>
                    <div class="mon-borrower" id="viewBorrowerBlock">
                        <img src="" alt="" class="mon-borrower-avatar" id="viewBorrowerAvatar">
                        <div class="mon-borrower-meta">
                            <span class="mon-borrower-name" id="viewBorrower">-</span>
                            <span class="mon-borrower-sub"><span id="viewRole">-</span> · ID <span id="viewBorrowerId">-</span></span>
                        </div>
                    </div>
                    <p class="mon-empty" id="viewNotBorrowed" hidden>
                        <i class="fa-regular fa-circle-check" aria-hidden="true"></i> Not currently borrowed
                    </p>
                </section>

                <section class="mon-section" aria-labelledby="viewEquipmentHeading">
                    <h4 class="mon-section-title" id="viewEquipmentHeading">Equipment</h4>
                    <div class="mon-equipment">
                        <div class="mon-equipment-media" id="viewEquipmentMedia">
                            <img src="" alt="" class="mon-media-backdrop" id="viewEquipmentImgBackdrop" aria-hidden="true">
                            <img src="" alt="Equipment photo" class="mon-media-img" id="viewEquipmentImg">
                            <div class="mon-media-placeholder">
                                <i class="fa-regular fa-image" aria-hidden="true"></i>
                                <span>No image available</span>
                            </div>
                        </div>
                        <div class="mon-equipment-meta">
                            <span class="mon-equipment-name" id="viewEquipmentName">-</span>
                            <span class="mon-equipment-sub"><span id="viewCategory">-</span><span id="viewSerialWrap"> · <span class="mono" id="viewSerial">-</span></span></span>
                        </div>
                    </div>
                </section>

                <section class="mon-section" aria-labelledby="viewLoanHeading" id="viewLoanSection">
                    <h4 class="mon-section-title" id="viewLoanHeading">Loan</h4>
                    <dl class="mon-details-list">
                        <div>
                            <dt>Borrowed</dt>
                            <dd id="viewBorrowDate">-</dd>
                        </div>
                        <div>
                            <dt>Due</dt>
                            <dd><span id="viewReturnDate">-</span><span class="mon-due-note" id="viewDueNote" hidden></span></dd>
                        </div>
                    </dl>
                </section>
            </div>

            <footer class="mon-modal-footer">
                <button type="button" class="btn-modal-close" id="closeViewModalFooterBtn">Close</button>
            </footer>
        </div>
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

                // Overdue loans first (most overdue at the top); the rest keep their order
                filtered.sort((a, b) => (Number(b.daysOverdue) || 0) - (Number(a.daysOverdue) || 0));

                const totalEntries = filtered.length;
                const totalPages = Math.ceil(totalEntries / pageSize);

                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const pageItems = filtered.slice(startIndex, endIndex);

                pageItems.forEach(item => {
                    const tr = document.createElement('tr');
                    if ((Number(item.daysOverdue) || 0) > 0 || String(item.status).toLowerCase() === 'overdue') {
                        tr.classList.add('is-overdue-row');
                    }

                    // View is on every row. (Borrowers get an automatic overdue
                    // notification in their own bell, so there is no reminder button.)
                    const actionContent = `
                        <div class="monitoring-actions">
                            <button type="button" class="btn-action-view" onclick="openViewModal('${item.id}')">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                            </button>
                        </div>
                    `;

                    // Clicking the row (outside its buttons) is a shortcut to View
                    tr.addEventListener('click', (e) => {
                        if (!e.target.closest('button, a, input, select')) openViewModal(item.id);
                    });

                    tr.innerHTML = `
                        <td class="col-equipment">${equipmentCell(item)}</td>
                        <td class="col-user">${userCell(item)}</td>
                        <td class="col-date">${escapeHTML(item.borrowDate)}</td>
                        <td class="col-due">${dueCell(item)}</td>
                        <td class="col-status"><span class="status-badge ${statusClass(item.status)}">${escapeHTML(item.status)}</span></td>
                        <td class="col-action">${actionContent}</td>
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

            // Equipment: name (one line, "…" when long) with category and
            // serial number underneath.
            function equipmentCell(item) {
                const sub = [item.category, item.serial].filter(Boolean).join(' · ');
                return `<span class="equipment-name-text" title="${escapeHTML(item.equipment)}">${escapeHTML(item.equipment)}</span>`
                    + (sub ? `<span class="cell-sub" title="${escapeHTML(sub)}">${escapeHTML(sub)}</span>` : '');
            }

            // User: borrower's name with the role underneath, or "Not borrowed"
            // for equipment that has no active loan.
            function userCell(item) {
                if (!item.borrower || item.borrower === '—') {
                    return '<span class="cell-muted">Not borrowed</span>';
                }
                return `<span class="user-name-text" title="${escapeHTML(item.borrower)}">${escapeHTML(item.borrower)}</span>`
                    + `<span class="user-role-text">${escapeHTML(item.role)}</span>`;
            }

            // Due: the due date, plus how late the loan is (or "Due today" /
            // "Due tomorrow" when it is close).
            function dueCell(item) {
                if (!item.returnDate || item.returnDate === '—') return '<span class="cell-muted">—</span>';
                let note = '';
                const late = Number(item.daysOverdue) || 0;
                if (late > 0) {
                    note = `<span class="due-note is-overdue">${late} ${late === 1 ? 'day' : 'days'} overdue</span>`;
                } else if (item.daysUntilDue === 0) {
                    note = '<span class="due-note is-soon">Due today</span>';
                } else if (item.daysUntilDue === 1) {
                    note = '<span class="due-note is-soon">Due tomorrow</span>';
                }
                return `<span class="due-date-text">${escapeHTML(item.returnDate)}</span>${note}`;
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

            // Borrower avatar: their own profile picture; otherwise (or if it can't
            // be loaded) their initials on the EquipTrack blue, drawn locally so it
            // never depends on an outside service.
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
                const avatar = document.getElementById('viewBorrowerAvatar');
                const fallback = initialsAvatar(name);
                avatar.alt = `${name || 'Borrower'}'s profile picture`;
                avatar.onerror = function () {
                    this.onerror = null;
                    this.src = fallback;
                };
                avatar.src = url || fallback;
            }

            // Equipment photo: the whole item on a blurred fill, or a placeholder
            function showEquipmentPhoto(url, name) {
                const media = document.getElementById('viewEquipmentMedia');
                const img = document.getElementById('viewEquipmentImg');
                const backdrop = document.getElementById('viewEquipmentImgBackdrop');
                media.classList.toggle('is-missing', !url);
                img.alt = name || 'Equipment photo';
                img.onerror = function () {
                    this.onerror = null;
                    media.classList.add('is-missing');
                };
                img.src = url || '';
                backdrop.src = url || '';
            }

            window.openViewModal = function(id) {
                const item = monitoringData.find(m => m.id === id);
                if (!item) return;

                const borrowed = item.borrower && item.borrower !== '—';

                // Borrower (or "Not currently borrowed")
                document.getElementById('viewBorrowerBlock').hidden = !borrowed;
                document.getElementById('viewNotBorrowed').hidden = borrowed;
                if (borrowed) {
                    showBorrowerAvatar(item.avatar, item.borrower);
                    document.getElementById('viewBorrower').textContent = item.borrower;
                    document.getElementById('viewRole').textContent = item.role || '—';
                    document.getElementById('viewBorrowerId').textContent = item.id_number || 'N/A';
                }

                // Equipment
                showEquipmentPhoto(item.img, item.equipment);
                document.getElementById('viewEquipmentName').textContent = item.equipment || '—';
                document.getElementById('viewCategory').textContent = item.category || '—';
                document.getElementById('viewSerial').textContent = item.serial || '';
                document.getElementById('viewSerialWrap').hidden = !item.serial;

                // Loan dates, with the same overdue / due-soon note as the table
                document.getElementById('viewLoanSection').hidden = !borrowed;
                document.getElementById('viewBorrowDate').textContent = item.borrowDate || '—';
                document.getElementById('viewReturnDate').textContent = item.returnDate || '—';
                const note = document.getElementById('viewDueNote');
                const late = Number(item.daysOverdue) || 0;
                note.hidden = true;
                note.className = 'mon-due-note';
                if (late > 0) {
                    note.textContent = `${late} ${late === 1 ? 'day' : 'days'} overdue`;
                    note.classList.add('is-overdue');
                    note.hidden = false;
                } else if (item.daysUntilDue === 0 || item.daysUntilDue === 1) {
                    note.textContent = item.daysUntilDue === 0 ? 'Due today' : 'Due tomorrow';
                    note.classList.add('is-soon');
                    note.hidden = false;
                }

                const statusBadge = document.getElementById('viewStatus');
                statusBadge.textContent = item.status;
                statusBadge.className = 'status-badge ' + statusClass(item.status);

                viewMonitoringModal.classList.add('show');
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
