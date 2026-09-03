<?php
require_once __DIR__ . '/auth_check.php';

// Handle AJAX POST requests for profile updates & avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_avatar' && !empty($_FILES['avatar_file']['tmp_name'])) {
        $file = $_FILES['avatar_file'];
        $fileMime = mime_content_type($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        if (in_array($fileMime, $allowedMimes) && $file['size'] <= 5 * 1024 * 1024) {
            $binaryData = file_get_contents($file['tmp_name']);
            $base64Data = 'data:' . $fileMime . ';base64,' . base64_encode($binaryData);
            
            $conn->query("ALTER TABLE department MODIFY COLUMN profile_image LONGTEXT DEFAULT NULL");
            $conn->query("ALTER TABLE department_account MODIFY COLUMN profile_image LONGTEXT DEFAULT NULL");
            
            $stmtUpd = $conn->prepare("UPDATE department_account SET profile_image = ? WHERE dept_acc_id = ?");
            if ($stmtUpd) {
                $stmtUpd->bind_param("si", $base64Data, $dept_acc_id);
                $stmtUpd->execute();
            }
            if (!empty($dept_id)) {
                $stmtUpdDept = $conn->prepare("UPDATE department SET profile_image = ? WHERE department_id = ?");
                if ($stmtUpdDept) {
                    $stmtUpdDept->bind_param("si", $base64Data, $dept_id);
                    $stmtUpdDept->execute();
                }
            }
            $_SESSION['dept_profile_image'] = $base64Data;
            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully!', 'profile_image' => $base64Data]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid image file or file size exceeds 5MB limit.']);
        exit;
    }
    
    if ($action === 'update_profile') {
        $fullName   = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $employeeId = trim($_POST['employee_id'] ?? '');
        $role       = trim($_POST['role'] ?? '');
        $currPass   = $_POST['current_password'] ?? '';
        $newPass    = $_POST['new_password'] ?? '';
        $confPass   = $_POST['confirm_password'] ?? '';

        if (empty($fullName) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Full Name and Email are required.']);
            exit;
        }

        $stmtChk = $conn->prepare("SELECT dept_acc_id FROM department_account WHERE (email = ? OR (employee_id = ? AND employee_id != '')) AND dept_acc_id != ?");
        if ($stmtChk) {
            $stmtChk->bind_param("ssi", $email, $employeeId, $dept_acc_id);
            $stmtChk->execute();
            if ($stmtChk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email or Employee ID is already used by another account.']);
                exit;
            }
        }

        if (!empty($newPass)) {
            if ($newPass !== $confPass) {
                echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match.']);
                exit;
            }
            $stmtP = $conn->prepare("SELECT password FROM department_account WHERE dept_acc_id = ?");
            $stmtP->bind_param("i", $dept_acc_id);
            $stmtP->execute();
            $pRes = $stmtP->get_result();
            if ($pRow = $pRes->fetch_assoc()) {
                if (!password_verify($currPass, $pRow['password'])) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                    exit;
                }
            }
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            $stmtUpd = $conn->prepare("UPDATE department_account SET full_name = ?, email = ?, employee_id = ?, role = ?, password = ? WHERE dept_acc_id = ?");
            $stmtUpd->bind_param("sssssi", $fullName, $email, $employeeId, $role, $hashedPass, $dept_acc_id);
        } else {
            $stmtUpd = $conn->prepare("UPDATE department_account SET full_name = ?, email = ?, employee_id = ?, role = ? WHERE dept_acc_id = ?");
            $stmtUpd->bind_param("ssssi", $fullName, $email, $employeeId, $role, $dept_acc_id);
        }

        if ($stmtUpd && $stmtUpd->execute()) {
            $_SESSION['dept_name'] = $fullName;
            $_SESSION['dept_email'] = $email;
            echo json_encode(['success' => true, 'message' => 'Profile information updated successfully!']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
    }
}

