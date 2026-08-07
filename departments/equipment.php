<?php
// EquipTrack — Department Personnel Dashboard
// Department Equipment Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Department Equipment</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Base Layout Stylesheet -->
    <link rel="stylesheet" href="../ccs/userdashboard.css">
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Page Stylesheet -->
    <link rel="stylesheet" href="css/equipment.css">
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
            <a href="equipment.php" class="nav-item active">
                <i class="fa-solid fa-box"></i> <span>Department Equipment</span>
            </a>
            <a href="requests.php" class="nav-item">
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
            <h2>Department Equipment</h2>
            <p>Manage equipment assigned to your department.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="equipment-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Registered Equipment -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumRegistered">80</span>
                        <div class="summary-card-icon icon-gray">
                            <i class="fa-solid fa-laptop"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Registered Equipment</span>
                </div>

                <!-- Card 2: Ready for Borrowing -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumReady">50</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-hand-holding"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Ready for Borrowing</span>
                </div>

                <!-- Card 3: Currently Borrowed -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumBorrowed">23</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Currently Borrowed</span>
                </div>

                <!-- Card 4: Unavailable -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumUnavailable">7</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-ban"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Unavailable</span>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchEquipment" placeholder="Search equipment...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterCategory">
                    <option value="all">All Categories</option>
                    <option value="laptop">Laptop</option>
                    <option value="camera">Camera</option>
                    <option value="audio equipment">Audio Equipment</option>
                    <option value="projector">Projector</option>
                    <option value="others">Others</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterStatus">
                    <option value="all">Status</option>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <button class="btn-add-equipment-primary" id="addEquipmentBtn">
                <i class="fa-solid fa-plus"></i> Add Equipment
            </button>
        </div>

        <!-- Equipment Cards Grid -->
        <div class="equipment-grid" id="equipmentGrid">
            <!-- Dynamic rendering via JS -->
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-boxes-stacked empty-state-icon"></i>
            <h4>No equipment found</h4>
            <p>No inventory items match your current search query or category filter.</p>
        </div>
    </main>

    <!-- Equipment Modal (Add / Edit) -->
    <div class="modal-overlay" id="equipmentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top" id="modalSubtitle">Provide details for the inventory item</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeModalBtn">&times;</button>
                <h3 class="modal-title-center" id="modalTitle">Add New Equipment</h3>
                
                <form id="equipmentForm" class="new-modal-form" style="padding-top: 10px;">
                    <input type="hidden" id="editItemId" value="">
                    
                    <div class="form-group-flat">
                        <label>Equipment Name</label>
                        <input type="text" id="eqFormName" class="form-control-flat" required placeholder="e.g., Laptop Dell XPS">
                    </div>

                    <div class="form-group-flat">
                        <label>Category</label>
                        <div class="flat-select-wrapper" style="width: 100%;">
                            <select id="eqFormCategory" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                <option value="Laptop">Laptop</option>
                                <option value="Camera">Camera</option>
                                <option value="Audio Equipment">Audio Equipment</option>
                                <option value="Projector">Projector</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Equipment Image</label>
                        <div class="image-upload-wrapper" id="imageUploadWrapper">
                            <!-- Dropzone State -->
                            <div class="image-dropzone" id="imageDropzone">
                                <i class="fa-solid fa-cloud-arrow-up cloud-icon"></i>
                                <div class="dropzone-text">
                                    <p class="main-text">Drag and drop or <span class="browse-link">browse image</span></p>
                                    <p class="sub-text">PNG, JPG, JPEG, or WEBP up to 5MB</p>
                                </div>
                                <input type="file" id="imageFileInput" accept="image/*" class="file-input-hidden">
                            </div>

                            <!-- URL Input Alternative -->
                            <div class="image-url-alternative" id="imageUrlAlternative">
                                <span class="or-separator">or</span>
                                <div class="url-input-container">
                                    <input type="url" id="eqFormImg" class="form-control-flat" placeholder="Paste image URL here...">
                                    <button type="button" class="btn-apply-url" id="btnApplyUrl">Apply</button>
                                </div>
                            </div>

                            <!-- Image Preview State -->
                            <div class="image-preview-container" id="imagePreviewContainer" style="display: none;">
                                <img src="" alt="Preview" id="imagePreviewImg">
                                <div class="preview-actions-overlay">
                                    <button type="button" class="btn-preview-action replace" id="btnReplacePreview">
                                        <i class="fa-solid fa-arrows-rotate"></i> Change
                                    </button>
                                    <button type="button" class="btn-preview-action remove" id="btnRemovePreview">
                                        <i class="fa-solid fa-trash-can"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-flat">
                        <div class="form-group-flat">
                            <label>Available Quantity</label>
                            <input type="number" id="eqFormAvail" class="form-control-flat" min="0" required value="4">
                        </div>
                        <div class="form-group-flat">
                            <label>Total Quantity</label>
                            <input type="number" id="eqFormTotal" class="form-control-flat" min="0" required value="10">
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Status</label>
                        <div class="flat-select-wrapper" style="width: 100%;">
                            <select id="eqFormStatus" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-request" id="submitBtn">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Equipment Modal -->
    <div class="modal-overlay" id="viewEquipmentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Equipment Information</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeViewModalBtn">&times;</button>
                <h3 class="modal-title-center" id="viewModalTitle">Laptop Dell XPS</h3>
                
                <div class="view-details-card">
                    <div class="view-details-img">
                        <img src="" alt="Equipment Image" id="viewModalImg">
                    </div>

                    <div class="view-details-grid">
                        <div class="view-detail-item">
                            <span class="label">Category</span>
                            <span class="val" id="viewModalCategory">Laptop</span>
                        </div>
                        <div class="view-detail-item">
                            <span class="label">Status</span>
                            <span class="val" id="viewModalStatus">Available</span>
                        </div>
                        <div class="view-detail-item">
                            <span class="label">Available Stock</span>
                            <span class="val" id="viewModalAvail">4</span>
                        </div>
                        <div class="view-detail-item">
                            <span class="label">Total Quantity</span>
                            <span class="val" id="viewModalTotal">10</span>
                        </div>
                        <div class="view-detail-item full">
                            <span class="label">Department Assignment</span>
                            <span class="val">College of Computer Studies (CCS)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Interactivity -->
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
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (sidebarScrim) sidebarScrim.classList.add('show');
        }
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (sidebarScrim) sidebarScrim.classList.remove('show');
        }

        if (topbarMenuBtn) topbarMenuBtn.addEventListener('click', openSidebar);
        if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
        if (sidebarScrim) sidebarScrim.addEventListener('click', closeSidebar);

        // Notifications
        const notifBtn = document.getElementById('notifBtn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                alert('No new notifications.');
            });
        }
    </script>

    <!-- Data Management & Interactive Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Search & Filters
            const searchInput = document.getElementById('searchEquipment');
            const filterCategorySelect = document.getElementById('filterCategory');
            const filterStatusSelect = document.getElementById('filterStatus');
            const grid = document.getElementById('equipmentGrid');
            const emptyStateContainer = document.getElementById('emptyStateContainer');

            // Summary Counters
            const sumRegistered = document.getElementById('sumRegistered');
            const sumReady = document.getElementById('sumReady');
            const sumBorrowed = document.getElementById('sumBorrowed');
            const sumUnavailable = document.getElementById('sumUnavailable');

            // Add/Edit Modal
            const modal = document.getElementById('equipmentModal');
            const addBtn = document.getElementById('addEquipmentBtn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const form = document.getElementById('equipmentForm');
            
            const modalTitle = document.getElementById('modalTitle');
            const modalSubtitle = document.getElementById('modalSubtitle');
            const submitBtn = document.getElementById('submitBtn');
            const editItemIdInput = document.getElementById('editItemId');
            
            const eqNameField = document.getElementById('eqFormName');
            const eqCategoryField = document.getElementById('eqFormCategory');
            const eqImgField = document.getElementById('eqFormImg');
            const eqAvailField = document.getElementById('eqFormAvail');
            const eqTotalField = document.getElementById('eqFormTotal');
            const eqStatusField = document.getElementById('eqFormStatus');

            // Image Upload Components
            const imageDropzone = document.getElementById('imageDropzone');
            const imageFileInput = document.getElementById('imageFileInput');
            const imageUrlAlternative = document.getElementById('imageUrlAlternative');
            const btnApplyUrl = document.getElementById('btnApplyUrl');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const imagePreviewImg = document.getElementById('imagePreviewImg');
            const btnReplacePreview = document.getElementById('btnReplacePreview');
            const btnRemovePreview = document.getElementById('btnRemovePreview');

            // View Modal
            const viewModal = document.getElementById('viewEquipmentModal');
            const closeViewModalBtn = document.getElementById('closeViewModalBtn');
            const viewModalTitle = document.getElementById('viewModalTitle');
            const viewModalImg = document.getElementById('viewModalImg');
            const viewModalCategory = document.getElementById('viewModalCategory');
            const viewModalStatus = document.getElementById('viewModalStatus');
            const viewModalAvail = document.getElementById('viewModalAvail');
            const viewModalTotal = document.getElementById('viewModalTotal');

            // Toast
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Default Equipment List
            const defaultEquipmentList = [
                { id: 1, name: "Laptop Dell XPS", category: "Laptop", imgUrl: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 4, total: 10, status: "Available" },
                { id: 2, name: "Camera Canon EOS", category: "Camera", imgUrl: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 5, total: 10, status: "Available" },
                { id: 3, name: "Projector Epson", category: "Projector", imgUrl: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 10, total: 15, status: "Available" },
                { id: 4, name: "Wireless Microphone Set", category: "Audio Equipment", imgUrl: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 10, total: 20, status: "Available" },
                { id: 5, name: "Scientific Calculator", category: "Others", imgUrl: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 4, total: 10, status: "Available" },
                { id: 6, name: "Lenovo ThinkPad", category: "Laptop", imgUrl: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 5, total: 10, status: "Available" }
            ];

            let equipment = JSON.parse(localStorage.getItem('equip-track-equipment'));
            if (!equipment || !Array.isArray(equipment) || equipment.length === 0) {
                equipment = defaultEquipmentList;
                localStorage.setItem('equip-track-equipment', JSON.stringify(equipment));
            }

            let itemIdCounter = equipment.length > 0 ? Math.max(...equipment.map(e => e.id || 0)) + 1 : 1;

            // Recalculate summary card metrics
            function updateSummaryCards() {
                const totalRegistered = equipment.reduce((acc, curr) => acc + (parseInt(curr.total) || 0), 0);
                const totalReady = equipment.reduce((acc, curr) => acc + (parseInt(curr.available) || 0), 0);
                const totalBorrowed = totalRegistered - totalReady;
                const totalUnavailable = equipment.filter(e => e.status === 'Unavailable').length;

                sumRegistered.textContent = totalRegistered > 0 ? totalRegistered : 80;
                sumReady.textContent = totalReady > 0 ? totalReady : 50;
                sumBorrowed.textContent = totalBorrowed >= 0 ? totalBorrowed : 23;
                sumUnavailable.textContent = totalUnavailable > 0 ? totalUnavailable : 7;
            }

            // Render equipment cards
            function renderEquipment() {
                grid.innerHTML = '';
                equipment.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'eq-card-admin';
                    card.setAttribute('data-category', item.category.toLowerCase());
                    card.setAttribute('data-status', item.status.toLowerCase());
                    card.setAttribute('data-id', item.id);

                    const isContain = item.name.toLowerCase().includes('calculator') || item.name.toLowerCase().includes('microphone') || item.name.toLowerCase().includes('projector');

                    card.innerHTML = `
                        <div class="eq-card-img-wrapper">
                            <img src="${escapeHTML(item.imgUrl)}" alt="${escapeHTML(item.name)}" class="${isContain ? 'fit-contain' : ''}">
                        </div>
                        <div class="eq-card-details">
                            <h4 class="eq-card-title">${escapeHTML(item.name)}</h4>
                            <div class="eq-card-meta-line">
                                <span class="label">Category :</span>
                                <span class="value cat-val">${escapeHTML(item.category)}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Available :</span>
                                <span class="value avail-val">${escapeHTML(item.available)}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Total Quantity :</span>
                                <span class="value total-val">${escapeHTML(item.total)}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Status :</span>
                                <span class="value status-val">${escapeHTML(item.status)}</span>
                            </div>
                        </div>
                        <div class="eq-card-actions">
                            <button class="btn-card-edit" onclick="openEditModal(${item.id})">Edit</button>
                            <button class="btn-card-view" onclick="openViewModal(${item.id})">View</button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
                updateSummaryCards();
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

            // Image Preview Helpers
            function showPreview(url) {
                imagePreviewImg.src = url;
                eqImgField.value = url;
                imageDropzone.style.display = 'none';
                imageUrlAlternative.style.display = 'none';
                imagePreviewContainer.style.display = 'block';
            }

            function resetUploader() {
                imagePreviewImg.src = "";
                imageFileInput.value = "";
                eqImgField.value = "";
                imageDropzone.style.display = 'flex';
                imageUrlAlternative.style.display = 'flex';
                imagePreviewContainer.style.display = 'none';
            }

            function handleFile(file) {
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        showPreview(e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            }

            imageFileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                handleFile(file);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                imageDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    imageDropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                imageDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    imageDropzone.classList.remove('dragover');
                }, false);
            });

            imageDropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const file = dt.files[0];
                handleFile(file);
            }, false);

            btnApplyUrl.addEventListener('click', (e) => {
                e.preventDefault();
                const urlValue = eqImgField.value.trim();
                if (urlValue) {
                    showPreview(urlValue);
                } else {
                    showNotification('Empty URL', 'Please paste a valid image URL.', 'error');
                }
            });

            eqImgField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    btnApplyUrl.click();
                }
            });

            btnReplacePreview.addEventListener('click', () => {
                imageFileInput.click();
            });

            btnRemovePreview.addEventListener('click', () => {
                resetUploader();
            });

            // Filter execution
            function filterEquipment() {
                const query = searchInput.value.toLowerCase().trim();
                const category = filterCategorySelect.value.toLowerCase();
                const status = filterStatusSelect.value.toLowerCase();
                const cards = grid.querySelectorAll('.eq-card-admin');
                let visibleCount = 0;

                cards.forEach(card => {
                    const title = card.querySelector('.eq-card-title').textContent.toLowerCase();
                    const cardCategory = card.getAttribute('data-category').toLowerCase();
                    const cardStatus = card.getAttribute('data-status').toLowerCase();

                    const matchesSearch = title.includes(query);
                    const matchesCategory = (category === 'all' || cardCategory.includes(category));
                    const matchesStatus = (status === 'all' || cardStatus === status);

                    if (matchesSearch && matchesCategory && matchesStatus) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            searchInput.addEventListener('input', filterEquipment);
            filterCategorySelect.addEventListener('change', filterEquipment);
            filterStatusSelect.addEventListener('change', filterEquipment);

            // Toast Helper
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

            // Open Add Modal
            addBtn.addEventListener('click', () => {
                form.reset();
                editItemIdInput.value = "";
                modalTitle.textContent = "Add New Equipment";
                modalSubtitle.textContent = "Provide details for the inventory item";
                submitBtn.textContent = "Add Item";
                
                showPreview("https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60");
                modal.classList.add('show');
            });

            // Close Add/Edit Modal
            closeModalBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            function closeModal() {
                modal.classList.remove('show');
                form.reset();
            }

            // Open Edit Modal
            window.openEditModal = function(id) {
                const item = equipment.find(e => e.id == id);
                if (!item) return;

                editItemIdInput.value = item.id;
                eqNameField.value = item.name;
                eqCategoryField.value = item.category;
                eqAvailField.value = item.available;
                eqTotalField.value = item.total;
                eqStatusField.value = item.status;
                
                showPreview(item.imgUrl);

                modalTitle.textContent = "Edit Equipment";
                modalSubtitle.textContent = "Update inventory details for this item";
                submitBtn.textContent = "Save Changes";
                
                modal.classList.add('show');
            };

            // Open View Modal
            window.openViewModal = function(id) {
                const item = equipment.find(e => e.id == id);
                if (!item) return;

                viewModalTitle.textContent = item.name;
                viewModalImg.src = item.imgUrl;
                viewModalCategory.textContent = item.category;
                viewModalStatus.textContent = item.status;
                viewModalAvail.textContent = item.available;
                viewModalTotal.textContent = item.total;

                viewModal.classList.add('show');
            };

            closeViewModalBtn.addEventListener('click', () => {
                viewModal.classList.remove('show');
            });

            viewModal.addEventListener('click', (e) => {
                if (e.target === viewModal) {
                    viewModal.classList.remove('show');
                }
            });

            // Form Submit (Add / Edit)
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const id = editItemIdInput.value;
                const name = eqNameField.value.trim();
                const category = eqCategoryField.value;
                const imgUrl = eqImgField.value.trim();
                const available = parseInt(eqAvailField.value) || 0;
                const total = parseInt(eqTotalField.value) || 0;
                const status = eqStatusField.value;

                if (!imgUrl) {
                    showNotification('Image Required', 'Please upload an image or enter a URL first.', 'error');
                    return;
                }

                if (id) {
                    // Update item
                    const item = equipment.find(e => e.id == id);
                    if (item) {
                        item.name = name;
                        item.category = category;
                        item.imgUrl = imgUrl;
                        item.available = available;
                        item.total = total;
                        item.status = status;
                        
                        localStorage.setItem('equip-track-equipment', JSON.stringify(equipment));
                        showNotification('Updated Successfully', `Saved changes for ${name}.`, 'success');
                    }
                } else {
                    // Add item
                    const newItem = {
                        id: itemIdCounter++,
                        name: name,
                        category: category,
                        imgUrl: imgUrl,
                        available: available,
                        total: total,
                        status: status
                    };
                    equipment.push(newItem);
                    localStorage.setItem('equip-track-equipment', JSON.stringify(equipment));
                    showNotification('Added Successfully', `Registered ${name} in department equipment inventory.`, 'success');
                }

                closeModal();
                renderEquipment();
                filterEquipment();
            });

            // Initial render
            renderEquipment();
            filterEquipment();
        });
    </script>
</body>
</html>
