<?php
require_once __DIR__ . '/auth_check.php';

// -------------------------------------------------------------
// 1. GET ALL EQUIPMENT (JSON)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_equipment') {
    header('Content-Type: application/json');
    $items = [];
    $res = $conn->query("
        SELECT e.*, 
               c.category_name, 
               d.department_name, 
               d.department_code 
        FROM equipment e 
        LEFT JOIN equipment_category c ON e.category_id = c.category_id 
        LEFT JOIN department d ON e.department_id = d.department_id 
        ORDER BY e.equipment_id DESC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
    echo json_encode(['success' => true, 'equipment' => $items]);
    exit;
}

// -------------------------------------------------------------
// 2. ADD CATEGORY (JSON)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    header('Content-Type: application/json');
    $category_name = trim($_POST['category_name'] ?? '');

    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required.']);
        exit;
    }

    $stmtChk = $conn->prepare("SELECT category_id FROM equipment_category WHERE LOWER(category_name) = LOWER(?)");
    if ($stmtChk) {
        $stmtChk->bind_param("s", $category_name);
        $stmtChk->execute();
        $resChk = $stmtChk->get_result();
        if ($resChk && $resChk->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A category with this name already exists.']);
            exit;
        }
    }

    $stmtIns = $conn->prepare("INSERT INTO equipment_category (category_name) VALUES (?)");
    if ($stmtIns) {
        $stmtIns->bind_param("s", $category_name);
        if ($stmtIns->execute()) {
            $new_id = $stmtIns->insert_id;
            echo json_encode([
                'success'  => true,
                'message'  => 'Category added successfully!',
                'category' => [
                    'category_id'   => $new_id,
                    'category_name' => $category_name
                ]
            ]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Failed to save category: ' . $conn->error]);
    exit;
}

// -------------------------------------------------------------
// 3. SAVE EQUIPMENT - ADD OR EDIT (JSON)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_equipment') {
    header('Content-Type: application/json');

    $equipment_id         = (int)($_POST['equipment_id'] ?? 0);
    $name                 = trim($_POST['name'] ?? '');
    $brand                = trim($_POST['brand'] ?? '');
    $model                = trim($_POST['model'] ?? '');
    $serial_number        = trim($_POST['serial_number'] ?? '');
    $image                = trim($_POST['image'] ?? '');
    $available_qty        = (int)($_POST['available_qty'] ?? 0);
    $total_qty            = (int)($_POST['total_qty'] ?? 0);
    $status               = trim($_POST['status'] ?? 'Available');
    $accessories_included = trim($_POST['accessories_included'] ?? '');
    $category_id          = (int)($_POST['category_id'] ?? 0);
    $department_id        = (int)($_POST['department_id'] ?? 0);

    // Backend Validation
    if (empty($name) || empty($brand) || empty($serial_number) || empty($image) || $category_id <= 0 || $department_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Equipment Name, Brand, Serial Number, Image, Category, and Department).']);
        exit;
    }

    if ($available_qty < 0 || $total_qty < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantities cannot be negative values.']);
        exit;
    }

    if ($available_qty > $total_qty) {
        echo json_encode(['success' => false, 'message' => 'Available Quantity cannot be greater than Total Quantity.']);
        exit;
    }

    $validStatuses = ['Available', 'Unavailable', 'On Hold', 'Under Maintenance'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid equipment status selected.']);
        exit;
    }

    // Check Serial Number Uniqueness
    if ($equipment_id > 0) {
        $stmtChkSN = $conn->prepare("SELECT equipment_id FROM equipment WHERE LOWER(serial_number) = LOWER(?) AND equipment_id != ?");
        $stmtChkSN->bind_param("si", $serial_number, $equipment_id);
    } else {
        $stmtChkSN = $conn->prepare("SELECT equipment_id FROM equipment WHERE LOWER(serial_number) = LOWER(?)");
        $stmtChkSN->bind_param("s", $serial_number);
    }
    $stmtChkSN->execute();
    $resChkSN = $stmtChkSN->get_result();
    if ($resChkSN && $resChkSN->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Serial Number "' . htmlspecialchars($serial_number) . '" is already registered to another equipment item.']);
        exit;
    }

    if ($equipment_id > 0) {
        // Update Equipment
        $stmtUpd = $conn->prepare("
            UPDATE equipment 
            SET name = ?, 
                brand = ?, 
                model = ?, 
                serial_number = ?, 
                image = ?, 
                available_qty = ?, 
                total_qty = ?, 
                status = ?, 
                accessories_included = ?, 
                category_id = ?, 
                department_id = ? 
            WHERE equipment_id = ?
        ");
        if ($stmtUpd) {
            $stmtUpd->bind_param("sssssiissiii", $name, $brand, $model, $serial_number, $image, $available_qty, $total_qty, $status, $accessories_included, $category_id, $department_id, $equipment_id);
            if ($stmtUpd->execute()) {
                echo json_encode(['success' => true, 'message' => 'Equipment details updated successfully!']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Failed to update equipment: ' . $conn->error]);
        exit;
    } else {
        // Insert Equipment
        $stmtIns = $conn->prepare("
            INSERT INTO equipment 
            (name, brand, model, serial_number, image, available_qty, total_qty, status, accessories_included, category_id, department_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmtIns) {
            $stmtIns->bind_param("sssssiissii", $name, $brand, $model, $serial_number, $image, $available_qty, $total_qty, $status, $accessories_included, $category_id, $department_id);
            if ($stmtIns->execute()) {
                echo json_encode(['success' => true, 'message' => 'Equipment registered successfully!']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Failed to register equipment: ' . $conn->error]);
        exit;
    }
}

// -------------------------------------------------------------
// 4. DELETE EQUIPMENT (JSON)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment') {
    header('Content-Type: application/json');
    $equipment_id = (int)($_POST['equipment_id'] ?? 0);

    if ($equipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid equipment ID provided.']);
        exit;
    }

    $stmtCheck = $conn->prepare("SELECT request_id FROM borrow_request WHERE equipment_id = ? AND status IN ('Pending', 'Approved', 'Borrowed') LIMIT 1");
    if ($stmtCheck) {
        $stmtCheck->bind_param("i", $equipment_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($resCheck && $resCheck->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete this equipment because it has active or pending borrow requests.']);
            exit;
        }
    }

    $stmtDel = $conn->prepare("DELETE FROM equipment WHERE equipment_id = ?");
    if ($stmtDel) {
        $stmtDel->bind_param("i", $equipment_id);
        if ($stmtDel->execute()) {
            echo json_encode(['success' => true, 'message' => 'Equipment removed from inventory successfully!']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Failed to delete equipment: ' . $conn->error]);
    exit;
}

// Fetch all categories strictly from equipment_category table
$dbCategories = [];
$catRes = $conn->query("SELECT category_id, category_name FROM equipment_category ORDER BY category_name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $dbCategories[] = $row;
    }
}

// Fetch all departments strictly from department table
$dbDepartments = [];
$deptRes = $conn->query("SELECT department_id, department_name, department_code FROM department ORDER BY department_name ASC");
if ($deptRes) {
    while ($row = $deptRes->fetch_assoc()) {
        $dbDepartments[] = $row;
    }
}
?>
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
    <link rel="stylesheet" href="../ccs/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/adminequipment.css?v=<?php echo time(); ?>">
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
    <style>
        .eq-status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-available { background-color: #dcfce7; color: #15803d; }
        .status-unavailable { background-color: #fee2e2; color: #b91c1c; }
        .status-on-hold { background-color: #ffedd5; color: #c2410c; }
        .status-under-maintenance { background-color: #fef3c7; color: #b45309; }
        
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 640px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }
    </style>
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
            <a href="profile.php" class="nav-item">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="equipment.php" class="nav-item active">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
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
                <div class="icon-btn" id="themeToggleBtn" title="Toggle theme">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification" id="notifBtn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; <?php echo !empty($admin_profile_image) ? 'padding: 0; background: transparent;' : ''; ?>">
                        <?php if (!empty($admin_profile_image)): ?>
                            <img src="<?php echo htmlspecialchars($admin_profile_image); ?>" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($admin_initials); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name"><?php echo htmlspecialchars($admin_name); ?></span>
                            <span class="header-email"><?php echo htmlspecialchars($admin_email); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
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
                            <option value="all">All Categories</option>
                            <?php foreach ($dbCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category_id']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
                <div class="controls-right" style="display: flex; gap: 10px; align-items: center;">
                    <button class="btn-add-category" id="addCategoryBtn" style="height: 46px; padding: 0 20px; border-radius: 23px; background-color: var(--card-bg, #ffffff); color: var(--text-main, #0f172a); border: 1px solid var(--border-color, #e2e8f0); font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                        <i class="fa-solid fa-folder-plus" style="color: #385585;"></i> Add Category
                    </button>
                    <button class="btn-add-equipment" id="addEquipmentBtn">
                        <i class="fa-solid fa-plus"></i> Add Equipment
                    </button>
                </div>
            </div>

            <!-- Equipment cards grid -->
            <div class="equipment-grid" id="equipmentGrid">
                <!-- Loaded dynamically via JavaScript -->
            </div>
        </div>
    </main>

    <!-- Equipment Modal (Add / Edit) -->
    <div class="modal-overlay" id="equipmentModal">
        <div class="modal-card eq-modal-card" style="max-width: 680px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top" id="modalSubtitle">Provide details for the inventory item</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeModalBtn">&times;</button>
                <h3 class="modal-title-center" id="modalTitle">Add New Equipment</h3>
                
                <form id="equipmentForm" class="new-modal-form" style="padding-top: 10px;">
                    <input type="hidden" id="editItemId" name="equipment_id" value="">
                    <input type="hidden" name="action" value="save_equipment">
                    <input type="hidden" id="eqFormImg" name="image" value="">
                    
                    <!-- Equipment Name -->
                    <div class="form-group-flat">
                        <label for="eqFormName">Equipment Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="eqFormName" name="name" class="form-control-flat" required placeholder="e.g., Dell Latitude 5420 Laptop">
                    </div>

                    <!-- Brand & Model -->
                    <div class="form-grid-2">
                        <div class="form-group-flat">
                            <label for="eqFormBrand">Brand <span style="color: #ef4444;">*</span></label>
                            <input type="text" id="eqFormBrand" name="brand" class="form-control-flat" required placeholder="e.g., Dell">
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormModel">Model</label>
                            <input type="text" id="eqFormModel" name="model" class="form-control-flat" placeholder="e.g., Latitude 5420">
                        </div>
                    </div>

                    <!-- Serial Number & Status -->
                    <div class="form-grid-2">
                        <div class="form-group-flat">
                            <label for="eqFormSerial">Serial Number <span style="color: #ef4444;">*</span></label>
                            <input type="text" id="eqFormSerial" name="serial_number" class="form-control-flat" required placeholder="e.g., SN-DELL-98765">
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormStatus">Status <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="eqFormStatus" name="status" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="Available">Available</option>
                                    <option value="Unavailable">Unavailable</option>
                                    <option value="On Hold">On Hold</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Category & Department -->
                    <div class="form-grid-2">
                        <div class="form-group-flat">
                            <label for="eqFormCategory">Category <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="eqFormCategory" name="category_id" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="">Select Category</option>
                                    <?php foreach ($dbCategories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category_id']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormDepartment">Department <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="eqFormDepartment" name="department_id" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="">Select Department</option>
                                    <?php foreach ($dbDepartments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept['department_id']); ?>"><?php echo htmlspecialchars($dept['department_name']); ?><?php echo !empty($dept['department_code']) ? ' ('.$dept['department_code'].')' : ''; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Available Quantity & Total Quantity -->
                    <div class="form-grid-2">
                        <div class="form-group-flat">
                            <label for="eqFormAvail">Available Quantity <span style="color: #ef4444;">*</span></label>
                            <input type="number" id="eqFormAvail" name="available_qty" class="form-control-flat" min="0" required value="1">
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormTotal">Total Quantity <span style="color: #ef4444;">*</span></label>
                            <input type="number" id="eqFormTotal" name="total_qty" class="form-control-flat" min="0" required value="1">
                        </div>
                    </div>

                    <!-- Accessories Included -->
                    <div class="form-group-flat">
                        <label for="eqFormAccessories">Accessories Included</label>
                        <input type="text" id="eqFormAccessories" name="accessories_included" class="form-control-flat" placeholder="e.g., Charger, Power Adapter, Carrying Case">
                    </div>

                    <!-- Equipment Image -->
                    <div class="form-group-flat">
                        <label>Equipment Image <span style="color: #ef4444;">*</span></label>
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
                                    <input type="url" id="eqFormImgUrl" class="form-control-flat" placeholder="Paste image URL here...">
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

                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-request" id="submitBtn">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Category Modal (Add Category) -->
    <div class="modal-overlay" id="categoryModal" style="display: none;">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Create a new equipment classification</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeCategoryModalBtn">&times;</button>
                <h3 class="modal-title-center">Add New Category</h3>
                
                <form id="categoryForm" class="new-modal-form" style="padding-top: 10px;">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div class="form-group-flat">
                        <label for="catFormName">Category Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="catFormName" name="category_name" class="form-control-flat" required placeholder="e.g., Laboratory Equipment">
                    </div>

                    <div class="modal-actions-footer" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                        <button type="button" class="btn-modal-close" id="cancelCategoryBtn" style="height: 42px; padding: 0 18px; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0); background: transparent; color: var(--text-main); font-weight: 600; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn-submit-request" id="saveCategoryBtn" style="height: 42px; width: auto; padding: 0 24px;">Save Category</button>
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
            // Master Equipment Array loaded from database
            let equipment = [];
            let dbCategories = <?php echo json_encode($dbCategories); ?>;
            let dbDepartments = <?php echo json_encode($dbDepartments); ?>;

            // Navbar Avatar Sync Helper
            function syncNavbarAvatar(newAvatar) {
                const dbAvatar = <?php echo json_encode($admin_profile_image); ?>;
                let currentAvatar = null;
                if (newAvatar !== undefined) {
                    currentAvatar = newAvatar;
                } else if (dbAvatar) {
                    currentAvatar = dbAvatar;
                } else {
                    localStorage.removeItem('admin-avatar-src');
                    currentAvatar = null;
                }

                const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');
                if (currentAvatar) {
                    localStorage.setItem('admin-avatar-src', currentAvatar);
                    navAvatars.forEach(navAvatar => {
                        navAvatar.innerHTML = `<img src="${currentAvatar}" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                        navAvatar.style.padding = '0';
                        navAvatar.style.background = 'transparent';
                    });
                } else {
                    localStorage.removeItem('admin-avatar-src');
                    navAvatars.forEach(navAvatar => {
                        navAvatar.style.padding = '';
                        navAvatar.style.background = 'var(--primary-color)';
                        navAvatar.innerHTML = <?php echo json_encode(htmlspecialchars($admin_initials)); ?>;
                    });
                }
            }
            syncNavbarAvatar();

            window.addEventListener('storage', function(e) {
                if (e.key === 'admin-avatar-src') {
                    syncNavbarAvatar(e.newValue);
                }
            });

            // DOM Elements
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            // Search & Filter
            const searchInput = document.getElementById('searchEquipment');
            const filterSelect = document.getElementById('filterCategory');
            const grid = document.getElementById('equipmentGrid');

            // Equipment Modal
            const modal = document.getElementById('equipmentModal');
            const addBtn = document.getElementById('addEquipmentBtn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const form = document.getElementById('equipmentForm');
            
            const modalTitle = document.getElementById('modalTitle');
            const modalSubtitle = document.getElementById('modalSubtitle');
            const submitBtn = document.getElementById('submitBtn');
            const editItemIdInput = document.getElementById('editItemId');
            
            // Category Modal Elements
            const addCategoryBtn = document.getElementById('addCategoryBtn');
            const categoryModal = document.getElementById('categoryModal');
            const closeCategoryModalBtn = document.getElementById('closeCategoryModalBtn');
            const cancelCategoryBtn = document.getElementById('cancelCategoryBtn');
            const categoryForm = document.getElementById('categoryForm');
            const catFormNameInput = document.getElementById('catFormName');

            // Form Fields
            const eqNameField = document.getElementById('eqFormName');
            const eqBrandField = document.getElementById('eqFormBrand');
            const eqModelField = document.getElementById('eqFormModel');
            const eqSerialField = document.getElementById('eqFormSerial');
            const eqCategoryField = document.getElementById('eqFormCategory');
            const eqDepartmentField = document.getElementById('eqFormDepartment');
            const eqImgHiddenField = document.getElementById('eqFormImg');
            const eqImgUrlField = document.getElementById('eqFormImgUrl');
            const eqAvailField = document.getElementById('eqFormAvail');
            const eqTotalField = document.getElementById('eqFormTotal');
            const eqStatusField = document.getElementById('eqFormStatus');
            const eqAccessoriesField = document.getElementById('eqFormAccessories');

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

            // Fetch Equipment from Database
            function loadEquipment() {
                fetch('equipment.php?action=get_equipment')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            equipment = data.equipment || [];
                            renderEquipment();
                            filterEquipment();
                        }
                    })
                    .catch(err => console.error('Error loading equipment:', err));
            }

            // Render equipment cards dynamically
            function renderEquipment() {
                grid.innerHTML = '';
                if (!equipment || equipment.length === 0) {
                    grid.innerHTML = `
                        <div class="empty-state-container" style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 48px 24px; background: var(--bg-card); border-radius: 12px; border: 1px dashed var(--border-color);">
                            <i class="fa-solid fa-boxes-stacked" style="font-size: 32px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                            <h4 style="font-size: 16px; font-weight: 600; color: var(--text-main); margin-bottom: 4px;">No Equipment Registered</h4>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Click "Add Equipment" above to populate the equipment inventory.</p>
                        </div>
                    `;
                    return;
                }
                equipment.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'eq-card-admin';
                    card.setAttribute('data-category-id', item.category_id || '');
                    card.setAttribute('data-id', item.equipment_id);
                    
                    let statusClass = 'status-available';
                    if (item.status === 'Unavailable') statusClass = 'status-unavailable';
                    else if (item.status === 'On Hold') statusClass = 'status-on-hold';
                    else if (item.status === 'Under Maintenance') statusClass = 'status-under-maintenance';

                    card.innerHTML = `
                        <div class="eq-card-img-wrapper">
                            <img src="${escapeHTML(item.image)}" alt="${escapeHTML(item.name)}" onerror="this.onerror=null; this.src='../images/logo_only.png';">
                        </div>
                        <div class="eq-card-details">
                            <h4 class="eq-card-title">${escapeHTML(item.name)}</h4>
                            <div class="eq-card-meta-line">
                                <span class="label">Brand/Model :</span>
                                <span class="value">${escapeHTML(item.brand)}${item.model ? ' ' + escapeHTML(item.model) : ''}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">S/N :</span>
                                <span class="value">${escapeHTML(item.serial_number)}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Category :</span>
                                <span class="value cat-val">${escapeHTML(item.category_name || 'Unassigned')}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Department :</span>
                                <span class="value">${escapeHTML(item.department_name || 'Unassigned')}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Available / Total :</span>
                                <span class="value">${escapeHTML(item.available_qty)} / ${escapeHTML(item.total_qty)}</span>
                            </div>
                            <div class="eq-card-meta-line">
                                <span class="label">Status :</span>
                                <span class="value"><span class="eq-status-badge ${statusClass}">${escapeHTML(item.status)}</span></span>
                            </div>
                            ${item.accessories_included ? `
                            <div class="eq-card-meta-line" style="margin-top: 4px;">
                                <span class="label">Accessories :</span>
                                <span class="value" style="font-size: 12px; opacity: 0.85;">${escapeHTML(item.accessories_included)}</span>
                            </div>` : ''}
                        </div>
                        <div class="eq-card-actions">
                            <button class="btn-card-edit" onclick="openEditModal(${item.equipment_id})"><i class="fa-regular fa-edit"></i> Edit</button>
                            <button class="btn-card-delete" onclick="deleteEquipment(${item.equipment_id})"><i class="fa-regular fa-trash-can"></i> Delete</button>
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

            // Helper to show preview state
            function showPreview(url) {
                imagePreviewImg.src = url;
                eqImgHiddenField.value = url;
                imageDropzone.style.display = 'none';
                imageUrlAlternative.style.display = 'none';
                imagePreviewContainer.style.display = 'block';
            }

            // Helper to reset back to empty upload state
            function resetUploader() {
                imagePreviewImg.src = "";
                imageFileInput.value = "";
                eqImgHiddenField.value = "";
                if (eqImgUrlField) eqImgUrlField.value = "";
                imageDropzone.style.display = 'flex';
                imageUrlAlternative.style.display = 'flex';
                imagePreviewContainer.style.display = 'none';
            }

            function handleFile(file) {
                if (file && file.type.startsWith('image/')) {
                    if (file.size > 5 * 1024 * 1024) {
                        showNotification('File Too Large', 'Please upload an image smaller than 5MB.', 'error');
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        showPreview(e.target.result);
                    };
                    reader.readAsDataURL(file);
                } else {
                    showNotification('Invalid File', 'Please select a valid image file.', 'error');
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
                const urlValue = eqImgUrlField.value.trim();
                if (urlValue) {
                    showPreview(urlValue);
                } else {
                    showNotification('Empty URL', 'Please paste a valid image URL.', 'error');
                }
            });

            if (eqImgUrlField) {
                eqImgUrlField.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        btnApplyUrl.click();
                    }
                });
            }

            btnReplacePreview.addEventListener('click', () => {
                imageFileInput.click();
            });

            btnRemovePreview.addEventListener('click', () => {
                resetUploader();
            });

            // Open / Close Category Modal Logic
            function openCategoryModal() {
                categoryForm.reset();
                categoryModal.style.display = 'flex';
                setTimeout(() => {
                    categoryModal.classList.add('show');
                    if (catFormNameInput) catFormNameInput.focus();
                }, 10);
            }

            function closeCategoryModal() {
                categoryModal.classList.remove('show');
                setTimeout(() => {
                    categoryModal.style.display = 'none';
                    categoryForm.reset();
                }, 200);
            }

            if (addCategoryBtn) addCategoryBtn.addEventListener('click', openCategoryModal);
            if (closeCategoryModalBtn) closeCategoryModalBtn.addEventListener('click', closeCategoryModal);
            if (cancelCategoryBtn) cancelCategoryBtn.addEventListener('click', closeCategoryModal);
            if (categoryModal) {
                categoryModal.addEventListener('click', (e) => {
                    if (e.target === categoryModal) closeCategoryModal();
                });
            }

            // Add Category Submit
            if (categoryForm) {
                categoryForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const catName = catFormNameInput.value.trim();
                    if (!catName) {
                        showNotification('Validation Error', 'Please enter a category name.', 'error');
                        return;
                    }

                    const formData = new FormData(categoryForm);
                    fetch('equipment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const newCat = data.category;
                            dbCategories.push(newCat);

                            // Add to Category Filter
                            const filterOpt = document.createElement('option');
                            filterOpt.value = newCat.category_id;
                            filterOpt.textContent = newCat.category_name;
                            filterSelect.appendChild(filterOpt);

                            // Add to Form Select
                            const formOpt = document.createElement('option');
                            formOpt.value = newCat.category_id;
                            formOpt.textContent = newCat.category_name;
                            eqCategoryField.appendChild(formOpt);
                            eqCategoryField.value = newCat.category_id;

                            closeCategoryModal();
                            showNotification('Category Added', `Category "${newCat.category_name}" saved to database successfully!`, 'success');
                        } else {
                            showNotification('Error', data.message || 'Failed to save category.', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error saving category:', err);
                        showNotification('System Error', 'An unexpected error occurred while saving the category.', 'error');
                    });
                });
            }

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
                const selectedCatId = filterSelect.value;
                const cards = grid.querySelectorAll('.eq-card-admin');

                cards.forEach(card => {
                    const title = card.querySelector('.eq-card-title').textContent.toLowerCase();
                    const cardCatId = card.getAttribute('data-category-id');

                    const matchesSearch = title.includes(query);
                    const matchesCategory = (selectedCatId === 'all' || cardCatId === selectedCatId);

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
                
                resetUploader();
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
                resetUploader();
            }

            // Open Edit Modal
            window.openEditModal = function(id) {
                const item = equipment.find(e => e.equipment_id == id);
                if (!item) return;

                editItemIdInput.value = item.equipment_id;
                eqNameField.value = item.name || '';
                eqBrandField.value = item.brand || '';
                eqModelField.value = item.model || '';
                eqSerialField.value = item.serial_number || '';
                eqCategoryField.value = item.category_id || '';
                eqDepartmentField.value = item.department_id || '';
                eqAvailField.value = item.available_qty !== undefined ? item.available_qty : 0;
                eqTotalField.value = item.total_qty !== undefined ? item.total_qty : 0;
                eqStatusField.value = item.status || 'Available';
                eqAccessoriesField.value = item.accessories_included || '';

                if (item.image) {
                    showPreview(item.image);
                } else {
                    resetUploader();
                }

                modalTitle.textContent = "Edit Equipment";
                modalSubtitle.textContent = "Update inventory details for this item";
                submitBtn.textContent = "Save Changes";
                
                modal.classList.add('show');
            };

            // Delete Equipment
            window.deleteEquipment = function(id) {
                const item = equipment.find(e => e.equipment_id == id);
                if (!item) return;

                if (confirm(`Are you sure you want to delete "${item.name}" (${item.serial_number})?`)) {
                    const formData = new FormData();
                    formData.append('action', 'delete_equipment');
                    formData.append('equipment_id', id);

                    fetch('equipment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Deleted Successfully', data.message, 'success');
                            loadEquipment();
                        } else {
                            showNotification('Error', data.message || 'Failed to delete equipment.', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error deleting equipment:', err);
                        showNotification('System Error', 'An error occurred while deleting equipment.', 'error');
                    });
                }
            };

            // Form Submit (Add / Edit)
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const imgVal = eqImgHiddenField.value.trim();
                if (!imgVal) {
                    showNotification('Image Required', 'Please upload an image or enter an image URL.', 'error');
                    return;
                }

                const availQty = parseInt(eqAvailField.value) || 0;
                const totalQty = parseInt(eqTotalField.value) || 0;

                if (availQty > totalQty) {
                    showNotification('Validation Error', 'Available Quantity cannot exceed Total Quantity.', 'error');
                    return;
                }

                const formData = new FormData(form);

                fetch('equipment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeModal();
                        showNotification('Success', data.message, 'success');
                        loadEquipment();
                    } else {
                        showNotification('Validation Error', data.message || 'Failed to save equipment.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error saving equipment:', err);
                    showNotification('System Error', 'An unexpected error occurred while saving equipment.', 'error');
                });
            });

            // Initial rendering
            loadEquipment();
        });
    </script>
</body>
</html>
