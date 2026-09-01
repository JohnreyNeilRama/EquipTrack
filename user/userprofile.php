<?php
require_once __DIR__ . '/auth_check.php';

// Handle Personal Information Update POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    header('Content-Type: application/json');
    $post_fname      = trim($_POST['first_name'] ?? '');
    $post_lname      = trim($_POST['last_name'] ?? '');
    $post_email      = trim($_POST['email'] ?? '');
    $post_year_level = trim($_POST['year_level'] ?? '');
    $post_address    = trim($_POST['address'] ?? '');

    if (empty($post_fname) || empty($post_email)) {
        echo json_encode(['success' => false, 'message' => 'First name and email address are required.']);
        exit;
    }

    // Update email in user_account
    $stmtUpdUser = $conn->prepare("UPDATE user_account SET email = ? WHERE user_id = ?");
    $stmtUpdUser->bind_param("si", $post_email, $user_id);
    $stmtUpdUser->execute();

    if ($user_role === 'Student') {
        $stmtUpdStud = $conn->prepare("UPDATE student SET first_name = ?, last_name = ?, year_level = ?, address = ? WHERE user_id = ?");
        $stmtUpdStud->bind_param("ssssi", $post_fname, $post_lname, $post_year_level, $post_address, $user_id);
        $stmtUpdStud->execute();
    } elseif ($user_role === 'Faculty') {
        $stmtUpdFac = $conn->prepare("UPDATE faculty_member SET first_name = ?, last_name = ?, address = ? WHERE user_id = ?");
        $stmtUpdFac->bind_param("sssi", $post_fname, $post_lname, $post_address, $user_id);
        $stmtUpdFac->execute();
    }

    $_SESSION['email']      = $post_email;
    $_SESSION['user_name']  = trim($post_fname . ' ' . $post_lname);
    $_SESSION['year_level'] = $post_year_level;
    $_SESSION['address']    = $post_address;

    echo json_encode(['success' => true, 'message' => 'Personal information updated successfully!']);
    exit;
}

