<?php
// PHP page setup - static mockup prototype view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Reports Dashboard</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminreports.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="reports.php" class="nav-item active">
                <i class="fa-solid fa-file-lines"></i> <span>Reports</span>
            </a>
            <a href="audit.php" class="nav-item">
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

        <!-- Reports Header Section -->
        <div class="reports-header-section">
            <h1 class="reports-page-title">Reports</h1>
            <p class="reports-page-subtitle">Generate, analyze, and export borrowing, return, inventory, and user activity reports.</p>
        </div>

        <!-- Section Title for Summary Cards -->
        <div class="reports-section-title">Summary Cards</div>

        <!-- 5 Summary Statistic Cards -->
        <div class="reports-stats-row">
            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value">235</div>
                    <div class="report-stat-icon-box blue">
                        <i class="fa-solid fa-chart-simple"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Total Borrowings</div>
                    <div class="report-stat-caption">Total borrowing transactions</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value">210</div>
                    <div class="report-stat-icon-box green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Total Returns</div>
                    <div class="report-stat-caption">Successfully returned equipment</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value">18</div>
                    <div class="report-stat-icon-box indigo">
                        <i class="fa-solid fa-box"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Active Borrowings</div>
                    <div class="report-stat-caption">Equipment currently borrowed</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value">7</div>
                    <div class="report-stat-icon-box orange">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Overdue Cases</div>
                    <div class="report-stat-caption">Items past their due date</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value">35</div>
                    <div class="report-stat-icon-box teal">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Reports Generated</div>
                    <div class="report-stat-caption">Reports generated this month</div>
                </div>
            </div>
        </div>

        <!-- Reports Layout Grid (All full-width horizontal cards) -->
        <div class="reports-layout-grid">
            <!-- Charts Analytics Card -->
            <div class="reports-analytics-card">
                <div class="analytics-section">
                    <div class="analytics-section-title">
                        <span>Monthly Borrowings</span>
                        <i class="fa-solid fa-chart-bar" style="color: var(--primary-color);"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart" height="180"></canvas>
                    </div>
                </div>

                <div class="analytics-section">
                    <div class="analytics-section-title">
                        <span>Borrowing by Category</span>
                        <i class="fa-solid fa-chart-pie" style="color: var(--primary-color);"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart" height="180"></canvas>
                    </div>
                </div>

                <div class="analytics-section most-borrowed">
                    <div class="analytics-section-title-centered">Most Borrowed Equipment</div>
                    <div style="font-size: 18px; font-weight: 700; color: var(--primary-color); margin-top: 6px;">Laptop Dell XPS</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">42 borrowings this month</div>
                </div>
            </div>

            <!-- Generate Report Panel -->
            <div class="white-card">
                <div class="white-card-header">
                    <div class="white-card-title">
                        <i class="fa-solid fa-sliders" style="color: var(--primary-color);"></i> Generate Report Filters
                    </div>
                </div>
                <form id="reportFilterForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" id="filterFrom" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" id="filterTo" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label>Report Type</label>
                            <select id="filterReportType" class="form-control select-control">
                                <option value="Borrowing Report">Borrowing Report</option>
                                <option value="Return Report">Return Report</option>
                                <option value="Equipment Inventory Report">Equipment Inventory Report</option>
                                <option value="Overdue Report">Overdue Report</option>
                                <option value="User Activity Report">User Activity Report</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select id="filterStatus" class="form-control select-control">
                                <option value="All">All</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Borrowed">Borrowed</option>
                                <option value="Returned">Returned</option>
                                <option value="Overdue">Overdue</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select id="filterCategory" class="form-control select-control">
                                <option value="All Categories">All Categories</option>
                                <option value="Laptop">Laptop</option>
                                <option value="Projector">Projector</option>
                                <option value="Camera">Camera</option>
                                <option value="Laboratory Equipment">Laboratory Equipment</option>
                                <option value="Audio Equipment">Audio Equipment</option>
                            </select>
                        </div>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-gears"></i> Generate Report
                        </button>
                        <button type="button" id="btnResetFilters" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-rotate-left"></i> Reset Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Preview Card -->
            <div class="white-card">
                <div class="white-card-header">
                    <div class="white-card-title">
                        <i class="fa-solid fa-magnifying-glass-chart" style="color: var(--primary-color);"></i> Report Preview
                    </div>
                    <div class="preview-actions">
                        <button type="button" class="btn-export" onclick="triggerExport('PDF')">
                            <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Export PDF
                        </button>
                        <button type="button" class="btn-export" onclick="triggerExport('Excel')">
                            <i class="fa-solid fa-file-excel" style="color: #10b981;"></i> Export Excel
                        </button>
                        <button type="button" class="btn-export" onclick="triggerExport('Print')">
                            <i class="fa-solid fa-print" style="color: var(--primary-color);"></i> Print Report
                        </button>
                    </div>
                </div>
                
                <div class="reports-table-wrapper">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Student/Faculty ID</th>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody">
                            <!-- Rendered dynamically -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <span id="paginationInfo" style="font-size: 13px; color: var(--text-muted); margin-right: auto;"></span>
                    <div id="paginationButtons" style="display: flex; gap: 6px;">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>

            <!-- Recent Generated Reports Section -->
            <div class="white-card">
                <div class="white-card-header">
                    <div class="white-card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary-color);"></i> Recent Generated Reports
                    </div>
                    <button type="button" class="btn-view-all" onclick="showNotification('View All Reports', 'Navigating to all historical reports logs...', 'info')">
                        View All Reports <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <div class="reports-table-wrapper">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Report Name</th>
                                <th>Generated By</th>
                                <th>Date Generated</th>
                                <th>File Format</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong style="color: var(--text-main);"><i class="fa-solid fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>Borrowing_Report_June_2026.pdf</strong></td>
                                <td>Admin User</td>
                                <td>June 28, 2026, 09:14 AM</td>
                                <td><span class="status-pill returned" style="background-color: rgba(239, 68, 68, 0.08); color: #ef4444; text-transform: uppercase; font-size: 11px;">PDF</span></td>
                                <td style="text-align: right;">
                                    <button class="btn-action-outline" onclick="triggerFileAction('Borrowing_Report_June_2026.pdf', 'download')"><i class="fa-solid fa-download"></i> Download</button>
                                    <button class="btn-action-outline view" onclick="triggerFileAction('Borrowing_Report_June_2026.pdf', 'view')"><i class="fa-solid fa-eye"></i> View</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong style="color: var(--text-main);"><i class="fa-solid fa-file-excel" style="color: #10b981; margin-right: 8px;"></i>Inventory_Status_Q2_2026.xlsx</strong></td>
                                <td>Admin User</td>
                                <td>June 25, 2026, 04:30 PM</td>
                                <td><span class="status-pill returned" style="background-color: rgba(16, 185, 129, 0.08); color: #10b981; text-transform: uppercase; font-size: 11px;">Excel</span></td>
                                <td style="text-align: right;">
                                    <button class="btn-action-outline" onclick="triggerFileAction('Inventory_Status_Q2_2026.xlsx', 'download')"><i class="fa-solid fa-download"></i> Download</button>
                                    <button class="btn-action-outline view" onclick="triggerFileAction('Inventory_Status_Q2_2026.xlsx', 'view')"><i class="fa-solid fa-eye"></i> View</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong style="color: var(--text-main);"><i class="fa-solid fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>Overdue_Alerts_June2026.pdf</strong></td>
                                <td>Admin User</td>
                                <td>June 18, 2026, 11:05 AM</td>
                                <td><span class="status-pill returned" style="background-color: rgba(239, 68, 68, 0.08); color: #ef4444; text-transform: uppercase; font-size: 11px;">PDF</span></td>
                                <td style="text-align: right;">
                                    <button class="btn-action-outline" onclick="triggerFileAction('Overdue_Alerts_June2026.pdf', 'download')"><i class="fa-solid fa-download"></i> Download</button>
                                    <button class="btn-action-outline view" onclick="triggerFileAction('Overdue_Alerts_June2026.pdf', 'view')"><i class="fa-solid fa-eye"></i> View</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong style="color: var(--text-main);"><i class="fa-solid fa-file-excel" style="color: #10b981; margin-right: 8px;"></i>User_Activities_Q2.xlsx</strong></td>
                                <td>System Scheduler</td>
                                <td>June 10, 2026, 12:00 AM</td>
                                <td><span class="status-pill returned" style="background-color: rgba(16, 185, 129, 0.08); color: #10b981; text-transform: uppercase; font-size: 11px;">Excel</span></td>
                                <td style="text-align: right;">
                                    <button class="btn-action-outline" onclick="triggerFileAction('User_Activities_Q2.xlsx', 'download')"><i class="fa-solid fa-download"></i> Download</button>
                                    <button class="btn-action-outline view" onclick="triggerFileAction('User_Activities_Q2.xlsx', 'view')"><i class="fa-solid fa-eye"></i> View</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                    // Re-render charts with updated grid lines or colors if needed
                    updateChartsTheme();
                });
            }

            // Notification Bell Click
            const notificationBtn = document.getElementById('notificationBtn');
            notificationBtn.addEventListener('click', () => {
                showNotification('System Messages', 'No new reports alerts at the moment.', 'info');
            });

            // Sample database records for reports filtering
            const reportRecords = [
                { borrower: "Johnrey Neil Rama", id: "20230456", equipment: "Laptop Dell XPS", category: "Laptop", borrowDate: "2026-06-01", returnDate: "2026-06-05", status: "Borrowed" },
                { borrower: "Gabriel Fernandez", id: "20230123", equipment: "Lenovo ThinkPad", category: "Laptop", borrowDate: "2026-05-15", returnDate: "2026-05-20", status: "Overdue" },
                { borrower: "Jeffrey Gaviola", id: "20230789", equipment: "Projector Epson", category: "Projector", borrowDate: "2026-06-28", returnDate: "2026-07-02", status: "Pending" },
                { borrower: "Anna Mae Santos", id: "T-00987", equipment: "Scientific Calculator", category: "Laboratory Equipment", borrowDate: "2026-06-10", returnDate: "2026-06-14", status: "Returned" },
                { borrower: "Jeffrey Gaviola", id: "20230789", equipment: "iPad Pro", category: "Others", borrowDate: "2026-06-25", returnDate: "2026-07-03", status: "Approved" },
                { borrower: "Gabriel Fernandez", id: "20230123", equipment: "Oscilloscope", category: "Laboratory Equipment", borrowDate: "2026-05-10", returnDate: "2026-05-15", status: "Overdue" },
                { borrower: "Johnrey Neil Rama", id: "20230456", equipment: "Digital Multimeter", category: "Laboratory Equipment", borrowDate: "2026-06-02", returnDate: "2026-06-08", status: "Borrowed" },
                { borrower: "Anna Mae Santos", id: "T-00987", equipment: "Epson Projector", category: "Projector", borrowDate: "2026-05-20", returnDate: "2026-05-25", status: "Overdue" },
                { borrower: "Jeffrey Gaviola", id: "20230789", equipment: "Surveying Transit", category: "Laboratory Equipment", borrowDate: "2026-06-27", returnDate: "2026-07-04", status: "Approved" },
                { borrower: "Gabriel Fernandez", id: "20230123", equipment: "Barcode Scanner", category: "Others", borrowDate: "2026-06-12", returnDate: "2026-06-16", status: "Returned" },
                { borrower: "Johnrey Neil Rama", id: "20230456", equipment: "Document Scanner", category: "Others", borrowDate: "2026-05-05", returnDate: "2026-05-12", status: "Returned" },
                { borrower: "Anna Mae Santos", id: "T-00987", equipment: "Financial Calculator", category: "Others", borrowDate: "2026-06-15", returnDate: "2026-06-19", status: "Returned" },
                { borrower: "Johnrey Neil Rama", id: "20230456", equipment: "Projector Sony", category: "Projector", borrowDate: "2026-05-08", returnDate: "2026-05-14", status: "Returned" },
                { borrower: "Gabriel Fernandez", id: "20230123", equipment: "DSLR Camera Nikon", category: "Camera", borrowDate: "2026-06-20", returnDate: "2026-06-24", status: "Borrowed" },
                { borrower: "Jeffrey Gaviola", id: "20230789", equipment: "Evidence Scale", category: "Others", borrowDate: "2026-06-22", returnDate: "2026-06-29", status: "Pending" },
                { borrower: "Anna Mae Santos", id: "T-00987", equipment: "HP Laptop", category: "Laptop", borrowDate: "2026-06-18", returnDate: "2026-06-23", status: "Returned" },
                { borrower: "Johnrey Neil Rama", id: "20230456", equipment: "DSLR Camera Canon", category: "Camera", borrowDate: "2026-06-12", returnDate: "2026-06-18", status: "Borrowed" },
                { borrower: "Gabriel Fernandez", id: "20230123", equipment: "Sony Audio Console", category: "Audio Equipment", borrowDate: "2026-06-05", returnDate: "2026-06-10", status: "Returned" },
                { borrower: "Jeffrey Gaviola", id: "20230789", equipment: "Sennheiser Mic", category: "Audio Equipment", borrowDate: "2026-06-14", returnDate: "2026-06-19", status: "Borrowed" },
            ];

            let filteredRecords = [...reportRecords];
            let currentPage = 1;
            const entriesPerPage = 5;

            // Elements
            const previewTableBody = document.getElementById('previewTableBody');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            const filterFrom = document.getElementById('filterFrom');
            const filterTo = document.getElementById('filterTo');
            const filterReportType = document.getElementById('filterReportType');
            const filterStatus = document.getElementById('filterStatus');
            const filterCategory = document.getElementById('filterCategory');
            const reportFilterForm = document.getElementById('reportFilterForm');
            const btnResetFilters = document.getElementById('btnResetFilters');

            // Render Table Rows
            function renderTable() {
                previewTableBody.innerHTML = '';
                
                if (filteredRecords.length === 0) {
                    previewTableBody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px !important;">
                                <i class="fa-solid fa-folder-open" style="font-size: 24px; margin-bottom: 8px; display: block; color: var(--border-color);"></i>
                                No matching records found for the selected filters.
                            </td>
                        </tr>
                    `;
                    paginationInfo.textContent = 'Showing 0 to 0 of 0 entries';
                    paginationButtons.innerHTML = '';
                    return;
                }

                const totalEntries = filteredRecords.length;
                const totalPages = Math.ceil(totalEntries / entriesPerPage);

                if (currentPage > totalPages) currentPage = totalPages || 1;

                const startIndex = (currentPage - 1) * entriesPerPage;
                const endIndex = Math.min(startIndex + entriesPerPage, totalEntries);
                const pageRecords = filteredRecords.slice(startIndex, endIndex);

                pageRecords.forEach(rec => {
                    const tr = document.createElement('tr');
                    
                    let badgeClass = rec.status.toLowerCase();
                    if (rec.status === 'Approved') badgeClass = 'borrowed'; // Map approved to blue status
                    if (rec.status === 'Pending') badgeClass = 'pending'; // Map pending to orange status

                    tr.innerHTML = `
                        <td><strong>${escapeHTML(rec.borrower)}</strong></td>
                        <td>${escapeHTML(rec.id)}</td>
                        <td>${escapeHTML(rec.equipment)}</td>
                        <td>${escapeHTML(rec.category)}</td>
                        <td>${escapeHTML(rec.borrowDate)}</td>
                        <td>${escapeHTML(rec.returnDate)}</td>
                        <td>
                            <span class="status-pill ${badgeClass}">${rec.status}</span>
                        </td>
                    `;
                    previewTableBody.appendChild(tr);
                });

                // Update pagination info
                paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;

                // Render dynamic pagination buttons
                renderPagination(totalPages);
            }

            function renderPagination(totalPages) {
                paginationButtons.innerHTML = '';

                // Prev Button
                const prevBtn = document.createElement('button');
                prevBtn.type = "button";
                prevBtn.className = `pagination-btn ${currentPage === 1 ? 'disabled' : ''}`;
                prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
                if (currentPage > 1) {
                    prevBtn.addEventListener('click', () => {
                        currentPage--;
                        renderTable();
                    });
                }
                paginationButtons.appendChild(prevBtn);

                // Page Number Buttons
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.type = "button";
                    pageBtn.className = `pagination-btn ${currentPage === i ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderTable();
                    });
                    paginationButtons.appendChild(pageBtn);
                }

                // Next Button
                const nextBtn = document.createElement('button');
                nextBtn.type = "button";
                nextBtn.className = `pagination-btn ${currentPage === totalPages ? 'disabled' : ''}`;
                nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
                if (currentPage < totalPages) {
                    nextBtn.addEventListener('click', () => {
                        currentPage++;
                        renderTable();
                    });
                }
                paginationButtons.appendChild(nextBtn);
            }

            // Filter logic on form submit
            reportFilterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const fromVal = filterFrom.value;
                const toVal = filterTo.value;
                const statusVal = filterStatus.value;
                const categoryVal = filterCategory.value;

                filteredRecords = reportRecords.filter(rec => {
                    // Date filter
                    if (fromVal && rec.borrowDate < fromVal) return false;
                    if (toVal && rec.borrowDate > toVal) return false;
                    
                    // Status filter
                    if (statusVal !== 'All') {
                        if (statusVal === 'Borrowed' && rec.status !== 'Borrowed' && rec.status !== 'Approved') return false;
                        if (statusVal !== 'Borrowed' && rec.status !== statusVal) return false;
                    }

                    // Category filter
                    if (categoryVal !== 'All Categories' && rec.category !== categoryVal) return false;

                    return true;
                });

                currentPage = 1;
                renderTable();
                
                showNotification('Report Generated', `Successfully compiled report preview with ${filteredRecords.length} matching transactions.`, 'success');
            });

            // Reset Filters logic
            btnResetFilters.addEventListener('click', () => {
                reportFilterForm.reset();
                filteredRecords = [...reportRecords];
                currentPage = 1;
                renderTable();
                showNotification('Filters Reset', 'All report filter options have been cleared.', 'info');
            });

            // Trigger dynamic exports
            window.triggerExport = function(type) {
                showNotification('Export Successful', `Exported report preview to ${type} format successfully. Check your downloads directory.`, 'success');
            };

            // Download / view recent reports
            window.triggerFileAction = function(fileName, action) {
                if (action === 'download') {
                    showNotification('Downloading File', `Starting download for ${fileName}...`, 'success');
                } else {
                    showNotification('Viewing Report', `Opening inline report preview for ${fileName}...`, 'info');
                }
            };

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
                } else if (type === 'info') {
                    toastIcon.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
                    toastIcon.style.color = '#3b82f6';
                    toastIcon.innerHTML = '<i class="fa-solid fa-info"></i>';
                }

                toastNotification.classList.add('show');
                toastTimeout = setTimeout(() => {
                    toastNotification.classList.remove('show');
                }, 4000);
            };

            // Escape HTML helper
            function escapeHTML(str) {
                if (!str) return '';
                return String(str).replace(/[&<>'"]/g, 
                    tag => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    }[tag] || tag)
                );
            }

            // --- CHART.JS CONFIGURATIONS ---
            const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
            const ctxCategory = document.getElementById('categoryChart').getContext('2d');

            // Set global font family
            Chart.defaults.font.family = "'Inter', sans-serif";

            // Monthly Borrowing Bar Chart
            const monthlyChart = new Chart(ctxMonthly, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Borrowings',
                        data: [65, 85, 120, 95, 150, 235],
                        backgroundColor: '#385585',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#f8fafc',
                            bodyColor: '#f8fafc',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { size: 10 } }
                        },
                        y: {
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                            ticks: { color: '#64748b', font: { size: 10 } }
                        }
                    }
                }
            });

            // Borrowing by Category Doughnut Chart
            const categoryChart = new Chart(ctxCategory, {
                type: 'doughnut',
                data: {
                    labels: ['Laptops', 'Projectors', 'Cameras', 'Lab Equip', 'Audio'],
                    datasets: [{
                        data: [40, 25, 15, 12, 8],
                        backgroundColor: ['#385585', '#4f46e5', '#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#64748b',
                                font: { size: 9 },
                                boxWidth: 8,
                                padding: 12
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#f8fafc',
                            bodyColor: '#f8fafc',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 8
                        }
                    }
                }
            });

            // Adjust colors of chart elements depending on active theme
            function updateChartsTheme() {
                const isDark = document.documentElement.classList.contains('dark-theme');
                const tickColor = isDark ? '#94a3b8' : '#64748b';
                const gridColor = isDark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.6)';

                // Monthly Chart Updates
                monthlyChart.options.scales.x.ticks.color = tickColor;
                monthlyChart.options.scales.y.ticks.color = tickColor;
                monthlyChart.options.scales.y.grid.color = gridColor;
                monthlyChart.data.datasets[0].backgroundColor = isDark ? '#60a5fa' : '#385585';
                monthlyChart.update();

                // Category Chart Updates
                categoryChart.options.plugins.legend.labels.color = tickColor;
                categoryChart.update();
            }

            // Initial chart color update on page load
            updateChartsTheme();

            // Render table initially
            renderTable();
        });
    </script>
</body>
</html>
