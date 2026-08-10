<?php
// EquipTrack — Department Personnel Dashboard
// Department Borrowing History Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Borrowing History</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Base Layout Stylesheet -->
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Page Stylesheet -->
    <link rel="stylesheet" href="css/history.css">
    <script>
        (function() {
            if (localStorage.getItem('dept-dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body>

    <!-- Mobile scrim -->
    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../images/EquipTrack_logo.png" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="departmentdashboard.php" class="nav-item">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-box"></i> <span>Department Equipment</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Borrow Requests</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Department Users</span>
            </a>
            <a href="monitoring.php" class="nav-item">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="history.php" class="nav-item active">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Borrowing History</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">

        <!-- Top Navbar -->
        <header class="top-navbar profile-navbar">
            <button class="topbar-menu-btn" id="topbarMenuBtn" style="display: none;"><i class="fa-solid fa-bars"></i></button>
            <div class="navbar-right">
                <span class="navbar-divider"></span>
                <div class="icon-btn" id="themeToggleBtn" title="Toggle theme">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification" id="notifBtn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">DP</div>
                    <span class="user-name">Department</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">Department</span>
                            <span class="header-email">department@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../login.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

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
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterCategory">
                    <option value="all">All Categories</option>
                    <option value="laptops">Laptops & Computers</option>
                    <option value="audio-visual">Audio-Visual Equipment</option>
                    <option value="photography">Photography Equipment</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>

        <!-- Borrowing History Data Table Card -->
        <div class="history-table-container">
            <table class="history-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Transaction ID</th>
                        <th style="width: 10%;">ID Number</th>
                        <th style="width: 14%;">Name</th>
                        <th style="width: 10%;">User Type</th>
                        <th style="width: 14%;">Year Level / Attainment</th>
                        <th style="width: 18%;">Equipment</th>
                        <th style="width: 9%;">Borrow Date</th>
                        <th style="width: 9%;">Return Date</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 6%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>

            <!-- Table Footer Pagination Matching Reference Image -->
            <div class="table-footer-pagination">
                <span class="pagination-info" id="paginationInfo">Showing 0 of 0 transactions</span>
                <div class="pagination-controls">
                    <button class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-clock-rotate-left empty-state-icon"></i>
            <h4>No records found</h4>
            <p>No borrowing history transactions match your search or filter parameters.</p>
        </div>

    </main>

    <!-- Transaction Details Drawer -->
    <div class="drawer-overlay" id="drawerOverlay"></div>
    <aside class="user-drawer" id="txnDrawer">
        <div class="drawer-header">
            <h3>Transaction Details</h3>
            <button class="drawer-close-btn" id="drawerCloseBtn">&times;</button>
        </div>
        <div class="drawer-body">
            <span class="drawer-txn-badge" id="drawerTxnId">TXN-0000</span>

            <div class="info-grid">
                <div class="info-item"><span class="label">Full Name</span><span class="value" id="biFullName">—</span></div>
                <div class="info-item"><span class="label">ID Number</span><span class="value" id="biIdNumber">—</span></div>
                <div class="info-item"><span class="label">User Type</span><span class="value" id="biUserType">—</span></div>
                <div class="info-item"><span class="label">Year / Attainment</span><span class="value" id="biLevel">—</span></div>
                <div class="info-item full"><span class="label">Equipment Name</span><span class="value" id="eqName">—</span></div>
                <div class="info-item full"><span class="label">Category</span><span class="value" id="eqCategory">—</span></div>
                <div class="info-item"><span class="label">Borrow Date</span><span class="value" id="bdBorrowDate">—</span></div>
                <div class="info-item"><span class="label">Return Date</span><span class="value" id="bdReturnDate">—</span></div>
                <div class="info-item full"><span class="label">Return Status</span><span class="value" id="bdReturnStatus">—</span></div>
            </div>
        </div>
    </aside>

    <!-- Interactivity Script -->
    <script>
        // Dark mode toggle
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');

        if (document.documentElement.classList.contains('dark-theme')) {
            if (themeToggleIcon) themeToggleIcon.className = 'fa-solid fa-sun';
        }

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const isDark = document.documentElement.classList.toggle('dark-theme');
                if (themeToggleIcon) themeToggleIcon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
                localStorage.setItem('dept-dashboard-theme', isDark ? 'dark' : 'light');
            });
        }

        // Profile dropdown
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');

        if (userProfileDropdown && dropdownMenu) {
            userProfileDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                dropdownMenu.classList.remove('show');
            });
        }

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
    </script>

    <!-- Data & Table Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('historyTableBody');
            const searchInput = document.getElementById('searchHistory');
            const filterRoleSelect = document.getElementById('filterRole');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');
            const emptyStateContainer = document.getElementById('emptyStateContainer');
            const paginationInfo = document.getElementById('paginationInfo');

            // Drawer elements
            const drawerOverlay = document.getElementById('drawerOverlay');
            const txnDrawer = document.getElementById('txnDrawer');
            const drawerCloseBtn = document.getElementById('drawerCloseBtn');

            // Storage Management
            let historyData = JSON.parse(localStorage.getItem('equip-track-history-data'));
            if (!historyData || !Array.isArray(historyData)) {
                historyData = [];
            } else {
                historyData = historyData.filter(h => !['TXN-2451', 'TXN-2452', 'TXN-2453', 'TXN-2454'].includes(h.txnid));
            }
            localStorage.setItem('equip-track-history-data', JSON.stringify(historyData));

            function updateSummaryCards() {
                const totalCount = historyData.length;
                const returnedCount = historyData.filter(h => h.status.toLowerCase() === 'returned').length;
                const lateCount = historyData.filter(h => h.status.toLowerCase() === 'returned late').length;
                const damagedCount = historyData.filter(h => h.status.toLowerCase() === 'damaged').length;

                if (document.getElementById('statTotal')) document.getElementById('statTotal').textContent = totalCount;
                if (document.getElementById('statReturned')) document.getElementById('statReturned').textContent = returnedCount;
                if (document.getElementById('statLate')) document.getElementById('statLate').textContent = lateCount;
                if (document.getElementById('statDamaged')) document.getElementById('statDamaged').textContent = damagedCount;
            }

            function renderTable() {
                tableBody.innerHTML = '';

                historyData.forEach(item => {
                    const tr = document.createElement('tr');
                    const statusLower = item.status.toLowerCase();

                    tr.setAttribute('data-role', item.userType.toLowerCase());
                    tr.setAttribute('data-status', statusLower);
                    tr.setAttribute('data-category', item.category.toLowerCase());
                    tr.setAttribute('data-search', `${item.txnid} ${item.idnumber} ${item.name} ${item.equipment} ${item.userType}`.toLowerCase());

                    tr.innerHTML = `
                        <td class="col-txnid">${escapeHTML(item.txnid)}</td>
                        <td class="col-idnum">${escapeHTML(item.idnumber)}</td>
                        <td class="col-name">${escapeHTML(item.name)}</td>
                        <td><span class="type-badge ${item.userType.toLowerCase() === 'faculty' ? 'type-faculty' : 'type-student'}">${item.userType.toLowerCase() === 'faculty' ? '<i class="fa-solid fa-chalkboard-user"></i> Faculty' : '<i class="fa-solid fa-user-graduate"></i> Student'}</span></td>
                        <td>${escapeHTML(item.attainment)}</td>
                        <td>
                            <div class="equipment-meta-cell">
                                <span class="equipment-name-title">${escapeHTML(item.equipment)}</span>
                                <span class="equipment-category-sub">${escapeHTML(item.category)}</span>
                            </div>
                        </td>
                        <td>${escapeHTML(item.borrowDate)}</td>
                        <td>${escapeHTML(item.returnDate)}</td>
                        <td>
                            <span class="status-badge-dot ${statusClass(item.status)}">
                                <span class="dot"></span> ${escapeHTML(item.status)}
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <button class="btn-action-eye" onclick="openDrawer('${item.txnid}')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    `;

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();
                filterTable();
            }

            function statusClass(status) {
                switch (status.toUpperCase()) {
                    case 'RETURNED': return 'returned';
                    case 'RETURNED LATE': return 'returned-late';
                    case 'DAMAGED': return 'damaged';
                    default: return 'returned';
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
                    document.getElementById('bdReturnStatus').textContent = item.status;

                    txnDrawer.classList.add('show');
                    drawerOverlay.classList.add('show');
                }
            };

            function closeDrawer() {
                txnDrawer.classList.remove('show');
                drawerOverlay.classList.remove('show');
            }

            if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);
            if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);

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
                    paginationInfo.textContent = `Showing ${visibleCount > 0 ? 1 : 0}-${visibleCount} of ${visibleCount} transactions`;
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
</body>
</html>
