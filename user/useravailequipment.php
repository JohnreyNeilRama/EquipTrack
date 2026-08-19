<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Available Equipment</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <link rel="stylesheet" href="../ccs/global.css">
    <link rel="stylesheet" href="css/useravailequipment.css">
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
            <a href="useravailequipment.php" class="nav-item active">
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

        <div class="equipment-container">
            <!-- Categories Section -->
            <div class="categories-section">
                <h2 class="section-title">Categories</h2>
                <div class="categories-list">
                    <div class="category-card active" data-category="all">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <div class="cat-info">
                            <span class="cat-name">All</span>
                            <span class="cat-count">0</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="laptop">
                        <i class="fa-solid fa-laptop"></i>
                        <div class="cat-info">
                            <span class="cat-name">Laptops</span>
                            <span class="cat-count">0</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="projector">
                        <i class="fa-solid fa-video"></i>
                        <div class="cat-info">
                            <span class="cat-name">Projectors</span>
                            <span class="cat-count">0</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="camera">
                        <i class="fa-solid fa-flask"></i>
                        <div class="cat-info">
                            <span class="cat-name">Lab Equipment</span>
                            <span class="cat-count">0</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="audio">
                        <i class="fa-solid fa-music"></i>
                        <div class="cat-info">
                            <span class="cat-name">Audio Equipment</span>
                            <span class="cat-count">0</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="others">
                        <i class="fa-solid fa-box"></i>
                        <div class="cat-info">
                            <span class="cat-name">Others</span>
                            <span class="cat-count">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Equipments Section -->
            <div class="equipments-section">
                <div class="equipments-header">
                    <h2 class="section-title">Available Equipments</h2>
                    <div class="search-wrapper">
                        <input type="text" placeholder="Search equipment...">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    </div>
                </div>

                <div class="equipment-grid" id="equipmentGrid">
                    <div class="empty-state-card" style="grid-column: 1 / -1; text-align: center; padding: 48px 24px; color: var(--text-muted, #64748b);">
                        <i class="fa-solid fa-box-open" style="font-size: 36px; margin-bottom: 12px; opacity: 0.5; display: block;"></i>
                        <h4 style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">No available equipment</h4>
                        <p style="font-size: 13px;">Equipment items will appear here once added to the system inventory.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Borrow Request Modal -->
        <div class="modal-overlay" id="borrowModal">
            <div class="modal-card borrow-modal-card">
                <div class="modal-outer-header">
                    <p class="modal-subtitle-top">Fill in the details to borrow equipment</p>
                </div>
                <div class="modal-inner-card">
                    <button class="modal-close" id="closeModalBtn">&times;</button>
                    <h3 class="modal-title-center">Request Equipment</h3>
                    
                    <form id="borrowForm" class="new-modal-form">
                        <div class="form-main-content">
                            <!-- Left Side: Image Preview -->
                            <div class="form-left-img">
                                <img src="" alt="" id="modalEqImg">
                            </div>
                            
                            <!-- Right Side: Details -->
                            <div class="form-right-fields">
                                <div class="form-horizontal-group">
                                    <label class="flat-label">Name:</label>
                                    <input type="text" id="modalEqName" class="flat-control" readonly>
                                </div>
                                <div class="form-horizontal-group">
                                    <label class="flat-label">Available:</label>
                                    <input type="text" id="modalEqAvailable" class="flat-control" readonly>
                                </div>
                                <div class="form-horizontal-group">
                                    <label class="flat-label">Category:</label>
                                    <input type="text" id="modalEqCategory" class="flat-control" readonly>
                                </div>
                                <div class="form-horizontal-group">
                                    <label class="flat-label">Borrow Date:</label>
                                    <input type="date" id="borrowDate" class="flat-control" required>
                                </div>
                                <div class="form-horizontal-group">
                                    <label class="flat-label">Return Date:</label>
                                    <input type="date" id="returnDate" class="flat-control" required>
                                </div>
                            </div>
                        </div>

                        <!-- Lower section: Purpose and Notes -->
                        <div class="form-lower-section">
                            <div class="form-horizontal-group align-start">
                                <label class="flat-label">Purpose:</label>
                                <div class="flat-select-wrapper">
                                    <select id="borrowPurpose" class="flat-control flat-select" required>
                                        <option value="" disabled selected>Select your purpose...</option>
                                        <option value="Class Project / Presentation">Class Project / Presentation</option>
                                        <option value="Laboratory Activity">Laboratory Activity</option>
                                        <option value="Research & Development">Research & Development</option>
                                        <option value="School Event / Organization">School Event / Organization</option>
                                        <option value="Personal Study">Personal Study</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-horizontal-group align-start">
                                <label class="flat-label">Notes:</label>
                                <textarea id="borrowNotes" class="flat-control flat-textarea" rows="3" placeholder="Additional notes or instructions (optional)..."></textarea>
                            </div>
                        </div>

                        <!-- Warning Message -->
                        <div class="form-warning">
                            <i class="fa-solid fa-triangle-exclamation warning-icon"></i>
                            <span>Please return the equipment on time to avoid penalties.</span>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-submit-container">
                            <button type="submit" class="btn-submit-request">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categoryCards = document.querySelectorAll('.category-card');
            const equipmentCards = document.querySelectorAll('.equipment-card');
            const searchInput = document.querySelector('.search-wrapper input');

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

            function filterItems() {
                const activeCard = document.querySelector('.category-card.active');
                const selectedCategory = activeCard ? activeCard.getAttribute('data-category') : 'all';
                const searchTerm = searchInput.value.toLowerCase().trim();

                equipmentCards.forEach(card => {
                    const cardCategory = card.getAttribute('data-category');
                    const cardName = card.querySelector('.eq-name').textContent.toLowerCase();

                    const matchesCategory = (selectedCategory === 'all' || cardCategory === selectedCategory);
                    const matchesSearch = cardName.includes(searchTerm);

                    if (matchesCategory && matchesSearch) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            categoryCards.forEach(card => {
                card.addEventListener('click', () => {
                    categoryCards.forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    filterItems();
                });
            });

            searchInput.addEventListener('input', filterItems);

            // Modal Interactions
            const modal = document.getElementById('borrowModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const borrowForm = document.getElementById('borrowForm');
            const borrowDateInput = document.getElementById('borrowDate');
            const returnDateInput = document.getElementById('returnDate');

            // Set minimum dates
            const today = new Date().toISOString().split('T')[0];
            borrowDateInput.min = today;
            borrowDateInput.value = today;

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            returnDateInput.min = tomorrow.toISOString().split('T')[0];

            // Adjust return date min dynamically based on borrow date
            borrowDateInput.addEventListener('change', () => {
                const selectedBorrowDate = new Date(borrowDateInput.value);
                selectedBorrowDate.setDate(selectedBorrowDate.getDate() + 1);
                returnDateInput.min = selectedBorrowDate.toISOString().split('T')[0];
                if (returnDateInput.value && returnDateInput.value < returnDateInput.min) {
                    returnDateInput.value = returnDateInput.min;
                }
            });

            equipmentCards.forEach(card => {
                const requestBtn = card.querySelector('.btn-request');
                requestBtn.addEventListener('click', () => {
                    // Get equipment details
                    const eqName = card.querySelector('.eq-name').textContent;
                    const eqCategory = card.querySelector('.eq-category').textContent.trim();
                    const eqAvailableText = card.querySelector('.eq-available').textContent.trim();
                    const eqImgSrc = card.querySelector('.eq-img-container img').src;
                    const maxAvailable = parseInt(eqAvailableText.replace(/[^0-9]/g, ''), 10);

                    // Populate Modal Fields (inputs)
                    document.getElementById('modalEqName').value = eqName;
                    document.getElementById('modalEqCategory').value = eqCategory;
                    document.getElementById('modalEqAvailable').value = maxAvailable;
                    
                    const modalImg = document.getElementById('modalEqImg');
                    modalImg.src = eqImgSrc;
                    modalImg.alt = eqName;

                    // Reset date fields to default
                    borrowDateInput.value = today;
                    returnDateInput.value = "";
                    returnDateInput.min = tomorrow.toISOString().split('T')[0];

                    // Show Modal
                    modal.classList.add('show');
                });
            });

            // Close modal functions
            const closeModal = () => {
                modal.classList.remove('show');
                borrowForm.reset();
            };

            closeModalBtn.addEventListener('click', closeModal);

            // Close modal when clicking outside the card
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Handle Borrow Form Submission
            borrowForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const borrowDate = borrowDateInput.value;
                const returnDate = returnDateInput.value;
                const purpose = document.getElementById('borrowPurpose').value;
                const notes = document.getElementById('borrowNotes').value;
                const eqName = document.getElementById('modalEqName').value;

                // Professional mockup feedback
                alert(`Borrow Request Submitted Successfully!\n\nEquipment: ${eqName}\nBorrow Date: ${borrowDate}\nReturn Date: ${returnDate}\nPurpose: ${purpose}\nNotes: ${notes || 'None'}`);
                
                closeModal();
            });
        });
    </script>
</body>
</html>
