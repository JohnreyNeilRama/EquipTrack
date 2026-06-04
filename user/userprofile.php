<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - User Profile</title>
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <link rel="stylesheet" href="../ccs/userprofile.css">
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
            <i class="fa-solid fa-backpack"></i> <span>EQUIPTRACK</span>
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
            <div class="nav-line"></div>
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

        <div class="profile-container">
            <!-- Left Column: Profile Summary -->
            <div class="profile-summary card">
                <div class="profile-banner"></div>
                <div class="profile-img-wrapper">
                    <div class="profile-img-container">
                        <img src="https://ui-avatars.com/api/?name=Gabriel+Fernandez&background=random&size=200" alt="Gabriel Fernandez" class="profile-img">
                    </div>
                    <button type="button" class="btn-upload-icon" onclick="document.getElementById('profilePicInput').click()" title="Upload Picture">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                    <input type="file" id="profilePicInput" style="display: none;" accept="image/*">
                </div>
                <div class="profile-info">
                    <h3 class="profile-name">Gabriel Fernandez</h3>
                    <p class="profile-role">Student</p>
                    <div class="profile-divider"></div>
                    <ul class="profile-stats">
                        <li>
                            <span class="stat-label">Active Borrows</span>
                            <span class="stat-num">1</span>
                        </li>
                        <li>
                            <span class="stat-label">Total Requests</span>
                            <span class="stat-num">12</span>
                        </li>
                    </ul>
                    <a href="../login.php" class="btn-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
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
                                <input type="text" class="form-control" value="Gabriel" placeholder="Enter first name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" class="form-control" value="Fernandez" placeholder="Enter last name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" class="form-control" value="gabriel.fernandez@example.com" placeholder="Enter email address">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year & Level</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-graduation-cap input-icon"></i>
                            <select class="form-control form-select">
                                <option>3rd Year - College</option>
                                <option>4th Year - College</option>
                                <option>2nd Year - College</option>
                                <option>1st Year - College</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Home Address</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-map-pin input-icon"></i>
                            <input type="text" class="form-control" value="123 University Ave, Tech City" placeholder="Enter full address">
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
        });
    </script>
</body>
</html>
