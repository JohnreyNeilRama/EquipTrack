<?php
require_once __DIR__ . '/auth_check.php';


$admin_id = (int)$_SESSION['admin_id'];
$success_msg = '';
$error_msg = '';

// Handle AJAX Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['admin_avatar_file'])) {
    header('Content-Type: application/json');
    $file = $_FILES['admin_avatar_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $fileMime = mime_content_type($file['tmp_name']);
        if (in_array($fileMime, $allowedMimes) && $file['size'] <= 5 * 1024 * 1024) {
            $binaryData = file_get_contents($file['tmp_name']);
            $base64Data = 'data:' . $fileMime . ';base64,' . base64_encode($binaryData);
            
            $stmtUpdImg = $conn->prepare("UPDATE admin SET profile_image = ? WHERE admin_id = ?");
            if ($stmtUpdImg) {
                $stmtUpdImg->bind_param("si", $base64Data, $admin_id);
                $stmtUpdImg->execute();
                $_SESSION['admin_profile_image'] = $base64Data;
                echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully!', 'image_url' => $base64Data]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture.']);
    exit;
}

// Handle Profile Updates via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['admin_full_name'] ?? '');
    $email       = trim($_POST['admin_email'] ?? '');
    $employee_id = trim($_POST['admin_employee_id'] ?? '');
    $current_pwd = $_POST['current_password'] ?? '';
    $new_pwd     = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($email)) {
        $error_msg = 'Full Name and Email are required.';
    } else {
        $stmtCurrent = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
        $stmtCurrent->bind_param("i", $admin_id);
        $stmtCurrent->execute();
        $currAdmin = $stmtCurrent->get_result()->fetch_assoc();

        $update_pwd_ok = true;
        if (!empty($new_pwd)) {
            if ($new_pwd !== $confirm_pwd) {
                $error_msg = 'New password and confirm password do not match.';
                $update_pwd_ok = false;
            } elseif (!empty($current_pwd) && !password_verify($current_pwd, $currAdmin['password']) && $current_pwd !== $currAdmin['password']) {
                $error_msg = 'Current password is incorrect.';
                $update_pwd_ok = false;
            }
        }

        if ($update_pwd_ok && empty($error_msg)) {
            if (!empty($new_pwd)) {
                $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmtUpdate = $conn->prepare("UPDATE admin SET name = ?, username = ?, email = ?, employee_id = ?, password = ? WHERE admin_id = ?");
                $stmtUpdate->bind_param("sssssi", $full_name, $email, $email, $employee_id, $hashed_pwd, $admin_id);
            } else {
                $stmtUpdate = $conn->prepare("UPDATE admin SET name = ?, username = ?, email = ?, employee_id = ? WHERE admin_id = ?");
                $stmtUpdate->bind_param("ssssi", $full_name, $email, $email, $employee_id, $admin_id);
            }

            if ($stmtUpdate->execute()) {
                $_SESSION['admin_name']        = $full_name;
                $_SESSION['admin_email']       = $email;
                $_SESSION['admin_employee_id'] = $employee_id;
                $success_msg = 'Profile changes saved successfully!';
            } else {
                $error_msg = 'Failed to update profile: ' . $conn->error;
            }
        }
    }
}

// Fetch current admin info from database
$stmtFetch = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmtFetch->bind_param("i", $admin_id);
$stmtFetch->execute();
$adminData = $stmtFetch->get_result()->fetch_assoc();

$admin_name        = $adminData['name'] ?? ($_SESSION['admin_name'] ?? 'System Admin');
$admin_email       = !empty($adminData['email']) ? $adminData['email'] : ($adminData['username'] ?? ($_SESSION['admin_email'] ?? 'admin@equiptrack.edu'));
$admin_employee_id = !empty($adminData['employee_id']) ? $adminData['employee_id'] : ($_SESSION['admin_employee_id'] ?? 'ADM-0001');

