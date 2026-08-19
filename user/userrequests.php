<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - My Requests</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <link rel="stylesheet" href="../ccs/global.css">
    <link rel="stylesheet" href="css/userrequests.css">
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
            <a href="userprofile.php" class="nav-item">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            <div class="sidebar-section-label">Equipment</div>
            <a href="useravailequipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Available Equipment</span>
            </a>
            <a href="userrequests.php" class="nav-item active">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">US</div>
                    <span class="user-name">User</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">User</span>
                            <span class="header-email">user@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="userprofile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../login.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Subtitle (Header in mockup) -->
        <h3 class="track-status-header">Track the status of your equipment requests</h3>

        <!-- Search and Filter Row -->
        <div class="search-filter-row">
            <div class="search-wrapper">
                <input type="text" id="searchRequests" placeholder="Search equipment..." autocomplete="off">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
            </div>
            <div class="filter-wrapper">
                <select id="statusFilter" class="filter-select">
                    <option value="All">All</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container card requests-table-card">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Equipment</th>
                        <th>Request Date</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                            No equipment requests found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Request Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card details-modal-card">
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>
                
                <h3 class="modal-title-center">Request Details</h3>
                <p class="modal-subtitle-center">Detailed view of your borrowing request</p>
                
                <div class="detail-main-content">
                    <!-- Left Side: Image Preview & Status -->
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="detailEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Request Status</label>
                            <div class="status-badge-wrapper">
                                <span class="detail-status-badge" id="detailStatusBadge">Approved</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Text Details Form Grid -->
                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="detailEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="detailEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Request Date</label>
                                <input type="text" id="detailReqDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="detailBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group full-width">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="detailDueDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lower Section: Purpose & Notes -->
                <div class="detail-lower-section">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Borrowing Purpose</label>
                        <textarea id="detailPurpose" class="detail-form-control textarea-control" readonly rows="2"></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Additional Notes</label>
                        <textarea id="detailNotes" class="detail-form-control textarea-control" readonly rows="2"></textarea>
                    </div>
                    <div class="detail-form-group" id="detailReasonGroup" style="display: none;">
                        <label class="detail-form-label text-danger">Reason for Rejection</label>
                        <textarea id="detailReason" class="detail-form-control textarea-control text-danger-control" readonly rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Live Search, Status Filtering, and Modal Detail View -->
    <script>
        const searchInput = document.getElementById('searchRequests');
        const statusFilter = document.getElementById('statusFilter');
        const requestRows = document.querySelectorAll('.request-row');
        const detailsModal = document.getElementById('detailsModal');
        const closeDetailsBtn = document.getElementById('closeDetailsBtn');

        // Dark Mode Toggle Logic
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

        // Function to filter requests in real-time
        function filterRequests() {
            const query = searchInput.value.toLowerCase().trim();
            const filterVal = statusFilter.value;

            requestRows.forEach(row => {
                const equipment = row.getAttribute('data-equipment').toLowerCase();
                const status = row.getAttribute('data-status');

                const matchesSearch = equipment.includes(query);
                const matchesFilter = (filterVal === 'All' || status === filterVal);

                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Function to open request details modal
        function openRequestDetails(button) {
            const row = button.closest('.request-row');
            
            // Extract data attributes
            const eqName = row.getAttribute('data-equipment');
            const eqCategory = row.getAttribute('data-category');
            const reqDate = row.getAttribute('data-req-date');
            const borrowDate = row.getAttribute('data-borrow-date');
            const dueDate = row.getAttribute('data-due-date');
            const status = row.getAttribute('data-status');
            const purpose = row.getAttribute('data-purpose');
            const notes = row.getAttribute('data-notes') || 'N/A';
            const imgUrl = row.getAttribute('data-img');
            const rejectReason = row.getAttribute('data-reject-reason');

            // Populate fields
            document.getElementById('detailEqName').value = eqName;
            document.getElementById('detailEqCategory').value = eqCategory;
            document.getElementById('detailReqDate').value = reqDate;
            document.getElementById('detailBorrowDate').value = borrowDate;
            document.getElementById('detailDueDate').value = dueDate;
            document.getElementById('detailPurpose').value = purpose;
            document.getElementById('detailNotes').value = notes;
            
            // Set image
            const imgElement = document.getElementById('detailEqImg');
            if (imgUrl) {
                imgElement.src = imgUrl;
                imgElement.style.display = 'block';
            } else {
                imgElement.style.display = 'none';
            }

            // Set status badge style
            const badge = document.getElementById('detailStatusBadge');
            badge.textContent = status;
            badge.className = 'detail-status-badge'; // Reset class
            badge.classList.add('status-' + status.toLowerCase());

            // Handle rejection reason display
            const reasonGroup = document.getElementById('detailReasonGroup');
            if (status === 'Rejected' && rejectReason) {
                document.getElementById('detailReason').value = rejectReason;
                reasonGroup.style.display = 'block';
            } else {
                reasonGroup.style.display = 'none';
            }

            // Show modal
            detailsModal.classList.add('show');
        }

        // Close modal
        closeDetailsBtn.addEventListener('click', () => {
            detailsModal.classList.remove('show');
        });

        // Close modal on background click
        detailsModal.addEventListener('click', (e) => {
            if (e.target === detailsModal) {
                detailsModal.classList.remove('show');
            }
        });

        searchInput.addEventListener('input', filterRequests);
        statusFilter.addEventListener('change', filterRequests);
    </script>
</body>
</html>
