<?php
// Department Borrowing History Page Placeholder
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
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/department.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
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
            <a href="dashboard.php" class="nav-item">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fa-solid fa-user-circle"></i> <span>Profile</span>
            </a>
            <a href="users.php" class="nav-item">
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
            <a href="history.php" class="nav-item active">
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
            <h1 class="page-title">Borrowing History</h1>
            <p class="page-subtitle">Historical records of all equipment borrow transactions and logs within your department.</p>
        </section>

        <!-- Empty state placeholder card -->
        <section class="table-card" style="flex: 1; min-height: 400px; display: flex; align-items: center; justify-content: center;">
            <div class="empty-state-container">
                <div class="empty-state-icon-box" style="color: var(--accent-blue);">
                    <i class="fa-solid fa-history"></i>
                </div>
                <h3 class="empty-state-title">Borrowing History Section</h3>
                <p class="empty-state-desc">This section will contain long-term archival history logs, borrow frequency statistics, and returned equipment history details.</p>
            </div>
        </section>
    </main>

    <!-- Scripting Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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

            // Initialize logged-in personnel
            function initPersonnel() {
                const defaultPersonnel = {
                    name: "Information Technology Department",
                    badgeName: "IT Department",
                    email: "it.personnel@equiptrack.edu",
                    avatar: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=150&h=150&q=80",
                    nameLabel: "Prof. Sarah Johnson"
                };

                let savedPersonnel = localStorage.getItem('equip-track-dept-personnel');
                let currentPersonnel = savedPersonnel ? JSON.parse(savedPersonnel) : defaultPersonnel;

                // Update UI elements
                navbarPersonnelName.textContent = currentPersonnel.nameLabel;
                dropdownPersonnelName.textContent = currentPersonnel.nameLabel;
                dropdownPersonnelRole.textContent = currentPersonnel.badgeName + " Head";
                dropdownPersonnelEmail.textContent = currentPersonnel.email;
                navbarAvatar.src = currentPersonnel.avatar;
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
                    dropdownMenu.classList.remove('show');
                }
            };

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
        });
    </script>
</body>
</html>
