<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Admin Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Base Layout Stylesheet -->
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <!-- Admin Specific Stylesheet -->
    <link rel="stylesheet" href="../ccs/admindashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            <div class="sidebar-section-label">General</div>
            <a href="admindashboard.php" class="nav-item active">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="monitoring.php" class="nav-item">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="reports.php" class="nav-item">
                <i class="fa-solid fa-file-lines"></i> <span>Reports</span>
            </a>
            <a href="audit.php" class="nav-item">
                <i class="fa-solid fa-clipboard-check"></i> <span>Audit Trail</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar profile-navbar">
            <div class="navbar-right">
                <span class="navbar-divider"></span>
                <div class="icon-btn" id="themeToggleBtn">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80" alt="Admin Avatar" class="avatar">
                    <span class="user-name">Admin</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">Admin</span>
                            <span class="header-email">admin@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="admindashboard.php"><i class="fa-solid fa-sliders"></i> Settings</a>
                        <a href="../login.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="welcome-text">
                <span class="welcome-pill">Dashboard Overview</span>
                <h3>Welcome back, Admin!</h3>
                <p>Here's an overview of equipment requests, borrowings, and system activity.</p>
            </div>
            <img src="../images/admin_design1.png" alt="Admin Illustration" class="welcome-banner-img">
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <!-- Card 1 – Total Users -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Users</span>
                            <span class="quick-stat-card-value" id="statTotalUsers">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 2 – Total Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Equipment</span>
                            <span class="quick-stat-card-value" id="statTotalEq">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 3 – Pending Requests -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Pending Requests</span>
                            <span class="quick-stat-card-value" id="statPending">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-orange">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 4 – Borrowed Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Borrowed Equipment</span>
                            <span class="quick-stat-card-value" id="statBorrowed">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 5 – Overdue Items -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Overdue Items</span>
                            <span class="quick-stat-card-value" id="statOverdue">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Requests Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Recent Requests</h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="action-column">Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading"><i class="fa-solid fa-triangle-exclamation warning-icon" style="color: #fbbf24;"></i> Overdue Alerts</h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Late</th>
                        </tr>
                    </thead>
                    <tbody id="overdueTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Two Column Bottom Section: Low Stock & System Summary -->
        <div class="bottom-grid">
            <!-- Left Column: Low Stock -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">Low Stock</h4>
                <div class="low-stock-card card flex-table">
                    <div class="stock-item-row">
                        <div class="stock-item-left">
                            <div class="stock-item-icon-box critical">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div class="stock-item-meta">
                                <span class="stock-item-name">Mouse</span>
                                <span class="stock-status-label text-critical">Critical Level</span>
                            </div>
                        </div>
                        <span class="stock-pill pill-critical">1 left</span>
                    </div>
                    <div class="stock-item-divider"></div>
                    <div class="stock-item-row">
                        <div class="stock-item-left">
                            <div class="stock-item-icon-box warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div class="stock-item-meta">
                                <span class="stock-item-name">Projector</span>
                                <span class="stock-status-label text-warning">Low Stock</span>
                            </div>
                        </div>
                        <span class="stock-pill pill-warning">2 left</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: System Summary -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">System Summary</h4>
                <div class="system-summary-card card">
                    <div class="summary-hero">
                        <div class="summary-hero-meta">
                            <span class="summary-hero-label">Requests This Month</span>
                            <p class="summary-hero-desc">Total borrowing activity processed</p>
                        </div>
                        <span class="summary-hero-value" id="summaryTotalCount">0</span>
                    </div>
                    
                    <div class="summary-segment-bar" id="summarySegmentBar">
                        <div class="segment-fill approved" style="width: 0%;"></div>
                        <div class="segment-fill rejected" style="width: 0%;"></div>
                        <div class="segment-fill pending" style="width: 0%;"></div>
                    </div>
                    
                    <div class="summary-details-grid">
                        <div class="summary-detail-item">
                            <span class="detail-dot approved"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Approved</span>
                                <span class="detail-value" id="summaryApprovedCount">0</span>
                            </div>
                        </div>
                        <div class="summary-detail-item">
                            <span class="detail-dot rejected"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Rejected</span>
                                <span class="detail-value" id="summaryRejectedCount">0</span>
                            </div>
                        </div>
                        <div class="summary-detail-item">
                            <span class="detail-dot pending"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Pending</span>
                                <span class="detail-value" id="summaryPendingCount">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Success Toast Notification -->
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- Scripting for Toggles and Interactivity -->
    <script>
        // DOM Elements
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const toast = document.getElementById('toast');
        const toastIcon = document.getElementById('toastIcon');
        const toastTitle = document.getElementById('toastTitle');
        const toastMsg = document.getElementById('toastMsg');
        
        // Stats elements
        const statTotalUsers = document.getElementById('statTotalUsers');
        const statTotalEq = document.getElementById('statTotalEq');
        const statPending = document.getElementById('statPending');
        const statBorrowed = document.getElementById('statBorrowed');
        const statOverdue = document.getElementById('statOverdue');
        const summaryPendingCount = document.getElementById('summaryPendingCount');
        const summaryApprovedCount = document.getElementById('summaryApprovedCount');
        const summaryRejectedCount = document.getElementById('summaryRejectedCount');
        const summaryTotalCount = document.getElementById('summaryTotalCount');
        const summarySegmentBar = document.getElementById('summarySegmentBar');

        // Requests Data Sync
        const defaultRequestsList = [
            {
                id: 1,
                user: "Gabriel F.",
                role: "Student",
                avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Laptop Dell",
                category: "Laptop",
                date: "May 1",
                fullDate: "May 1, 2026",
                borrowDate: "May 2, 2026",
                dueDate: "May 5, 2026",
                status: "Pending",
                purpose: "Class Presentation",
                notes: "Need a high-performance laptop for my Software Engineering presentation.",
                img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 2,
                user: "Anna Mae S.",
                role: "Teacher",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Camera Canon",
                category: "Camera",
                date: "May 11",
                fullDate: "May 11, 2026",
                borrowDate: "May 12, 2026",
                dueDate: "May 15, 2026",
                status: "Pending",
                purpose: "Field Research Documentation",
                notes: "Will document plant samples in biology forestry campus lab.",
                img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 3,
                user: "Anna Mae S.",
                role: "Teacher",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Camera Canon",
                category: "Camera",
                date: "May 11",
                fullDate: "May 11, 2026",
                borrowDate: "May 13, 2026",
                dueDate: "May 16, 2026",
                status: "Pending",
                purpose: "Classroom Activity",
                notes: "Needed for photography lighting demonstration in multimedia lab.",
                img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 4,
                user: "Anna Mae S.",
                role: "Teacher",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Camera Canon",
                category: "Camera",
                date: "May 11",
                fullDate: "May 11, 2026",
                borrowDate: "May 14, 2026",
                dueDate: "May 17, 2026",
                status: "Pending",
                purpose: "Event Documentation",
                notes: "Documenting the school's intra-mural sports activities.",
                img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 5,
                user: "Anna Mae S.",
                role: "Teacher",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Camera Canon",
                category: "Camera",
                date: "May 11",
                fullDate: "May 11, 2026",
                borrowDate: "May 15, 2026",
                dueDate: "May 18, 2026",
                status: "Pending",
                purpose: "Class Activity",
                notes: "Visual arts photography workshop session.",
                img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 6,
                user: "Anna Mae S.",
                role: "Teacher",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Camera Canon",
                category: "Camera",
                date: "May 11",
                fullDate: "May 11, 2026",
                borrowDate: "May 16, 2026",
                dueDate: "May 19, 2026",
                status: "Pending",
                purpose: "Research Project",
                notes: "Gathering high resolution visuals for the regional science fair project.",
                img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 7,
                user: "Johnrey Neil R.",
                role: "Student",
                avatar: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Lenovo ThinkPad",
                category: "Laptop",
                date: "May 12",
                fullDate: "May 12, 2026",
                borrowDate: "May 13, 2026",
                dueDate: "May 16, 2026",
                status: "Pending",
                purpose: "Software Development",
                notes: "Developing database prototype for final project.",
                img: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 8,
                user: "Johnrey Neil R.",
                role: "Student",
                avatar: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Projector Epson",
                category: "Projector",
                date: "May 12",
                fullDate: "May 12, 2026",
                borrowDate: "May 14, 2026",
                dueDate: "May 15, 2026",
                status: "Pending",
                purpose: "Group Study",
                notes: "Needed for interactive group presentation in study hall.",
                img: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 9,
                user: "Engineering Department",
                role: "Department",
                avatar: "https://images.unsplash.com/photo-1457369804613-52c61a468e7d?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Wireless Microphone Set",
                category: "Audio",
                date: "May 13",
                fullDate: "May 13, 2026",
                borrowDate: "May 14, 2026",
                dueDate: "May 17, 2026",
                status: "Pending",
                purpose: "Seminar Event",
                notes: "Audio amplification for the guest lecture series.",
                img: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 10,
                user: "Information Technology Department",
                role: "Department",
                avatar: "https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Laptop Dell",
                category: "Laptop",
                date: "May 13",
                fullDate: "May 13, 2026",
                borrowDate: "May 15, 2026",
                dueDate: "May 18, 2026",
                status: "Pending",
                purpose: "Lab Exam Setup",
                notes: "Additional laptop for students with hardware issues.",
                img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 11,
                user: "Gabriel F.",
                role: "Student",
                avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Camera Canon",
                category: "Camera",
                date: "May 14",
                fullDate: "May 14, 2026",
                borrowDate: "May 15, 2026",
                dueDate: "May 16, 2026",
                status: "Pending",
                purpose: "Club Activity",
                notes: "Taking photos for the Student Council newsletter.",
                img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            },
            {
                id: 12,
                user: "Anna Mae S.",
                role: "Teacher",
                avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                equipment: "Scientific Calculator",
                category: "Others",
                date: "May 14",
                fullDate: "May 14, 2026",
                borrowDate: "May 15, 2026",
                dueDate: "May 16, 2026",
                status: "Pending",
                purpose: "Math Olympiad Training",
                notes: "Providing calculators for selected trainees during mock quiz.",
                img: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                rejectReason: ""
            }
        ];

        // Overdue Items default list
        const defaultOverdueList = [
            { user: "Gabriel F.", equipment: "Scientific Calculator", dueDate: "May 1", daysLate: "2 Days" },
            { user: "Johnrey Neil R.", equipment: "Laptop Dell", dueDate: "April 30", daysLate: "5 Days" },
            { user: "Anna Mae S.", equipment: "Camera Canon", dueDate: "May 10", daysLate: "15 Days" },
            { user: "Information Technology Department", equipment: "Projector Epson", dueDate: "May 12", daysLate: "13 Days" },
            { user: "Gabriel F.", equipment: "Lenovo ThinkPad", dueDate: "May 14", daysLate: "11 Days" }
        ];

        // Initialize Users if not present
        const defaultUsers = [
            { id: 1, first_name: "Gabriel", last_name: "Fernandez", id_number: "20230123", role: "student", year_level: "3rd Year", email: "gabriel.fernandez@example.com", address: "123 University Ave, Tech City", department: "Information Technology Department", status: "Active", username: "gfernandez", created_at: "May 10, 2026", last_login: "June 17, 2026, 10:15 AM" },
            { id: 2, first_name: "Johnrey Neil", last_name: "Rama", id_number: "20230456", role: "student", year_level: "4th Year", email: "johnrey.rama@example.com", address: "456 College Lane, Tech City", department: "Engineering Department", status: "Active", username: "johnrey.rama", created_at: "May 12, 2026", last_login: "June 16, 2026, 02:45 PM" },
            { id: 3, first_name: "Information Technology Department", last_name: "", id_number: "D-IT-200", role: "department", year_level: "N/A", email: "it.dept@example.com", address: "IT Building Room 301", department: "Information Technology Department", status: "Active", username: "it.dept", created_at: "May 8, 2026", last_login: "June 15, 2026, 08:30 AM" },
            { id: 4, first_name: "Engineering Department", last_name: "", id_number: "D-ENG-300", role: "department", year_level: "N/A", email: "engineering.dept@example.com", address: "Engineering Building Room 101", department: "Engineering Department", status: "Active", username: "engineering.dept", created_at: "May 9, 2026", last_login: "June 14, 2026, 11:20 AM" },
            { id: 5, first_name: "Anna Mae", last_name: "S.", id_number: "T-00987", role: "teacher", year_level: "N/A", email: "anna.mae@example.com", address: "Science Hall Room 102", department: "Education Department", status: "Active", username: "anna.mae", created_at: "May 15, 2026", last_login: "June 17, 2026, 09:10 AM" },
            { id: 6, first_name: "Education Department", last_name: "", id_number: "D-EDU-400", role: "department", year_level: "N/A", email: "education.dept@example.com", address: "Education Hall Room 105", department: "Education Department", status: "Active", username: "education.dept", created_at: "May 3, 2026", last_login: "June 14, 2026, 02:15 PM" }
        ];
        let users = JSON.parse(localStorage.getItem('equip-track-users'));
        const isOutdated = !users || users.some(u => u.first_name === "Science" || u.first_name === "College of IT Department" || u.id_number === "2023-00123" || !users.some(dept => dept.first_name === "Education Department") || !users.some(dept => dept.first_name === "Information Technology Department") || users.some(u => !u.department));
        if (isOutdated) {
            users = defaultUsers;
            localStorage.setItem('equip-track-users', JSON.stringify(users));
        }

        // Initialize Equipment if not present
        const defaultEquipmentList = [
            { id: 1, name: "Laptop Dell XPS", category: "laptop", imgUrl: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 4, total: 10, status: "Available" },
            { id: 2, name: "Camera Canon EOS", category: "camera", imgUrl: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 5, total: 10, status: "Available" },
            { id: 3, name: "Wireless Microphone Set", category: "audio", imgUrl: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 10, total: 10, status: "Available" },
            { id: 4, name: "Lenovo ThinkPad", category: "laptop", imgUrl: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 2, total: 5, status: "Available" },
            { id: 5, name: "Projector Epson", category: "projector", imgUrl: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 10, total: 10, status: "Available" },
            { id: 6, name: "Scientific Calculator", category: "others", imgUrl: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 4, total: 5, status: "Available" }
        ];
        let equipment = JSON.parse(localStorage.getItem('equip-track-equipment'));
        if (!equipment) {
            equipment = defaultEquipmentList;
            localStorage.setItem('equip-track-equipment', JSON.stringify(equipment));
        }

        let requests = JSON.parse(localStorage.getItem('equip-track-requests'));
        const requestsOutdated = !requests || requests.some(r => r.user === "Science Department" || r.user === "IT Department" || r.user === "College of IT Department");
        if (requestsOutdated) {
            // Set first 8 to Pending and last 4 to Approved
            defaultRequestsList.forEach((req, index) => {
                if (index >= 8) {
                    req.status = 'Approved';
                } else {
                    req.status = 'Pending';
                }
            });
            requests = defaultRequestsList;
            localStorage.setItem('equip-track-requests', JSON.stringify(requests));
        }

        // Render Dashboard Stats and Table
        function renderDashboard() {
            // Calculate Stats
            const pendingRequests = requests.filter(r => r.status.toLowerCase() === 'pending');
            const approvedRequests = requests.filter(r => r.status.toLowerCase() === 'approved');
            const rejectedRequests = requests.filter(r => r.status.toLowerCase() === 'rejected');
            
            const totalPending = pendingRequests.length;
            const totalApproved = approvedRequests.length;
            const totalRejected = rejectedRequests.length;
            
            // System summary totals
            const baseApproved = 30; 
            const baseRejected = 10;
            const finalApproved = baseApproved + totalApproved;
            const finalRejected = baseRejected + totalRejected;
            const finalTotal = finalApproved + finalRejected + totalPending;

            // Get users from localStorage to count dynamically
            const currentUsers = JSON.parse(localStorage.getItem('equip-track-users')) || users;
            const totalUsersVal = currentUsers.length;

            // Get equipment from localStorage to count dynamically
            const currentEquipment = JSON.parse(localStorage.getItem('equip-track-equipment')) || equipment;
            const totalEquipmentQty = currentEquipment.reduce((sum, item) => sum + parseInt(item.total || 0), 0);

            // Update DOM Stats
            if (statTotalUsers) statTotalUsers.textContent = totalUsersVal;
            if (statTotalEq) statTotalEq.textContent = totalEquipmentQty;
            if (statPending) statPending.textContent = totalPending;
            if (statBorrowed) statBorrowed.textContent = totalApproved; 
            if (statOverdue) statOverdue.textContent = defaultOverdueList.length;
            
            if (summaryPendingCount) summaryPendingCount.textContent = totalPending;
            if (summaryApprovedCount) summaryApprovedCount.textContent = finalApproved;
            if (summaryRejectedCount) summaryRejectedCount.textContent = finalRejected;
            if (summaryTotalCount) summaryTotalCount.textContent = finalTotal;

            // Render Segment Bar
            if (summarySegmentBar) {
                const appPct = finalTotal > 0 ? (finalApproved / finalTotal) * 100 : 0;
                const rejPct = finalTotal > 0 ? (finalRejected / finalTotal) * 100 : 0;
                const penPct = finalTotal > 0 ? (totalPending / finalTotal) * 100 : 0;

                summarySegmentBar.innerHTML = `
                    <div class="segment-fill approved" style="width: ${appPct}%;"></div>
                    <div class="segment-fill rejected" style="width: ${rejPct}%;"></div>
                    <div class="segment-fill pending" style="width: ${penPct}%;"></div>
                `;
            }

            // Render Table (maximum 2 pending rows shown on dashboard recent section)
            const tableBody = document.getElementById('requestsTableBody');
            if (tableBody) {
                tableBody.innerHTML = '';
                const recent = pendingRequests.slice(0, 2);

                if (recent.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                <i class="fa-solid fa-check-double" style="color: #10b981; font-size: 20px; margin-bottom: 8px; display: block;"></i>
                                All pending requests processed!
                            </td>
                        </tr>
                    `;
                } else {
                    recent.forEach(req => {
                        const tr = document.createElement('tr');
                        tr.className = 'admin-table-row';
                        tr.innerHTML = `
                            <td>${escapeHTML(req.user)}</td>
                            <td>${escapeHTML(req.equipment)}</td>
                            <td>${escapeHTML(req.date)}</td>
                            <td>Pending</td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <button class="btn-approve" onclick="handleRequestAction(this, ${req.id}, 'approved')">Approve</button>
                                    <button class="btn-reject" onclick="handleRequestAction(this, ${req.id}, 'rejected')">Reject</button>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });
                }
            }

            // Render Overdue Alerts Table dynamically
            const overdueTableBody = document.getElementById('overdueTableBody');
            if (overdueTableBody) {
                overdueTableBody.innerHTML = '';
                defaultOverdueList.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.className = 'admin-table-row';
                    tr.innerHTML = `
                        <td>${escapeHTML(item.user)}</td>
                        <td>${escapeHTML(item.equipment)}</td>
                        <td>${escapeHTML(item.dueDate)}</td>
                        <td><span class="days-late">${escapeHTML(item.daysLate)}</span></td>
                    `;
                    overdueTableBody.appendChild(tr);
                });
            }
        }

        // Helper to escape HTML values
        function escapeHTML(str) {
            if (!str) return '';
            return str.replace(/[&<>'"]/g, 
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        // Dark Mode Logic
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

        // Toggle user dropdown on click
        if (userProfileDropdown && dropdownMenu) {
            userProfileDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            // Close dropdown if clicked outside
            document.addEventListener('click', () => {
                dropdownMenu.classList.remove('show');
            });
        }

        // Show Toast Function
        function showNotification(title, message, type = 'success') {
            toastTitle.textContent = title;
            toastMsg.textContent = message;
            
            // Set style based on status
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

        // Handle Approve/Reject Actions
        window.handleRequestAction = function(button, id, action) {
            const row = button.closest('.admin-table-row');
            const req = requests.find(r => r.id === id);
            if (!req) return;

            // Apply a nice fade-out animation
            row.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                if (action === 'approved') {
                    req.status = 'Approved';
                    req.rejectReason = '';
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    showNotification('Approved', `Request for ${req.equipment} by ${req.user} has been approved.`, 'success');
                } else {
                    req.status = 'Rejected';
                    req.rejectReason = 'Rejected from Dashboard';
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    showNotification('Rejected', `Request for ${req.equipment} by ${req.user} has been rejected.`, 'error');
                }

                // Re-render whole dashboard stats and items lists
                renderDashboard();
            }, 500);
        }

        // Initial rendering of dashboard items
        renderDashboard();
    </script>
</body>
</html>
