<?php
// Department Users page setup
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Department Users</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/department.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Check for saved theme preference immediately to prevent flash of light theme
        (function() {
            if (localStorage.getItem('dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../images/EquipTrack_logo.png" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fa-solid fa-user-circle"></i> <span>Profile</span>
            </a>
            <a href="users.php" class="nav-item active">
                <i class="fa-solid fa-users"></i> <span>Department Users</span>
            </a>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Department Equipment</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Borrow Requests</span>
            </a>
            <a href="monitoring.php" class="nav-item">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="history.php" class="nav-item">
                <i class="fa-solid fa-history"></i> <span>Borrowing History</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="navbar-right">
                <div class="icon-btn" id="themeToggleBtn" title="Toggle Theme">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <img src="" alt="Avatar" class="avatar" id="navbarAvatar">
                    <span class="user-name" id="navbarPersonnelName">Loading...</span>
                    <i class="fa-solid fa-chevron-down"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name" id="dropdownPersonnelName">Loading...</span>
                            <span class="header-role" id="dropdownPersonnelRole">Lab Personnel</span>
                            <span class="header-email" id="dropdownPersonnelEmail">loading@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div style="padding: 6px 12px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Switch Department</div>
                        <button onclick="switchPersonnelDept('Information Technology Department')"><i class="fa-solid fa-building"></i> IT Department</button>
                        <button onclick="switchPersonnelDept('Engineering Department')"><i class="fa-solid fa-gears"></i> Engineering Dept</button>
                        <button onclick="switchPersonnelDept('Education Department')"><i class="fa-solid fa-graduation-cap"></i> Education Dept</button>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user-circle"></i> Profile Settings</a>
                        <a href="../login.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Header -->
        <section class="page-header">
            <h1 class="page-title">Department Users</h1>
            <p class="page-subtitle">View students and faculty members assigned to your department and monitor their borrowing activities.</p>
        </section>

        <!-- Quick Statistics Section -->
        <section class="stats-grid">
            <!-- Card 1 -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Users</span>
                    <div class="stat-icon-container icon-blue">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" id="statTotalUsers">0</span>
                    <span class="stat-desc">Registered users in your department</span>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Students</span>
                    <div class="stat-icon-container icon-indigo">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" id="statStudents">0</span>
                    <span class="stat-desc">Student accounts</span>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Faculty Members</span>
                    <div class="stat-icon-container icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" id="statFaculty">0</span>
                    <span class="stat-desc">Faculty accounts</span>
                </div>
            </div>
            <!-- Card 4 -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Active Borrowers</span>
                    <div class="stat-icon-container icon-orange">
                        <i class="fa-solid fa-box"></i>
                    </div>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" id="statActiveBorrowers">0</span>
                    <span class="stat-desc">Users currently borrowing equipment</span>
                </div>
            </div>
        </section>

        <!-- Search and Filters Card -->
        <section class="toolbar-card">
            <div class="toolbar-left">
                <!-- Search Bar -->
                <div class="search-box-wrapper">
                    <input type="text" id="searchUsers" placeholder="Search by name or ID number...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <!-- User Type Dropdown -->
                <div class="select-wrapper">
                    <select id="filterUserType">
                        <option value="all">All Users</option>
                        <option value="student">Students</option>
                        <option value="teacher">Faculty Members</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <!-- Borrowing Status Dropdown -->
                <div class="select-wrapper">
                    <select id="filterBorrowStatus">
                        <option value="all">All Status</option>
                        <option value="borrowing">Currently Borrowing</option>
                        <option value="none">No Active Borrowing</option>
                        <option value="overdue">Overdue</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            
            <div class="toolbar-right">
                <span class="dept-label-desc">Assigned:</span>
                <!-- Department Badge (Read-Only) -->
                <div class="dept-badge">
                    <i class="fa-solid fa-building-columns"></i>
                    <span id="departmentBadgeText">IT Department</span>
                </div>
            </div>
        </section>

        <!-- Department Users Table -->
        <section class="table-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Full Name</th>
                            <th>User Type</th>
                            <th>Course / Position</th>
                            <th>Borrowing Status</th>
                            <th style="width: 120px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <!-- Populated dynamically via JS -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state-container" style="display: none;">
                <div class="empty-state-icon-box">
                    <i class="fa-solid fa-users-slash"></i>
                </div>
                <h3 class="empty-state-title">No department users found.</h3>
                <p class="empty-state-desc">Users assigned to your department will appear here.</p>
            </div>

            <!-- Pagination Footer -->
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons">
                    <!-- Buttons added dynamically -->
                </div>
            </div>
        </section>
    </main>

    <!-- Sliding User Details Panel (Drawer) -->
    <div class="side-panel-overlay" id="panelOverlay"></div>
    <div class="side-panel" id="userDetailsPanel">
        <div class="side-panel-header">
            <div class="side-panel-title-area">
                <h3 class="side-panel-title">User Profile Details</h3>
                <span class="side-panel-subtitle">Read-Only View</span>
            </div>
            <button class="btn-close-panel" id="btnClosePanel" title="Close Profile">&times;</button>
        </div>
        <div class="side-panel-body">
            <!-- User Hero Banner -->
            <div class="panel-user-hero">
                <div class="panel-user-avatar" id="panelAvatar">👤</div>
                <div class="panel-user-meta">
                    <h4 class="panel-user-name" id="panelFullName">Johnrey Rama</h4>
                    <span class="panel-user-type" id="panelRoleBadge">
                        <i class="fa-solid fa-circle" style="font-size: 8px;"></i> Student
                    </span>
                </div>
            </div>

            <!-- Personal Information Section -->
            <div>
                <h4 class="info-section-title">
                    <i class="fa-solid fa-address-card" style="color: var(--accent-blue);"></i> Personal Information
                </h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value" id="panelValName">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" id="panelIdLabel">Student ID</span>
                        <span class="info-value" id="panelValId">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">User Type</span>
                        <span class="info-value" id="panelValRole">-</span>
                    </div>
                    <div class="info-item" id="panelCoursePositionContainer">
                        <span class="info-label" id="panelCoursePositionLabel">Course & Year</span>
                        <span class="info-value" id="panelValCoursePosition">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Department</span>
                        <span class="info-value" id="panelValDept">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value" id="panelValEmail">-</span>
                    </div>
                </div>
            </div>

            <!-- Current Borrowing Table Section -->
            <div>
                <h4 class="info-section-title">
                    <i class="fa-solid fa-boxes-stacked" style="color: var(--accent-blue);"></i> Current Borrowing
                </h4>
                <div class="panel-table-container">
                    <table class="panel-table">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="panelCurrentBorrowBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Borrowing History Table Section -->
            <div>
                <h4 class="info-section-title">
                    <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent-blue);"></i> Borrowing History
                </h4>
                <div class="panel-table-container">
                    <table class="panel-table">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="panelHistoryBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripting Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Seeding users in localStorage if needed
            seedUsers();
            
            // Setup current personnel logged in
            initPersonnel();

            // Dom Elements
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            const navbarPersonnelName = document.getElementById('navbarPersonnelName');
            const dropdownPersonnelName = document.getElementById('dropdownPersonnelName');
            const dropdownPersonnelRole = document.getElementById('dropdownPersonnelRole');
            const dropdownPersonnelEmail = document.getElementById('dropdownPersonnelEmail');
            const navbarAvatar = document.getElementById('navbarAvatar');
            const departmentBadgeText = document.getElementById('departmentBadgeText');

            const searchInput = document.getElementById('searchUsers');
            const filterUserType = document.getElementById('filterUserType');
            const filterBorrowStatus = document.getElementById('filterBorrowStatus');

            const tableBody = document.getElementById('usersTableBody');
            const emptyState = document.getElementById('emptyState');
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            // Quick Stats
            const statTotalUsers = document.getElementById('statTotalUsers');
            const statStudents = document.getElementById('statStudents');
            const statFaculty = document.getElementById('statFaculty');
            const statActiveBorrowers = document.getElementById('statActiveBorrowers');

            // Sliding side panel elements
            const panelOverlay = document.getElementById('panelOverlay');
            const userDetailsPanel = document.getElementById('userDetailsPanel');
            const btnClosePanel = document.getElementById('btnClosePanel');

            const panelAvatar = document.getElementById('panelAvatar');
            const panelFullName = document.getElementById('panelFullName');
            const panelRoleBadge = document.getElementById('panelRoleBadge');
            const panelValName = document.getElementById('panelValName');
            const panelIdLabel = document.getElementById('panelIdLabel');
            const panelValId = document.getElementById('panelValId');
            const panelValRole = document.getElementById('panelValRole');
            const panelCoursePositionLabel = document.getElementById('panelCoursePositionLabel');
            const panelValCoursePosition = document.getElementById('panelValCoursePosition');
            const panelValDept = document.getElementById('panelValDept');
            const panelValEmail = document.getElementById('panelValEmail');

            const panelCurrentBorrowBody = document.getElementById('panelCurrentBorrowBody');
            const panelHistoryBody = document.getElementById('panelHistoryBody');

            // Pagination state
            let currentPage = 1;
            const pageSize = 10; // 10 rows per page looks very balanced and modern
            let currentPersonnel = null;
            let users = [];
            let filteredUsers = [];

            // Initialize logged-in personnel
            function initPersonnel() {
                const defaultPersonnel = {
                    name: "Information Technology Department",
                    badgeName: "IT Department",
                    email: "it.personnel@equiptrack.edu",
                    avatar: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=150&h=150&q=80",
                    nameLabel: "Prof. Sarah Johnson"
                };

                const personnelMap = {
                    "Information Technology Department": {
                        name: "Information Technology Department",
                        badgeName: "IT Department",
                        email: "it.personnel@equiptrack.edu",
                        avatar: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=150&h=150&q=80",
                        nameLabel: "Prof. Sarah Johnson"
                    },
                    "Engineering Department": {
                        name: "Engineering Department",
                        badgeName: "Engineering Department",
                        email: "eng.personnel@equiptrack.edu",
                        avatar: "https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=150&h=150&q=80",
                        nameLabel: "Dr. Alexander Wright"
                    },
                    "Education Department": {
                        name: "Education Department",
                        badgeName: "Education Department",
                        email: "edu.personnel@equiptrack.edu",
                        avatar: "https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=150&h=150&q=80",
                        nameLabel: "Dr. Eleanor Vance"
                    }
                };

                let savedPersonnel = localStorage.getItem('equip-track-dept-personnel');
                currentPersonnel = defaultPersonnel; // Default fallback

                if (savedPersonnel) {
                    try {
                        const parsed = JSON.parse(savedPersonnel);
                        if (parsed && typeof parsed === 'object' && parsed.name) {
                            currentPersonnel = parsed;
                        } else if (typeof parsed === 'string') {
                            const cleanStr = parsed.replace(/^"|"$/g, '');
                            if (personnelMap[cleanStr]) {
                                currentPersonnel = personnelMap[cleanStr];
                                localStorage.setItem('equip-track-dept-personnel', JSON.stringify(currentPersonnel));
                            }
                        }
                    } catch (e) {
                        const cleanStr = savedPersonnel.replace(/^"|"$/g, '');
                        if (personnelMap[cleanStr]) {
                            currentPersonnel = personnelMap[cleanStr];
                        } else {
                            currentPersonnel = defaultPersonnel;
                        }
                        localStorage.setItem('equip-track-dept-personnel', JSON.stringify(currentPersonnel));
                    }
                } else {
                    localStorage.setItem('equip-track-dept-personnel', JSON.stringify(defaultPersonnel));
                }

                // Update UI elements
                if (navbarPersonnelName) navbarPersonnelName.textContent = currentPersonnel.nameLabel;
                if (dropdownPersonnelName) dropdownPersonnelName.textContent = currentPersonnel.nameLabel;
                if (dropdownPersonnelRole) dropdownPersonnelRole.textContent = currentPersonnel.badgeName + " Head";
                if (dropdownPersonnelEmail) dropdownPersonnelEmail.textContent = currentPersonnel.email;
                if (navbarAvatar) navbarAvatar.src = currentPersonnel.avatar;
                if (departmentBadgeText) departmentBadgeText.textContent = currentPersonnel.badgeName;
            }

            // Switch department personnel
            window.switchPersonnelDept = function(deptName) {
                const personnelMap = {
                    "Information Technology Department": {
                        name: "Information Technology Department",
                        badgeName: "IT Department",
                        email: "it.personnel@equiptrack.edu",
                        avatar: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=150&h=150&q=80",
                        nameLabel: "Prof. Sarah Johnson"
                    },
                    "Engineering Department": {
                        name: "Engineering Department",
                        badgeName: "Engineering Department",
                        email: "eng.personnel@equiptrack.edu",
                        avatar: "https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=150&h=150&q=80",
                        nameLabel: "Dr. Alexander Wright"
                    },
                    "Education Department": {
                        name: "Education Department",
                        badgeName: "Education Department",
                        email: "edu.personnel@equiptrack.edu",
                        avatar: "https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=150&h=150&q=80",
                        nameLabel: "Dr. Eleanor Vance"
                    }
                };

                if (personnelMap[deptName]) {
                    localStorage.setItem('equip-track-dept-personnel', JSON.stringify(personnelMap[deptName]));
                    initPersonnel();
                    currentPage = 1;
                    loadAndRenderData();
                    dropdownMenu.classList.remove('show');
                }
            };

            // Seeder Function
            function seedUsers() {
                let localUsers = [];
                try {
                    const raw = localStorage.getItem('equip-track-users');
                    localUsers = raw ? JSON.parse(raw) : [];
                } catch (e) {
                    localUsers = [];
                }

                if (!Array.isArray(localUsers)) {
                    localUsers = [];
                }

                // Check if the IT department contains our requested sample data
                const hasRequiredUsers = localUsers.some(u => 
                    u && 
                    u.department === 'Information Technology Department' && 
                    u.first_name === "Johnrey Neil" && 
                    u.last_name === "Rama"
                );

                if (!hasRequiredUsers || !localStorage.getItem('equip-track-users-seeded-v3')) {
                    // Filter out any existing IT users
                    localUsers = localUsers.filter(u => u && u.department !== 'Information Technology Department');

                    const specificITUsers = [
                        {
                            id: 10001,
                            first_name: "Johnrey Neil",
                            last_name: "Rama",
                            id_number: "2023-00125",
                            role: "student",
                            year_level: "3rd Year",
                            course_position: "BSIT-3",
                            email: "johnrey.rama@equiptrack.edu",
                            address: "123 Main St, Tech City",
                            department: "Information Technology Department",
                            status: "Active",
                            borrowing_status: "Borrowing",
                            username: "johnrey.rama",
                            created_at: "Aug 15, 2025",
                            last_login: "Jun 30, 2026, 09:12 AM"
                        },
                        {
                            id: 10002,
                            first_name: "Jeffrey",
                            last_name: "Gaviola",
                            id_number: "FAC-2024-015",
                            role: "teacher",
                            year_level: "N/A",
                            course_position: "Instructor",
                            email: "jeffrey.gaviola@equiptrack.edu",
                            address: "789 Pine Rd, Academia",
                            department: "Information Technology Department",
                            status: "Active",
                            borrowing_status: "Borrowing",
                            username: "jeffrey.gaviola",
                            created_at: "Jan 10, 2024",
                            last_login: "Jun 30, 2026, 08:30 AM"
                        },
                        {
                            id: 10003,
                            first_name: "Michael John",
                            last_name: "Silva",
                            id_number: "2022-00418",
                            role: "student",
                            year_level: "2nd Year",
                            course_position: "BSIT-2",
                            email: "michael.silva@equiptrack.edu",
                            address: "456 Oak Ave, Greenview",
                            department: "Information Technology Department",
                            status: "Active",
                            borrowing_status: "None",
                            username: "michael.silva",
                            created_at: "Sep 01, 2024",
                            last_login: "Jun 29, 2026, 02:45 PM"
                        },
                        {
                            id: 10004,
                            first_name: "Gabriel",
                            last_name: "Fernandez",
                            id_number: "2023-00126",
                            role: "student",
                            year_level: "1st Year",
                            course_position: "BSIT-1",
                            email: "gabriel.fernandez@equiptrack.edu",
                            address: "321 Cedar Blvd, Metro",
                            department: "Information Technology Department",
                            status: "Active",
                            borrowing_status: "Overdue",
                            username: "gabriel.fernandez",
                            created_at: "Oct 15, 2025",
                            last_login: "Jun 30, 2026, 11:05 AM"
                        }
                    ];

                    localUsers.push(...specificITUsers);

                    // Seed Engineering Department if missing
                    localUsers = localUsers.filter(u => u && u.department !== 'Engineering Department');
                    localUsers.push({
                        id: 30001,
                        first_name: "EngStudent",
                        last_name: "One",
                        id_number: "ENG-2023-001",
                        role: "student",
                        year_level: "3rd Year",
                        course_position: "BSCE-3",
                        email: "eng1@equiptrack.edu",
                        address: "Engineering Rd",
                        department: "Engineering Department",
                        status: "Active",
                        borrowing_status: "Borrowing",
                        username: "eng1",
                        created_at: "Sep 05, 2025",
                        last_login: "Jun 28, 2026"
                    });

                    // Seed Education Department if missing
                    localUsers = localUsers.filter(u => u && u.department !== 'Education Department');
                    localUsers.push({
                        id: 40001,
                        first_name: "EduStudent",
                        last_name: "One",
                        id_number: "EDU-2023-001",
                        role: "student",
                        year_level: "2nd Year",
                        course_position: "BSED-2",
                        email: "edu1@equiptrack.edu",
                        address: "Education Rd",
                        department: "Education Department",
                        status: "Active",
                        borrowing_status: "None",
                        username: "edu1",
                        created_at: "Sep 05, 2025",
                        last_login: "Jun 28, 2026"
                    });

                    localStorage.setItem('equip-track-users', JSON.stringify(localUsers));
                    localStorage.setItem('equip-track-users-seeded-v3', 'true');
                }
            }

            // Load users and render UI
            function loadAndRenderData() {
                let localUsers = [];
                try {
                    const raw = localStorage.getItem('equip-track-users');
                    localUsers = raw ? JSON.parse(raw) : [];
                } catch (e) {
                    localUsers = [];
                }

                if (!Array.isArray(localUsers)) {
                    localUsers = [];
                }
                
                // Filter users belonging to current department personnel
                users = localUsers.filter(u => u && u.department === currentPersonnel.name);

                updateStatistics();
                renderTable();
            }

            // Update statistics cards
            function updateStatistics() {
                const total = users.length;
                const students = users.filter(u => u.role === 'student').length;
                const faculty = users.filter(u => u.role === 'teacher' || u.role === 'faculty').length;
                const activeBorrowers = users.filter(u => u.borrowing_status === 'Borrowing').length;

                // Update text
                statTotalUsers.textContent = total;
                statStudents.textContent = students;
                statFaculty.textContent = faculty;
                statActiveBorrowers.textContent = activeBorrowers;
            }

            // Render department users table
            function renderTable() {
                const searchQuery = searchInput.value.toLowerCase().trim();
                const typeFilter = filterUserType.value;
                const borrowFilter = filterBorrowStatus.value;

                // Apply searching & filtering
                filteredUsers = users.filter(user => {
                    const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
                    const matchesSearch = fullName.includes(searchQuery) || user.id_number.toLowerCase().includes(searchQuery);

                    // User Type filter mapping
                    let matchesType = true;
                    if (typeFilter === 'student') {
                        matchesType = (user.role === 'student');
                    } else if (typeFilter === 'teacher') {
                        matchesType = (user.role === 'teacher' || user.role === 'faculty');
                    }

                    // Borrow Status filter mapping
                    let matchesBorrow = true;
                    if (borrowFilter === 'borrowing') {
                        matchesBorrow = (user.borrowing_status === 'Borrowing');
                    } else if (borrowFilter === 'none') {
                        matchesBorrow = (user.borrowing_status === 'None');
                    } else if (borrowFilter === 'overdue') {
                        matchesBorrow = (user.borrowing_status === 'Overdue');
                    }

                    return matchesSearch && matchesType && matchesBorrow;
                });

                // Paginate
                const totalEntries = filteredUsers.length;
                const totalPages = Math.ceil(totalEntries / pageSize);

                if (currentPage > totalPages) {
                    currentPage = Math.max(1, totalPages);
                }

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const paginatedUsers = filteredUsers.slice(startIndex, endIndex);

                // Clear table
                tableBody.innerHTML = '';

                if (paginatedUsers.length === 0) {
                    emptyState.style.display = 'flex';
                    paginationContainer.style.display = 'none';
                    document.querySelector('.table-wrapper').style.display = 'none';
                } else {
                    emptyState.style.display = 'none';
                    paginationContainer.style.display = 'flex';
                    document.querySelector('.table-wrapper').style.display = 'block';

                    paginatedUsers.forEach(user => {
                        const tr = document.createElement('tr');
                        const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim();
                        
                        // Status badge logic
                        let statusText = "⚪ None";
                        let statusClass = "none";
                        if (user.borrowing_status === 'Borrowing') {
                            statusText = "🟢 Borrowing";
                            statusClass = "borrowing";
                        } else if (user.borrowing_status === 'Overdue') {
                            statusText = "🔴 Overdue";
                            statusClass = "overdue";
                        }

                        // Type label safely formatted
                        let typeLabel = "Student";
                        if (user.role && typeof user.role === 'string') {
                            const formattedRole = user.role.toLowerCase();
                            if (formattedRole === 'teacher' || formattedRole === 'faculty') {
                                typeLabel = "Faculty";
                            }
                        }

                        tr.innerHTML = `
                            <td><strong>${escapeHTML(user.id_number)}</strong></td>
                            <td>
                                <div class="name-cell-wrapper">
                                    <span class="name-cell-text">${escapeHTML(fullName)}</span>
                                </div>
                            </td>
                            <td>${escapeHTML(typeLabel)}</td>
                            <td>${escapeHTML(user.course_position || 'N/A')}</td>
                            <td>
                                <span class="status-badge ${statusClass}">${statusText}</span>
                            </td>
                            <td style="text-align: center;">
                                <button class="btn-view" onclick="openDetailsPanel(${user.id})">
                                    <i class="fa-regular fa-eye"></i> View
                                </button>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });

                    // Update pagination text
                    paginationInfo.textContent = `Showing ${totalEntries === 0 ? 0 : startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                    renderPaginationButtons(totalPages);
                }
            }

            // Pagination button generation
            function renderPaginationButtons(totalPages) {
                paginationButtons.innerHTML = '';

                // Prev Button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'btn-page';
                prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
                prevBtn.disabled = (currentPage === 1);
                prevBtn.addEventListener('click', () => {
                    currentPage--;
                    renderTable();
                });
                paginationButtons.appendChild(prevBtn);

                // Number buttons
                let startPage = Math.max(1, currentPage - 1);
                let endPage = Math.min(totalPages, startPage + 2);
                if (endPage - startPage < 2) {
                    startPage = Math.max(1, endPage - 2);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderTable();
                    });
                    paginationButtons.appendChild(pageBtn);
                }

                // Next Button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'btn-page';
                nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
                nextBtn.disabled = (currentPage === totalPages);
                nextBtn.addEventListener('click', () => {
                    currentPage++;
                    renderTable();
                });
                paginationButtons.appendChild(nextBtn);
            }

            // Open User Details Drawer
            window.openDetailsPanel = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;

                const fullName = `${user.first_name} ${user.last_name}`;
                const isStudent = (user.role === 'student');

                // Render User Details Drawer
                panelFullName.textContent = fullName;
                panelValName.textContent = fullName;
                panelValId.textContent = user.id_number;
                panelValRole.textContent = isStudent ? "Student" : "Faculty Member";
                panelValDept.textContent = currentPersonnel.badgeName;
                panelValEmail.textContent = user.email;

                // Adjust labels for students/faculty
                if (isStudent) {
                    panelIdLabel.textContent = "Student ID";
                    panelCoursePositionLabel.textContent = "Course & Year";
                    panelValCoursePosition.textContent = user.course_position || "BSIT - 3rd Year";
                    panelRoleBadge.className = "panel-user-type";
                    panelRoleBadge.innerHTML = `<i class="fa-solid fa-circle" style="font-size: 8px; color: #2563EB;"></i> Student`;
                } else {
                    panelIdLabel.textContent = "Faculty ID";
                    panelCoursePositionLabel.textContent = "Academic Position";
                    panelValCoursePosition.textContent = user.course_position || "Instructor";
                    panelRoleBadge.className = "panel-user-type";
                    panelRoleBadge.innerHTML = `<i class="fa-solid fa-circle" style="font-size: 8px; color: #10B981;"></i> Faculty`;
                }

                // Display dynamic current borrowing records
                panelCurrentBorrowBody.innerHTML = '';
                if (user.borrowing_status === 'Borrowing') {
                    panelCurrentBorrowBody.innerHTML = `
                        <tr>
                            <td><strong>Arduino Uno Kit</strong></td>
                            <td>Jun 28, 2026</td>
                            <td>Jul 05, 2026</td>
                            <td><span class="status-badge borrowing" style="padding: 4px 8px; font-size: 11px;">🟢 Active</span></td>
                        </tr>
                    `;
                } else if (user.borrowing_status === 'Overdue') {
                    panelCurrentBorrowBody.innerHTML = `
                        <tr>
                            <td><strong>Digital Oscilloscope</strong></td>
                            <td>Jun 12, 2026</td>
                            <td>Jun 19, 2026</td>
                            <td><span class="status-badge overdue" style="padding: 4px 8px; font-size: 11px;">🔴 Overdue</span></td>
                        </tr>
                    `;
                } else {
                    panelCurrentBorrowBody.innerHTML = `
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); font-style: italic;">No currently borrowed equipment.</td>
                        </tr>
                    `;
                }

                // Display dynamic borrowing history records
                panelHistoryBody.innerHTML = `
                    <tr>
                        <td>Soldering Iron (60W)</td>
                        <td>Jun 02, 2026</td>
                        <td>Jun 04, 2026</td>
                        <td><span class="status-badge none" style="padding: 4px 8px; font-size: 11px; background-color: #DCFCE7; color: #15803D;">🟢 Returned</span></td>
                    </tr>
                    <tr>
                        <td>Digital Multimeter</td>
                        <td>May 14, 2026</td>
                        <td>May 15, 2026</td>
                        <td><span class="status-badge none" style="padding: 4px 8px; font-size: 11px; background-color: #DCFCE7; color: #15803D;">🟢 Returned</span></td>
                    </tr>
                `;

                // Slide in Panel
                panelOverlay.classList.add('show');
                userDetailsPanel.classList.add('show');
            };

            // Close Side Panel
            function closePanel() {
                panelOverlay.classList.remove('show');
                userDetailsPanel.classList.remove('show');
            }

            btnClosePanel.addEventListener('click', closePanel);
            panelOverlay.addEventListener('click', closePanel);

            // Filter Event Listeners
            searchInput.addEventListener('input', () => {
                currentPage = 1;
                renderTable();
            });

            filterUserType.addEventListener('change', () => {
                currentPage = 1;
                renderTable();
            });

            filterBorrowStatus.addEventListener('change', () => {
                currentPage = 1;
                renderTable();
            });

            // Profile Dropdown Toggle
            userProfileDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                dropdownMenu.classList.remove('show');
            });

            // Dark Mode Toggler
            if (themeToggleBtn && themeToggleIcon) {
                if (document.documentElement.classList.contains('dark-theme')) {
                    themeToggleIcon.className = 'fa-solid fa-sun';
                } else {
                    themeToggleIcon.className = 'fa-solid fa-moon';
                }

                themeToggleBtn.addEventListener('click', () => {
                    const isDark = document.documentElement.classList.toggle('dark-theme');
                    if (isDark) {
                        themeToggleIcon.className = 'fa-solid fa-sun';
                        localStorage.setItem('dashboard-theme', 'dark');
                    } else {
                        themeToggleIcon.className = 'fa-solid fa-moon';
                        localStorage.setItem('dashboard-theme', 'light');
                    }
                });
            }

            // HTML Escaping Helper
            function escapeHTML(str) {
                if (str === null || str === undefined) return '';
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

            // Initial Data load and render
            loadAndRenderData();
        });
    </script>
</body>
</html>
