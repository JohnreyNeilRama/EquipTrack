<?php
require_once __DIR__ . '/auth_check.php';
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
    <link rel="stylesheet" href="../ccs/global.css">
    <!-- Admin Specific Stylesheet -->
    <link rel="stylesheet" href="../admin/css/admindashboard.css">
    <!-- Page Stylesheet -->
    <link rel="stylesheet" href="css/departmentdashboard.css">
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
            <a href="history.php" class="nav-item">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Borrowing History</span>
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; <?php echo !empty($dept_profile_image) ? 'padding: 0; background: transparent;' : ''; ?>">
                        <?php if (!empty($dept_profile_image)): ?>
                            <img src="<?php echo htmlspecialchars($dept_profile_image); ?>" alt="Department Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($dept_initials); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($dept_name); ?></span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name"><?php echo htmlspecialchars($dept_name); ?></span>
                            <span class="header-email"><?php echo htmlspecialchars($dept_email); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="banner-text">
                <span class="banner-date">Today</span>
                <h2>Welcome back, Department Personnel! 👋</h2>
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
                            <span class="quick-stat-card-value">0</span>
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
                            <span class="quick-stat-card-value">0</span>
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
                            <span class="quick-stat-card-value">0</span>
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
                            <span class="quick-stat-card-value">0</span>
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
                            <span class="quick-stat-card-value">0</span>
                            <p class="quick-stat-card-desc">Registered students & faculty</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Borrow Requests Section -->
        <div class="admin-section">
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
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                No recent borrow requests found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
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
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                No overdue items.
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                No upcoming returns scheduled.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Interactivity -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bannerDate = document.querySelector('.banner-date');
            if (bannerDate) {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                bannerDate.textContent = new Date().toLocaleDateString('en-US', options);
            }
        });

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

        // Navbar Avatar Sync Helper
        function syncNavbarAvatar(newAvatar) {
            const dbAvatar = <?php echo json_encode($dept_profile_image); ?>;
            let currentAvatar = null;
            if (newAvatar !== undefined) {
                currentAvatar = newAvatar;
            } else if (dbAvatar) {
                currentAvatar = dbAvatar;
            } else {
                localStorage.removeItem('dept-avatar-src');
                currentAvatar = null;
            }

            const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');

            if (currentAvatar) {
                localStorage.setItem('dept-avatar-src', currentAvatar);
                navAvatars.forEach(navAvatar => {
                    navAvatar.innerHTML = `<img src="${currentAvatar}" alt="Department Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                    navAvatar.style.padding = '0';
                    navAvatar.style.background = 'transparent';
                });
            } else {
                localStorage.removeItem('dept-avatar-src');
                navAvatars.forEach(navAvatar => {
                    navAvatar.style.padding = '';
                    navAvatar.style.background = 'var(--primary-color)';
                    navAvatar.innerHTML = <?php echo json_encode(htmlspecialchars($dept_initials)); ?>;
                });
            }
        }
        syncNavbarAvatar();

        window.addEventListener('storage', function(e) {
            if (e.key === 'dept-avatar-src') {
                syncNavbarAvatar(e.newValue);
            }
        });

        // Notification bell (placeholder)
        document.getElementById('notifBtn').addEventListener('click', () => {
            alert('No new notifications yet.');
        });
    </script>
</body>
</html>
