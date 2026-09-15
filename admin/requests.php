<?php
require_once __DIR__ . '/auth_check.php';

// Helper function to fetch all requests from MySQL database
function fetchAllBorrowRequests($conn) {
    $requests = [];
    $sql = "
        SELECT 
            r.request_id,
            r.user_id,
            r.equipment_id,
            r.quantity,
            r.purpose,
            r.notes,
            r.date_requested,
            r.date_needed,
            r.borrow_date,
            r.return_date,
            r.due_date,
            r.admin_status,
            r.dept_status,
            r.overall_status,
            r.reject_reason,
            u.role AS user_role,
            u.email AS user_email,
            u.profile_image AS user_avatar,
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(fm.first_name, ''), ' ', COALESCE(fm.last_name, ''))), ''),
                u.email
            ) AS user_full_name,
            e.name AS equipment_name,
            e.image AS equipment_image,
            e.available_qty,
            e.total_qty,
            c.category_name
        FROM borrow_request r
        LEFT JOIN user_account u ON r.user_id = u.user_id
        LEFT JOIN student s ON u.user_id = s.user_id
        LEFT JOIN faculty_member fm ON u.user_id = fm.user_id
        LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
        LEFT JOIN equipment_category c ON e.category_id = c.category_id
        ORDER BY r.request_id DESC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $reqDate = !empty($row['date_requested']) ? date('M d, Y', strtotime($row['date_requested'])) : 'N/A';
            $fullReqDate = !empty($row['date_requested']) ? date('M d, Y h:i A', strtotime($row['date_requested'])) : 'N/A';
            $bDateRaw = !empty($row['borrow_date']) ? $row['borrow_date'] : ($row['date_needed'] ?? '');
            $bDate = !empty($bDateRaw) ? date('M d, Y', strtotime($bDateRaw)) : 'N/A';
            $dDateRaw = !empty($row['due_date']) ? $row['due_date'] : ($row['return_date'] ?? '');
            $dDate = !empty($dDateRaw) ? date('M d, Y', strtotime($dDateRaw)) : 'N/A';

            $uAvatar = trim($row['user_avatar'] ?? '');
            if (empty($uAvatar)) {
                $uAvatar = "https://ui-avatars.com/api/?name=" . urlencode($row['user_full_name'] ?: 'User') . "&background=385585&color=fff";
            }

            $rawImg = trim($row['equipment_image'] ?? '');
            if (empty($rawImg)) {
                $eqImg = '../images/EquipTrack_logo.png';
            } elseif (preg_match('/^(https?:\/\/|data:)/i', $rawImg)) {
                $eqImg = $rawImg;
            } else {
                $eqImg = '../' . ltrim($rawImg, '/');
            }

            $requests[] = [
                'id'           => (int)$row['request_id'],
                'user_id'      => (int)$row['user_id'],
                'user'         => !empty($row['user_full_name']) ? $row['user_full_name'] : 'Unknown User',
                'role'         => ucfirst($row['user_role'] ?? 'Student'),
                'email'        => $row['user_email'] ?? '',
                'avatar'       => $uAvatar,
                'equipment_id' => (int)$row['equipment_id'],
                'equipment'    => !empty($row['equipment_name']) ? $row['equipment_name'] : 'Unknown Equipment',
                'category'     => !empty($row['category_name']) ? $row['category_name'] : 'General',
                'img'          => $eqImg,
                'quantity'     => (int)($row['quantity'] ?? 1),
                'date'         => $reqDate,
                'fullDate'     => $fullReqDate,
                'borrowDate'   => $bDate,
                'dueDate'      => $dDate,
                'purpose'      => $row['purpose'] ?: 'N/A',
                'notes'        => $row['notes'] ?: 'None',
                'status'       => ucfirst($row['overall_status'] ?? 'Pending'),
                'adminStatus'  => ucfirst($row['admin_status'] ?? 'Pending'),
                'rejectReason' => $row['reject_reason'] ?? ''
            ];
        }
    }
    return $requests;
}

// GET Endpoint for live JSON polling
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_requests') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'requests' => fetchAllBorrowRequests($conn)]);
    exit;
}

