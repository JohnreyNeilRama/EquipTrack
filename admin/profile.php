<?php
// EquipTrack — Admin Account Profile Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Admin Profile</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminprofile.css?v=<?php echo time(); ?>">
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
            <div class="sidebar-section-label">General</div>
            <a href="admindashboard.php" class="nav-item">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="profile.php" class="nav-item active">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
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
                <div class="icon-btn" id="themeToggleBtn" title="Toggle theme">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <img src="../images/uc_logo.png" alt="Admin Avatar" class="avatar profile-top-avatar" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover;">
                    <span class="user-name" id="topNavName">Admin</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name" id="dropdownHeaderName">—</span>
                            <span class="header-email" id="dropdownHeaderEmail">—</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                        <a href="../login.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="profile-container">
            <!-- Header Section -->
            <div class="profile-header-section">
                <h1 class="profile-page-title">Profile</h1>
                <p class="profile-page-subtitle">Manage your administrator account information and security settings.</p>
            </div>

            <!-- Two-Column Profile Grid -->
            <div class="profile-layout-grid">
                <!-- Left Overview Card -->
                <div class="profile-card profile-sidebar-card">
                    <div class="avatar-upload-container">
                        <div class="avatar-image-ring">
                            <img src="../images/logo_only.png" id="profileAvatarImg" alt="Admin Avatar">
                        </div>
                        <button type="button" class="btn-avatar-camera" id="btnUploadAvatar" title="Change Profile Picture">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                        <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                    </div>

                    <h3 class="profile-card-name" id="cardProfileName">—</h3>
                    <p class="profile-card-role">System Administrator</p>
                    <p class="profile-card-email" id="cardProfileEmail">—</p>

                    <div class="profile-status-wrapper">
                        <span class="profile-status-badge">ACTIVE</span>
                    </div>

                    <div class="profile-card-footer">
                        <a href="../login.php" class="btn-profile-logout">LOGOUT</a>
                    </div>
                </div>

                <!-- Right Form Card -->
                <div class="profile-card profile-details-card">
                    <form id="adminProfileForm" onsubmit="return false;">
                        <!-- Personal Information Block -->
                        <div class="form-section-block">
                            <h2 class="form-section-title">Personal Information</h2>
                            
                            <div class="profile-form-group">
                                <label for="adminFullName">Full Name</label>
                                <input type="text" id="adminFullName" class="profile-input" value="" placeholder="Enter full name" required>
                            </div>

                            <div class="profile-form-group">
                                <label for="adminEmail">Email</label>
                                <input type="email" id="adminEmail" class="profile-input" value="" placeholder="Enter email address" required>
                            </div>

                            <div class="profile-form-row">
                                <div class="profile-form-group">
                                    <label for="adminEmployeeId">Employee ID</label>
                                    <input type="text" id="adminEmployeeId" class="profile-input" value="" placeholder="Enter employee ID">
                                </div>
                                <div class="profile-form-group">
                                    <label for="adminRole">Role</label>
                                    <input type="text" id="adminRole" class="profile-input" value="System Administrator" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="profile-section-divider"></div>

                        <!-- Change Password Block -->
                        <div class="form-section-block">
                            <h2 class="form-section-title">Change Password</h2>
                            
                            <div class="profile-form-group">
                                <label for="currentPassword">Current Password</label>
                                <div class="password-field-container">
                                    <input type="password" id="currentPassword" class="profile-input" placeholder="Enter current password">
                                    <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('currentPassword', this)" title="Toggle password visibility">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="profile-form-group">
                                <label for="newPassword">New Password</label>
                                <div class="password-field-container">
                                    <input type="password" id="newPassword" class="profile-input" placeholder="Enter new password">
                                    <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('newPassword', this)" title="Toggle password visibility">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="profile-form-group">
                                <label for="confirmPassword">Confirm Password</label>
                                <div class="password-field-container">
                                    <input type="password" id="confirmPassword" class="profile-input" placeholder="Confirm new password">
                                    <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('confirmPassword', this)" title="Toggle password visibility">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="profile-actions-bar">
                            <button type="button" class="btn-form-cancel" id="btnCancelProfile">Cancel</button>
                            <button type="submit" class="btn-form-save" id="btnSaveProfile">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotif">
        <i class="fa-solid fa-circle-check"></i> <span id="toastMsg">Profile changes saved successfully!</span>
    </div>

    <!-- JavaScript Logic -->
    <script>
        // Password Visibility Toggle Function
        function togglePasswordVisibility(inputId, buttonEl) {
            const inputField = document.getElementById(inputId);
            const icon = buttonEl.querySelector('i');
            if (inputField.type === 'password') {
                inputField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                inputField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Dropdown Toggle
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');

            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!userProfileDropdown.contains(e.target)) {
                        dropdownMenu.classList.remove('show');
                    }
                });
            }

            // Theme Toggle
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');

            function updateThemeIcon() {
                const isDark = document.documentElement.classList.contains('dark-theme');
                if (themeToggleIcon) {
                    if (isDark) {
                        themeToggleIcon.classList.remove('fa-moon');
                        themeToggleIcon.classList.add('fa-sun');
                    } else {
                        themeToggleIcon.classList.remove('fa-sun');
                        themeToggleIcon.classList.add('fa-moon');
                    }
                }
            }

            updateThemeIcon();

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    document.documentElement.classList.toggle('dark-theme');
                    const isDark = document.documentElement.classList.contains('dark-theme');
                    localStorage.setItem('dashboard-theme', isDark ? 'dark' : 'light');
                    updateThemeIcon();
                });
            }

            // Load Saved Profile Data if available
            const savedName = localStorage.getItem('admin-fullname');
            const savedEmail = localStorage.getItem('admin-email');
            const savedEmpId = localStorage.getItem('admin-employee-id');
            const savedAvatar = localStorage.getItem('admin-avatar-src');

            if (savedName) {
                document.getElementById('adminFullName').value = savedName;
                document.getElementById('cardProfileName').textContent = savedName;
                document.getElementById('dropdownHeaderName').textContent = savedName;
            }
            if (savedEmail) {
                document.getElementById('adminEmail').value = savedEmail;
                document.getElementById('cardProfileEmail').textContent = savedEmail;
                document.getElementById('dropdownHeaderEmail').textContent = savedEmail;
            }
            if (savedEmpId) {
                document.getElementById('adminEmployeeId').value = savedEmpId;
            }
            if (savedAvatar) {
                document.getElementById('profileAvatarImg').src = savedAvatar;
                document.querySelectorAll('.profile-top-avatar').forEach(img => img.src = savedAvatar);
            }

            // Avatar Upload Trigger & Handler
            const btnUploadAvatar = document.getElementById('btnUploadAvatar');
            const avatarFileInput = document.getElementById('avatarFileInput');
            const profileAvatarImg = document.getElementById('profileAvatarImg');

            if (btnUploadAvatar && avatarFileInput) {
                btnUploadAvatar.addEventListener('click', function() {
                    avatarFileInput.click();
                });

                avatarFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            const newSrc = evt.target.result;
                            profileAvatarImg.src = newSrc;
                            document.querySelectorAll('.profile-top-avatar').forEach(img => img.src = newSrc);
                            localStorage.setItem('admin-avatar-src', newSrc);
                            showToast('Profile picture updated successfully!');
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Toast Helper
            function showToast(msg) {
                const toast = document.getElementById('toastNotif');
                const toastMsg = document.getElementById('toastMsg');
                if (toast && toastMsg) {
                    toastMsg.textContent = msg;
                    toast.classList.add('show');
                    setTimeout(() => {
                        toast.classList.remove('show');
                    }, 3500);
                }
            }

            // Form Submission Handler
            const adminProfileForm = document.getElementById('adminProfileForm');
            if (adminProfileForm) {
                adminProfileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const fullName = document.getElementById('adminFullName').value.trim();
                    const email = document.getElementById('adminEmail').value.trim();
                    const empId = document.getElementById('adminEmployeeId').value.trim();
                    const currentPwd = document.getElementById('currentPassword').value;
                    const newPwd = document.getElementById('newPassword').value;
                    const confirmPwd = document.getElementById('confirmPassword').value;

                    if (newPwd || confirmPwd) {
                        if (newPwd !== confirmPwd) {
                            alert('New password and confirm password do not match!');
                            return;
                        }
                    }

                    // Save data
                    localStorage.setItem('admin-fullname', fullName);
                    localStorage.setItem('admin-email', email);
                    localStorage.setItem('admin-employee-id', empId);

                    // Update UI text
                    document.getElementById('cardProfileName').textContent = fullName;
                    document.getElementById('cardProfileEmail').textContent = email;
                    document.getElementById('dropdownHeaderName').textContent = fullName;
                    document.getElementById('dropdownHeaderEmail').textContent = email;

                    // Clear password fields
                    document.getElementById('currentPassword').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';

                    showToast('Profile changes saved successfully!');
                });
            }

            // Cancel Button Handler
            const btnCancelProfile = document.getElementById('btnCancelProfile');
            if (btnCancelProfile) {
                btnCancelProfile.addEventListener('click', function() {
                    const currentSavedName = localStorage.getItem('admin-fullname') || '';
                    const currentSavedEmail = localStorage.getItem('admin-email') || '';
                    const currentSavedEmpId = localStorage.getItem('admin-employee-id') || '';

                    document.getElementById('adminFullName').value = currentSavedName;
                    document.getElementById('adminEmail').value = currentSavedEmail;
                    document.getElementById('adminEmployeeId').value = currentSavedEmpId;
                    document.getElementById('currentPassword').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';
                });
            }
        });
    </script>
</body>
</html>