// Handle Profile Picture Upload POST Request (Store directly in Database as Base64 Data URI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image_file'])) {
    header('Content-Type: application/json');
    $file = $_FILES['profile_image_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed. Error code: ' . $file['error']]);
        exit;
    }
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileMime = mime_content_type($file['tmp_name']);
    if (!in_array($fileMime, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF']);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
        exit;
    }
    
    // Read raw binary image bytes and encode directly as base64 Data URI
    $binaryData = file_get_contents($file['tmp_name']);
    $base64Data = 'data:' . $fileMime . ';base64,' . base64_encode($binaryData);
    
    // Ensure column type supports long base64 strings if needed
    $conn->query("ALTER TABLE user_account MODIFY COLUMN profile_image LONGTEXT DEFAULT NULL");
    
    $stmtUpd = $conn->prepare("UPDATE user_account SET profile_image = ? WHERE user_id = ?");
    $stmtUpd->bind_param("si", $base64Data, $user_id);
    
    if ($stmtUpd->execute()) {
        $_SESSION['profile_image'] = $base64Data;
        echo json_encode([
            'success'   => true, 
            'image_url' => $base64Data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save profile picture in database: ' . $conn->error]);
    }
    exit;
}

$display_avatar_url = !empty($user_profile_image) 
    ? $user_profile_image
    : 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=385585&color=fff&size=300&bold=true';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - User Profile</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <link rel="stylesheet" href="../ccs/global.css">
    <link rel="stylesheet" href="css/userprofile.css">
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
            <a href="userdashboard.php" class="nav-item">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="userprofile.php" class="nav-item active">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($user_initials); ?></div>
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

        <div class="profile-container">
            <!-- Left Column: Profile Summary -->
            <div class="profile-summary card">
                <div class="profile-banner"></div>
                <div class="profile-img-wrapper">
                    <div class="profile-img-container">
                        <img src="<?php echo htmlspecialchars($display_avatar_url); ?>" alt="User" class="profile-img">
                    </div>
                    <button type="button" class="btn-upload-icon" onclick="document.getElementById('profilePicInput').click()" title="Upload Picture">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                    <input type="file" id="profilePicInput" style="display: none;" accept="image/*">
                </div>
                <div class="profile-info">
                    <h3 class="profile-name"><?php echo htmlspecialchars($full_name); ?></h3>
                    <p class="profile-role"><?php echo htmlspecialchars($user_role); ?></p>
                    <div class="profile-divider"></div>
                    <ul class="profile-stats">
                        <li>
                            <span class="stat-label">Active Borrows</span>
                            <span class="stat-num">0</span>
                        </li>
                        <li>
                            <span class="stat-label">Total Requests</span>
                            <span class="stat-num">0</span>
                        </li>
                    </ul>
                    <a href="../logout.php" class="btn-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                </div>
            </div>

            <!-- Right Column: Personal Information -->
            <div class="profile-details card">
                <div class="details-header">
                    <h2 class="details-title">Personal Information</h2>
                    <p class="details-subtitle">View and update your profile details and settings.</p>
                </div>
                <form class="profile-form" id="personalInfoForm" method="POST" action="userprofile.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" placeholder="Enter first name" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" placeholder="Enter last name" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" placeholder="Enter email address" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year Level</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-graduation-cap input-icon"></i>
                            <select name="year_level" class="form-control form-select" disabled>
                                <option value="" disabled <?php echo (empty($user_year_level) || $user_year_level === 'N/A') ? 'selected' : ''; ?>>Select Year Level</option>
                                <?php 
                                $year_options = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
                                foreach ($year_options as $opt): 
                                    $isSelected = (strcasecmp(trim($user_year_level), $opt) === 0 || strpos($user_year_level, $opt) !== false);
                                ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $isSelected ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                                <?php if ($user_role === 'Faculty'): ?>
                                    <option value="N/A" <?php echo ($user_year_level === 'N/A') ? 'selected' : ''; ?>>N/A</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Home Address</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-map-pin input-icon"></i>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user_address); ?>" placeholder="Enter full address" readonly>
                        </div>
                    </div>

                    <div class="form-actions" id="formActions">
                        <button type="button" class="btn-edit-profile" id="btnEditProfile">
                            <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                        </button>
                        <button type="button" class="btn-cancel" id="btnCancelEdit" style="display: none;">Cancel</button>
                        <button type="submit" class="btn-save" id="btnSaveEdit" style="display: none;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotif" style="position: fixed; bottom: 24px; right: 24px; background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 9999;">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
        <span id="toastMsg">Profile picture updated successfully!</span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');

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

            // Profile Avatar Image Upload & Sync
            const profilePicInput = document.getElementById('profilePicInput');
            const profileImg = document.querySelector('.profile-img-container .profile-img');

            if (profilePicInput && profileImg) {
                profilePicInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const formData = new FormData();
                        formData.append('profile_image_file', file);

                        fetch('userprofile.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                profileImg.src = data.image_url;
                                localStorage.setItem('user-avatar-src', data.image_url);
                                syncNavbarAvatar();
                                showToast('Profile picture uploaded and saved to database!');
                            } else {
                                alert(data.message || 'Error uploading profile picture');
                            }
                        })
                        .catch(err => {
                            console.error('Error:', err);
                            alert('An error occurred while uploading your profile picture.');
                        });
                    }
                });
            }

            // Personal Information Edit / Read-only Mode Toggle Logic
            const personalInfoForm = document.getElementById('personalInfoForm');
            const btnEditProfile = document.getElementById('btnEditProfile');
            const btnCancelEdit = document.getElementById('btnCancelEdit');
            const btnSaveEdit = document.getElementById('btnSaveEdit');

            let initialFormState = {};

            function captureFormState() {
                if (!personalInfoForm) return;
                const inputs = personalInfoForm.querySelectorAll('.form-control');
                inputs.forEach(input => {
                    initialFormState[input.name] = input.value;
                });
            }

            function setFormReadOnly(isReadOnly) {
                if (!personalInfoForm) return;
                const inputs = personalInfoForm.querySelectorAll('.form-control');
                inputs.forEach(input => {
                    if (isReadOnly) {
                        input.setAttribute('readonly', 'readonly');
                        if (input.tagName === 'SELECT') {
                            input.setAttribute('disabled', 'disabled');
                        }
                    } else {
                        input.removeAttribute('readonly');
                        if (input.tagName === 'SELECT') {
                            input.removeAttribute('disabled');
                        }
                    }
                });

                if (isReadOnly) {
                    if (btnEditProfile) btnEditProfile.style.display = 'inline-flex';
                    if (btnCancelEdit) btnCancelEdit.style.display = 'none';
                    if (btnSaveEdit) btnSaveEdit.style.display = 'none';
                } else {
                    if (btnEditProfile) btnEditProfile.style.display = 'none';
                    if (btnCancelEdit) btnCancelEdit.style.display = 'inline-flex';
                    if (btnSaveEdit) btnSaveEdit.style.display = 'inline-flex';
                }
            }

            captureFormState();

            if (btnEditProfile) {
                btnEditProfile.addEventListener('click', () => {
                    captureFormState();
                    setFormReadOnly(false);
                    const firstInput = personalInfoForm.querySelector('input[name="first_name"]');
                    if (firstInput) firstInput.focus();
                });
            }

            if (btnCancelEdit) {
                btnCancelEdit.addEventListener('click', () => {
                    const inputs = personalInfoForm.querySelectorAll('.form-control');
                    inputs.forEach(input => {
                        if (initialFormState.hasOwnProperty(input.name)) {
                            input.value = initialFormState[input.name];
                        }
                    });
                    setFormReadOnly(true);
                });
            }

            if (personalInfoForm) {
                personalInfoForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(personalInfoForm);
                    fetch('userprofile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            captureFormState();
                            setFormReadOnly(true);
                            showToast(data.message || 'Personal information updated successfully!');

                            const profileNameHeading = document.querySelector('.profile-info .profile-name');
                            if (profileNameHeading && formData.get('first_name')) {
                                profileNameHeading.textContent = (formData.get('first_name') + ' ' + (formData.get('last_name') || '')).trim();
                            }
                        } else {
                            alert(data.message || 'Error updating profile');
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('An error occurred while updating your profile.');
                    });
                });
            }

            // Toast Helper
            function showToast(msg) {
                const toast = document.getElementById('toastNotif');
                const toastMsg = document.getElementById('toastMsg');
                if (toast && toastMsg) {
                    toastMsg.textContent = msg;
                    toast.style.opacity = '1';
                    toast.style.visibility = 'visible';
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.visibility = 'hidden';
                    }, 3500);
                }
            }
        });
    </script>
</body>
</html>
