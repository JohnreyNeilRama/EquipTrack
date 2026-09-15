<?php
require_once __DIR__ . '/auth_check.php';

// -------------------------------------------------------------
// 1. GET ALL EQUIPMENT (JSON Endpoint for live polling)
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
// 1.5 SUBMIT BORROW REQUEST (POST Endpoint)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    header('Content-Type: application/json');

    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User session expired. Please log in again.']);
        exit;
    }

    $equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $borrowDate  = trim($_POST['borrow_date'] ?? '');
    $returnDate  = trim($_POST['return_date'] ?? '');
    $purpose     = trim($_POST['purpose'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    // Validation
    if ($equipmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid equipment selected.']);
        exit;
    }

    if (empty($borrowDate) || empty($returnDate)) {
        echo json_encode(['success' => false, 'message' => 'Please specify both borrow and return dates.']);
        exit;
    }

    if (strtotime($returnDate) < strtotime($borrowDate)) {
        echo json_encode(['success' => false, 'message' => 'Return date cannot be earlier than borrow date.']);
        exit;
    }

    if (empty($purpose)) {
        echo json_encode(['success' => false, 'message' => 'Please select a borrowing purpose.']);
        exit;
    }

    // Check equipment availability
    $eqCheckStmt = $conn->prepare("SELECT name, available_qty, status FROM equipment WHERE equipment_id = ?");
    $eqCheckStmt->bind_param("i", $equipmentId);
    $eqCheckStmt->execute();
    $eqResult = $eqCheckStmt->get_result();

    if (!$eqResult || $eqResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'The selected equipment was not found in the database.']);
        exit;
    }

    $eqData = $eqResult->fetch_assoc();
    if (strtolower($eqData['status']) !== 'available' || (int)$eqData['available_qty'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sorry, this equipment is currently unavailable for borrowing.']);
        exit;
    }

    // Insert into borrow_request
    $insertStmt = $conn->prepare("
        INSERT INTO borrow_request 
        (user_id, equipment_id, quantity, purpose, notes, date_requested, date_needed, borrow_date, return_date, due_date, admin_status, dept_status, overall_status) 
        VALUES 
        (?, ?, 1, ?, ?, NOW(), ?, ?, ?, ?, 'Pending', 'Pending', 'Pending')
    ");

    if (!$insertStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $insertStmt->bind_param("iissssss", $userId, $equipmentId, $purpose, $notes, $borrowDate, $borrowDate, $returnDate, $returnDate);

    if ($insertStmt->execute()) {
        $requestId = $insertStmt->insert_id;

        // Log audit trail if table exists
        $auditAction = "Submitted borrow request #" . $requestId . " for " . $eqData['name'];
        $auditStmt = $conn->prepare("INSERT INTO audit_trail (user_id, action, timestamp) VALUES (?, ?, NOW())");
        if ($auditStmt) {
            $auditStmt->bind_param("is", $userId, $auditAction);
            $auditStmt->execute();
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Your borrowing request has been submitted successfully!',
            'request_id' => $requestId,
            'redirect' => 'userrequests.php'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process request: ' . $insertStmt->error]);
    }
    exit;
}

// -------------------------------------------------------------
// 2. FETCH CATEGORIES & EQUIPMENT FROM DATABASE
// -------------------------------------------------------------
$dbCategories = [];
$catRes = $conn->query("SELECT category_id, category_name FROM equipment_category ORDER BY category_name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $dbCategories[] = $row;
    }
}

$dbEquipment = [];
$eqRes = $conn->query("
    SELECT e.*, 
           c.category_name, 
           d.department_name, 
           d.department_code 
    FROM equipment e 
    LEFT JOIN equipment_category c ON e.category_id = c.category_id 
    LEFT JOIN department d ON e.department_id = d.department_id 
    ORDER BY e.equipment_id DESC
");
if ($eqRes) {
    while ($row = $eqRes->fetch_assoc()) {
        $dbEquipment[] = $row;
    }
}

// Count items per category
$categoryCounts = [];
$totalEquipmentCount = count($dbEquipment);
foreach ($dbEquipment as $eqItem) {
    $cName = !empty($eqItem['category_name']) ? $eqItem['category_name'] : 'Others';
    $categoryCounts[$cName] = ($categoryCounts[$cName] ?? 0) + 1;
}

// Helper to resolve fontawesome icon per category
function getCategoryIconClass($catName) {
    $lower = strtolower($catName);
    if (strpos($lower, 'laptop') !== false || strpos($lower, 'computer') !== false) return 'fa-laptop';
    if (strpos($lower, 'projector') !== false || strpos($lower, 'video') !== false) return 'fa-video';
    if (strpos($lower, 'camera') !== false) return 'fa-camera';
    if (strpos($lower, 'audio') !== false || strpos($lower, 'sound') !== false || strpos($lower, 'speaker') !== false) return 'fa-music';
    if (strpos($lower, 'mic') !== false) return 'fa-microphone';
    if (strpos($lower, 'lab') !== false) return 'fa-flask';
    if (strpos($lower, 'calc') !== false) return 'fa-calculator';
    return 'fa-box';
}
?>
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; <?php echo !empty($user_profile_image) ? 'padding: 0; background: transparent;' : ''; ?>">
                        <?php if (!empty($user_profile_image)): ?>
                            <img src="<?php echo htmlspecialchars($user_profile_image); ?>" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($user_initials); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name"><?php echo htmlspecialchars($full_name); ?></span>
                            <span class="header-email"><?php echo htmlspecialchars($user_email); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="userprofile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="../logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="equipment-container">
            <!-- Categories Section -->
            <div class="categories-section">
                <h2 class="section-title">Categories</h2>
                <div class="categories-list" id="categoriesList">
                    <div class="category-card active" data-category="all">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <div class="cat-info">
                            <span class="cat-name">All</span>
                            <span class="cat-count"><?php echo $totalEquipmentCount; ?></span>
                        </div>
                    </div>
                    <?php foreach ($dbCategories as $catRow): 
                        $cName = $catRow['category_name'];
                        $cCount = $categoryCounts[$cName] ?? 0;
                        $cIcon = getCategoryIconClass($cName);
                    ?>
                        <div class="category-card" data-category="<?php echo htmlspecialchars(strtolower($cName)); ?>">
                            <i class="fa-solid <?php echo $cIcon; ?>"></i>
                            <div class="cat-info">
                                <span class="cat-name"><?php echo htmlspecialchars($cName); ?></span>
                                <span class="cat-count"><?php echo $cCount; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Available Equipments Section -->
            <div class="equipments-section">
                <div class="equipments-header">
                    <h2 class="section-title">Available Equipments</h2>
                    <div class="search-wrapper">
                        <input type="text" id="searchInput" placeholder="Search equipment by name, brand, model...">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    </div>
                </div>

                <div class="equipment-grid" id="equipmentGrid">
                    <?php if (empty($dbEquipment)): ?>
                        <div class="empty-state-card" id="emptyStateCard">
                            <i class="fa-solid fa-box-open empty-icon"></i>
                            <h4 class="empty-title">No available equipment</h4>
                            <p class="empty-desc">Equipment items created by the admin will appear here automatically.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dbEquipment as $eq): 
                            $eqId         = (int)$eq['equipment_id'];
                            $eqName       = $eq['name'];
                            $eqBrand      = $eq['brand'] ?? '';
                            $eqModel      = $eq['model'] ?? '';
                            $eqCategory   = !empty($eq['category_name']) ? $eq['category_name'] : 'Uncategorized';
                            $eqDept       = !empty($eq['department_name']) ? $eq['department_name'] : (!empty($eq['department_code']) ? $eq['department_code'] : 'General');
                            $eqAvail      = (int)$eq['available_qty'];
                            $eqTotal      = (int)$eq['total_qty'];
                            $eqStatus     = !empty($eq['status']) ? $eq['status'] : 'Available';
                            $rawImg       = trim($eq['image'] ?? '');

                            if (empty($rawImg)) {
                                $imgUrl = '../images/EquipTrack_logo.png';
                            } elseif (preg_match('/^(https?:\/\/|data:)/i', $rawImg)) {
                                $imgUrl = $rawImg;
                            } else {
                                $imgUrl = '../' . ltrim($rawImg, '/');
                            }

                            $isLowStock    = ($eqAvail <= 1);
                            $isUnavailable = (strtolower($eqStatus) !== 'available' || $eqAvail <= 0);
                        ?>
                            <div class="equipment-card" 
                                 data-id="<?php echo $eqId; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($eqName)); ?>"
                                 data-brand="<?php echo htmlspecialchars(strtolower($eqBrand)); ?>"
                                 data-model="<?php echo htmlspecialchars(strtolower($eqModel)); ?>"
                                 data-category="<?php echo htmlspecialchars(strtolower($eqCategory)); ?>"
                                 data-status="<?php echo htmlspecialchars(strtolower($eqStatus)); ?>">
                                
                                <div class="eq-img-container">
                                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                                         alt="<?php echo htmlspecialchars($eqName); ?>" 
                                         onerror="this.onerror=null; this.src='../images/EquipTrack_logo.png';">
                                </div>
                                <div class="eq-details">
                                    <h4 class="eq-name" title="<?php echo htmlspecialchars($eqName); ?>"><?php echo htmlspecialchars($eqName); ?></h4>
                                    
                                    <div class="eq-meta-row">
                                        <span class="eq-category"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($eqCategory); ?></span>
                                        <span class="eq-dept"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($eqDept); ?></span>
                                    </div>

                                    <div class="eq-stock-row">
                                        <span class="eq-available <?php echo $isLowStock ? 'low-stock' : ''; ?>">
                                            <i class="fa-solid fa-circle-check"></i> Stock: <strong><?php echo $eqAvail; ?> / <?php echo $eqTotal; ?></strong>
                                        </span>
                                        <span class="eq-status-badge <?php echo strtolower($eqStatus) === 'available' ? 'status-available' : 'status-unavailable'; ?>">
                                            <?php echo htmlspecialchars($eqStatus); ?>
                                        </span>
                                    </div>
                                </div>

                                <button type="button" class="btn-request" 
                                        data-id="<?php echo $eqId; ?>"
                                        data-name="<?php echo htmlspecialchars($eqName); ?>"
                                        data-category="<?php echo htmlspecialchars($eqCategory); ?>"
                                        data-available="<?php echo $eqAvail; ?>"
                                        data-img="<?php echo htmlspecialchars($imgUrl); ?>"
                                        <?php echo $isUnavailable ? 'disabled' : ''; ?>>
                                    <?php echo $isUnavailable ? 'Not Available' : 'Request Item'; ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Dynamic Empty State JS Container -->
                <div class="empty-state-card" id="searchEmptyState" style="display: none;">
                    <i class="fa-solid fa-magnifying-glass empty-icon"></i>
                    <h4 class="empty-title">No matching equipment found</h4>
                    <p class="empty-desc">Try adjusting your search terms or category selection.</p>
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
                        <input type="hidden" id="modalEqId" value="">
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
            const equipmentGrid = document.getElementById('equipmentGrid');
            const equipmentCards = document.querySelectorAll('.equipment-card');
            const searchInput = document.getElementById('searchInput');
            const searchEmptyState = document.getElementById('searchEmptyState');

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

            // Navbar Avatar Sync Helper
            function syncNavbarAvatar(newAvatar) {
                const dbAvatar = <?php echo json_encode($user_profile_image); ?>;
                let currentAvatar = null;
                if (newAvatar !== undefined) {
                    currentAvatar = newAvatar;
                } else if (dbAvatar) {
                    currentAvatar = dbAvatar;
                } else {
                    localStorage.removeItem('user-avatar-src');
                    currentAvatar = null;
                }

                const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');

                if (currentAvatar) {
                    localStorage.setItem('user-avatar-src', currentAvatar);
                    navAvatars.forEach(navAvatar => {
                        navAvatar.innerHTML = `<img src="${currentAvatar}" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                        navAvatar.style.padding = '0';
                        navAvatar.style.background = 'transparent';
                    });
                } else {
                    localStorage.removeItem('user-avatar-src');
                    navAvatars.forEach(navAvatar => {
                        navAvatar.style.padding = '';
                        navAvatar.style.background = 'var(--primary-color)';
                        navAvatar.innerHTML = <?php echo json_encode(htmlspecialchars($user_initials)); ?>;
                    });
                }
            }
            syncNavbarAvatar();

            window.addEventListener('storage', function(e) {
                if (e.key === 'user-avatar-src') {
                    syncNavbarAvatar(e.newValue);
                }
            });

            // Filter items function
            function filterItems() {
                const activeCard = document.querySelector('.category-card.active');
                const selectedCategory = activeCard ? activeCard.getAttribute('data-category').toLowerCase() : 'all';
                const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

                let visibleCount = 0;

                equipmentCards.forEach(card => {
                    const cardCategory = card.getAttribute('data-category').toLowerCase();
                    const cardName     = card.getAttribute('data-name') || '';
                    const cardBrand    = card.getAttribute('data-brand') || '';
                    const cardModel    = card.getAttribute('data-model') || '';

                    const matchesCategory = (selectedCategory === 'all' || cardCategory === selectedCategory);
                    const matchesSearch   = !searchTerm || 
                                           cardName.includes(searchTerm) || 
                                           cardBrand.includes(searchTerm) || 
                                           cardModel.includes(searchTerm);

                    if (matchesCategory && matchesSearch) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (searchEmptyState) {
                    if (visibleCount === 0 && equipmentCards.length > 0) {
                        searchEmptyState.style.display = 'block';
                    } else {
                        searchEmptyState.style.display = 'none';
                    }
                }
            }

            categoryCards.forEach(card => {
                card.addEventListener('click', () => {
                    categoryCards.forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    filterItems();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', filterItems);
            }

            // Modal Interactions
            const modal = document.getElementById('borrowModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const borrowForm = document.getElementById('borrowForm');
            const borrowDateInput = document.getElementById('borrowDate');
            const returnDateInput = document.getElementById('returnDate');

            // Set minimum dates
            const today = new Date().toISOString().split('T')[0];
            if (borrowDateInput) {
                borrowDateInput.min = today;
                borrowDateInput.value = today;
            }

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            if (returnDateInput) {
                returnDateInput.min = tomorrow.toISOString().split('T')[0];
            }

            if (borrowDateInput && returnDateInput) {
                borrowDateInput.addEventListener('change', () => {
                    const selectedBorrowDate = new Date(borrowDateInput.value);
                    selectedBorrowDate.setDate(selectedBorrowDate.getDate() + 1);
                    returnDateInput.min = selectedBorrowDate.toISOString().split('T')[0];
                    if (returnDateInput.value && returnDateInput.value < returnDateInput.min) {
                        returnDateInput.value = returnDateInput.min;
                    }
                });
            }

            // Attach request click event to buttons
            const requestBtns = document.querySelectorAll('.btn-request');
            requestBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (btn.disabled) return;
                    
                    const eqId       = btn.getAttribute('data-id');
                    const eqName     = btn.getAttribute('data-name');
                    const eqCategory = btn.getAttribute('data-category');
                    const eqAvail    = btn.getAttribute('data-available');
                    const eqImg      = btn.getAttribute('data-img');

                    document.getElementById('modalEqId').value        = eqId;
                    document.getElementById('modalEqName').value      = eqName;
                    document.getElementById('modalEqCategory').value  = eqCategory;
                    document.getElementById('modalEqAvailable').value = eqAvail;
                    
                    const modalImg = document.getElementById('modalEqImg');
                    modalImg.src = eqImg;
                    modalImg.alt = eqName;

                    if (borrowDateInput) borrowDateInput.value = today;
                    if (returnDateInput) {
                        returnDateInput.value = "";
                        returnDateInput.min = tomorrow.toISOString().split('T')[0];
                    }

                    if (modal) modal.classList.add('show');
                });
            });

            // Close modal functions
            const closeModal = () => {
                if (modal) modal.classList.remove('show');
                if (borrowForm) borrowForm.reset();
            };

            if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);

            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }

            // Handle Borrow Form Submission
            if (borrowForm) {
                borrowForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    
                    const eqId       = document.getElementById('modalEqId').value;
                    const borrowDate = borrowDateInput ? borrowDateInput.value : '';
                    const returnDate = returnDateInput ? returnDateInput.value : '';
                    const purpose    = document.getElementById('borrowPurpose').value;
                    const notes      = document.getElementById('borrowNotes').value;
                    const submitBtn  = borrowForm.querySelector('.btn-submit-request');

                    if (!eqId) {
                        alert('Invalid equipment item selected.');
                        return;
                    }

                    if (!borrowDate || !returnDate) {
                        alert('Please select both borrow date and return date.');
                        return;
                    }

                    if (returnDate < borrowDate) {
                        alert('Return date cannot be earlier than borrow date.');
                        return;
                    }

                    if (!purpose) {
                        alert('Please select a purpose for borrowing.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'submit_request');
                    formData.append('equipment_id', eqId);
                    formData.append('borrow_date', borrowDate);
                    formData.append('return_date', returnDate);
                    formData.append('purpose', purpose);
                    formData.append('notes', notes);

                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
                    }

                    fetch('useravailequipment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message || 'Borrow request submitted successfully!');
                            closeModal();
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        } else {
                            alert('Submission Error: ' + (data.message || 'Failed to submit request.'));
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('An unexpected network error occurred while submitting your request.');
                    })
                    .finally(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Submit Request';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
