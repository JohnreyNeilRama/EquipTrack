<?php
// EquipTrack — Department Personnel Dashboard
// Design-only static layout (no backend logic yet)
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
    <!-- Admin Specific Stylesheet -->
    <link rel="stylesheet" href="../ccs/admindashboard.css">
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Stylesheet -->
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

    <div class="app-shell">

        <!-- Mobile scrim -->
        <div class="sidebar-scrim" id="sidebarScrim"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <img src="../images/EquipTrack_logo.png" alt="EquipTrack Logo" class="sidebar-logo-img">
                <button class="sidebar-close-btn" id="sidebarCloseBtn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">General</div>
                <a href="departmentdashboard.php" class="nav-item">
                    <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
                </a>
                <a href="equipment.php" class="nav-item">
                    <i class="fa-solid fa-box"></i> <span>Department Equipment</span>
                </a>
                <a href="requests.php" class="nav-item">
                    <i class="fa-solid fa-clipboard-list"></i> <span>Borrow Requests</span>
                </a>
                <a href="users.php" class="nav-item">
                    <i class="fa-solid fa-users"></i> <span>Department Users</span>
                </a>
                
                <div class="sidebar-section-label">Monitoring</div>
                <a href="monitoring.php" class="nav-item">
                    <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
                </a>
                <a href="history.php" class="nav-item active">
                    <i class="fa-solid fa-clock-rotate-left"></i> <span>Borrowing History</span>
                </a>

                <div class="sidebar-section-label">Account</div>
                <a href="profile.php" class="nav-item">
                    <i class="fa-solid fa-user"></i> <span>Profile</span>
                </a>
                <a href="../login.php" class="nav-item logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main column -->
        <div class="main-column">

            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="topbar-menu-btn" id="topbarMenuBtn"><i class="fa-solid fa-bars"></i></button>
                    <div class="page-title-group">
                        <h1>Borrowing History</h1>
                        <span>Completed transactions within your department</span>
                    </div>
                </div>

                <div class="topbar-right">
                    <button class="icon-btn" id="themeToggleBtn" title="Toggle dark mode">
                        <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                    </button>
                    <button class="icon-btn" id="notifBtn" title="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <span class="notif-dot"></span>
                    </button>

                    <span class="topbar-divider"></span>

                    <div class="profile-dropdown-wrap" id="profileDropdownWrap">
                        <button class="profile-trigger" id="profileTrigger">
                            <span class="profile-avatar">IT</span>
                            <span class="profile-meta">
                                <span class="profile-name">IT Department</span>
                                <span class="profile-role">Department Personnel</span>
                            </span>
                            <i class="fa-solid fa-chevron-down chev"></i>
                        </button>

                        <div class="profile-dropdown-menu" id="profileDropdownMenu">
                            <div class="dropdown-header">
                                <span class="name">IT Department</span>
                                <span class="email">it.dept@equiptrack.edu</span>
                            </div>
                            <a href="profile.php" class="dropdown-link"><i class="fa-solid fa-user"></i> My Profile</a>
                            <a href="../login.php" class="dropdown-link danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="content">
                <div class="page-header">
                    <span class="page-header-eyebrow">Department Personnel</span>
                    <h2>Borrowing History</h2>
                    <p>View completed borrowing and return transactions within your department.</p>
                </div>

                <!-- Quick Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-total"><i class="fa-solid fa-file-lines"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statTotal">4</span>
                            <span class="stat-label">Total Transactions</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-returned"><i class="fa-solid fa-box-open"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statReturned">3</span>
                            <span class="stat-label">Successfully Returned</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-duration"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statDuration">5.3 days</span>
                            <span class="stat-label">Average Borrowing Duration</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-late"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statLate">1</span>
                            <span class="stat-label">Late Returns</span>
                        </div>
                    </div>
                </div>

                <!-- Search & Filter Toolbar -->
                <div class="controls-bar">
                    <div class="controls-left">
                        <div class="search-box-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="searchInput" placeholder="Search by name, ID number, or equipment...">
                        </div>

                        <div class="filter-select-wrapper">
                            <select id="dateRangeFilter">
                                <option value="all">All Dates</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>

                        <div class="custom-range-wrapper" id="customRangeWrapper">
                            <input type="date" id="customDateFrom">
                            <span>to</span>
                            <input type="date" id="customDateTo">
                        </div>

                        <div class="filter-select-wrapper">
                            <select id="categoryFilter">
                                <option value="all">All Categories</option>
                                <option value="Laptops & Computers">Laptops & Computers</option>
                                <option value="Audio-Visual Equipment">Audio-Visual Equipment</option>
                                <option value="Photography Equipment">Photography Equipment</option>
                                <option value="Networking Equipment">Networking Equipment</option>
                                <option value="Cables & Accessories">Cables & Accessories</option>
                                <option value="Office Supplies">Office Supplies</option>
                            </select>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>

                        <div class="filter-select-wrapper">
                            <select id="userTypeFilter">
                                <option value="all">All Users</option>
                                <option value="Student">Students</option>
                                <option value="Faculty">Faculty Members</option>
                            </select>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                    </div>

                    <div class="dept-badge" title="Your department">
                        <i class="fa-solid fa-building"></i>
                        <span>IT Department</span>
                    </div>
                </div>

                <!-- Borrowing History Table -->
                <div class="admin-table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>ID Number</th>
                                <th>Name</th>
                                <th>User Type</th>
                                <th>Year Level / Attainment</th>
                                <th>Equipment</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr class="admin-table-row" data-id="TXN-2451" data-name="johnrey neil rama" data-idnumber="20230456" data-usertype="Student" data-category="Laptops & Computers" data-equipment="dell latitude 5420 laptop" data-date="2026-06-10">
                                <td class="row-transaction-id">TXN-2451</td>
                                <td class="row-id">20230456</td>
                                <td class="row-name">Johnrey Neil Rama</td>
                                <td><span class="type-badge type-student"><i class="fa-solid fa-user-graduate"></i> Student</span></td>
                                <td>4th Year</td>
                                <td class="eq-name-cell"><span class="eq-name-text">Dell Latitude 5420 Laptop</span><span class="eq-category-text">Laptops & Computers</span></td>
                                <td>Jun 10, 2026</td>
                                <td>Jun 16, 2026</td>
                                <td><span class="status-badge status-returned"><span class="status-dot"></span> Returned</span></td>
                                <td class="action-cell"><div class="action-buttons"><button class="btn-action-view" data-view="TXN-2451" title="View transaction details"><i class="fa-solid fa-eye"></i></button></div></td>
                            </tr>
                            <tr class="admin-table-row" data-id="TXN-2452" data-name="jeffrey gaviola" data-idnumber="fac-2023-014" data-usertype="Faculty" data-category="Audio-Visual Equipment" data-equipment="epson projector eb-x06" data-date="2026-06-08">
                                <td class="row-transaction-id">TXN-2452</td>
                                <td class="row-id">FAC-2023-014</td>
                                <td class="row-name">Jeffrey Gaviola</td>
                                <td><span class="type-badge type-faculty"><i class="fa-solid fa-chalkboard-user"></i> Faculty</span></td>
                                <td>Master's Degree</td>
                                <td class="eq-name-cell"><span class="eq-name-text">Epson Projector EB-X06</span><span class="eq-category-text">Audio-Visual Equipment</span></td>
                                <td>Jun 8, 2026</td>
                                <td>Jun 15, 2026</td>
                                <td><span class="status-badge status-returned-late"><span class="status-dot"></span> Returned Late</span></td>
                                <td class="action-cell"><div class="action-buttons"><button class="btn-action-view" data-view="TXN-2452" title="View transaction details"><i class="fa-solid fa-eye"></i></button></div></td>
                            </tr>
                            <tr class="admin-table-row" data-id="TXN-2453" data-name="gabriel fernandez" data-idnumber="20230123" data-usertype="Student" data-category="Photography Equipment" data-equipment="canon eos 200d camera" data-date="2026-05-28">
                                <td class="row-transaction-id">TXN-2453</td>
                                <td class="row-id">20230123</td>
                                <td class="row-name">Gabriel Fernandez</td>
                                <td><span class="type-badge type-student"><i class="fa-solid fa-user-graduate"></i> Student</span></td>
                                <td>3rd Year</td>
                                <td class="eq-name-cell"><span class="eq-name-text">Canon EOS 200D Camera</span><span class="eq-category-text">Photography Equipment</span></td>
                                <td>May 28, 2026</td>
                                <td>Jun 1, 2026</td>
                                <td><span class="status-badge status-returned"><span class="status-dot"></span> Returned</span></td>
                                <td class="action-cell"><div class="action-buttons"><button class="btn-action-view" data-view="TXN-2453" title="View transaction details"><i class="fa-solid fa-eye"></i></button></div></td>
                            </tr>
                            <tr class="admin-table-row" data-id="TXN-2454" data-name="jeffrey gaviola" data-idnumber="fac-2023-014" data-usertype="Faculty" data-category="Audio-Visual Equipment" data-equipment="wireless microphone set" data-date="2026-05-20">
                                <td class="row-transaction-id">TXN-2454</td>
                                <td class="row-id">FAC-2023-014</td>
                                <td class="row-name">Jeffrey Gaviola</td>
                                <td><span class="type-badge type-faculty"><i class="fa-solid fa-chalkboard-user"></i> Faculty</span></td>
                                <td>Master's Degree</td>
                                <td class="eq-name-cell"><span class="eq-name-text">Wireless Microphone Set</span><span class="eq-category-text">Audio-Visual Equipment</span></td>
                                <td>May 20, 2026</td>
                                <td>May 24, 2026</td>
                                <td><span class="status-badge status-returned"><span class="status-dot"></span> Returned</span></td>
                                <td class="action-cell"><div class="action-buttons"><button class="btn-action-view" data-view="TXN-2454" title="View transaction details"><i class="fa-solid fa-eye"></i></button></div></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="empty-state-container" id="emptyState" style="display:none;">
                        <div class="empty-state-icon"><i class="fa-solid fa-inbox"></i></div>
                        <h4>No borrowing history found.</h4>
                        <p>Completed borrowing transactions within your department will appear here.</p>
                    </div>

                    <div class="pagination-container" id="paginationBar">
                        <span class="pagination-info" id="paginationInfo">Showing 1-10 of 10 transactions</span>
                        <div class="pagination-buttons">
                            <button class="btn-page" disabled><i class="fa-solid fa-chevron-left"></i></button>
                            <button class="btn-page active">1</button>
                            <button class="btn-page" disabled><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </main>

        </div>
    </div>

    <!-- Transaction Details Drawer -->
    <div class="drawer-overlay" id="drawerOverlay"></div>
    <aside class="user-drawer" id="txnDrawer">
        <div class="drawer-header">
            <h3>Transaction Details</h3>
            <button class="drawer-close-btn" id="drawerCloseBtn"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="drawer-body">
            <span class="drawer-txn-badge" id="drawerTxnId">TXN-0000</span>

            <div class="drawer-profile">
                <div class="drawer-avatar" id="drawerAvatar">--</div>
                <div class="drawer-profile-meta">
                    <h4 id="drawerFullName">—</h4>
                    <span id="drawerUserTypeLine">—</span>
                </div>
            </div>

            <div class="drawer-section">
                <p class="drawer-section-title">Borrower Information</p>
                <div class="info-grid">
                    <div class="info-item"><span class="label">Full Name</span><span class="value" id="biFullName">—</span></div>
                    <div class="info-item"><span class="label">ID Number</span><span class="value" id="biIdNumber">—</span></div>
                    <div class="info-item"><span class="label">User Type</span><span class="value" id="biUserType">—</span></div>
                    <div class="info-item"><span class="label" id="biLevelLabel">Year Level</span><span class="value" id="biLevel">—</span></div>
                    <div class="info-item"><span class="label">Department</span><span class="value" id="biDepartment">—</span></div>
                    <div class="info-item"><span class="label">Email Address</span><span class="value" id="biEmail">—</span></div>
                </div>
            </div>

            <div class="drawer-section">
                <p class="drawer-section-title">Equipment Information</p>
                <div class="equipment-info-card">
                    <div class="equipment-info-img" id="eqImg" style="display:flex;align-items:center;justify-content:center;background:var(--accent);color:var(--primary);font-size:26px;">
                        <i class="fa-solid fa-box" id="eqIcon"></i>
                    </div>
                    <div class="equipment-info-meta">
                        <span class="equipment-info-name" id="eqName">—</span>
                        <span class="equipment-info-code" id="eqCode">—</span>
                        <span class="equipment-info-category" id="eqCategory">—</span>
                    </div>
                </div>
            </div>

            <div class="drawer-section">
                <p class="drawer-section-title">Borrowing Details</p>
                <div class="info-grid">
                    <div class="info-item"><span class="label">Borrow Date</span><span class="value" id="bdBorrowDate">—</span></div>
                    <div class="info-item"><span class="label">Due Date</span><span class="value" id="bdDueDate">—</span></div>
                    <div class="info-item"><span class="label">Return Date</span><span class="value" id="bdReturnDate">—</span></div>
                    <div class="info-item"><span class="label">Borrowing Duration</span><span class="value" id="bdDuration">—</span></div>
                    <div class="info-item full"><span class="label">Return Status</span><span class="value" id="bdReturnStatus">—</span></div>
                </div>
            </div>

            <div class="drawer-section">
                <p class="drawer-section-title">Return Information</p>
                <div class="info-grid">
                    <div class="info-item full"><span class="label">Equipment Condition Upon Return</span><span class="value" id="riCondition">—</span></div>
                    <div class="info-item full"><span class="label">Confirmed By</span><span class="value" id="riConfirmedBy">—</span></div>
                </div>
                <div class="remarks-block">
                    <span class="remarks-label">Remarks</span>
                    <p class="drawer-remarks-note" id="riRemarks">No remarks.</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Interactivity -->
    <script>
        // Dark mode toggle
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');

        if (document.documentElement.classList.contains('dark-theme')) {
            themeToggleIcon.className = 'fa-solid fa-sun';
        }

        themeToggleBtn.addEventListener('click', () => {
            const isDark = document.documentElement.classList.toggle('dark-theme');
            themeToggleIcon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            localStorage.setItem('dept-dashboard-theme', isDark ? 'dark' : 'light');
        });

        // Profile dropdown
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');

        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            profileDropdownMenu.classList.remove('show');
        });

        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarScrim = document.getElementById('sidebarScrim');
        const topbarMenuBtn = document.getElementById('topbarMenuBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

        function openSidebar() {
            sidebar.classList.add('open');
            sidebarScrim.classList.add('show');
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarScrim.classList.remove('show');
        }

        topbarMenuBtn.addEventListener('click', openSidebar);
        sidebarCloseBtn.addEventListener('click', closeSidebar);
        sidebarScrim.addEventListener('click', closeSidebar);

        // Notification bell (placeholder)
        document.getElementById('notifBtn').addEventListener('click', () => {
            alert('No new notifications yet.');
        });

        // ==================================================================
        // Borrowing History — data, drawer, search & filters
        // ==================================================================

        const txnData = {
            'TXN-2451': {
                fullName: 'Johnrey Neil Rama', idNumber: '20230456', userType: 'Student',
                levelLabel: 'Year Level', level: '4th Year', department: 'IT Department',
                email: 'johnrey.rama@student.equiptrack.edu',
                equipment: 'Dell Latitude 5420 Laptop', code: 'EQ-LAP-014', category: 'Laptops & Computers', icon: 'fa-laptop',
                borrowDate: 'June 10, 2026', dueDate: 'June 17, 2026', returnDate: 'June 16, 2026',
                duration: '6 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Good — no visible damage', confirmedBy: 'Engr. Ramon Cruz', remarks: 'No remarks.'
            },
            'TXN-2452': {
                fullName: 'Jeffrey Gaviola', idNumber: 'FAC-2023-014', userType: 'Faculty',
                levelLabel: 'Highest Educational Attainment', level: "Master's Degree", department: 'IT Department',
                email: 'N/A',
                equipment: 'Epson Projector EB-X06', code: 'EQ-PRJ-007', category: 'Audio-Visual Equipment', icon: 'fa-video',
                borrowDate: 'June 8, 2026', dueDate: 'June 12, 2026', returnDate: 'June 15, 2026',
                duration: '7 days', returnStatus: 'Returned 3 day(s) late', statusClass: 'late',
                condition: 'Good — minor lens smudge, cleaned', confirmedBy: 'Mr. John Dela Cruz',
                remarks: 'Returned 3 days late due to extended seminar use.'
            },
            'TXN-2453': {
                fullName: 'Gabriel Fernandez', idNumber: '20230123', userType: 'Student',
                levelLabel: 'Year Level', level: '3rd Year', department: 'IT Department',
                email: 'gabriel.fernandez@student.equiptrack.edu',
                equipment: 'Canon EOS 200D Camera', code: 'EQ-CAM-003', category: 'Photography Equipment', icon: 'fa-camera',
                borrowDate: 'May 28, 2026', dueDate: 'June 2, 2026', returnDate: 'June 1, 2026',
                duration: '4 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Excellent', confirmedBy: 'Engr. Ramon Cruz', remarks: 'No remarks.'
            },
            'TXN-2454': {
                fullName: 'Jeffrey Gaviola', idNumber: 'FAC-2023-014', userType: 'Faculty',
                levelLabel: 'Highest Educational Attainment', level: "Master's Degree", department: 'IT Department',
                email: 'N/A',
                equipment: 'Wireless Microphone Set', code: 'EQ-MIC-011', category: 'Audio-Visual Equipment', icon: 'fa-microphone',
                borrowDate: 'May 20, 2026', dueDate: 'May 25, 2026', returnDate: 'May 24, 2026',
                duration: '4 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Good', confirmedBy: 'Mr. John Dela Cruz', remarks: 'No remarks.'
            },
            'TXN-2455': {
                fullName: 'Michael John Silva', idNumber: '20230812', userType: 'Student',
                levelLabel: 'Year Level', level: '2nd Year', department: 'IT Department',
                email: 'michaeljohn.silva@student.equiptrack.edu',
                equipment: 'Samsung Galaxy Tab A8', code: 'EQ-TAB-006', category: 'Laptops & Computers', icon: 'fa-tablet-screen-button',
                borrowDate: 'May 15, 2026', dueDate: 'May 20, 2026', returnDate: 'May 23, 2026',
                duration: '8 days', returnStatus: 'Returned 3 day(s) late', statusClass: 'late',
                condition: 'Good — no visible damage', confirmedBy: 'Engr. Ramon Cruz',
                remarks: 'Late return due to unforeseen exam conflicts.'
            },
            'TXN-2456': {
                fullName: 'Johnrey Neil Rama', idNumber: '20230456', userType: 'Student',
                levelLabel: 'Year Level', level: '4th Year', department: 'IT Department',
                email: 'johnrey.rama@student.equiptrack.edu',
                equipment: 'TP-Link Wireless Router', code: 'EQ-NET-002', category: 'Networking Equipment', icon: 'fa-wifi',
                borrowDate: 'May 10, 2026', dueDate: 'May 13, 2026', returnDate: 'May 13, 2026',
                duration: '3 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Excellent', confirmedBy: 'Mr. John Dela Cruz', remarks: 'No remarks.'
            },
            'TXN-2457': {
                fullName: 'Jeffrey Gaviola', idNumber: 'FAC-2023-014', userType: 'Faculty',
                levelLabel: 'Highest Educational Attainment', level: "Master's Degree", department: 'IT Department',
                email: 'N/A',
                equipment: 'HDMI Cable 10m', code: 'EQ-CBL-020', category: 'Cables & Accessories', icon: 'fa-ethernet',
                borrowDate: 'May 5, 2026', dueDate: 'May 8, 2026', returnDate: 'May 8, 2026',
                duration: '3 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Good', confirmedBy: 'Engr. Ramon Cruz', remarks: 'No remarks.'
            },
            'TXN-2458': {
                fullName: 'Gabriel Fernandez', idNumber: '20230123', userType: 'Student',
                levelLabel: 'Year Level', level: '3rd Year', department: 'IT Department',
                email: 'gabriel.fernandez@student.equiptrack.edu',
                equipment: 'Extension Cord (Heavy Duty)', code: 'EQ-CBL-009', category: 'Cables & Accessories', icon: 'fa-plug',
                borrowDate: 'April 28, 2026', dueDate: 'May 2, 2026', returnDate: 'May 5, 2026',
                duration: '7 days', returnStatus: 'Returned 3 day(s) late', statusClass: 'late',
                condition: 'Fair — slight wear on cord', confirmedBy: 'Mr. John Dela Cruz',
                remarks: 'Returned late; borrower was on department leave.'
            },
            'TXN-2459': {
                fullName: 'Michael John Silva', idNumber: '20230812', userType: 'Student',
                levelLabel: 'Year Level', level: '2nd Year', department: 'IT Department',
                email: 'michaeljohn.silva@student.equiptrack.edu',
                equipment: 'Portable Bluetooth Speaker', code: 'EQ-AUD-015', category: 'Audio-Visual Equipment', icon: 'fa-volume-high',
                borrowDate: 'April 20, 2026', dueDate: 'April 24, 2026', returnDate: 'April 23, 2026',
                duration: '3 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Excellent', confirmedBy: 'Engr. Ramon Cruz', remarks: 'No remarks.'
            },
            'TXN-2460': {
                fullName: 'Jeffrey Gaviola', idNumber: 'FAC-2023-014', userType: 'Faculty',
                levelLabel: 'Highest Educational Attainment', level: "Master's Degree", department: 'IT Department',
                email: 'N/A',
                equipment: 'Whiteboard Marker Set', code: 'EQ-STA-004', category: 'Office Supplies', icon: 'fa-marker',
                borrowDate: 'April 10, 2026', dueDate: 'April 13, 2026', returnDate: 'April 13, 2026',
                duration: '3 days', returnStatus: 'Returned on time', statusClass: 'returned',
                condition: 'Good', confirmedBy: 'Mr. John Dela Cruz', remarks: 'No remarks.'
            }
        };

        function initials(name) {
            const clean = name.replace(/^(Prof\.|Dr\.|Mr\.|Ms\.|Mrs\.|Engr\.)\s*/i, '');
            const parts = clean.trim().split(/\s+/);
            return ((parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')).toUpperCase();
        }

        // Drawer open/close
        const drawerOverlay = document.getElementById('drawerOverlay');
        const txnDrawer = document.getElementById('txnDrawer');

        function openDrawer(id) {
            const t = txnData[id];
            if (!t) return;

            document.getElementById('drawerTxnId').textContent = id;
            document.getElementById('drawerAvatar').textContent = initials(t.fullName);
            document.getElementById('drawerFullName').textContent = t.fullName;
            document.getElementById('drawerUserTypeLine').textContent = t.userType + ' · ' + t.department;

            document.getElementById('biFullName').textContent = t.fullName;
            document.getElementById('biIdNumber').textContent = t.idNumber;
            document.getElementById('biUserType').textContent = t.userType;
            document.getElementById('biLevelLabel').textContent = t.levelLabel;
            document.getElementById('biLevel').textContent = t.level;
            document.getElementById('biDepartment').textContent = t.department;
            document.getElementById('biEmail').textContent = t.email;

            document.getElementById('eqIcon').className = 'fa-solid ' + t.icon;
            document.getElementById('eqName').textContent = t.equipment;
            document.getElementById('eqCode').textContent = t.code;
            document.getElementById('eqCategory').textContent = t.category;

            document.getElementById('bdBorrowDate').textContent = t.borrowDate;
            document.getElementById('bdDueDate').textContent = t.dueDate;
            document.getElementById('bdReturnDate').textContent = t.returnDate;
            document.getElementById('bdDuration').textContent = t.duration;

            const returnStatusEl = document.getElementById('bdReturnStatus');
            returnStatusEl.textContent = t.returnStatus;
            returnStatusEl.style.color = t.statusClass === 'late' ? 'var(--warning)' : 'var(--success)';

            document.getElementById('riCondition').textContent = t.condition;
            document.getElementById('riConfirmedBy').textContent = t.confirmedBy;
            document.getElementById('riRemarks').textContent = t.remarks;

            txnDrawer.classList.add('show');
            drawerOverlay.classList.add('show');
        }

        function closeDrawer() {
            txnDrawer.classList.remove('show');
            drawerOverlay.classList.remove('show');
        }

        document.getElementById('drawerCloseBtn').addEventListener('click', closeDrawer);
        drawerOverlay.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDrawer();
        });

        // Search & filter toolbar
        const searchInput = document.getElementById('searchInput');
        const dateRangeFilter = document.getElementById('dateRangeFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const userTypeFilter = document.getElementById('userTypeFilter');
        const customRangeWrapper = document.getElementById('customRangeWrapper');
        const customDateFrom = document.getElementById('customDateFrom');
        const customDateTo = document.getElementById('customDateTo');
        const emptyState = document.getElementById('emptyState');
        const paginationBar = document.getElementById('paginationBar');
        const paginationInfo = document.getElementById('paginationInfo');

        // Restore dynamic click listeners for visual view buttons on static rows
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', () => openDrawer(btn.getAttribute('data-view')));
        });
        
        // Also support clicking anywhere on static rows to open drawer
        const tableRows = Array.from(document.querySelectorAll('#historyTableBody .admin-table-row'));
        tableRows.forEach(row => {
            row.addEventListener('click', (e) => {
                if (!e.target.closest('button')) {
                    openDrawer(row.getAttribute('data-id'));
                }
            });
        });

        dateRangeFilter.addEventListener('change', () => {
            customRangeWrapper.classList.toggle('show', dateRangeFilter.value === 'custom');
            applyFilters();
        });

        [searchInput, categoryFilter, userTypeFilter, customDateFrom, customDateTo].forEach(el => {
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });

        function isWithinRange(dateStr) {
            const rowDate = new Date(dateStr + 'T00:00:00');
            const today = new Date('2026-06-17T00:00:00'); // latest sample transaction date as "today" reference
            const range = dateRangeFilter.value;

            if (range === 'all') return true;

            if (range === 'today') {
                return rowDate.toDateString() === today.toDateString();
            }
            if (range === 'week') {
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                return rowDate >= weekStart && rowDate <= weekEnd;
            }
            if (range === 'month') {
                return rowDate.getMonth() === today.getMonth() && rowDate.getFullYear() === today.getFullYear();
            }
            if (range === 'custom') {
                const from = customDateFrom.value ? new Date(customDateFrom.value + 'T00:00:00') : null;
                const to = customDateTo.value ? new Date(customDateTo.value + 'T00:00:00') : null;
                if (from && rowDate < from) return false;
                if (to && rowDate > to) return false;
                return true;
            }
            return true;
        }

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const category = categoryFilter.value;
            const userType = userTypeFilter.value;
            let visibleCount = 0;

            tableRows.forEach(row => {
                const name = row.getAttribute('data-name');
                const idNumber = row.getAttribute('data-idnumber');
                const equipment = row.getAttribute('data-equipment');
                const rowCategory = row.getAttribute('data-category');
                const rowUserType = row.getAttribute('data-usertype');
                const rowDate = row.getAttribute('data-date');

                const matchesSearch = !query || name.includes(query) || idNumber.includes(query) || equipment.includes(query);
                const matchesCategory = category === 'all' || rowCategory === category;
                const matchesUserType = userType === 'all' || rowUserType === userType;
                const matchesDate = isWithinRange(rowDate);

                const visible = matchesSearch && matchesCategory && matchesUserType && matchesDate;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            const hasResults = visibleCount > 0;
            emptyState.style.display = hasResults ? 'none' : 'flex';
            paginationBar.style.display = hasResults ? 'flex' : 'none';
            if (hasResults) {
                paginationInfo.textContent = 'Showing 1-' + visibleCount + ' of ' + visibleCount + ' transactions';
            }
        }
    </script>
</body>
</html>