// Compute initials for profile picture
$nameParts = explode(' ', trim($admin_name));
$initials = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
}
$admin_initials = !empty($initials) ? substr($initials, 0, 2) : 'AD';
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
    <link rel="stylesheet" href="../ccs/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/adminprofile.css?v=<?php echo time(); ?>">
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
                <div class="icon-btn notification" id="notifBtn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; <?php echo !empty($admin_profile_image) ? 'padding: 0; background: transparent;' : ''; ?>">
                        <?php if (!empty($admin_profile_image)): ?>
                            <img src="<?php echo htmlspecialchars($admin_profile_image); ?>" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($admin_initials); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name" id="dropdownHeaderName"><?php echo htmlspecialchars($admin_name); ?></span>
                            <span class="header-email" id="dropdownHeaderEmail"><?php echo htmlspecialchars($admin_email); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
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

            <?php if (!empty($success_msg)): ?>
                <div style="color: #065f46; background: #d1fae5; padding: 12px 18px; border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; border: 1px solid #6ee7b7; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 1.1rem;"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div style="color: #ef4444; background: #fee2e2; padding: 12px 18px; border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-exclamation" style="color: #dc2626; font-size: 1.1rem;"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Two-Column Profile Grid -->
            <div class="profile-layout-grid">
                <!-- Left Overview Card -->
                <div class="profile-card profile-sidebar-card">
                    <div class="avatar-upload-container">
                        <div class="avatar-image-ring">
                            <img src="<?php echo !empty($admin_profile_image) ? htmlspecialchars($admin_profile_image) : 'https://ui-avatars.com/api/?name=' . urlencode($admin_name) . '&background=5C74A8&color=fff&size=200'; ?>" id="profileAvatarImg" alt="Admin Avatar">
                        </div>
                        <button type="button" class="btn-avatar-camera" id="btnUploadAvatar" title="Change Profile Picture">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                        <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                    </div>

                    <h3 class="profile-card-name" id="cardProfileName"><?php echo htmlspecialchars($admin_name); ?></h3>
                    <p class="profile-card-role">System Administrator</p>
                    <p class="profile-card-email" id="cardProfileEmail"><?php echo htmlspecialchars($admin_email); ?></p>

                    <div class="profile-status-wrapper">
                        <span class="profile-status-badge">ACTIVE</span>
                    </div>

                    <div class="profile-card-footer">
                        <a href="../logout.php" class="btn-profile-logout">LOGOUT</a>
                    </div>
                </div>

                <!-- Right Form Card -->
                <div class="profile-card profile-details-card">
                    <form id="adminProfileForm" action="profile.php" method="POST">
                        <!-- Personal Information Block -->
                        <div class="form-section-block">
                            <h2 class="form-section-title">Personal Information</h2>
                            
                            <div class="profile-form-group">
                                <label for="adminFullName">Full Name</label>
                                <input type="text" id="adminFullName" name="admin_full_name" class="profile-input" value="<?php echo htmlspecialchars($admin_name); ?>" placeholder="Enter full name" required>
                            </div>

                            <div class="profile-form-group">
                                <label for="adminEmail">Email</label>
                                <input type="email" id="adminEmail" name="admin_email" class="profile-input" value="<?php echo htmlspecialchars($admin_email); ?>" placeholder="Enter email address" required>
                            </div>

                            <div class="profile-form-row">
                                <div class="profile-form-group">
                                    <label for="adminEmployeeId">Employee ID</label>
                                    <input type="text" id="adminEmployeeId" name="admin_employee_id" class="profile-input" value="<?php echo htmlspecialchars($admin_employee_id); ?>" placeholder="Enter employee ID">
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
                                    <input type="password" id="currentPassword" name="current_password" class="profile-input" placeholder="Enter current password">
                                    <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('currentPassword', this)" title="Toggle password visibility">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="profile-form-group">
                                <label for="newPassword">New Password</label>
                                <div class="password-field-container">
                                    <input type="password" id="newPassword" name="new_password" class="profile-input" placeholder="Enter new password">
                                    <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('newPassword', this)" title="Toggle password visibility">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="profile-form-group">
                                <label for="confirmPassword">Confirm Password</label>
                                <div class="password-field-container">
                                    <input type="password" id="confirmPassword" name="confirm_password" class="profile-input" placeholder="Confirm new password">
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

            // Navbar Avatar Sync Helper
            function syncNavbarAvatar(newAvatar) {
                const dbAvatar = <?php echo json_encode($admin_profile_image); ?>;
                let currentAvatar = null;
                if (newAvatar !== undefined) {
                    currentAvatar = newAvatar;
                } else if (dbAvatar) {
                    currentAvatar = dbAvatar;
                } else {
                    localStorage.removeItem('admin-avatar-src');
                    currentAvatar = null;
                }

                const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');

                if (currentAvatar) {
                    localStorage.setItem('admin-avatar-src', currentAvatar);
                    navAvatars.forEach(navAvatar => {
                        navAvatar.innerHTML = `<img src="${currentAvatar}" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                        navAvatar.style.padding = '0';
                        navAvatar.style.background = 'transparent';
                    });
                } else {
                    localStorage.removeItem('admin-avatar-src');
                    navAvatars.forEach(navAvatar => {
                        navAvatar.style.padding = '';
                        navAvatar.style.background = 'var(--primary-color)';
                        navAvatar.innerHTML = <?php echo json_encode(htmlspecialchars($admin_initials)); ?>;
                    });
                }
            }
            syncNavbarAvatar();

            window.addEventListener('storage', function(e) {
                if (e.key === 'admin-avatar-src') {
                    syncNavbarAvatar(e.newValue);
                }
            });

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
                        const formData = new FormData();
                        formData.append('admin_avatar_file', file);
                        fetch('profile.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.image_url) {
                                if (profileAvatarImg) profileAvatarImg.src = data.image_url;
                                localStorage.setItem('admin-avatar-src', data.image_url);
                                syncNavbarAvatar(data.image_url);
                                showToast('Profile picture updated successfully!');
                            } else {
                                alert(data.message || 'Error uploading profile picture');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('An error occurred while uploading profile picture.');
                        });
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

            // Form Validation on submit
            const adminProfileForm = document.getElementById('adminProfileForm');
            if (adminProfileForm) {
                adminProfileForm.addEventListener('submit', function(e) {
                    const newPwd = document.getElementById('newPassword').value;
                    const confirmPwd = document.getElementById('confirmPassword').value;

                    if (newPwd || confirmPwd) {
                        if (newPwd !== confirmPwd) {
                            e.preventDefault();
                            alert('New password and confirm password do not match!');
                            return false;
                        }
                    }
                });
            }

            // Cancel Button Handler
            const btnCancelProfile = document.getElementById('btnCancelProfile');
            if (btnCancelProfile) {
                btnCancelProfile.addEventListener('click', function() {
                    window.location.reload();
                });
            }
        });
    </script>
</body>
</html>
