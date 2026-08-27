<?php
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - User Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <link rel="stylesheet" href="../ccs/global.css">
    <link rel="stylesheet" href="css/userdashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
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
            <a href="userdashboard.php" class="nav-item active">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="userprofile.php" class="nav-item">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            <div class="sidebar-section-label">Equipment</div>
            <a href="useravailequipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Available Equipment</span>
            </a>
            <a href="userrequests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>My Request</span>
            </a>
            <a href="userreturns.php" class="nav-item">
                <i class="fa-solid fa-check-double"></i> <span>Return Item</span>
            </a>
            <a href="userhistory.php" class="nav-item">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Borrowing History</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                        <?php echo htmlspecialchars($user_initials); ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name"><?php echo htmlspecialchars($full_name); ?></span>
                            <span class="header-email"><?php echo htmlspecialchars($user_email); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="userprofile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- Left Column -->
            <div class="dashboard-left">
                <!-- Welcome Banner -->
                <div class="welcome-banner card">
                    <div class="banner-text">
                        <span class="banner-date">Today</span>
                        <h2>Welcome back, <?php echo htmlspecialchars($first_name); ?>!</h2>
                        <p>Here's your equipment activity overview for today.</p>
                    </div>
                    <img src="../images/user_design1.png" alt="User Illustration" class="banner-img">
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Quick Stats</h3>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card card">
                            <div class="stat-icon info"><i class="fa-solid fa-box-open"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">0</div>
                                <div class="stat-title">Active Borrows</div>
                            </div>
                        </div>
                        <div class="stat-card card">
                            <div class="stat-icon warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">0</div>
                                <div class="stat-title">Pending Requests</div>
                            </div>
                        </div>
                        <div class="stat-card card">
                            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">0</div>
                                <div class="stat-title">Overdue Items</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Active Borrow Items</h3>
                        <a href="userhistory.php" class="view-all">View All</a>
                    </div>
                    <div class="table-container card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 24px;">No active borrow items.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Requests</h3>
                    </div>
                    <div class="table-container card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--text-muted, #64748b); padding: 24px;">No recent requests found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="dashboard-right">
                <div class="action-card card">
                    <h3 class="card-title">Quick Actions</h3>
                    <div class="action-grid">
                        <a href="useravailequipment.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-desktop"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">Browse Equipment</span>
                                <span class="action-desc">Explore available items</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                        <a href="useravailequipment.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-cart-plus"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">Request Equipment</span>
                                <span class="action-desc">Borrow items for your use</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                        <a href="userrequests.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">View My Requests</span>
                                <span class="action-desc">Check your current status</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                    </div>
                </div>

                <div class="reminder-card card">
                    <div class="card-header">
                        <h3 class="card-title">Reminders</h3>
                    </div>
                    <ul class="reminder-list">
                        <li class="reminder-item" style="justify-content: center; text-align: center; color: var(--text-muted, #64748b); padding: 16px;">
                            <span style="font-size: 13px;">No active reminders at this time.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bannerDate = document.querySelector('.banner-date');
            if (bannerDate) {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                bannerDate.textContent = new Date().toLocaleDateString('en-US', options);
            }

            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');

            // Initialize icon based on current theme class
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

            // Navbar Avatar Sync Helper
            function syncNavbarAvatar() {
                const savedAvatar = localStorage.getItem('user-avatar-src');
                const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');
                navAvatars.forEach(navAvatar => {
                    if (savedAvatar) {
                        navAvatar.innerHTML = `<img src="${savedAvatar}" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                        navAvatar.style.padding = '0';
                        navAvatar.style.background = 'transparent';
                    }
                });
            }
            syncNavbarAvatar();

            window.addEventListener('storage', function(e) {
                if (e.key === 'user-avatar-src') {
                    syncNavbarAvatar();
                }
            });
        });
    </script>
</body>
</html>
