<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - My Requests</title>
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <link rel="stylesheet" href="../ccs/userrequests.css">
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
                    <tr class="request-row" 
                        data-equipment="Laptop" 
                        data-category="Gadgets / Devices"
                        data-req-date="May 1, 2026"
                        data-borrow-date="May 2, 2026"
                        data-due-date="May 5, 2026"
                        data-status="Pending"
                        data-purpose="Class Project / Presentation"
                        data-notes="Required for Software Engineering presentation and demonstration."
                        data-img="https://images.unsplash.com/photo-1496181130204-7552cc15745e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td>1</td>
                        <td>Laptop</td>
                        <td>May 1</td>
                        <td>May 2</td>
                        <td>May 5</td>
                        <td><span class="status-text status-pending">Pending</span></td>
                        <td><span class="action-placeholder">---</span></td>
                    </tr>
                    <tr class="request-row" 
                        data-equipment="Calculator" 
                        data-category="Others"
                        data-req-date="May 7, 2026"
                        data-borrow-date="May 8, 2026"
                        data-due-date="May 9, 2026"
                        data-status="Approved"
                        data-purpose="Laboratory Activity"
                        data-notes="Required for final engineering calculus exam."
                        data-img="https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td>2</td>
                        <td>Calculator</td>
                        <td>May 7</td>
                        <td>May 8</td>
                        <td>May 9</td>
                        <td><span class="status-text status-approved">Approved</span></td>
                        <td><button class="btn-view-request" onclick="openRequestDetails(this)">View</button></td>
                    </tr>
                    <tr class="request-row" 
                        data-equipment="Projector" 
                        data-category="Gadgets / Devices"
                        data-req-date="May 11, 2026"
                        data-borrow-date="May 11, 2026"
                        data-due-date="May 13, 2026"
                        data-status="Rejected"
                        data-purpose="School Event / Organization"
                        data-notes="Student organization general assembly presentation."
                        data-img="https://images.unsplash.com/photo-1535016120720-40c646be5580?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60"
                        data-reject-reason="Requested equipment is currently reserved by another department for a priority event.">
                        <td>3</td>
                        <td>Projector</td>
                        <td>May 11</td>
                        <td>May 11</td>
                        <td>May 13</td>
                        <td><span class="status-text status-rejected">Rejected</span></td>
                        <td><button class="btn-view-request" onclick="openRequestDetails(this)">View</button></td>
                    </tr>
                    <tr class="request-row" 
                        data-equipment="Laptop" 
                        data-category="Gadgets / Devices"
                        data-req-date="May 1, 2026"
                        data-borrow-date="May 2, 2026"
                        data-due-date="May 5, 2026"
                        data-status="Pending"
                        data-purpose="Class Project / Presentation"
                        data-notes="Term project programming tasks."
                        data-img="https://images.unsplash.com/photo-1496181130204-7552cc15745e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td>1</td>
                        <td>Laptop</td>
                        <td>May 1</td>
                        <td>May 2</td>
                        <td>May 5</td>
                        <td><span class="status-text status-pending">Pending</span></td>
                        <td><span class="action-placeholder">---</span></td>
                    </tr>
                    <tr class="request-row" 
                        data-equipment="Calculator" 
                        data-category="Others"
                        data-req-date="May 7, 2026"
                        data-borrow-date="May 8, 2026"
                        data-due-date="May 9, 2026"
                        data-status="Approved"
                        data-purpose="Laboratory Activity"
                        data-notes="Midterm calculus assessment."
                        data-img="https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td>2</td>
                        <td>Calculator</td>
                        <td>May 7</td>
                        <td>May 8</td>
                        <td>May 9</td>
                        <td><span class="status-text status-approved">Approved</span></td>
                        <td><button class="btn-view-request" onclick="openRequestDetails(this)">View</button></td>
                    </tr>
                    <tr class="request-row" 
                        data-equipment="Projector" 
                        data-category="Gadgets / Devices"
                        data-req-date="May 11, 2026"
                        data-borrow-date="May 11, 2026"
                        data-due-date="May 13, 2026"
                        data-status="Rejected"
                        data-purpose="School Event / Organization"
                        data-notes="Org general assembly."
                        data-img="https://images.unsplash.com/photo-1535016120720-40c646be5580?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60"
                        data-reject-reason="Required items are under scheduled maintenance.">
                        <td>3</td>
                        <td>Projector</td>
                        <td>May 11</td>
                        <td>May 11</td>
                        <td>May 13</td>
                        <td><span class="status-text status-rejected">Rejected</span></td>
                        <td><button class="btn-view-request" onclick="openRequestDetails(this)">View</button></td>
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
