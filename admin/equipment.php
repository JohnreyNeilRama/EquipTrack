<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Equipment Management</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminequipment.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
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
            <a href="equipment.php" class="nav-item active">
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

        <div class="equipment-container">
            <!-- Header section -->
            <div class="equipment-header-section">
                <h2>Equipment Management</h2>
                <p>Manage and monitor all school equipment inventory</p>
            </div>

            <!-- Controls bar -->
            <div class="controls-bar">
                <div class="controls-left">
                    <div class="search-box-wrapper">
                        <input type="text" id="searchEquipment" placeholder="Search equipment...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="filter-select-wrapper">
                        <select id="filterCategory">
                            <option value="all">All</option>
                            <option value="laptop">Laptop</option>
                            <option value="camera">Camera</option>
                            <option value="audio">Audio</option>
                            <option value="projector">Projector</option>
                            <option value="others">Others</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
                <button class="btn-add-equipment" id="addEquipmentBtn">
                    <i class="fa-solid fa-plus"></i> Add Equipment
                </button>
            </div>

            <!-- Equipment cards grid -->
            <div class="equipment-grid" id="equipmentGrid">
                <!-- Loaded dynamically via JavaScript -->
            </div>
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
                                <option value="Audio">Audio</option>
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
                            <input type="number" id="eqFormAvail" class="form-control-flat" min="0" required value="5">
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

    <!-- Scripting for Interactivity -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // DOM Elements
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            // Search & Filter
            const searchInput = document.getElementById('searchEquipment');
            const filterSelect = document.getElementById('filterCategory');
            const grid = document.getElementById('equipmentGrid');

            // Modal
            const modal = document.getElementById('equipmentModal');
            const addBtn = document.getElementById('addEquipmentBtn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const form = document.getElementById('equipmentForm');
            
            const modalTitle = document.getElementById('modalTitle');
            const modalSubtitle = document.getElementById('modalSubtitle');
            const submitBtn = document.getElementById('submitBtn');
            const editItemIdInput = document.getElementById('editItemId');
            
            // Form Fields
            const eqNameField = document.getElementById('eqFormName');
            const eqCategoryField = document.getElementById('eqFormCategory');
            const eqImgField = document.getElementById('eqFormImg');
            const eqAvailField = document.getElementById('eqFormAvail');
            const eqTotalField = document.getElementById('eqFormTotal');
            const eqStatusField = document.getElementById('eqFormStatus');

            // Uploader Elements
            const imageDropzone = document.getElementById('imageDropzone');
            const imageFileInput = document.getElementById('imageFileInput');
            const imageUrlAlternative = document.getElementById('imageUrlAlternative');
            const btnApplyUrl = document.getElementById('btnApplyUrl');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const imagePreviewImg = document.getElementById('imagePreviewImg');
            const btnReplacePreview = document.getElementById('btnReplacePreview');
            const btnRemovePreview = document.getElementById('btnRemovePreview');

            // Toast
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Global state variable for tracking items loaded from localStorage
            const defaultEquipmentList = [
                { id: 1, name: "Laptop Dell XPS", category: "Laptop", imgUrl: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 4, total: 10, status: "Available" },
                { id: 2, name: "Camera Canon EOS", category: "Camera", imgUrl: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 5, total: 10, status: "Available" },
                { id: 3, name: "Wireless Microphone Set", category: "Audio", imgUrl: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 10, total: 10, status: "Available" },
                { id: 4, name: "Lenovo ThinkPad", category: "Laptop", imgUrl: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 2, total: 5, status: "Available" },
                { id: 5, name: "Projector Epson", category: "Projector", imgUrl: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 10, total: 10, status: "Available" },
                { id: 6, name: "Scientific Calculator", category: "Others", imgUrl: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60", available: 4, total: 5, status: "Available" }
            ];

            let equipment = JSON.parse(localStorage.getItem('equip-track-equipment'));
            if (!equipment) {
                equipment = defaultEquipmentList;
                localStorage.setItem('equip-track-equipment', JSON.stringify(equipment));
            }

            let itemIdCounter = equipment.length > 0 ? Math.max(...equipment.map(e => e.id)) + 1 : 1;

            // Render equipment cards dynamically
            function renderEquipment() {
                grid.innerHTML = '';
                equipment.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'eq-card-admin';
                    card.setAttribute('data-category', item.category.toLowerCase());
                    card.setAttribute('data-id', item.id);
                    card.innerHTML = `
                        <div class="eq-card-img-wrapper">
                            <img src="${escapeHTML(item.imgUrl)}" alt="${escapeHTML(item.name)}">
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
                            <button class="btn-card-edit" onclick="openEditModal(${item.id})"><i class="fa-regular fa-edit"></i> Edit</button>
                            <button class="btn-card-delete" onclick="deleteEquipment(${item.id})"><i class="fa-regular fa-trash-can"></i> Delete</button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
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

            // Helper to show the preview state
            function showPreview(url) {
                imagePreviewImg.src = url;
                eqImgField.value = url;
                imageDropzone.style.display = 'none';
                imageUrlAlternative.style.display = 'none';
                imagePreviewContainer.style.display = 'block';
            }

            // Helper to reset back to empty upload state
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

            // Drag and drop event listeners
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

            // Prevent enter key in URL input from submitting the main form, instead apply it
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

            // Toggle profile dropdown
            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });
                document.addEventListener('click', () => {
                    dropdownMenu.classList.remove('show');
                });
            }

            // Search & Filter Trigger
            function filterEquipment() {
                const query = searchInput.value.toLowerCase().trim();
                const category = filterSelect.value.toLowerCase();
                const cards = grid.querySelectorAll('.eq-card-admin');

                cards.forEach(card => {
                    const title = card.querySelector('.eq-card-title').textContent.toLowerCase();
                    const cardCategory = card.getAttribute('data-category').toLowerCase();

                    const matchesSearch = title.includes(query);
                    const matchesCategory = (category === 'all' || cardCategory === category);

                    if (matchesSearch && matchesCategory) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            searchInput.addEventListener('input', filterEquipment);
            filterSelect.addEventListener('change', filterEquipment);

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
                
                // Show default placeholder preview
                showPreview("https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60");
                
                modal.classList.add('show');
            });

            // Close Modal
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

                // Fill inputs
                editItemIdInput.value = item.id;
                eqNameField.value = item.name;
                eqCategoryField.value = item.category;
                eqAvailField.value = item.available;
                eqTotalField.value = item.total;
                eqStatusField.value = item.status;
                
                // Show actual preview
                showPreview(item.imgUrl);

                modalTitle.textContent = "Edit Equipment";
                modalSubtitle.textContent = "Update inventory details for this item";
                submitBtn.textContent = "Save Changes";
                
                modal.classList.add('show');
            };

            // Delete Equipment
            window.deleteEquipment = function(id) {
                const item = equipment.find(e => e.id == id);
                if (!item) return;

                if (confirm(`Are you sure you want to delete "${item.name}"?`)) {
                    equipment = equipment.filter(e => e.id != id);
                    localStorage.setItem('equip-track-equipment', JSON.stringify(equipment));
                    
                    const card = grid.querySelector(`.eq-card-admin[data-id="${id}"]`);
                    if (card) {
                        card.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(10px) scale(0.95)';
                        setTimeout(() => {
                            renderEquipment();
                            filterEquipment();
                            showNotification('Deleted Successfully', `Removed ${item.name} from inventory.`, 'success');
                        }, 500);
                    } else {
                        renderEquipment();
                        filterEquipment();
                        showNotification('Deleted Successfully', `Removed ${item.name} from inventory.`, 'success');
                    }
                }
            };

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
                    // Editing existing item
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
                    // Adding new item
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
                    showNotification('Added Successfully', `Registered ${name} in equipment inventory.`, 'success');
                }

                closeModal();
                renderEquipment();
                filterEquipment(); // Re-apply current search/filter
            });

            // Initial rendering
            renderEquipment();
            filterEquipment();
        });
    </script>
</body>
</html>
