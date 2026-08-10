<?php
// EquipTrack — Department Personnel Dashboard
// Department Profile Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Profile</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Base Layout Stylesheet -->
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Page Stylesheet -->
    <link rel="stylesheet" href="css/profile.css">
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
            <a href="profile.php" class="nav-item active">
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
            <h2>Profile</h2>
            <p>Manage your administrator account information and security settings.</p>
        </div>

        <!-- Main Profile Grid Layout Matching Reference Screenshot -->
        <div class="profile-grid-container">

            <!-- Left Profile Card -->
            <div class="profile-card-left">
                <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                    <div class="avatar-wrapper">
                        <img src="../images/logo_only.png" alt="Department Logo" class="avatar-img" id="avatarImage">
                        <button class="avatar-camera-btn" id="changeAvatarBtn" title="Upload new photo">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>

                    <h3 class="account-name" id="displayAccountName">DEPARTMENT PERSONNEL</h3>
                    <p class="account-role">Department Equipment Personnel</p>

                    <span class="badge-active-status">ACTIVE</span>
                </div>

                <a href="../login.php" class="btn-profile-logout">LOGOUT</a>
            </div>

            <!-- Right Profile Form Card -->
            <div class="profile-card-right">
                <form id="profileUpdateForm" onsubmit="handleProfileSubmit(event)">
                    
                    <!-- Personal Information Section -->
                    <h3 class="form-section-header">Personal Information</h3>

                    <div class="form-group-item">
                        <label for="fullName">Full Name</label>
                        <div class="form-input-box">
                            <input type="text" id="fullName" value="" placeholder="Enter full name">
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="username">Username</label>
                        <div class="form-input-box">
                            <input type="text" id="username" value="" placeholder="Enter username">
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="email">Email</label>
                        <div class="form-input-box">
                            <input type="email" id="email" value="" placeholder="Enter email address">
                        </div>
                    </div>

                    <div class="form-row-two-cols">
                        <div class="form-group-item">
                            <label for="employeeId">Employee ID</label>
                            <div class="form-input-box">
                                <input type="text" id="employeeId" value="" placeholder="Enter employee ID">
                            </div>
                        </div>

                        <div class="form-group-item">
                            <label for="role">Role</label>
                            <div class="form-input-box">
                                <input type="text" id="role" value="" placeholder="Enter role">
                            </div>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="department">Assigned Department</label>
                        <div class="form-input-box">
                            <input type="text" id="department" value="" placeholder="Enter assigned department">
                        </div>
                    </div>

                    <div class="form-divider-line"></div>

                    <!-- Change Password Section -->
                    <h3 class="form-section-header">Change Password</h3>

                    <div class="form-group-item">
                        <label for="currentPassword">Current Password</label>
                        <div class="form-input-box">
                            <input type="password" id="currentPassword" placeholder="••••••••">
                            <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility('currentPassword', this)"></i>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="newPassword">New Password</label>
                        <div class="form-input-box">
                            <input type="password" id="newPassword" placeholder="••••••••">
                            <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility('newPassword', this)"></i>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="confirmPassword">Confirm Password</label>
                        <div class="form-input-box">
                            <input type="password" id="confirmPassword" placeholder="••••••••">
                            <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility('confirmPassword', this)"></i>
                        </div>
                    </div>

                    <div style="margin-top: 28px; text-align: right;">
                        <button type="submit" class="btn-save-profile">Save Changes</button>
                    </div>

                </form>
            </div>

        </div>

    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotif">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
        <span id="toastMessage">Profile updated successfully.</span>
    </div>

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

        // Toggle password visibility
        function togglePasswordVisibility(inputId, iconEl) {
            const inputEl = document.getElementById(inputId);
            if (inputEl) {
                if (inputEl.type === 'password') {
                    inputEl.type = 'text';
                    iconEl.className = 'fa-solid fa-eye-slash eye-icon';
                } else {
                    inputEl.type = 'password';
                    iconEl.className = 'fa-solid fa-eye eye-icon';
                }
            }
        }

        // Toast message display
        function showToast(msg) {
            const toastNotif = document.getElementById('toastNotif');
            const toastMessage = document.getElementById('toastMessage');
            if (toastNotif && toastMessage) {
                toastMessage.textContent = msg;
                toastNotif.classList.add('show');
                setTimeout(() => {
                    toastNotif.classList.remove('show');
                }, 3000);
            }
        }

        // Handle profile submit
        function handleProfileSubmit(e) {
            e.preventDefault();
            const fullNameVal = document.getElementById('fullName').value;
            document.getElementById('displayAccountName').textContent = fullNameVal.toUpperCase();
            showToast('Account details updated successfully.');
        }

        // Avatar change simulator
        document.getElementById('changeAvatarBtn').addEventListener('click', () => {
            showToast('Avatar upload option opened.');
        });
    </script>
</body>
</html>
