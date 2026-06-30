<?php
// PHP page setup - static mockup prototype view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Audit Trail</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminaudit.css?v=<?php echo time(); ?>">
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
            <a href="admindashboard.php" class="nav-item">
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
            <a href="audit.php" class="nav-item active">
                <i class="fa-solid fa-clipboard-check"></i> <span>Audit Trail</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content" style="min-width: 0;">
        <!-- Top Navbar -->
        <header class="top-navbar profile-navbar">
            <div class="navbar-right">
                <span class="navbar-divider"></span>
                <div class="icon-btn" id="themeToggleBtn">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification" id="notificationBtn">
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
                        <a href="../login.php" style="color: #ef4444;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body Container -->
        <div style="padding: 30px; max-width: 1400px; margin: 0 auto; width: 100%;">
            <!-- Header Section -->
            <div class="audit-header-section">
                <h1 class="audit-page-title">Audit Trail</h1>
                <p class="audit-page-subtitle">Monitor administrator activities and system changes for accountability and security.</p>
            </div>

            <!-- Summary Statistic Cards Row -->
            <div class="audit-stats-row">
                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statToday">42</div>
                        <div class="audit-stat-icon-box blue">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">Today's Activities</div>
                        <div class="audit-stat-caption">Actions performed today</div>
                    </div>
                </div>

                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statWeek">218</div>
                        <div class="audit-stat-icon-box green">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">This Week</div>
                        <div class="audit-stat-caption">Activities this week</div>
                    </div>
                </div>

                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statMonth">945</div>
                        <div class="audit-stat-icon-box orange">
                            <i class="fa-solid fa-chart-column"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">This Month</div>
                        <div class="audit-stat-caption">Monthly activity logs</div>
                    </div>
                </div>

                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statAdmins">3</div>
                        <div class="audit-stat-icon-box purple">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">Active Administrators</div>
                        <div class="audit-stat-caption">Admins active today</div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="white-card">
                <div class="filter-toolbar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="auditSearchInput" placeholder="Search by administrator, module, or action...">
                    </div>
                    <div class="filter-dropdowns">
                        <select id="filterAction" class="filter-select">
                            <option value="All">All Actions</option>
                            <option value="Login">Login</option>
                            <option value="Logout">Logout</option>
                            <option value="Added Equipment">Added Equipment</option>
                            <option value="Updated Equipment">Updated Equipment</option>
                            <option value="Deleted Equipment">Deleted Equipment</option>
                            <option value="Approved Request">Approved Request</option>
                            <option value="Rejected Request">Rejected Request</option>
                            <option value="Confirmed Return">Confirmed Return</option>
                            <option value="Added User">Added User</option>
                            <option value="Updated User">Updated User</option>
                            <option value="Deactivated User">Deactivated User</option>
                        </select>
                        
                        <select id="filterModule" class="filter-select">
                            <option value="All">All Modules</option>
                            <option value="Equipment Management">Equipment Management</option>
                            <option value="Requests">Requests</option>
                            <option value="Users">Users</option>
                            <option value="Equipment Monitoring">Equipment Monitoring</option>
                            <option value="Reports">Reports</option>
                        </select>

                        <select id="filterDateRange" class="filter-select">
                            <option value="All">All Dates</option>
                            <option value="Today">Today</option>
                            <option value="This Week">This Week</option>
                            <option value="This Month">This Month</option>
                            <option value="Custom">Custom Date Range</option>
                        </select>
                    </div>
                    <button type="button" id="btnExportLogs" class="btn-export-logs">
                        <i class="fa-solid fa-download"></i> Export Logs
                    </button>
                </div>
                
                <!-- Custom Date Range Picker Container -->
                <div class="custom-date-container" id="customDateContainer" style="display: none;">
                    <div class="custom-date-group">
                        <label>Start Date</label>
                        <input type="date" id="customStartDate">
                    </div>
                    <div class="custom-date-group">
                        <label>End Date</label>
                        <input type="date" id="customEndDate">
                    </div>
                    <button type="button" id="btnApplyCustomDate" class="btn btn-primary" style="margin-top: auto; padding: 10px 18px; font-size: 13px; height: 38px; border-radius: 8px;">Apply Filter</button>
                </div>
            </div>

            <!-- Audit Log Table & Empty State Wrapper -->
            <div class="white-card" style="margin-top: 24px;">
                <div id="tableContainer">
                    <div class="reports-table-wrapper">
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Administrator</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody id="auditTableBody">
                                <!-- Generated Dynamically -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Wrapper -->
                    <div class="pagination-wrapper">
                        <span id="paginationInfo" style="font-size: 13px; color: var(--text-muted); margin-right: auto;">Showing 1 to 5 of 5 entries</span>
                        <div id="paginationButtons" style="display: flex; gap: 6px;">
                            <!-- Generated Dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Empty State Container -->
                <div id="emptyState" class="empty-state-container" style="display: none;">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3 class="empty-state-title">No audit records found.</h3>
                    <p class="empty-state-subtitle">Administrator activities will appear here once actions are performed within the system.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Success Notification Container -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-content">
            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 16px;" id="toastIcon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <span style="font-weight: 700; font-size: 14px; color: var(--text-main);" id="toastTitle">Notification Title</span>
                <span style="font-size: 12.5px; color: var(--text-muted);" id="toastMessage">Notification details message goes here.</span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Dropdown profile logic
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');

            userProfileDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                dropdownMenu.classList.remove('show');
            });

            // Theme switching logic
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

            // Notification Bell Click
            const notificationBtn = document.getElementById('notificationBtn');
            if (notificationBtn) {
                notificationBtn.addEventListener('click', () => {
                    showNotification('Security Alerts', 'System audit security levels are optimal. No threats detected.', 'info');
                });
            }

            // Mock Audit Trail database logs
            const auditRecords = [
                { date: "June 30, 2026 • 9:15 AM", admin: "John Cruz", module: "Requests", action: "Approved Request", details: "Approved Borrow Request #102", ip: "192.168.1.12" },
                { date: "June 30, 2026 • 10:05 AM", admin: "Sarah Reyes", module: "Equipment Management", action: "Added Equipment", details: "Dell Latitude 5420 Laptop", ip: "192.168.1.18" },
                { date: "June 30, 2026 • 10:42 AM", admin: "John Cruz", module: "Equipment Monitoring", action: "Confirmed Return", details: "Borrow Transaction #058", ip: "192.168.1.12" },
                { date: "June 30, 2026 • 11:30 AM", admin: "Sarah Reyes", module: "Users", action: "Deactivated User", details: "Student ID 2023-00125", ip: "192.168.1.18" },
                { date: "June 30, 2026 • 1:15 PM", admin: "John Cruz", module: "Reports", action: "Exported Borrowing Report", details: "Monthly Report (PDF)", ip: "192.168.1.12" },
                { date: "June 29, 2026 • 8:45 AM", admin: "Sarah Reyes", module: "Users", action: "Added User", details: "Teacher Maria Santos", ip: "192.168.1.18" },
                { date: "June 29, 2026 • 11:20 AM", admin: "Sarah Reyes", module: "Requests", action: "Rejected Request", details: "Rejected Borrow Request #099", ip: "192.168.1.18" },
                { date: "June 29, 2026 • 3:00 PM", admin: "John Cruz", module: "Equipment Management", action: "Updated Equipment", details: "Updated quantity for Epson Projector", ip: "192.168.1.12" },
                { date: "June 29, 2026 • 4:10 PM", admin: "Sarah Reyes", module: "Users", action: "Login", details: "Administrator logged in", ip: "192.168.1.18" },
                { date: "June 29, 2026 • 5:00 PM", admin: "Sarah Reyes", module: "Users", action: "Logout", details: "Administrator logged out", ip: "192.168.1.18" }
            ];

            let filteredRecords = [...auditRecords];
            const recordsPerPage = 5;
            let currentPage = 1;

            // DOM elements
            const auditTableBody = document.getElementById('auditTableBody');
            const tableContainer = document.getElementById('tableContainer');
            const emptyState = document.getElementById('emptyState');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            const auditSearchInput = document.getElementById('auditSearchInput');
            const filterAction = document.getElementById('filterAction');
            const filterModule = document.getElementById('filterModule');
            const filterDateRange = document.getElementById('filterDateRange');

            const customDateContainer = document.getElementById('customDateContainer');
            const customStartDate = document.getElementById('customStartDate');
            const customEndDate = document.getElementById('customEndDate');
            const btnApplyCustomDate = document.getElementById('btnApplyCustomDate');
            const btnExportLogs = document.getElementById('btnExportLogs');

            // Escape HTML helper
            function escapeHTML(str) {
                if (!str) return '';
                return str.replace(/[&<>'"]/g, 
                    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
                );
            }

            // Parse date string (e.g. "June 30, 2026 • 9:15 AM") into JS Date
            function parseDateString(dateStr) {
                const datePart = dateStr.split('•')[0].trim();
                return new Date(datePart);
            }

            // Render table view
            function renderTable() {
                auditTableBody.innerHTML = '';

                if (filteredRecords.length === 0) {
                    tableContainer.style.display = 'none';
                    emptyState.style.display = 'flex';
                    return;
                }

                tableContainer.style.display = 'block';
                emptyState.style.display = 'none';

                const totalEntries = filteredRecords.length;
                const totalPages = Math.ceil(totalEntries / recordsPerPage);

                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                const startIndex = (currentPage - 1) * recordsPerPage;
                const endIndex = Math.min(startIndex + recordsPerPage, totalEntries);
                const pageRecords = filteredRecords.slice(startIndex, endIndex);

                pageRecords.forEach(rec => {
                    const tr = document.createElement('tr');
                    const badgeClass = getBadgeClass(rec.action);

                    tr.innerHTML = `
                        <td><strong>${escapeHTML(rec.date)}</strong></td>
                        <td>${escapeHTML(rec.admin)}</td>
                        <td>${escapeHTML(rec.module)}</td>
                        <td>
                            <span class="status-badge ${badgeClass}">${escapeHTML(rec.action)}</span>
                        </td>
                        <td>${escapeHTML(rec.details)}</td>
                        <td><code style="background-color: var(--primary-light); padding: 2px 6px; border-radius: 4px; font-size: 12px; color: var(--primary-color);">${escapeHTML(rec.ip)}</code></td>
                    `;
                    auditTableBody.appendChild(tr);
                });

                // Update pagination info
                paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;

                // Render pagination buttons
                renderPagination(totalPages);
            }

            // Map Action to SaaS Badge Color
            function getBadgeClass(action) {
                const act = action.toLowerCase();
                if (act.includes('login')) return 'badge-blue';
                if (act.includes('logout')) return 'badge-gray';
                if (act.includes('added') || act.includes('add ')) return 'badge-green';
                if (act.includes('updated') || act.includes('update ')) return 'badge-orange';
                if (act.includes('approved') || act.includes('approve ')) return 'badge-purple';
                if (act.includes('deleted') || act.includes('delete ') || act.includes('deactivated') || act.includes('deactivate ')) return 'badge-red';
                if (act.includes('confirmed') || act.includes('confirm ') || act.includes('return')) return 'badge-teal';
                if (act.includes('exported') || act.includes('export')) return 'badge-yellow';
                if (act.includes('rejected') || act.includes('reject ')) return 'badge-red';
                return 'badge-blue';
            }

            function renderPagination(totalPages) {
                paginationButtons.innerHTML = '';

                // Previous Button
                const prevBtn = document.createElement('button');
                prevBtn.className = `pagination-btn ${currentPage === 1 ? 'disabled' : ''}`;
                prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                if (currentPage !== 1) {
                    prevBtn.addEventListener('click', () => {
                        currentPage--;
                        renderTable();
                    });
                }
                paginationButtons.appendChild(prevBtn);

                // Page Number Buttons
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.className = `pagination-btn ${currentPage === i ? 'active' : ''}`;
                    btn.textContent = i;
                    btn.addEventListener('click', () => {
                        currentPage = i;
                        renderTable();
                    });
                    paginationButtons.appendChild(btn);
                }

                // Next Button
                const nextBtn = document.createElement('button');
                nextBtn.className = `pagination-btn ${currentPage === totalPages ? 'disabled' : ''}`;
                nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                if (currentPage !== totalPages) {
                    nextBtn.addEventListener('click', () => {
                        currentPage++;
                        renderTable();
                    });
                }
                paginationButtons.appendChild(nextBtn);
            }

            // Real-time filter method
            function filterRecords() {
                const searchVal = auditSearchInput.value.toLowerCase().trim();
                const actionVal = filterAction.value;
                const moduleVal = filterModule.value;
                const dateRangeVal = filterDateRange.value;

                filteredRecords = auditRecords.filter(rec => {
                    // Search bar filter (admin, module, action, or details)
                    if (searchVal) {
                        const matchesSearch = 
                            rec.admin.toLowerCase().includes(searchVal) ||
                            rec.module.toLowerCase().includes(searchVal) ||
                            rec.action.toLowerCase().includes(searchVal) ||
                            rec.details.toLowerCase().includes(searchVal);
                        if (!matchesSearch) return false;
                    }

                    // Action dropdown filter
                    if (actionVal !== 'All' && rec.action !== actionVal) {
                        return false;
                    }

                    // Module dropdown filter
                    if (moduleVal !== 'All' && rec.module !== moduleVal) {
                        return false;
                    }

                    // Date range selection filter
                    if (dateRangeVal !== 'All') {
                        const recDate = parseDateString(rec.date);
                        const refDate = new Date("2026-06-30"); // System relative anchor day

                        if (dateRangeVal === 'Today') {
                            const isToday = recDate.getFullYear() === refDate.getFullYear() &&
                                            recDate.getMonth() === refDate.getMonth() &&
                                            recDate.getDate() === refDate.getDate();
                            if (!isToday) return false;
                        } else if (dateRangeVal === 'This Week') {
                            const diffTime = Math.abs(refDate - recDate);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            if (diffDays > 7) return false;
                        } else if (dateRangeVal === 'This Month') {
                            const isThisMonth = recDate.getFullYear() === refDate.getFullYear() &&
                                                recDate.getMonth() === refDate.getMonth();
                            if (!isThisMonth) return false;
                        } else if (dateRangeVal === 'Custom') {
                            const startVal = customStartDate.value;
                            const endVal = customEndDate.value;
                            if (startVal) {
                                const startDate = new Date(startVal);
                                startDate.setHours(0,0,0,0);
                                if (recDate < startDate) return false;
                            }
                            if (endVal) {
                                const endDate = new Date(endVal);
                                endDate.setHours(23,59,59,999);
                                if (recDate > endDate) return false;
                            }
                        }
                    }

                    return true;
                });

                currentPage = 1;
                renderTable();
            }

            // Bind Event Listeners
            auditSearchInput.addEventListener('input', filterRecords);
            filterAction.addEventListener('change', filterRecords);
            filterModule.addEventListener('change', filterRecords);
            
            filterDateRange.addEventListener('change', () => {
                if (filterDateRange.value === 'Custom') {
                    customDateContainer.style.display = 'flex';
                } else {
                    customDateContainer.style.display = 'none';
                    filterRecords();
                }
            });

            btnApplyCustomDate.addEventListener('click', filterRecords);

            // Export logic
            btnExportLogs.addEventListener('click', () => {
                if (filteredRecords.length === 0) {
                    showNotification('Export Failed', 'There are no audit logs available to export.', 'error');
                    return;
                }
                showNotification('Export Successful', `Exported ${filteredRecords.length} audit logs in CSV format successfully.`, 'success');
            });

            // Toast system notification
            const toastNotification = document.getElementById('toastNotification');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            let toastTimeout;

            window.showNotification = function(title, message, type = 'success') {
                clearTimeout(toastTimeout);
                toastTitle.textContent = title;
                toastMessage.textContent = message;

                if (type === 'success') {
                    toastIcon.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                    toastIcon.style.color = '#10b981';
                    toastIcon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                } else if (type === 'error') {
                    toastIcon.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                    toastIcon.style.color = '#ef4444';
                    toastIcon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
                } else {
                    toastIcon.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
                    toastIcon.style.color = '#3b82f6';
                    toastIcon.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
                }

                toastNotification.classList.add('show');
                toastTimeout = setTimeout(() => {
                    toastNotification.classList.remove('show');
                }, 4000);
            };

            // Initial render
            renderTable();
        });
    </script>
</body>
</html>
