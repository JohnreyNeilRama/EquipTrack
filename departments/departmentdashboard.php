<?php
// EquipTrack — Department Personnel Dashboard
// Design-only static layout (no backend logic yet)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Dashboard</title>
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
    <script>
        (function() {
            if (localStorage.getItem('dept-dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../images/EquipTrack_logo.png" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="departmentdashboard.php" class="nav-item active">
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
            <a href="history.php" class="nav-item">
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

    <!-- Main Content Area -->
    <main class="main-content">
        
        <!-- Top Navbar -->
        <header class="top-navbar profile-navbar">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">IT</div>
                    <span class="user-name">IT Department</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">IT Department</span>
                            <span class="header-email">it.dept@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../login.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="banner-text">
                <span class="banner-date">Dashboard Overview</span>
                <h2>Welcome back, IT Department! 👋</h2>
                <p>Manage your department's equipment, monitor borrowing activities, and review requests efficiently.</p>
            </div>
            <!-- Styled Icon Graphic instead of PNG/JPG image -->
            <div class="welcome-banner-graphic" style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); font-size: 80px; color: rgba(56, 85, 133, 0.08); pointer-events: none;">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <!-- Total Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Equipment</span>
                            <span class="quick-stat-card-value">45</span>
                            <p class="quick-stat-card-desc">Items assigned to department</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-blue">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Pending Requests</span>
                            <span class="quick-stat-card-value">3</span>
                            <p class="quick-stat-card-desc">Awaiting review</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-orange">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <!-- Currently Borrowed -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Currently Borrowed</span>
                            <span class="quick-stat-card-value">8</span>
                            <p class="quick-stat-card-desc">Equipment on active loan</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <!-- Overdue Items -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Overdue Items</span>
                            <span class="quick-stat-card-value">3</span>
                            <p class="quick-stat-card-desc">Exceeded return date</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>

                <!-- Department Users -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Department Users</span>
                            <span class="quick-stat-card-value">4</span>
                            <p class="quick-stat-card-desc">Registered students & faculty</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Column Grid -->
        <div class="bottom-grid" style="margin-bottom: 32px;">
            <!-- Recent Requests Section -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">
                    Recent Borrow Requests
                    <a href="requests.php" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: var(--primary-color);">View All Requests &rarr;</a>
                </h4>
                <div class="table-container card admin-table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Borrower Name</th>
                                <th>Equipment</th>
                                <th>Request Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="admin-table-row">
                                <td><strong>REQ-8821</strong></td>
                                <td>Johnrey Neil Rama</td>
                                <td>Dell Latitude 5420 Laptop</td>
                                <td>Jun 15, 2026</td>
                                <td><span class="badge badge-warning">Pending</span></td>
                            </tr>
                            <tr class="admin-table-row">
                                <td><strong>REQ-8820</strong></td>
                                <td>Gabriel Fernandez</td>
                                <td>Canon EOS 200D Camera</td>
                                <td>Jun 14, 2026</td>
                                <td><span class="badge badge-success">Approved</span></td>
                            </tr>
                            <tr class="admin-table-row">
                                <td><strong>REQ-8819</strong></td>
                                <td>Michael John Silva</td>
                                <td>TP-Link Wireless Router</td>
                                <td>Jun 12, 2026</td>
                                <td><span class="badge badge-success">Approved</span></td>
                            </tr>
                            <tr class="admin-table-row">
                                <td><strong>REQ-8818</strong></td>
                                <td>Jeffrey Gaviola</td>
                                <td>Epson Projector EB-X06</td>
                                <td>Jun 10, 2026</td>
                                <td><span class="badge badge-danger">Rejected</span></td>
                            </tr>
                            <tr class="admin-table-row">
                                <td><strong>REQ-8817</strong></td>
                                <td>Johnrey Neil Rama</td>
                                <td>Extension Cord (Heavy Duty)</td>
                                <td>Jun 08, 2026</td>
                                <td><span class="badge badge-success">Approved</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Overdue Alerts Section -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">
                    <i class="fa-solid fa-triangle-exclamation warning-icon" style="color: #ef4444;"></i> Overdue Alerts
                    <a href="monitoring.php" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: #ef4444;">View Monitoring &rarr;</a>
                </h4>
                <div class="table-container card admin-table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Borrower Name</th>
                                <th>Equipment</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="admin-table-row" style="border-left: 4px solid #ef4444;">
                                <td>Gabriel Fernandez</td>
                                <td>Dell Latitude 5420 Laptop</td>
                                <td>Jun 20, 2026</td>
                                <td><span class="days-late">13 Days</span></td>
                            </tr>
                            <tr class="admin-table-row" style="border-left: 4px solid #ef4444;">
                                <td>Jeffrey Gaviola</td>
                                <td>Epson Projector EB-X06</td>
                                <td>Jun 25, 2026</td>
                                <td><span class="days-late">8 Days</span></td>
                            </tr>
                            <tr class="admin-table-row" style="border-left: 4px solid #ef4444;">
                                <td>Michael John Silva</td>
                                <td>Portable Bluetooth Speaker</td>
                                <td>Jun 30, 2026</td>
                                <td><span class="days-late">3 Days</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Returns Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                Upcoming Returns
                <a href="monitoring.php" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: var(--success-color);">View Equipment Monitoring &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="admin-table-row">
                            <td>Johnrey Neil Rama</td>
                            <td>Wireless Microphone Set</td>
                            <td>Jul 03, 2026</td>
                            <td><span class="text-danger"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #ef4444;"></i>Due Today</span></td>
                        </tr>
                        <tr class="admin-table-row">
                            <td>Gabriel Fernandez</td>
                            <td>TP-Link Wireless Router</td>
                            <td>Jul 04, 2026</td>
                            <td><span style="color: #f59e0b; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #f59e0b;"></i>Due Tomorrow</span></td>
                        </tr>
                        <tr class="admin-table-row">
                            <td>Michael John Silva</td>
                            <td>HDMI Cable 10m</td>
                            <td>Jul 07, 2026</td>
                            <td><span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #10b981;"></i>Due in 4 Days</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

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
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');

        userProfileDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            dropdownMenu.classList.remove('show');
        });

        // Notification bell (placeholder)
        document.getElementById('notifBtn').addEventListener('click', () => {
            alert('No new notifications yet.');
        });
    </script>
</body>
</html>
