<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Return Item</title>
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <link rel="stylesheet" href="../ccs/userreturns.css">
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
            <a href="userreturns.php" class="nav-item active">
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

        <!-- Page Subtitle Header -->
        <h3 class="manage-return-header">Manage and return your borrowed equipment</h3>

        <!-- Search Bar Aligned Right -->
        <div class="search-row-right">
            <div class="search-wrapper">
                <input type="text" id="searchReturns" placeholder="Search equipment..." autocomplete="off">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container card returns-table-card">
            <table>
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Equipment</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody id="returnsTableBody">
                    <tr class="return-row" 
                        data-equipment="Laptop"
                        data-category="Gadgets / Devices"
                        data-borrow-date="May 2, 2026"
                        data-due-date="May 5, 2026"
                        data-img="https://images.unsplash.com/photo-1496181130204-7552cc15745e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td class="row-index">1</td>
                        <td class="col-eq">Laptop</td>
                        <td>May 2</td>
                        <td>May 5</td>
                        <td><span class="status-text status-borrowed">Borrowed</span></td>
                        <td><button class="btn-return-action" onclick="openReturnModal(this)">Return</button></td>
                    </tr>
                    <tr class="return-row" 
                        data-equipment="Calculator"
                        data-category="Others"
                        data-borrow-date="May 8, 2026"
                        data-due-date="May 9, 2026"
                        data-img="https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td class="row-index">2</td>
                        <td class="col-eq">Calculator</td>
                        <td>May 8</td>
                        <td>May 9</td>
                        <td><span class="status-text status-borrowed">Borrowed</span></td>
                        <td><button class="btn-return-action" onclick="openReturnModal(this)">Return</button></td>
                    </tr>
                    <tr class="return-row" 
                        data-equipment="Projector"
                        data-category="Gadgets / Devices"
                        data-borrow-date="May 11, 2026"
                        data-due-date="May 11, 2026"
                        data-img="https://images.unsplash.com/photo-1535016120720-40c646be5580?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60">
                        <td class="row-index">3</td>
                        <td class="col-eq">Projector</td>
                        <td>May 11</td>
                        <td>May 11</td>
                        <td><span class="status-text status-borrowed">Borrowed</span></td>
                        <td><button class="btn-return-action" onclick="openReturnModal(this)">Return</button></td>
                    </tr>
                </tbody>
            </table>
            <!-- Empty state illustration if all items returned -->
            <div id="emptyState" class="empty-state-container" style="display: none;">
                <i class="fa-solid fa-circle-check empty-state-icon"></i>
                <h4>All items returned!</h4>
                <p>You currently do not have any borrowed equipment to return.</p>
            </div>
        </div>
    </main>

    <!-- Return Modal -->
    <div class="modal-overlay" id="returnModal">
        <div class="modal-card return-modal-card">
            <div class="modal-inner-card">
                <button class="modal-close" id="closeReturnBtn">&times;</button>
                
                <h3 class="modal-title-center">Return Equipment</h3>
                <p class="modal-subtitle-center">Verify return details and specify the item condition</p>
                
                <form id="returnForm" onsubmit="submitReturn(event)">
                    <div class="detail-main-content">
                        <!-- Left Side: Image Preview & Condition Select -->
                        <div class="detail-left-side">
                            <div class="detail-img-container">
                                <img src="" alt="Equipment Image" id="returnEqImg">
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Item Condition</label>
                                <div class="select-wrapper">
                                    <select id="returnCondition" class="detail-form-control select-control" required>
                                        <option value="Good">Good / Working</option>
                                        <option value="Damaged">Damaged</option>
                                        <option value="Lost">Lost</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side: Details Form Grid -->
                        <div class="detail-right-side">
                            <div class="detail-form-grid">
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Equipment Name</label>
                                    <input type="text" id="returnEqName" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Category</label>
                                    <input type="text" id="returnEqCategory" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Borrow Date</label>
                                    <input type="text" id="returnBorrowDate" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Due Date</label>
                                    <input type="text" id="returnDueDate" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Return Date</label>
                                    <input type="text" id="returnDateToday" class="detail-form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lower Section: Remarks -->
                    <div class="detail-lower-section">
                        <div class="detail-form-group">
                            <label class="detail-form-label">Remarks / Return Notes</label>
                            <textarea id="returnRemarks" class="detail-form-control textarea-control" placeholder="Add any comments on item condition, usage notes, or missing accessories..." rows="2"></textarea>
                        </div>
                        <div class="modal-form-actions">
                            <button type="button" class="btn-modal-cancel" id="cancelReturnBtn">Cancel</button>
                            <button type="submit" class="btn-modal-submit">Confirm Return</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Success Notification -->
    <div class="toast-notification" id="successToast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon"></i>
            <div class="toast-message">
                <span class="toast-title">Success</span>
                <span class="toast-desc" id="toastDescMsg">Equipment returned successfully!</span>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchReturns');
        const returnRows = document.querySelectorAll('.return-row');
        const returnModal = document.getElementById('returnModal');
        const closeReturnBtn = document.getElementById('closeReturnBtn');
        const cancelReturnBtn = document.getElementById('cancelReturnBtn');
        const successToast = document.getElementById('successToast');
        
        let activeRow = null;

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

        // Set return date to current local date
        const today = new Date();
        const formattedToday = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('returnDateToday').value = formattedToday;

        // Search filtering function
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            returnRows.forEach(row => {
                const equipment = row.getAttribute('data-equipment').toLowerCase();
                if (equipment.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const emptyState = document.getElementById('emptyState');
            const tableElement = document.querySelector('.returns-table-card table');
            if (visibleCount === 0 && query !== '') {
                emptyState.style.display = 'flex';
                tableElement.style.opacity = '0.3';
            } else {
                emptyState.style.display = 'none';
                tableElement.style.opacity = '1';
            }
        });

        // Open Modal and Populate Fields
        function openReturnModal(button) {
            activeRow = button.closest('.return-row');
            
            const eqName = activeRow.getAttribute('data-equipment');
            const category = activeRow.getAttribute('data-category');
            const borrowDate = activeRow.getAttribute('data-borrow-date');
            const dueDate = activeRow.getAttribute('data-due-date');
            const imgUrl = activeRow.getAttribute('data-img');

            document.getElementById('returnEqName').value = eqName;
            document.getElementById('returnEqCategory').value = category;
            document.getElementById('returnBorrowDate').value = borrowDate;
            document.getElementById('returnDueDate').value = dueDate;
            document.getElementById('returnRemarks').value = '';
            document.getElementById('returnCondition').value = 'Good';

            const imgElement = document.getElementById('returnEqImg');
            if (imgUrl) {
                imgElement.src = imgUrl;
                imgElement.style.display = 'block';
            } else {
                imgElement.style.display = 'none';
            }

            returnModal.classList.add('show');
        }

        // Close Modal Functions
        function closeModal() {
            returnModal.classList.remove('show');
            activeRow = null;
        }

        closeReturnBtn.addEventListener('click', closeModal);
        cancelReturnBtn.addEventListener('click', closeModal);

        returnModal.addEventListener('click', (e) => {
            if (e.target === returnModal) {
                closeModal();
            }
        });

        // Submit return request
        function submitReturn(event) {
            event.preventDefault();
            
            const eqName = document.getElementById('returnEqName').value;
            const condition = document.getElementById('returnCondition').value;

            // Close the modal
            closeModal();

            // Set toast text and show it
            document.getElementById('toastDescMsg').textContent = `Returned ${eqName} in ${condition} condition.`;
            successToast.classList.add('show');

            // Hide active row in table
            if (activeRow) {
                activeRow.style.transition = 'all 0.5s ease';
                activeRow.style.opacity = '0';
                activeRow.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    activeRow.remove();
                    reindexTable();
                }, 500);
            }

            // Hide Toast after 4 seconds
            setTimeout(() => {
                successToast.classList.remove('show');
            }, 4000);
        }

        // Recalculate Row Indices & Show Empty State if no rows left
        function reindexTable() {
            const remainingRows = document.querySelectorAll('.return-row');
            if (remainingRows.length === 0) {
                document.getElementById('emptyState').style.display = 'flex';
                document.querySelector('.returns-table-card table').style.display = 'none';
            } else {
                remainingRows.forEach((row, index) => {
                    row.querySelector('.row-index').textContent = index + 1;
                });
            }
        }
    </script>
</body>
</html>
