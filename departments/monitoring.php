<?php
// EquipTrack — Department Personnel Dashboard
// Design-only static layout (no backend logic yet)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Equipment Monitoring</title>
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
    <link rel="stylesheet" href="css/monitoring.css">
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
                <a href="monitoring.php" class="nav-item active">
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

        <!-- Main column -->
        <div class="main-column">

            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="topbar-menu-btn" id="topbarMenuBtn"><i class="fa-solid fa-bars"></i></button>
                    <div class="page-title-group">
                        <h1>Equipment Monitoring</h1>
                        <span>Live status of borrowed equipment</span>
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
                    <h2>Equipment Monitoring</h2>
                    <p>Monitor equipment currently borrowed by your department, including due dates and overdue items.</p>
                </div>

                <div class="placeholder-card">
                    <div class="placeholder-inner">
                        <div class="placeholder-icon"><i class="fa-solid fa-desktop"></i></div>
                        <h3>Monitoring view coming soon</h3>
                        <p>This area will track borrowed, reserved, and overdue equipment in real time.</p>
                    </div>
                </div>
            </main>

        </div>
    </div>

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
    </script>
</body>
</html>
