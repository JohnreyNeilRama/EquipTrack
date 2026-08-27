<?php
require_once __DIR__ . '/auth_check.php';
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
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=random&size=200" alt="User" class="profile-img">
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
                    <p class="details-subtitle">Update your profile details and settings.</p>
                </div>
                <form class="profile-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" placeholder="Enter first name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" placeholder="Enter last name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" placeholder="Enter email address">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year & Level</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-graduation-cap input-icon"></i>
                            <select class="form-control form-select">
                                <option value="" disabled selected>Select Year & Level</option>
                                <option>1st Year - College</option>
                                <option>2nd Year - College</option>
                                <option>3rd Year - College</option>
                                <option>4th Year - College</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Home Address</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-map-pin input-icon"></i>
                            <input type="text" class="form-control" value="" placeholder="Enter full address">
                        </div>
                    </div>


                    <div class="form-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-save">Save Changes</button>
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

            const savedAvatar = localStorage.getItem('user-avatar-src');
            if (savedAvatar && profileImg) {
                profileImg.src = savedAvatar;
            }

            if (profilePicInput && profileImg) {
                profilePicInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            const newSrc = evt.target.result;
                            profileImg.src = newSrc;
                            localStorage.setItem('user-avatar-src', newSrc);
                            syncNavbarAvatar();
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
