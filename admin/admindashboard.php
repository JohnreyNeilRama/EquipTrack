<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Admin Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
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
            <img src="../images/EquipTrack_logo.png" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="admindashboard.php" class="nav-item active">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="profile.php" class="nav-item">
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
                <div class="icon-btn" id="themeToggleBtn">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80" alt="Admin Avatar" class="avatar">
                    <span class="user-name">Admin</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">Admin</span>
                            <span class="header-email">admin@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="admindashboard.php"><i class="fa-solid fa-sliders"></i> Settings</a>
                        <a href="../login.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="welcome-text">
                <span class="welcome-pill">Dashboard Overview</span>
                <h3>Welcome back, Admin!</h3>
                <p>Here's an overview of equipment requests, borrowings, and system activity.</p>
            </div>
            <img src="../images/admin_design1.png" alt="Admin Illustration" class="welcome-banner-img">
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <!-- Card 1 – Total Users -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Users</span>
                            <span class="quick-stat-card-value" id="statTotalUsers">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 2 – Total Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Equipment</span>
                            <span class="quick-stat-card-value" id="statTotalEq">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 3 – Pending Requests -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Pending Requests</span>
                            <span class="quick-stat-card-value" id="statPending">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-orange">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 4 – Borrowed Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Borrowed Equipment</span>
                            <span class="quick-stat-card-value" id="statBorrowed">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 5 – Overdue Items -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Overdue Items</span>
                            <span class="quick-stat-card-value" id="statOverdue">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
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
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading"><i class="fa-solid fa-triangle-exclamation warning-icon" style="color: #fbbf24;"></i> Overdue Alerts</h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Late</th>
                        </tr>
                    </thead>
                    <tbody id="overdueTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Two Column Bottom Section: Low Stock & System Summary -->
        <div class="bottom-grid">
            <!-- Left Column: Low Stock -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">Low Stock</h4>
                <div class="low-stock-card card flex-table" id="lowStockCard">
                    <div style="text-align: center; color: var(--text-muted); padding: 24px 16px;">
                        <i class="fa-solid fa-box-open" style="color: var(--text-muted); font-size: 20px; margin-bottom: 8px; display: block;"></i>
                        No low stock equipment.
                    </div>
                </div>
            </div>

            <!-- Right Column: System Summary -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">System Summary</h4>
                <div class="system-summary-card card">
                    <div class="summary-hero">
                        <div class="summary-hero-meta">
                            <span class="summary-hero-label">Requests This Month</span>
                            <p class="summary-hero-desc">Total borrowing activity processed</p>
                        </div>
                        <span class="summary-hero-value" id="summaryTotalCount">0</span>
                    </div>
                    
                    <div class="summary-segment-bar" id="summarySegmentBar">
                        <div class="segment-fill approved" style="width: 0%;"></div>
                        <div class="segment-fill rejected" style="width: 0%;"></div>
                        <div class="segment-fill pending" style="width: 0%;"></div>
                    </div>
                    
                    <div class="summary-details-grid">
                        <div class="summary-detail-item">
                            <span class="detail-dot approved"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Approved</span>
                                <span class="detail-value" id="summaryApprovedCount">0</span>
                            </div>
                        </div>
                        <div class="summary-detail-item">
                            <span class="detail-dot rejected"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Rejected</span>
                                <span class="detail-value" id="summaryRejectedCount">0</span>
                            </div>
                        </div>
                        <div class="summary-detail-item">
                            <span class="detail-dot pending"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Pending</span>
                                <span class="detail-value" id="summaryPendingCount">0</span>
                            </div>
                        </div>
                    </div>
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
        const statTotalUsers = document.getElementById('statTotalUsers');
        const statTotalEq = document.getElementById('statTotalEq');
        const statPending = document.getElementById('statPending');
        const statBorrowed = document.getElementById('statBorrowed');
        const statOverdue = document.getElementById('statOverdue');
        const summaryPendingCount = document.getElementById('summaryPendingCount');
        const summaryApprovedCount = document.getElementById('summaryApprovedCount');
        const summaryRejectedCount = document.getElementById('summaryRejectedCount');
        const summaryTotalCount = document.getElementById('summaryTotalCount');
        const summarySegmentBar = document.getElementById('summarySegmentBar');

        // Requests Data Sync
        const defaultRequestsList = [];

        // Overdue Items default list
        const defaultOverdueList = [];

        // Initialize Users
        const defaultUsers = [];
        let users = JSON.parse(localStorage.getItem('equip-track-users')) || [];

        // Initialize Equipment
        const defaultEquipmentList = [];
        let equipment = JSON.parse(localStorage.getItem('equip-track-equipment')) || [];

        let requests = JSON.parse(localStorage.getItem('equip-track-requests')) || [];

        // Render Dashboard Stats and Table
        function renderDashboard() {
            // Calculate Stats
            const pendingRequests = requests.filter(r => r.status && r.status.toLowerCase() === 'pending');
            const approvedRequests = requests.filter(r => r.status && r.status.toLowerCase() === 'approved');
            const rejectedRequests = requests.filter(r => r.status && r.status.toLowerCase() === 'rejected');
            
            const totalPending = pendingRequests.length;
            const totalApproved = approvedRequests.length;
            const totalRejected = rejectedRequests.length;
            
            // System summary totals
            const baseApproved = 0; 
            const baseRejected = 0;
            const finalApproved = baseApproved + totalApproved;
            const finalRejected = baseRejected + totalRejected;
            const finalTotal = finalApproved + finalRejected + totalPending;

            // Get users from localStorage to count dynamically
            const currentUsers = JSON.parse(localStorage.getItem('equip-track-users')) || users;
            const totalUsersVal = currentUsers.length;

            // Get equipment from localStorage to count dynamically
            const currentEquipment = JSON.parse(localStorage.getItem('equip-track-equipment')) || equipment;
            const totalEquipmentQty = currentEquipment.reduce((sum, item) => sum + parseInt(item.total || 0), 0);

            // Update DOM Stats
            if (statTotalUsers) statTotalUsers.textContent = totalUsersVal;
            if (statTotalEq) statTotalEq.textContent = totalEquipmentQty;
            if (statPending) statPending.textContent = totalPending;
            if (statBorrowed) statBorrowed.textContent = totalApproved; 
            if (statOverdue) statOverdue.textContent = defaultOverdueList.length;
            
            if (summaryPendingCount) summaryPendingCount.textContent = totalPending;
            if (summaryApprovedCount) summaryApprovedCount.textContent = finalApproved;
            if (summaryRejectedCount) summaryRejectedCount.textContent = finalRejected;
            if (summaryTotalCount) summaryTotalCount.textContent = finalTotal;

            // Render Segment Bar
            if (summarySegmentBar) {
                const appPct = finalTotal > 0 ? (finalApproved / finalTotal) * 100 : 0;
                const rejPct = finalTotal > 0 ? (finalRejected / finalTotal) * 100 : 0;
                const penPct = finalTotal > 0 ? (totalPending / finalTotal) * 100 : 0;

                summarySegmentBar.innerHTML = `
                    <div class="segment-fill approved" style="width: ${appPct}%;"></div>
                    <div class="segment-fill rejected" style="width: ${rejPct}%;"></div>
                    <div class="segment-fill pending" style="width: ${penPct}%;"></div>
                `;
            }

            // Render Table (maximum 2 pending rows shown on dashboard recent section)
            const tableBody = document.getElementById('requestsTableBody');
            if (tableBody) {
                tableBody.innerHTML = '';
                const recent = pendingRequests.slice(0, 2);

                if (recent.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                <i class="fa-solid fa-inbox" style="color: var(--text-muted); font-size: 20px; margin-bottom: 8px; display: block;"></i>
                                No recent requests found.
                            </td>
                        </tr>
                    `;
                } else {
                    recent.forEach(req => {
                        const tr = document.createElement('tr');
                        tr.className = 'admin-table-row';
                        tr.innerHTML = `
                            <td>${escapeHTML(req.user)}</td>
                            <td>${escapeHTML(req.equipment)}</td>
                            <td>${escapeHTML(req.date)}</td>
                            <td>Pending</td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <button class="btn-approve" onclick="handleRequestAction(this, ${req.id}, 'approved')">Approve</button>
                                    <button class="btn-reject" onclick="handleRequestAction(this, ${req.id}, 'rejected')">Reject</button>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });
                }
            }

            // Render Overdue Alerts Table dynamically
            const overdueTableBody = document.getElementById('overdueTableBody');
            if (overdueTableBody) {
                overdueTableBody.innerHTML = '';
                if (defaultOverdueList.length === 0) {
                    overdueTableBody.innerHTML = `
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                <i class="fa-solid fa-check-circle" style="color: #10b981; font-size: 20px; margin-bottom: 8px; display: block;"></i>
                                No overdue items recorded.
                            </td>
                        </tr>
                    `;
                } else {
                    defaultOverdueList.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.className = 'admin-table-row';
                        tr.innerHTML = `
                            <td>${escapeHTML(item.user)}</td>
                            <td>${escapeHTML(item.equipment)}</td>
                            <td>${escapeHTML(item.dueDate)}</td>
                            <td><span class="days-late">${escapeHTML(item.daysLate)}</span></td>
                        `;
                        overdueTableBody.appendChild(tr);
                    });
                }
            }
        }

        // Helper to escape HTML values
        function escapeHTML(str) {
            if (!str) return '';
            return str.replace(/[&<>'"]/g, 
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

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
        window.handleRequestAction = function(button, id, action) {
            const row = button.closest('.admin-table-row');
            const req = requests.find(r => r.id === id);
            if (!req) return;

            // Apply a nice fade-out animation
            row.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                if (action === 'approved') {
                    req.status = 'Approved';
                    req.rejectReason = '';
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    showNotification('Approved', `Request for ${req.equipment} by ${req.user} has been approved.`, 'success');
                } else {
                    req.status = 'Rejected';
                    req.rejectReason = 'Rejected from Dashboard';
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    showNotification('Rejected', `Request for ${req.equipment} by ${req.user} has been rejected.`, 'error');
                }

                // Re-render whole dashboard stats and items lists
                renderDashboard();
            }, 500);
        }

        // Initial rendering of dashboard items
        renderDashboard();
    </script>
</body>
</html>
