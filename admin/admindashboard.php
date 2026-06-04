<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Admin Dashboard</title>
    <!-- Base Layout Stylesheet -->
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <!-- Admin Specific Stylesheet -->
    <link rel="stylesheet" href="../ccs/admindashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            <i class="fa-solid fa-backpack"></i> <span>EQUIPTRACK</span>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="admindashboard.php" class="nav-item active">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
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
            <div class="nav-line"></div>
            <div class="navbar-right">
                <div class="icon-btn" id="themeToggleBtn">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=random" alt="Admin Avatar" class="avatar">
                    <span class="user-name">Admin</span>
                    <i class="fa-solid fa-chevron-down"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <a href="admindashboard.php"><i class="fa-solid fa-sliders"></i> Settings</a>
                        <a href="../login.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-banner card">
            <h3>Welcome back, Admin!</h3>
            <p>Here's an overview of equipment requests, borrowings, and system activity.</p>
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <div class="stat-card-mini">
                    <span class="stat-card-title">Total Equipments</span>
                    <span class="stat-card-value" id="statTotalEq">120</span>
                </div>
                <div class="stat-card-mini">
                    <span class="stat-card-title">Available</span>
                    <span class="stat-card-value" id="statAvailable">85</span>
                </div>
                <div class="stat-card-mini">
                    <span class="stat-card-title">Pending Requests</span>
                    <span class="stat-card-value" id="statPending">12</span>
                </div>
                <div class="stat-card-mini">
                    <span class="stat-card-title">Borrowed Items</span>
                    <span class="stat-card-value" id="statBorrowed">25</span>
                </div>
                <div class="stat-card-mini">
                    <span class="stat-card-title">Overdue Items</span>
                    <span class="stat-card-value" id="statOverdue">2</span>
                </div>
            </div>
        </div>

        <!-- Recent Requests Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Recent Requests</h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="action-column">Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <tr class="admin-table-row">
                            <td>Gabriel F.</td>
                            <td>Laptop Dell</td>
                            <td>May 1</td>
                            <td class="status-cell">Pending</td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <button class="btn-approve" onclick="handleRequestAction(this, 'Gabriel F.', 'Laptop Dell', 'approved')">Approve</button>
                                    <button class="btn-reject" onclick="handleRequestAction(this, 'Gabriel F.', 'Laptop Dell', 'rejected')">Reject</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="admin-table-row">
                            <td>Anna Mae S.</td>
                            <td>Camera Canon</td>
                            <td>May 11</td>
                            <td class="status-cell">Pending</td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <button class="btn-approve" onclick="handleRequestAction(this, 'Anna Mae S.', 'Camera Canon', 'approved')">Approve</button>
                                    <button class="btn-reject" onclick="handleRequestAction(this, 'Anna Mae S.', 'Camera Canon', 'rejected')">Reject</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading"><i class="fa-solid fa-triangle-exclamation warning-icon"></i> Overdue Alerts</h4>
            <div class="table-container card admin-table-card overdue-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Late</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="admin-table-row">
                            <td>Gabriel F.</td>
                            <td>Calculator</td>
                            <td>May 1</td>
                            <td class="text-danger-bold">2 Days</td>
                        </tr>
                        <tr class="admin-table-row">
                            <td>Johnrey Neil R.</td>
                            <td>Laptop Dell</td>
                            <td>April 30</td>
                            <td class="text-danger-bold">5 Days</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Two Column Bottom Section: Low Stock & System Summary -->
        <div class="bottom-grid">
            <!-- Left Column: Low Stock -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">Low Stock</h4>
                <div class="table-container card admin-table-card flex-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="admin-table-row">
                                <td>Mouse</td>
                                <td>1</td>
                            </tr>
                            <tr class="admin-table-row">
                                <td>Projector</td>
                                <td>2</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: System Summary -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">System Summary</h4>
                <div class="summary-card card">
                    <ul class="summary-list">
                        <li>
                            <span class="summary-label">Requests This Month</span>
                            <span class="summary-badge">45</span>
                        </li>
                        <li>
                            <span class="summary-label">Approved</span>
                            <span class="summary-badge success">30</span>
                        </li>
                        <li>
                            <span class="summary-label">Rejected</span>
                            <span class="summary-badge danger">10</span>
                        </li>
                        <li>
                            <span class="summary-label">Pending</span>
                            <span class="summary-badge warning" id="summaryPendingCount">5</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Success Toast Notification -->
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- Scripting for Toggles and Interactivity -->
    <script>
        // DOM Elements
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const userProfileDropdown = document.getElementById('userProfileDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const toast = document.getElementById('toast');
        const toastIcon = document.getElementById('toastIcon');
        const toastTitle = document.getElementById('toastTitle');
        const toastMsg = document.getElementById('toastMsg');
        
        // Stats elements
        const statPending = document.getElementById('statPending');
        const statBorrowed = document.getElementById('statBorrowed');
        const summaryPendingCount = document.getElementById('summaryPendingCount');

        // Dark Mode Logic
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

        // Toggle user dropdown on click
        if (userProfileDropdown && dropdownMenu) {
            userProfileDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            // Close dropdown if clicked outside
            document.addEventListener('click', () => {
                dropdownMenu.classList.remove('show');
            });
        }

        // Show Toast Function
        function showNotification(title, message, type = 'success') {
            toastTitle.textContent = title;
            toastMsg.textContent = message;
            
            // Set style based on status
            if (type === 'success') {
                toastIcon.className = 'fa-solid fa-circle-check toast-icon';
                toastIcon.style.color = '#10b981';
                document.querySelector('.toast-content').style.borderLeft = '4px solid #10b981';
            } else {
                toastIcon.className = 'fa-solid fa-circle-xmark toast-icon';
                toastIcon.style.color = '#ef4444';
                document.querySelector('.toast-content').style.borderLeft = '4px solid #ef4444';
            }
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3500);
        }

        // Handle Approve/Reject Actions
        function handleRequestAction(button, user, equipment, action) {
            const row = button.closest('.admin-table-row');
            
            // Apply a nice fade-out animation
            row.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                row.remove();
                
                // Update stats counter values dynamically
                let pendingVal = parseInt(statPending.textContent);
                if (pendingVal > 0) {
                    statPending.textContent = pendingVal - 1;
                }
                
                let summaryPendingVal = parseInt(summaryPendingCount.textContent);
                if (summaryPendingVal > 0) {
                    summaryPendingCount.textContent = summaryPendingVal - 1;
                }

                if (action === 'approved') {
                    let borrowedVal = parseInt(statBorrowed.textContent);
                    statBorrowed.textContent = borrowedVal + 1;
                    
                    showNotification('Approved', `Request for ${equipment} by ${user} has been approved.`, 'success');
                } else {
                    showNotification('Rejected', `Request for ${equipment} by ${user} has been rejected.`, 'error');
                }

                // Check if all rows are processed in the table
                const tableBody = document.getElementById('requestsTableBody');
                if (tableBody.querySelectorAll('.admin-table-row').length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                <i class="fa-solid fa-check-double" style="color: #10b981; font-size: 20px; margin-bottom: 8px; display: block;"></i>
                                All pending requests processed!
                            </td>
                        </tr>
                    `;
                }
            }, 500);
        }
    </script>
</body>
</html>
