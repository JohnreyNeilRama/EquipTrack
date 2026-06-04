<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Borrowing History</title>
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <link rel="stylesheet" href="../ccs/userhistory.css">
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
            <a href="userrequests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>My Request</span>
            </a>
            <a href="userreturns.php" class="nav-item">
                <i class="fa-solid fa-check-double"></i> <span>Return Item</span>
            </a>
            <a href="userhistory.php" class="nav-item active">
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

        <!-- Page Subtitle Header -->
        <h3 class="history-header">View your past equipment transactions</h3>

        <!-- Search and Filters Bar -->
        <div class="filters-row">
            <!-- Search bar on the left -->
            <div class="search-wrapper">
                <input type="text" id="searchHistory" placeholder="Search equipment..." autocomplete="off">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
            </div>
            
            <!-- Select dropdowns on the right -->
            <div class="filters-right">
                <div class="filter-wrapper">
                    <select id="statusHistoryFilter" class="filter-select">
                        <option value="All">All</option>
                        <option value="Returned">Returned</option>
                        <option value="Late Return">Late Return</option>
                    </select>
                </div>
                <div class="filter-wrapper">
                    <select id="sortHistoryFilter" class="filter-select sort-select">
                        <option value="latest">Sort by: Latest</option>
                        <option value="oldest">Sort by: Oldest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container card history-table-card">
            <table>
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Equipment</th>
                        <th>Borrow Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <tr class="history-row" 
                        data-equipment="Laptop"
                        data-category="Gadgets / Devices"
                        data-borrow-date="May 2, 2026"
                        data-return-date="May 5, 2026"
                        data-due-date="May 5, 2026"
                        data-status="Returned"
                        data-remarks="On Time"
                        data-condition="Good / Working"
                        data-handled-by="Admin Alexis"
                        data-img="https://images.unsplash.com/photo-1496181130204-7552cc15745e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60"
                        data-timestamp="2026-05-02">
                        <td class="row-index">1</td>
                        <td class="col-eq">Laptop</td>
                        <td>May 2</td>
                        <td>May 5</td>
                        <td><span class="status-text status-returned">Returned</span></td>
                        <td class="col-remarks">On Time</td>
                    </tr>
                    <tr class="history-row" 
                        data-equipment="Calculator"
                        data-category="Others"
                        data-borrow-date="May 8, 2026"
                        data-return-date="May 11, 2026"
                        data-due-date="May 9, 2026"
                        data-status="Late Return"
                        data-remarks="Returned 2 days late"
                        data-condition="Good / Working"
                        data-handled-by="Admin Alexis"
                        data-img="https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60"
                        data-timestamp="2026-05-08">
                        <td class="row-index">2</td>
                        <td class="col-eq">Calculator</td>
                        <td>May 8</td>
                        <td>May 9</td>
                        <td><span class="status-text status-late">Late Return</span></td>
                        <td class="col-remarks">Returned 2 days late</td>
                    </tr>
                    <tr class="history-row" 
                        data-equipment="Projector"
                        data-category="Gadgets / Devices"
                        data-borrow-date="May 11, 2026"
                        data-return-date="May 11, 2026"
                        data-due-date="May 11, 2026"
                        data-status="Returned"
                        data-remarks="Good Condition"
                        data-condition="Good / Working"
                        data-handled-by="Admin Alexis"
                        data-img="https://images.unsplash.com/photo-1535016120720-40c646be5580?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60"
                        data-timestamp="2026-05-11">
                        <td class="row-index">3</td>
                        <td class="col-eq">Projector</td>
                        <td>May 11</td>
                        <td>May 11</td>
                        <td><span class="status-text status-returned">Returned</span></td>
                        <td class="col-remarks">Good Condition</td>
                    </tr>
                </tbody>
            </table>
            <!-- Empty state illustration if search yields nothing -->
            <div id="historyEmptyState" class="empty-state-container" style="display: none;">
                <i class="fa-solid fa-magnifying-glass empty-state-icon"></i>
                <h4>No transactions found</h4>
                <p>Try refining your search term or selection filters.</p>
            </div>
        </div>
    </main>

    <!-- Transaction Details Modal -->
    <div class="modal-overlay" id="historyModal">
        <div class="modal-card history-modal-card">
            <div class="modal-inner-card">
                <button class="modal-close" id="closeHistoryBtn">&times;</button>
                
                <h3 class="modal-title-center">Transaction Details</h3>
                <p class="modal-subtitle-center">Full historical log of this borrowed equipment</p>
                
                <div class="detail-main-content">
                    <!-- Left Side: Image Preview & Status -->
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="historyEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Return Status</label>
                            <div class="status-badge-wrapper">
                                <span class="detail-status-badge" id="historyStatusBadge">Returned</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Details Form Grid -->
                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="historyEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="historyEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="historyBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Return Date</label>
                                <input type="text" id="historyReturnDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Condition on Return</label>
                                <input type="text" id="historyCondition" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lower Section: Remarks & Handled By -->
                <div class="detail-lower-section">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Transaction Remarks</label>
                        <textarea id="historyRemarks" class="detail-form-control textarea-control" readonly rows="2"></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Received By</label>
                        <input type="text" id="historyHandledBy" class="detail-form-control" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchHistory');
        const statusFilter = document.getElementById('statusHistoryFilter');
        const sortFilter = document.getElementById('sortHistoryFilter');
        const tableBody = document.getElementById('historyTableBody');
        const historyModal = document.getElementById('historyModal');
        const closeHistoryBtn = document.getElementById('closeHistoryBtn');

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

        // Filter and Sort function
        function updateHistoryTable() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedStatus = statusFilter.value;
            const sortOrder = sortFilter.value;
            
            // Get all rows
            const rows = Array.from(tableBody.querySelectorAll('.history-row'));
            let visibleCount = 0;

            rows.forEach(row => {
                const equipment = row.getAttribute('data-equipment').toLowerCase();
                const status = row.getAttribute('data-status');

                const matchesSearch = equipment.includes(query);
                const matchesFilter = (selectedStatus === 'All' || status === selectedStatus);

                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Sort remaining rows
            rows.sort((a, b) => {
                const dateA = new Date(a.getAttribute('data-timestamp'));
                const dateB = new Date(b.getAttribute('data-timestamp'));
                return sortOrder === 'latest' ? dateB - dateA : dateA - dateB;
            });

            // Re-append sorted rows to table body
            rows.forEach(row => tableBody.appendChild(row));

            // Re-calculate visible indices
            let visibleIndex = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    row.querySelector('.row-index').textContent = visibleIndex++;
                }
            });

            // Empty state display
            const emptyState = document.getElementById('historyEmptyState');
            const tableElement = document.querySelector('.history-table-card table');
            if (visibleCount === 0) {
                emptyState.style.display = 'flex';
                tableElement.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                tableElement.style.display = 'table';
            }
        }

        // Attach listeners
        searchInput.addEventListener('input', updateHistoryTable);
        statusFilter.addEventListener('change', updateHistoryTable);
        sortFilter.addEventListener('change', updateHistoryTable);

        // Click Row Event for Details Modal
        const historyRows = document.querySelectorAll('.history-row');
        historyRows.forEach(row => {
            row.addEventListener('click', () => {
                const eqName = row.getAttribute('data-equipment');
                const category = row.getAttribute('data-category');
                const borrowDate = row.getAttribute('data-borrow-date');
                const returnDate = row.getAttribute('data-return-date');
                const status = row.getAttribute('data-status');
                const remarks = row.getAttribute('data-remarks');
                const condition = row.getAttribute('data-condition');
                const handledBy = row.getAttribute('data-handled-by');
                const imgUrl = row.getAttribute('data-img');

                document.getElementById('historyEqName').value = eqName;
                document.getElementById('historyEqCategory').value = category;
                document.getElementById('historyBorrowDate').value = borrowDate;
                document.getElementById('historyReturnDate').value = returnDate;
                document.getElementById('historyCondition').value = condition;
                document.getElementById('historyRemarks').value = remarks;
                document.getElementById('historyHandledBy').value = handledBy;

                const imgElement = document.getElementById('historyEqImg');
                if (imgUrl) {
                    imgElement.src = imgUrl;
                    imgElement.style.display = 'block';
                } else {
                    imgElement.style.display = 'none';
                }

                const badge = document.getElementById('historyStatusBadge');
                badge.textContent = status;
                badge.className = 'detail-status-badge'; // Reset class
                badge.classList.add('status-' + (status === 'Late Return' ? 'rejected' : 'approved'));

                historyModal.classList.add('show');
            });
        });

        // Close functions
        function closeHistoryModal() {
            historyModal.classList.remove('show');
        }

        closeHistoryBtn.addEventListener('click', closeHistoryModal);
        historyModal.addEventListener('click', (e) => {
            if (e.target === historyModal) {
                closeHistoryModal();
            }
        });
    </script>
</body>
</html>