// Fetch logged in department details from database
$deptAccountData = null;
$stmtFetchDept = $conn->prepare("
    SELECT 
        da.*,
        d.department_name
    FROM department_account da
    LEFT JOIN department d ON da.department_id = d.department_id
    WHERE da.dept_acc_id = ?
");
if ($stmtFetchDept) {
    $stmtFetchDept->bind_param("i", $dept_acc_id);
    $stmtFetchDept->execute();
    $resFetch = $stmtFetchDept->get_result();
    if ($resFetch && $rowFetch = $resFetch->fetch_assoc()) {
        $deptAccountData = $rowFetch;
    }
}

$dept_full_name   = $deptAccountData['full_name'] ?? $dept_name;
$dept_email_val   = $deptAccountData['email'] ?? $dept_email;
$dept_employee_id = $deptAccountData['employee_id'] ?? '';
$dept_role_val    = $deptAccountData['role'] ?? 'Department Head';
$dept_assigned    = $deptAccountData['department_name'] ?? 'Department Office';
$dept_profile_img = !empty($deptAccountData['profile_image']) ? $deptAccountData['profile_image'] : null;
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
    <link rel="stylesheet" href="../ccs/global.css">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; <?php echo !empty($dept_profile_img) ? 'padding: 0; background: transparent;' : ''; ?>">
                        <?php if (!empty($dept_profile_img)): ?>
                            <img src="<?php echo htmlspecialchars($dept_profile_img); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($dept_initials); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($dept_full_name); ?></span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name"><?php echo htmlspecialchars($dept_full_name); ?></span>
                            <span class="header-email"><?php echo htmlspecialchars($dept_email_val); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
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
                        <div class="profile-card-avatar-circle" style="width: 120px; height: 120px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 36px; overflow: hidden; <?php echo !empty($dept_profile_img) ? 'padding: 0; background: transparent;' : ''; ?>">
                            <?php if (!empty($dept_profile_img)): ?>
                                <img src="<?php echo htmlspecialchars($dept_profile_img); ?>" alt="Department Avatar" class="avatar-img" id="avatarImage" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <span id="avatarInitials"><?php echo htmlspecialchars($dept_initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="avatar-camera-btn" id="changeAvatarBtn" title="Upload new photo">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                        <input type="file" id="deptAvatarInput" style="display: none;" accept="image/*">
                    </div>

                    <h3 class="account-name" id="displayAccountName"><?php echo htmlspecialchars(strtoupper($dept_full_name)); ?></h3>
                    <p class="account-role"><?php echo htmlspecialchars($dept_role_val); ?></p>

                    <span class="badge-active-status">ACTIVE</span>
                </div>

                <a href="../logout.php" class="btn-profile-logout">LOGOUT</a>
            </div>

            <!-- Right Profile Form Card -->
            <div class="profile-card-right">
                <form id="profileUpdateForm" onsubmit="handleProfileSubmit(event)">
                    
                    <!-- Personal Information Section -->
                    <h3 class="form-section-header">Personal Information</h3>

                    <div class="form-group-item">
                        <label for="fullName">Full Name</label>
                        <div class="form-input-box">
                            <input type="text" id="fullName" value="<?php echo htmlspecialchars($dept_full_name); ?>" placeholder="Enter full name" required>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="email">Email</label>
                        <div class="form-input-box">
                            <input type="email" id="email" value="<?php echo htmlspecialchars($dept_email_val); ?>" placeholder="Enter email address" required>
                        </div>
                    </div>

                    <div class="form-row-two-cols">
                        <div class="form-group-item">
                            <label for="employeeId">Employee ID</label>
                            <div class="form-input-box">
                                <input type="text" id="employeeId" value="<?php echo htmlspecialchars($dept_employee_id); ?>" placeholder="Enter employee ID">
                            </div>
                        </div>

                        <div class="form-group-item">
                            <label for="role">Role</label>
                            <div class="form-input-box">
                                <input type="text" id="role" value="<?php echo htmlspecialchars($dept_role_val); ?>" placeholder="Enter role">
                            </div>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="department">Assigned Department</label>
                        <div class="form-input-box">
                            <input type="text" id="department" value="<?php echo htmlspecialchars($dept_assigned); ?>" readonly placeholder="Assigned department" style="background-color: var(--bg-tertiary, #f3f4f6); cursor: not-allowed;">
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

        // Navbar Avatar Sync Helper
        function syncNavbarAvatar(newAvatar) {
            const dbAvatar = <?php echo json_encode($dept_profile_img); ?>;
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

        // Handle profile submit via AJAX
        function handleProfileSubmit(e) {
            e.preventDefault();
            const fullNameVal = document.getElementById('fullName').value.trim();
            const emailVal = document.getElementById('email').value.trim();
            const employeeIdVal = document.getElementById('employeeId').value.trim();
            const roleVal = document.getElementById('role').value.trim();
            const currentPasswordVal = document.getElementById('currentPassword').value;
            const newPasswordVal = document.getElementById('newPassword').value;
            const confirmPasswordVal = document.getElementById('confirmPassword').value;

            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('full_name', fullNameVal);
            formData.append('email', emailVal);
            formData.append('employee_id', employeeIdVal);
            formData.append('role', roleVal);
            formData.append('current_password', currentPasswordVal);
            formData.append('new_password', newPasswordVal);
            formData.append('confirm_password', confirmPasswordVal);

            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (fullNameVal) {
                        document.getElementById('displayAccountName').textContent = fullNameVal.toUpperCase();
                    }
                    showToast(data.message || 'Profile updated successfully.');
                } else {
                    showToast(data.message || 'Failed to update profile.');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error updating profile information.');
            });
        }

        // Avatar change & upload handler
        const changeAvatarBtn = document.getElementById('changeAvatarBtn');
        const deptAvatarInput = document.getElementById('deptAvatarInput');
        const avatarImage = document.getElementById('avatarImage');

        if (changeAvatarBtn && deptAvatarInput) {
            changeAvatarBtn.addEventListener('click', () => {
                deptAvatarInput.click();
            });

            deptAvatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const formData = new FormData();
                    formData.append('action', 'upload_avatar');
                    formData.append('avatar_file', file);

                    fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.profile_image) {
                            if (avatarImage) avatarImage.src = data.profile_image;
                            localStorage.setItem('dept-avatar-src', data.profile_image);
                            syncNavbarAvatar(data.profile_image);
                            showToast(data.message || 'Profile picture updated successfully!');
                        } else {
                            showToast(data.message || 'Failed to update profile picture.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('Error uploading profile picture.');
                    });
                }
            });
        }
    </script>
</body>
</html>