// POST Endpoint for Status Updates (Approve / Reject)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');

    $requestId    = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $status       = trim($_POST['status'] ?? '');
    $rejectReason = trim($_POST['reject_reason'] ?? '');

    if ($requestId <= 0 || !in_array($status, ['Approved', 'Rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    // Check request
    $chkStmt = $conn->prepare("SELECT r.*, e.name AS equipment_name FROM borrow_request r INNER JOIN equipment e ON r.equipment_id = e.equipment_id WHERE r.request_id = ?");
    $chkStmt->bind_param("i", $requestId);
    $chkStmt->execute();
    $chkRes = $chkStmt->get_result();

    if (!$chkRes || $chkRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Borrow request not found.']);
        exit;
    }

    $reqData = $chkRes->fetch_assoc();

    if ($status === 'Approved') {
        $updateStmt = $conn->prepare("
            UPDATE borrow_request 
            SET admin_status = 'Approved', 
                overall_status = 'Approved', 
                admin_id = ?, 
                admin_reviewed_at = NOW(), 
                reject_reason = NULL 
            WHERE request_id = ?
        ");
        $updateStmt->bind_param("ii", $admin_id, $requestId);

        if ($updateStmt->execute()) {
            // Log to audit trail
            $auditAction = "Approved borrow request #" . $requestId . " for " . $reqData['equipment_name'];
            $auditStmt = $conn->prepare("INSERT INTO audit_trail (admin_id, action, timestamp) VALUES (?, ?, NOW())");
            if ($auditStmt) {
                $auditStmt->bind_param("is", $admin_id, $auditAction);
                $auditStmt->execute();
            }

            echo json_encode(['success' => true, 'message' => 'Borrow request approved successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update request: ' . $conn->error]);
        }
    } elseif ($status === 'Rejected') {
        if (empty($rejectReason)) {
            echo json_encode(['success' => false, 'message' => 'Please state a reason for rejecting the request.']);
            exit;
        }

        $updateStmt = $conn->prepare("
            UPDATE borrow_request 
            SET admin_status = 'Rejected', 
                overall_status = 'Rejected', 
                admin_id = ?, 
                admin_reviewed_at = NOW(), 
                reject_reason = ? 
            WHERE request_id = ?
        ");
        $updateStmt->bind_param("isi", $admin_id, $rejectReason, $requestId);

        if ($updateStmt->execute()) {
            // Log to audit trail
            $auditAction = "Rejected borrow request #" . $requestId . " for " . $reqData['equipment_name'] . ". Reason: " . $rejectReason;
            $auditStmt = $conn->prepare("INSERT INTO audit_trail (admin_id, action, timestamp) VALUES (?, ?, NOW())");
            if ($auditStmt) {
                $auditStmt->bind_param("is", $admin_id, $auditAction);
                $auditStmt->execute();
            }

            echo json_encode(['success' => true, 'message' => 'Borrow request rejected.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject request: ' . $conn->error]);
        }
    }
    exit;
}

// Initial fetch for page load
$dbRequests = fetchAllBorrowRequests($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Equipment Requests</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/adminequipment.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/adminrequests.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    <style>
        /* Custom overrides to match the exact mockup screenshot */
        .requests-page-title {
            font-size: 28px;
            font-weight: 700;
            color: #385585; /* Specific blue for title */
            margin-bottom: 24px;
        }
        .dark-theme .requests-page-title {
            color: #60a5fa;
        }
        
        /* Actions container spacing */
        .action-cell {
            padding-right: 20px !important;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        /* Row clickable cursor for viewing details */
        .admin-table-row {
            cursor: pointer;
        }
        .admin-table-row:hover td {
            background-color: rgba(56, 85, 133, 0.03) !important;
        }
        
        /* Reject/View buttons styling */
        .btn-approve, .btn-reject, .btn-view-only {
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 32px;
        }

        .btn-approve {
            background-color: #3b5998;
            color: #ffffff;
        }
        .btn-approve:hover {
            background-color: #2f477a;
            transform: translateY(-1px);
        }

        .btn-reject {
            background-color: #ff0000;
            color: #ffffff;
        }
        .btn-reject:hover {
            background-color: #cc0000;
            transform: translateY(-1px);
        }

        .btn-view-only {
            background-color: rgba(79, 107, 156, 0.1);
            color: #4f6b9c;
        }
        .btn-view-only:hover {
            background-color: #4f6b9c;
            color: #ffffff;
            transform: translateY(-1px);
        }
        
        .dark-theme .btn-approve {
            background-color: #3b82f6;
        }
        .dark-theme .btn-approve:hover {
            background-color: #2563eb;
        }
        .dark-theme .btn-view-only {
            background-color: rgba(129, 140, 248, 0.1);
            color: #818cf8;
        }
        .dark-theme .btn-view-only:hover {
            background-color: #818cf8;
            color: #0f172a;
        }

        /* Modal Details Form styling                                            */
        /* Mirrors the structure/spacing of the User "My Request" details modal    */
        /* (.eq-modal-card alone is capped at 500px !important by adminequipment.css, */
        /* so we still need the extra class + !important here to win the cascade). */
        .eq-modal-card.request-details-card {
            max-width: 680px !important;
        }

        .detail-main-content {
            display: flex;
            gap: 28px;
            margin-bottom: 20px;
            align-items: stretch;
        }
        .detail-left-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .detail-img-container {
            width: 100%;
            height: 190px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
        }
        .detail-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .detail-right-side {
            flex: 1.3;
            min-width: 0;
        }

        /* Stacked single-column field list, same as the user page's form-grid */
        .detail-form-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .detail-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            text-align: left;
            width: 100%;
        }
        .detail-form-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-form-control {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            outline: none;
            font-family: inherit;
            box-sizing: border-box;
        }
        .detail-form-control[readonly] {
            cursor: default;
        }

        /* Lower section (Purpose / Notes / Reason) - vertical stack with a
           top divider, matching the user page's .detail-lower-section */
        .detail-lower-section {
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .detail-main-content {
                flex-direction: column;
                gap: 16px;
            }
            .detail-img-container {
                height: 150px;
            }
        }

        .textarea-control {
            resize: none;
            font-family: inherit;
            line-height: 1.5;
        }
        .text-danger-control {
            border-color: rgba(239, 68, 68, 0.3);
            background-color: rgba(239, 68, 68, 0.03);
            color: #ef4444;
        }
        .dark-theme .text-danger-control {
            border-color: rgba(248, 113, 113, 0.3);
            background-color: rgba(248, 113, 113, 0.05);
            color: #f87171;
        }
        
        /* Adjust detail status badge align */
        .status-badge-wrapper {
            display: flex;
            justify-content: flex-start;
        }

        .no-requests-message {
            text-align: center;
            color: var(--text-muted);
            padding: 40px;
        }
        .no-requests-message i {
            display: block;
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 12px;
            opacity: 0.7;
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
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item active">
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

        <!-- Requests Content Container -->
        <div class="requests-container">
            <!-- Header Section -->
            <div class="equipment-header-section">
                <h2>Requests</h2>
                <p>Review, approve, or reject student and faculty borrowing requests.</p>
            </div>

            <!-- Controls bar (Search & Filters) -->
            <div class="controls-bar">
                <div class="controls-left">
                    <div class="search-box-wrapper">
                        <input type="text" id="searchRequests" placeholder="Search by user or equipment...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="filter-select-wrapper">
                        <select id="filterStatus">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Requests Table Card -->
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Equipment</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="action-column">Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Request Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card eq-modal-card request-details-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user request</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Request Details</h3>
                
                <!-- Requester Profile Block -->
                <div class="modal-requester-profile">
                    <img src="" alt="User Avatar" class="modal-requester-avatar" id="modalUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalUserName">John Doe</span>
                        <span class="modal-requester-details" id="modalUserRole">Student</span>
                    </div>
                </div>

                <!-- Main Fields Section -->
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="modalEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">Pending</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="modalEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="modalEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Quantity Requested</label>
                                <input type="text" id="modalEqQty" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Request Date</label>
                                <input type="text" id="modalReqDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="modalBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="modalDueDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purpose & Notes Section -->
                <div class="detail-text-grid">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Purpose of Borrowing</label>
                        <textarea id="modalPurpose" class="detail-form-control textarea-control" rows="3" readonly></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Additional Notes</label>
                        <textarea id="modalNotes" class="detail-form-control textarea-control" rows="3" readonly></textarea>
                    </div>
                </div>
                <div class="detail-form-group" id="modalReasonGroup" style="display: none; margin-bottom: 16px;">
                    <label class="detail-form-label text-danger">Reason for Rejection</label>
                    <textarea id="modalReason" class="detail-form-control textarea-control text-danger-control" rows="2" readonly></textarea>
                </div>

                <!-- Modal Actions Footer -->
                <div class="modal-actions-footer" id="modalActionsFooter">
                    <!-- Loaded dynamically based on status -->
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Input Modal -->
    <div class="modal-overlay" id="rejectReasonModal">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Provide rejection feedback</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeRejectReasonBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Reject Request</h3>
                
                <form id="rejectReasonForm">
                    <input type="hidden" id="rejectRequestId">
                    <div class="reject-reason-input-group">
                        <label class="detail-form-label text-danger" style="text-align: left;">Reason for Rejection</label>
                        <textarea id="rejectReasonInput" class="detail-form-control textarea-control" rows="4" placeholder="Please provide a brief explanation for rejecting this request..." required style="border-color: rgba(239, 68, 68, 0.3);"></textarea>
                    </div>
                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-reject">Reject Request</button>
                    </div>
                </form>
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

    <!-- Interactivity Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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

            // Initial requests dataset loaded directly from PHP MySQL query
            let requests = <?php echo json_encode($dbRequests); ?>;

            // DOM Elements
            const tableBody = document.getElementById('requestsTableBody');
            const searchInput = document.getElementById('searchRequests');
            const filterSelect = document.getElementById('filterStatus');

            // Modals
            const detailsModal = document.getElementById('detailsModal');
            const closeDetailsBtn = document.getElementById('closeDetailsBtn');
            const rejectReasonModal = document.getElementById('rejectReasonModal');
            const closeRejectReasonBtn = document.getElementById('closeRejectReasonBtn');
            const rejectReasonForm = document.getElementById('rejectReasonForm');
            const rejectRequestIdInput = document.getElementById('rejectRequestId');
            const rejectReasonInput = document.getElementById('rejectReasonInput');

            // Toast Elements
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Toggle Profile Dropdown
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');

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

            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });
                document.addEventListener('click', () => {
                    dropdownMenu.classList.remove('show');
                });
            }

            // Function to fetch latest requests from backend (live polling & after updates)
            function loadRequests() {
                fetch('requests.php?action=get_requests')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && Array.isArray(data.requests)) {
                            requests = data.requests;
                            renderTable();
                            updateDashboardStats();
                        }
                    })
                    .catch(err => console.error('Error fetching requests:', err));
            }

            // Auto-refresh requests every 5 seconds for seamless automatic updating
            setInterval(loadRequests, 5000);

            // Render Table function
            function renderTable() {
                const query = searchInput.value.toLowerCase().trim();
                const filter = filterSelect.value;
                tableBody.innerHTML = '';

                const filtered = requests.filter(req => {
                    const matchesSearch = (req.user || '').toLowerCase().includes(query) || 
                                          (req.equipment || '').toLowerCase().includes(query) ||
                                          (req.role || '').toLowerCase().includes(query);
                    const matchesFilter = filter === 'all' || (req.status || '').toLowerCase() === filter;
                    return matchesSearch && matchesFilter;
                });

                if (filtered.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="no-requests-message">
                                <i class="fa-solid fa-folder-open"></i>
                                No requests found matching your query.
                            </td>
                        </tr>
                    `;
                    return;
                }

                filtered.forEach(req => {
                    const tr = document.createElement('tr');
                    tr.className = 'admin-table-row';
                    
                    // Click row opens details modal (excluding clicking action buttons)
                    tr.addEventListener('click', (e) => {
                        if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                            openDetailsModal(req.id);
                        }
                    });

                    // Action buttons cells
                    let actionHtml = '';
                    if (req.status.toLowerCase() === 'pending') {
                        actionHtml = `
                            <div class="action-buttons">
                                <button class="btn-approve" onclick="approveRequest(${req.id})">Approve</button>
                                <button class="btn-reject" onclick="triggerRejectReason(${req.id})">Reject</button>
                            </div>
                        `;
                    } else {
                        actionHtml = `
                            <button class="btn-view-only" onclick="openDetailsModal(${req.id})">
                                <i class="fa-solid fa-eye"></i> View
                            </button>
                        `;
                    }

                    // Format status text
                    const statusClass = 'status-' + req.status.toLowerCase();
                    const qtyBadge = req.quantity > 1 ? ` <span style="font-size: 11px; opacity: 0.8;">(${req.quantity} pcs)</span>` : '';

                    tr.innerHTML = `
                        <td>
                            <div class="requester-info">
                                <span class="requester-name">${escapeHTML(req.user)}</span>
                            </div>
                        </td>
                        <td>${escapeHTML(req.role)}</td>
                        <td>${escapeHTML(req.equipment)}${qtyBadge}</td>
                        <td>${escapeHTML(req.date)}</td>
                        <td>
                            <span class="status-badge ${statusClass}">${escapeHTML(req.status)}</span>
                        </td>
                        <td class="action-cell">${actionHtml}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            }

            // Toast helper
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

            // Approve Request function
            window.approveRequest = function(id) {
                if (!confirm('Are you sure you want to approve this equipment request?')) return;

                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('request_id', id);
                formData.append('status', 'Approved');

                fetch('requests.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Request Approved', data.message || 'Request approved successfully!', 'success');
                        detailsModal.classList.remove('show');
                        loadRequests();
                    } else {
                        showNotification('Action Failed', data.message || 'Failed to approve request.', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Error', 'Network error occurred.', 'error');
                });
            };

            // Trigger Rejection Reason modal
            window.triggerRejectReason = function(id) {
                rejectRequestIdInput.value = id;
                rejectReasonInput.value = '';
                rejectReasonModal.classList.add('show');
            };

            // Rejection reason form submission
            rejectReasonForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const id = parseInt(rejectRequestIdInput.value);
                const reason = rejectReasonInput.value.trim();

                if (!id || !reason) {
                    showNotification('Input Error', 'Please provide a valid rejection reason.', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('request_id', id);
                formData.append('status', 'Rejected');
                formData.append('reject_reason', reason);

                fetch('requests.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Request Rejected', data.message || 'Request rejected.', 'error');
                        rejectReasonModal.classList.remove('show');
                        detailsModal.classList.remove('show');
                        loadRequests();
                    } else {
                        showNotification('Action Failed', data.message || 'Failed to reject request.', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Error', 'Network error occurred.', 'error');
                });
            });

            // Open Details Modal
            window.openDetailsModal = function(id) {
                const req = requests.find(r => r.id === id);
                if (!req) return;

                // Populate modal user details
                document.getElementById('modalUserAvatar').src = req.avatar || "https://ui-avatars.com/api/?name=" + encodeURIComponent(req.user);
                document.getElementById('modalUserName').textContent = req.user;
                document.getElementById('modalUserRole').textContent = req.role;

                document.getElementById('modalEqImg').src = req.img;
                
                const statusBadge = document.getElementById('modalStatusBadge');
                statusBadge.textContent = req.status;
                statusBadge.className = 'status-badge status-' + req.status.toLowerCase();

                document.getElementById('modalEqName').value = req.equipment;
                document.getElementById('modalEqCategory').value = req.category;
                document.getElementById('modalEqQty').value = req.quantity + ' pc(s)';
                document.getElementById('modalReqDate').value = req.fullDate;
                document.getElementById('modalBorrowDate').value = req.borrowDate;
                document.getElementById('modalDueDate').value = req.dueDate;
                document.getElementById('modalPurpose').value = req.purpose;
                document.getElementById('modalNotes').value = req.notes || 'None';

                const reasonGroup = document.getElementById('modalReasonGroup');
                if (req.status.toLowerCase() === 'rejected') {
                    document.getElementById('modalReason').value = req.rejectReason || 'No reason provided.';
                    reasonGroup.style.display = 'block';
                } else {
                    reasonGroup.style.display = 'none';
                }

                // Populate action buttons in footer
                const footer = document.getElementById('modalActionsFooter');
                if (req.status.toLowerCase() === 'pending') {
                    footer.innerHTML = `
                        <button class="btn-modal-close" id="modalCancelBtn">Close</button>
                        <button class="btn-modal-reject-trigger" onclick="triggerRejectReason(${req.id})">Reject</button>
                        <button class="btn-modal-approve" onclick="approveRequest(${req.id})">Approve</button>
                    `;
                } else {
                    footer.innerHTML = `
                        <button class="btn-modal-close" style="width: 100%;" id="modalCancelBtn">Close</button>
                    `;
                }

                // Add close listener for modal close button
                document.getElementById('modalCancelBtn').addEventListener('click', () => {
                    detailsModal.classList.remove('show');
                });

                detailsModal.classList.add('show');
            };

            // Close modals functions
            closeDetailsBtn.addEventListener('click', () => {
                detailsModal.classList.remove('show');
            });
            detailsModal.addEventListener('click', (e) => {
                if (e.target === detailsModal) {
                    detailsModal.classList.remove('show');
                }
            });

            closeRejectReasonBtn.addEventListener('click', () => {
                rejectReasonModal.classList.remove('show');
            });
            rejectReasonModal.addEventListener('click', (e) => {
                if (e.target === rejectReasonModal) {
                    rejectReasonModal.classList.remove('show');
                }
            });

            // Helper to escape HTML values
            function escapeHTML(str) {
                if (!str) return '';
                return str.replace(/[&<>'"]/g, 
                    tag => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    }[tag] || tag)
                );
            }

            // Sync stats to dashboard in localStorage
            function updateDashboardStats() {
                const pendingCount = requests.filter(r => r.status.toLowerCase() === 'pending').length;
                localStorage.setItem('dashboard-pending-count', pendingCount);
            }

            // Event Listeners for search & filters
            searchInput.addEventListener('input', renderTable);
            filterSelect.addEventListener('change', renderTable);

            // Initial render
            renderTable();
            updateDashboardStats();
        });
    </script>
</body>
</html>
