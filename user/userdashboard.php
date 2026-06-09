<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - User Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <link rel="stylesheet" href="../ccs/userdashboard.css">
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
        <header class="top-navbar">
            <div class="navbar-right">
                <div class="icon-btn" id="themeToggleBtn"><i class="fa-solid fa-moon" id="themeToggleIcon"></i></div>
                <div class="icon-btn notification"><i class="fa-solid fa-bell"></i></div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Gabriel+Fernandez&background=random" alt="Gabriel Fernandez" class="avatar">
                    <span class="user-name">Gabriel Fernandez</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- Left Column -->
            <div class="dashboard-left">
                <!-- Welcome Banner -->
                <div class="welcome-banner card">
                    <div class="banner-text">
                        <span class="banner-date">Monday, June 1, 2026</span>
                        <h2>Welcome back, Gabriel!</h2>
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
                                <div class="stat-value">1</div>
                                <div class="stat-title">Active Borrows</div>
                            </div>
                        </div>
                        <div class="stat-card card">
                            <div class="stat-icon warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">3</div>
                                <div class="stat-title">Pending Requests</div>
                            </div>
                        </div>
                        <div class="stat-card card">
                            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">1</div>
                                <div class="stat-title">Overdue Items</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Active Borrow Items</h3>
                        <a href="#" class="view-all">View All</a>
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
                                    <td>Laptop</td>
                                    <td>May 1</td>
                                    <td>May 5</td>
                                    <td>Borrowed</td>
                                </tr>
                                <tr>
                                    <td>Calculator</td>
                                    <td>May 10</td>
                                    <td>May 11</td>
                                    <td class="text-danger">Over Due</td>
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
                                    <td>Projector</td>
                                    <td>May 2</td>
                                    <td>Pending</td>
                                </tr>
                                <tr>
                                    <td>Camera</td>
                                    <td>May 3</td>
                                    <td>Approved</td>
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
                        <li class="reminder-item warning">
                            <div class="reminder-icon">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="reminder-content">
                                <span class="reminder-title">Due Tomorrow</span>
                                <span class="reminder-desc">Your laptop is due to be returned.</span>
                            </div>
                        </li>
                        <li class="reminder-item danger">
                            <div class="reminder-icon">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div class="reminder-content">
                                <span class="reminder-title">Overdue Item</span>
                                <span class="reminder-desc">You have 1 overdue calculator.</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
        });
    </script>
</body>
</html>
