<?php
// EquipTrack — Department Personnel Dashboard
// Borrow Requests Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Borrow Requests</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Base Layout Stylesheet -->
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Page Stylesheet -->
    <link rel="stylesheet" href="css/requests.css">
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
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-box"></i> <span>Department Equipment</span>
            </a>
            <a href="requests.php" class="nav-item active">
                <i class="fa-solid fa-clipboard-list"></i> <span>Borrow Requests</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Department Users</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="monitoring.php" class="nav-item">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="history.php" class="nav-item">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Borrowing History</span>
            </a>

            <div class="sidebar-section-label">Account</div>
            <a href="profile.php" class="nav-item">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            <a href="../login.php" class="nav-item logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">CCS</div>
                    <span class="user-name">CCS</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">CCS Department</span>
                            <span class="header-email">ccs.dept@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../login.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
            <h2>Borrow Requests</h2>
            <p>Review and manage equipment borrowing requests for your department.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="requests-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Pending Request -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumPending">5</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Pending Request</span>
                </div>

                <!-- Card 2: Approved -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumApproved">5</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Approved</span>
                </div>

                <!-- Card 3: Rejected -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumRejected">3</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Rejected</span>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchRequests" placeholder="Search by user or Equipment...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterStatus">
                    <option value="all">Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterCategory">
                    <option value="all">Equipment Category</option>
                    <option value="laptop">Laptop</option>
                    <option value="camera">Camera</option>
                    <option value="audio equipment">Audio Equipment</option>
                    <option value="projector">Projector</option>
                    <option value="others">Others</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>

        <!-- Borrow Requests Data Table Card -->
        <div class="requests-table-container">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th style="width: 14%;">ID</th>
                        <th style="width: 18%;">User</th>
                        <th style="width: 14%;">Roles</th>
                        <th style="width: 18%;">Equipment</th>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 12%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-clipboard-list empty-state-icon"></i>
            <h4>No requests found</h4>
            <p>No borrow requests match your current search query or filter settings.</p>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- Interactivity & Data Logic -->
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
    </script>

    <!-- Table Management & Action Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('requestsTableBody');
            const searchInput = document.getElementById('searchRequests');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');
            const emptyStateContainer = document.getElementById('emptyStateContainer');

            // Summary metrics elements
            const sumPending = document.getElementById('sumPending');
            const sumApproved = document.getElementById('sumApproved');
            const sumRejected = document.getElementById('sumRejected');

            // Toast elements
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Default Requests matching reference mockup
            const defaultRequestsList = [
                { id: "21432132", user: "Gabriel F...", role: "Student", equipment: "Laptop Dell", category: "Laptop", date: "May 1", status: "Pending" },
                { id: "FAC-2023", user: "Jeff Gav...", role: "Faculty...", equipment: "Laptop Dell", category: "Laptop", date: "May 1", status: "Pending" },
                { id: "20230456", user: "Johnrey...", role: "Student", equipment: "Laptop Dell", category: "Laptop", date: "May 1", status: "Pending" },
                { id: "20230812", user: "Michael...", role: "Student", equipment: "Laptop Dell", category: "Laptop", date: "May 1", status: "Pending" },
                { id: "20230944", user: "Sarah Jenkins", role: "Faculty", equipment: "Camera Canon EOS", category: "Camera", date: "Apr 28", status: "Approved" },
                { id: "20230112", user: "David Miller", role: "Student", equipment: "Projector Epson", category: "Projector", date: "Apr 25", status: "Rejected" },
                { id: "20230554", user: "Elena Rostova", role: "Student", equipment: "Wireless Microphone Set", category: "Audio Equipment", date: "Apr 24", status: "Approved" },
                { id: "20230788", user: "Mark Anthony", role: "Faculty", equipment: "Scientific Calculator", category: "Others", date: "Apr 20", status: "Approved" }
            ];

            let requests = JSON.parse(localStorage.getItem('equip-track-borrow-requests'));
            if (!requests || !Array.isArray(requests) || requests.length === 0) {
                requests = defaultRequestsList;
                localStorage.setItem('equip-track-borrow-requests', JSON.stringify(requests));
            }

            function updateSummaryCards() {
                const pendingCount = requests.filter(r => r.status === 'Pending').length;
                const approvedCount = requests.filter(r => r.status === 'Approved').length;
                const rejectedCount = requests.filter(r => r.status === 'Rejected').length;

                sumPending.textContent = pendingCount;
                sumApproved.textContent = approvedCount;
                sumRejected.textContent = rejectedCount;
            }

            function renderTable() {
                tableBody.innerHTML = '';
                
                requests.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-status', item.status.toLowerCase());
                    tr.setAttribute('data-category', (item.category || '').toLowerCase());
                    tr.setAttribute('data-search', `${item.id} ${item.user} ${item.equipment}`.toLowerCase());

                    let actionHtml = '';
                    if (item.status === 'Pending') {
                        actionHtml = `
                            <div class="action-buttons-cell">
                                <button class="btn-action-approve" onclick="updateRequestStatus('${item.id}', 'Approved')">Approve</button>
                                <button class="btn-action-reject" onclick="updateRequestStatus('${item.id}', 'Rejected')">Reject</button>
                            </div>
                        `;
                    } else if (item.status === 'Approved') {
                        actionHtml = `<span class="status-badge approved"><i class="fa-solid fa-circle-check"></i> Approved</span>`;
                    } else {
                        actionHtml = `<span class="status-badge rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>`;
                    }

                    let statusBadgeHtml = '';
                    if (item.status === 'Pending') {
                        statusBadgeHtml = `<span class="status-badge pending">Pending</span>`;
                    } else if (item.status === 'Approved') {
                        statusBadgeHtml = `<span class="status-badge approved">Approved</span>`;
                    } else {
                        statusBadgeHtml = `<span class="status-badge rejected">Rejected</span>`;
                    }

                    tr.innerHTML = `
                        <td class="col-id">${escapeHTML(item.id)}</td>
                        <td class="col-user">${escapeHTML(item.user)}</td>
                        <td class="col-roles">${escapeHTML(item.role)}</td>
                        <td>${escapeHTML(item.equipment)}</td>
                        <td>${escapeHTML(item.date)}</td>
                        <td>${statusBadgeHtml}</td>
                        <td>${actionHtml}</td>
                    `;

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();
                filterTable();
            }

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

            window.updateRequestStatus = function(id, newStatus) {
                const req = requests.find(r => r.id === id);
                if (req) {
                    req.status = newStatus;
                    localStorage.setItem('equip-track-borrow-requests', JSON.stringify(requests));
                    
                    if (newStatus === 'Approved') {
                        showNotification('Request Approved', `Approved borrow request for ${req.user}.`, 'success');
                    } else {
                        showNotification('Request Rejected', `Rejected borrow request for ${req.user}.`, 'error');
                    }

                    renderTable();
                }
            };

            function filterTable() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = filterStatusSelect.value.toLowerCase();
                const selectedCategory = filterCategorySelect.value.toLowerCase();

                const rows = tableBody.querySelectorAll('tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search');
                    const rowStatus = row.getAttribute('data-status');
                    const rowCategory = row.getAttribute('data-category');

                    const matchesSearch = searchData.includes(query);
                    const matchesStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
                    const matchesCategory = (selectedCategory === 'all' || rowCategory.includes(selectedCategory));

                    if (matchesSearch && matchesStatus && matchesCategory) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            function showNotification(title, message, type = 'success') {
                toastTitle.textContent = title;
                toastMsg.textContent = message;
                
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

            searchInput.addEventListener('input', filterTable);
            filterStatusSelect.addEventListener('change', filterTable);
            filterCategorySelect.addEventListener('change', filterTable);

            // Initial render
            renderTable();
        });
    </script>
</body>
</html>
