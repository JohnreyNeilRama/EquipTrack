<?php
require_once __DIR__ . '/auth_check.php';

// Ensure department_account table structure exists
$conn->query("CREATE TABLE IF NOT EXISTS department_account (
    dept_acc_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    employee_id VARCHAR(100) NULL,
    role VARCHAR(100) DEFAULT 'Department Head',
    department_id INT NULL,
    profile_image VARCHAR(255) NULL,
    last_online DATETIME NULL,
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$serverMsg = null;
$serverMsgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_student') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $idNumber  = trim($_POST['id_number'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '1st Year');
        $email     = trim($_POST['email'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $deptName  = trim($_POST['department'] ?? 'Information Technology Department');
        $password  = $_POST['password'] ?? 'student123';
        
        $deptId = 1;
        $stmtDept = $conn->prepare("SELECT department_id FROM department WHERE department_name = ? LIMIT 1");
        if ($stmtDept) {
            $stmtDept->bind_param("s", $deptName);
            $stmtDept->execute();
            $resD = $stmtDept->get_result();
            if ($resD && $rowD = $resD->fetch_assoc()) {
                $deptId = (int)$rowD['department_id'];
            }
        }
        
        $stmtChk = $conn->prepare("SELECT user_id FROM user_account WHERE email = ?");
        $stmtChk->bind_param("s", $email);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows > 0) {
            $serverMsg = "An account with email '$email' already exists.";
            $serverMsgType = "error";
        } else {
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);
            $role = "Student";
            $stmtInsUser = $conn->prepare("INSERT INTO user_account (role, email, password, department_id) VALUES (?, ?, ?, ?)");
            $stmtInsUser->bind_param("sssi", $role, $email, $hashedPass, $deptId);
            if ($stmtInsUser->execute()) {
                $newUserId = $conn->insert_id;
                $stmtInsStud = $conn->prepare("INSERT INTO student (user_id, first_name, last_name, id_number, year_level, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtInsStud->bind_param("isssss", $newUserId, $firstName, $lastName, $idNumber, $yearLevel, $address);
                $stmtInsStud->execute();
                $serverMsg = "Student account for '$firstName $lastName' created successfully!";
                $serverMsgType = "success";
            } else {
                $serverMsg = "Failed to create student account: " . $conn->error;
                $serverMsgType = "error";
            }
        }
    } elseif ($action === 'add_department') {
        $fullName   = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $employeeId = trim($_POST['employee_id'] ?? '');
        $role       = trim($_POST['role'] ?? 'Department Head');
        $deptId     = (int)($_POST['department_id'] ?? 0);
        $password   = $_POST['password'] ?? 'dept123';

        if (empty($fullName) || empty($email) || empty($employeeId) || empty($role) || empty($deptId)) {
            $serverMsg = "All fields (Full Name, Email, Employee ID, Role, Department) are required.";
            $serverMsgType = "error";
        } else {
            $stmtChk = $conn->prepare("SELECT dept_acc_id FROM department_account WHERE email = ? OR employee_id = ?");
            if ($stmtChk) {
                $stmtChk->bind_param("ss", $email, $employeeId);
                $stmtChk->execute();
                if ($stmtChk->get_result()->num_rows > 0) {
                    $serverMsg = "A department account with this email or employee ID already exists.";
                    $serverMsgType = "error";
                } else {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmtInsDept = $conn->prepare("INSERT INTO department_account (full_name, email, employee_id, role, department_id, password) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmtInsDept) {
                        $stmtInsDept->bind_param("ssssis", $fullName, $email, $employeeId, $role, $deptId, $hashedPass);
                        if ($stmtInsDept->execute()) {
                            $serverMsg = "Department account for '$fullName' created successfully!";
                            $serverMsgType = "success";
                        } else {
                            $serverMsg = "Failed to create department account: " . $conn->error;
                            $serverMsgType = "error";
                        }
                    } else {
                        $serverMsg = "Database error: " . $conn->error;
                        $serverMsgType = "error";
                    }
                }
            }
        }
    } elseif ($action === 'delete_user') {
        $deleteId = (int)($_POST['user_id'] ?? 0);
        $userType = $_POST['user_type'] ?? 'user';
        if ($userType === 'department') {
            $actualId = $deleteId > 100000 ? ($deleteId - 100000) : $deleteId;
            $stmtDel = $conn->prepare("DELETE FROM department_account WHERE dept_acc_id = ?");
            $stmtDel->bind_param("i", $actualId);
            $stmtDel->execute();
            $serverMsg = "Department account deleted successfully.";
            $serverMsgType = "success";
        } else {
            $stmtDelS = $conn->prepare("DELETE FROM student WHERE user_id = ?");
            $stmtDelS->bind_param("i", $deleteId);
            $stmtDelS->execute();

            $stmtDelF = $conn->prepare("DELETE FROM faculty_member WHERE user_id = ?");
            $stmtDelF->bind_param("i", $deleteId);
            $stmtDelF->execute();

            $stmtDelU = $conn->prepare("DELETE FROM user_account WHERE user_id = ?");
            $stmtDelU->bind_param("i", $deleteId);
            $stmtDelU->execute();
            $serverMsg = "User account deleted successfully.";
            $serverMsgType = "success";
        }
    } elseif ($action === 'update_profile_image') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $targetUserType = $_POST['target_user_type'] ?? 'user_account';
        
        if (isset($_FILES['admin_profile_img_file']) && $_FILES['admin_profile_img_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['admin_profile_img_file'];
            $fileMime = mime_content_type($file['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            
            if (in_array($fileMime, $allowedMimes) && $file['size'] <= 5 * 1024 * 1024) {
                $binaryData = file_get_contents($file['tmp_name']);
                $base64Data = 'data:' . $fileMime . ';base64,' . base64_encode($binaryData);
                
                if ($targetUserType === 'department') {
                    $conn->query("ALTER TABLE department_account MODIFY COLUMN profile_image LONGTEXT DEFAULT NULL");
                    $actualId = $targetUserId > 100000 ? ($targetUserId - 100000) : $targetUserId;
                    $stmtUpd = $conn->prepare("UPDATE department_account SET profile_image = ? WHERE dept_acc_id = ?");
                    $stmtUpd->bind_param("si", $base64Data, $actualId);
                    $stmtUpd->execute();
                } else {
                    $conn->query("ALTER TABLE user_account MODIFY COLUMN profile_image LONGTEXT DEFAULT NULL");
                    $stmtUpd = $conn->prepare("UPDATE user_account SET profile_image = ? WHERE user_id = ?");
                    $stmtUpd->bind_param("si", $base64Data, $targetUserId);
                    $stmtUpd->execute();
                }
                $serverMsg = "Profile picture updated and saved to database successfully!";
                $serverMsgType = "success";
            } else {
                $serverMsg = "Invalid image file or file size exceeds 5MB limit.";
                $serverMsgType = "error";
            }
        }
    } elseif ($action === 'toggle_status') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $targetUserType = $_POST['target_user_type'] ?? 'user_account';
        $newStatus = ($_POST['new_status'] === 'Deactivated') ? 'Deactivated' : 'Active';
        
        if ($targetUserType === 'department') {
            $actualId = $targetUserId > 100000 ? ($targetUserId - 100000) : $targetUserId;
            $stmtUpd = $conn->prepare("UPDATE department_account SET status = ? WHERE dept_acc_id = ?");
            $stmtUpd->bind_param("si", $newStatus, $actualId);
            $stmtUpd->execute();
        } else {
            $stmtUpd = $conn->prepare("UPDATE user_account SET status = ? WHERE user_id = ?");
            $stmtUpd->bind_param("si", $newStatus, $targetUserId);
            $stmtUpd->execute();
        }
        $serverMsg = "User account status updated to '$newStatus' successfully!";
        $serverMsgType = "success";
    }
}

// Fetch all database users
$dbUsers = [];

// 1. Fetch Students & Faculty from user_account
$sqlUsers = "
    SELECT 
        u.user_id AS id,
        u.role,
        u.email,
        u.department_id,
        u.date_created,
        u.last_online,
        u.profile_image,
        u.status,
        s.first_name AS s_first_name,
        s.last_name AS s_last_name,
        s.id_number AS s_id_number,
        s.year_level AS s_year_level,
        s.address AS s_address,
        f.first_name AS f_first_name,
        f.last_name AS f_lname,
        f.faculty_id_number AS f_id_number,
        f.address AS f_address,
        d.department_name
    FROM user_account u
    LEFT JOIN student s ON u.user_id = s.user_id
    LEFT JOIN faculty_member f ON u.user_id = f.user_id
    LEFT JOIN department d ON u.department_id = d.department_id
    ORDER BY u.user_id DESC
";

$resUsers = $conn->query($sqlUsers);
if ($resUsers) {
    while ($row = $resUsers->fetch_assoc()) {
        $isStudent = (strtolower($row['role']) === 'student');
        $firstName = $isStudent ? ($row['s_first_name'] ?? '') : ($row['f_first_name'] ?? '');
        $lastName  = $isStudent ? ($row['s_last_name'] ?? '') : ($row['f_lname'] ?? '');
        $idNumber  = $isStudent ? ($row['s_id_number'] ?? '') : ($row['f_id_number'] ?? '');
        $address   = $isStudent ? ($row['s_address'] ?? '') : ($row['f_address'] ?? '');
        $yearLevel = $isStudent ? ($row['s_year_level'] ?? 'N/A') : 'N/A';
        $departmentName = !empty($row['department_name']) ? $row['department_name'] : 'College of Computer Studies';

        if (empty($firstName) && empty($lastName)) {
            $nameParts = explode('@', $row['email']);
            $firstName = ucfirst($nameParts[0]);
            $lastName  = '';
        }
        if (empty($idNumber)) {
            $idNumber = 'USR-' . sprintf('%04d', $row['id']);
        }

        $createdAtFormatted = 'Database Record';
        if (!empty($row['date_created'])) {
            $createdAtFormatted = date('M j, Y, g:i A', strtotime($row['date_created']));
        }

        $lastOnlineFormatted = 'Offline / Never';
        if (!empty($row['last_online'])) {
            $lastOnlineFormatted = date('M j, Y, g:i A', strtotime($row['last_online']));
        }

        $userStatus = !empty($row['status']) ? $row['status'] : 'Active';

        $dbUsers[] = [
            'id'            => (int)$row['id'],
            'db_type'       => 'user_account',
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'id_number'     => $idNumber,
            'role'          => strtolower($row['role']) === 'faculty' ? 'teacher' : strtolower($row['role']),
            'year_level'    => $yearLevel,
            'email'         => $row['email'],
            'address'       => $address,
            'department'    => $departmentName,
            'status'        => $userStatus,
            'username'      => explode('@', $row['email'])[0],
            'created_at'    => $createdAtFormatted,
            'last_online'   => $lastOnlineFormatted,
            'profile_image' => !empty($row['profile_image']) ? $row['profile_image'] : null
        ];
    }
}

// 2. Fetch Department accounts from department_account joined with department table
$sqlDepts = "
    SELECT 
        da.*,
        d.department_name,
        d.department_code
    FROM department_account da
    LEFT JOIN department d ON da.department_id = d.department_id
    ORDER BY da.dept_acc_id DESC
";
$resDepts = $conn->query($sqlDepts);
if ($resDepts) {
    while ($row = $resDepts->fetch_assoc()) {
        $fullName = !empty($row['full_name']) ? $row['full_name'] : 'Department Account';
        $empId    = !empty($row['employee_id']) ? $row['employee_id'] : ('EMP-' . $row['dept_acc_id']);
        $email    = !empty($row['email']) ? $row['email'] : 'dept@equiptrack.edu';
        $deptName = !empty($row['department_name']) ? $row['department_name'] : 'Department Office';
        $roleName = !empty($row['role']) ? $row['role'] : 'Department Head';

        $createdAtFormatted = 'Database Record';
        if (!empty($row['created_at'])) {
            $createdAtFormatted = date('M j, Y, g:i A', strtotime($row['created_at']));
        }

        $lastOnlineFormatted = 'Offline / Never';
        if (!empty($row['last_online'])) {
            $lastOnlineFormatted = date('M j, Y, g:i A', strtotime($row['last_online']));
        }

        $deptStatus = !empty($row['status']) ? $row['status'] : 'Active';

        $dbUsers[] = [
            'id'            => 100000 + (int)$row['dept_acc_id'],
            'db_type'       => 'department',
            'first_name'    => $fullName,
            'last_name'     => '',
            'id_number'     => $empId,
            'role'          => strtolower($roleName),
            'year_level'    => 'N/A',
            'email'         => $email,
            'address'       => $deptName,
            'department'    => $deptName,
            'status'        => $deptStatus,
            'username'      => !empty($email) ? explode('@', $email)[0] : 'dept',
            'employee_id'   => $empId,
            'department_id' => $row['department_id'],
            'created_at'    => $createdAtFormatted,
            'last_online'   => $lastOnlineFormatted,
            'profile_image' => !empty($row['profile_image']) ? $row['profile_image'] : null
        ];
    }
}

// 3. Fetch departments list for select options in modals
$departmentsList = [];
$dListRes = $conn->query("SELECT department_id, department_name, department_code FROM department ORDER BY department_name ASC");
if ($dListRes) {
    while ($dRow = $dListRes->fetch_assoc()) {
        $departmentsList[] = $dRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - User Management</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/adminequipment.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/adminusers.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
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
            <a href="profile.php" class="nav-item">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item active">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($admin_initials); ?></div>
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

        <div class="users-container" style="min-width: 0; max-width: 100%;">
            <!-- Header Section -->
            <div class="equipment-header-section">
                <h2>User Management</h2>
                <p>Manage registered users and departments.</p>
            </div>

            <!-- Summary Cards Section -->
            <div class="admin-section" style="margin-bottom: 8px; min-width: 0; width: 100%; overflow: hidden;">
                <h4 class="admin-section-heading">Summary Cards</h4>
                <div class="stats-scroll-container">
                <div class="stat-card-mini">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Users</span>
                        <div class="stat-icon-wrapper info">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <span class="stat-card-value" id="statTotalUsers">0</span>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Students</span>
                        <div class="stat-icon-wrapper success">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                    </div>
                    <span class="stat-card-value" id="statStudents">0</span>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Faculty Members</span>
                        <div class="stat-icon-wrapper warning">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                    </div>
                    <span class="stat-card-value" id="statFaculty">0</span>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Departments</span>
                        <div class="stat-icon-wrapper primary">
                            <i class="fa-solid fa-building"></i>
                        </div>
                    </div>
                    <span class="stat-card-value" id="statDepartments">0</span>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Active Accounts</span>
                        <div class="stat-icon-wrapper info" style="background-color: rgba(16, 185, 129, 0.08); color: #10b981;">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                    <span class="stat-card-value" id="statActive">0</span>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Deactivated Accounts</span>
                        <div class="stat-icon-wrapper danger">
                            <i class="fa-solid fa-user-slash"></i>
                        </div>
                    </div>
                    <span class="stat-card-value" id="statDeactivated">0</span>
                </div>
            </div>
            </div>

            <!-- Controls bar (Search, Filters & Actions) -->
            <div class="controls-bar">
                <div class="controls-left">
                    <div class="search-box-wrapper">
                        <input type="text" id="searchUsers" placeholder="Search by name, ID or email...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="filter-select-wrapper">
                        <select id="filterRole">
                            <option value="all">All Roles</option>
                            <option value="student">Student</option>
                            <option value="teacher">Faculty Member</option>
                            <option value="department">Department</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
                <div class="controls-right-buttons">
                    <button class="btn-add-student" id="addStudentBtn">
                        <i class="fa-solid fa-graduation-cap"></i> Add Student
                    </button>
                    <button class="btn-add-department" id="addDepartmentBtn">
                        <i class="fa-solid fa-building"></i> Add Department
                    </button>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="table-container card admin-table-card">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>ID Number</th>
                            <th>Role</th>
                            <th>Academic Info</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th class="action-column" style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>

                <!-- Empty state illustration -->
                <div id="usersEmptyState" class="empty-state-container" style="display: none;">
                    <i class="fa-solid fa-users-slash empty-state-icon"></i>
                    <h4>No users found</h4>
                    <p>Try adjusting your search criteria or filters.</p>
                </div>

                <!-- Pagination Footer -->
                <div class="pagination-container" id="paginationContainer">
                    <span class="pagination-info" id="paginationInfo">Showing 1 to 5 of 5 entries</span>
                    <div class="pagination-buttons" id="paginationButtons">
                        <!-- Buttons added dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Student Modal -->
    <div class="modal-overlay" id="studentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Register a new student account in the system</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeStudentModalBtn">&times;</button>
                <h3 class="modal-title-center">Add Student Account</h3>
                
                <form id="studentForm" action="users.php" method="POST" class="new-modal-form" style="padding-top: 10px;">
                    <input type="hidden" name="action" value="add_student">
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>First Name</label>
                            <input type="text" id="studFirstName" name="first_name" class="form-control-flat" required placeholder="Gabriel">
                        </div>
                        <div class="form-group-flat">
                            <label>Last Name</label>
                            <input type="text" id="studLastName" name="last_name" class="form-control-flat" required placeholder="Fernandez">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Student ID Number</label>
                            <input type="text" id="studIdNumber" name="id_number" class="form-control-flat" required placeholder="e.g., 20230123" pattern="\d{8}" title="Student ID must be exactly 8 digits." maxlength="8" inputmode="numeric">
                        </div>
                        <div class="form-group-flat">
                            <label>Year & Level</label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="studYearLevel" name="year_level" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year" selected>3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Department</label>
                        <div class="flat-select-wrapper" style="width: 100%;">
                            <select id="studDepartment" name="department" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                <option value="" disabled selected>Select Department</option>
                                <option value="Information Technology Department">Information Technology Department</option>
                                <option value="Engineering Department">Engineering Department</option>
                                <option value="Education Department">Education Department</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Email Address</label>
                        <input type="email" id="studEmail" name="email" class="form-control-flat" required placeholder="gabriel.fernandez@example.com">
                    </div>

                    <div class="form-group-flat">
                        <label>Home Address</label>
                        <input type="text" id="studAddress" name="address" class="form-control-flat" required placeholder="123 University Ave, Tech City">
                    </div>

                    <div class="form-group-flat">
                        <label>Password</label>
                        <input type="password" id="studPassword" name="password" class="form-control-flat" required placeholder="••••••••" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit-request" style="margin-top: 10px;">
                        Create Student Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal-overlay" id="departmentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Register a new department account in the system</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDeptModalBtn">&times;</button>
                <h3 class="modal-title-center">Add Department Account</h3>
                
                <form id="departmentForm" action="users.php" method="POST" class="new-modal-form" style="padding-top: 10px;">
                    <input type="hidden" name="action" value="add_department">
                    
                    <div class="form-group-flat">
                        <label>Full Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="deptFullName" name="full_name" class="form-control-flat" required placeholder="e.g., Dr. Jane Doe">
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Employee ID <span style="color: #ef4444;">*</span></label>
                            <input type="text" id="deptEmployeeId" name="employee_id" class="form-control-flat" required placeholder="e.g., EMP-2026-001">
                        </div>
                        <div class="form-group-flat">
                            <label>Email Address <span style="color: #ef4444;">*</span></label>
                            <input type="email" id="deptEmail" name="email" class="form-control-flat" required placeholder="e.g., jane.doe@equiptrack.edu">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Role <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="deptRole" name="role" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="Department Head" selected>Department Head</option>
                                    <option value="Department Staff">Department Staff</option>
                                    <option value="Department Admin">Department Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group-flat">
                            <label>Department <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="deptDepartmentId" name="department_id" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="" disabled selected>Select Department</option>
                                    <?php foreach ($departmentsList as $dItem): ?>
                                        <option value="<?php echo (int)$dItem['department_id']; ?>">
                                            <?php echo htmlspecialchars($dItem['department_name'] . ($dItem['department_code'] ? ' (' . $dItem['department_code'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" id="deptPassword" name="password" class="form-control-flat" required placeholder="••••••••" minlength="6" value="dept123">
                    </div>

                    <button type="submit" class="btn-submit-request" style="margin-top: 10px;">
                        Create Department Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal-overlay" id="userDetailsModal">
        <div class="modal-card eq-modal-card" style="max-width: 800px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user account information</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeUserDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">User Details</h3>
                
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
                        <div class="detail-img-container" style="position: relative;">
                            <img src="" alt="User Profile Image" id="modalUserProfileImg">
                            <button type="button" class="btn-upload-icon" id="adminUploadPicBtn" style="position: absolute; bottom: 8px; right: 8px; background: #385585; color: #fff; border: none; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.3);" title="Upload / Change Profile Picture">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                            <input type="file" id="adminProfilePicInput" style="display: none;" accept="image/*">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">Active</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Full Name</label>
                                <input type="text" id="modalFullName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">ID Number</label>
                                <input type="text" id="modalStudentId" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Year Level</label>
                                <input type="text" id="modalYearLevel" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Email</label>
                                <input type="text" id="modalEmail" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Role</label>
                                <input type="text" id="modalRole" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Department</label>
                                <input type="text" id="modalDepartment" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Date Created</label>
                                <input type="text" id="modalDateCreated" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Last Online</label>
                                <input type="text" id="modalLastOnline" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address / Location Info Section -->
                <div class="detail-form-group" style="margin-bottom: 16px;">
                    <label class="detail-form-label">Address</label>
                    <input type="text" id="modalAddress" class="detail-form-control" readonly>
                </div>

                <!-- Modal Actions Footer -->
                <div class="modal-actions-footer" style="display: flex; gap: 12px; align-items: center;">
                    <button type="button" id="modalToggleStatusBtn" class="btn-modal-toggle-status" style="flex: 1; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: background 0.2s ease, transform 0.1s ease;">Deactivate Account</button>
                    <button type="button" class="btn-modal-close" style="flex: 1;" id="modalCloseDetailsBtn">Close</button>
                </div>
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

    <!-- JavaScript logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Navbar Avatar Sync Helper
            function syncNavbarAvatar() {
                const savedAvatar = localStorage.getItem('admin-avatar-src');
                const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');
                navAvatars.forEach(navAvatar => {
                    if (savedAvatar) {
                        navAvatar.innerHTML = `<img src="${savedAvatar}" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                        navAvatar.style.padding = '0';
                        navAvatar.style.background = 'transparent';
                    }
                });
            }
            syncNavbarAvatar();

            window.addEventListener('storage', function(e) {
                if (e.key === 'admin-avatar-src') {
                    syncNavbarAvatar();
                }
            });

            // DOM Elements
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Search, Filter & Sort inputs
            const searchInput = document.getElementById('searchUsers');
            const filterRole = document.getElementById('filterRole');
            const tableBody = document.getElementById('usersTableBody');
            const emptyState = document.getElementById('usersEmptyState');

            // Modals & Forms
            const studentModal = document.getElementById('studentModal');
            const addStudentBtn = document.getElementById('addStudentBtn');
            const closeStudentModalBtn = document.getElementById('closeStudentModalBtn');
            const studentForm = document.getElementById('studentForm');

            const departmentModal = document.getElementById('departmentModal');
            const addDepartmentBtn = document.getElementById('addDepartmentBtn');
            const closeDeptModalBtn = document.getElementById('closeDeptModalBtn');
            const departmentForm = document.getElementById('departmentForm');

            // User Details Modal DOM Elements
            const userDetailsModal = document.getElementById('userDetailsModal');
            const closeUserDetailsBtn = document.getElementById('closeUserDetailsBtn');
            const modalCloseDetailsBtn = document.getElementById('modalCloseDetailsBtn');
            const modalUserAvatar = document.getElementById('modalUserAvatar');
            const modalUserName = document.getElementById('modalUserName');
            const modalUserRole = document.getElementById('modalUserRole');
            const modalUserProfileImg = document.getElementById('modalUserProfileImg');
            const modalStatusBadge = document.getElementById('modalStatusBadge');
            const modalFullName = document.getElementById('modalFullName');
            const modalRole = document.getElementById('modalRole');
            const modalDepartment = document.getElementById('modalDepartment');
            const modalStudentId = document.getElementById('modalStudentId');
            const modalYearLevel = document.getElementById('modalYearLevel');
            const modalUsername = document.getElementById('modalUsername');
            const modalEmail = document.getElementById('modalEmail');
            const modalDateCreated = document.getElementById('modalDateCreated');
            const modalLastLogin = document.getElementById('modalLastLogin');
            const modalAddress = document.getElementById('modalAddress');

            // Pagination Elements
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            // Initial users loaded directly from MySQL database tables
            let users = <?php echo json_encode($dbUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            // Pagination state
            let currentPage = 1;
            const pageSize = 5;
            let filteredUsers = [];

            // Display Toast Notifications
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

            <?php if (!empty($serverMsg)): ?>
            showNotification(
                <?php echo json_encode($serverMsgType === 'success' ? 'Success' : 'Error'); ?>,
                <?php echo json_encode($serverMsg); ?>,
                <?php echo json_encode($serverMsgType); ?>
            );
            <?php endif; ?>

            // Render table based on current page, search, filter and sort
            function renderUsersTable() {
                const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
                const role = filterRole ? filterRole.value : 'all';

                // 1. Filter
                filteredUsers = users.filter(user => {
                    if (!user) return false;
                    const firstName = user.first_name || '';
                    const lastName = user.last_name || '';
                    const fullName = `${firstName} ${lastName}`.trim().toLowerCase();
                    const idNum = (user.id_number || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    const userRole = (user.role || '').toLowerCase();

                    const matchesSearch = fullName.includes(query) || 
                                          idNum.includes(query) || 
                                          email.includes(query);
                    const matchesRole = role === 'all' || userRole === role || (role === 'department' && user.db_type === 'department');
                    return matchesSearch && matchesRole;
                });

                // 2. Sort (Default by Name A-Z)
                filteredUsers.sort((a, b) => {
                    const valA = `${a.first_name || ''} ${a.last_name || ''}`.trim().toLowerCase();
                    const valB = `${b.first_name || ''} ${b.last_name || ''}`.trim().toLowerCase();
                    return valA.localeCompare(valB);
                });

                // 3. Paginate
                const totalEntries = filteredUsers.length;
                const totalPages = Math.ceil(totalEntries / pageSize);
                
                // Adjust current page if it is out of range after filtering
                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const paginatedUsers = filteredUsers.slice(startIndex, endIndex);

                // Render Table rows
                tableBody.innerHTML = '';

                if (paginatedUsers.length === 0) {
                    emptyState.style.display = 'flex';
                    document.querySelector('.table-container table').style.display = 'none';
                    paginationContainer.style.display = 'none';
                } else {
                    emptyState.style.display = 'none';
                    document.querySelector('.table-container table').style.display = 'table';
                    paginationContainer.style.display = 'flex';

                    paginatedUsers.forEach(user => {
                        const tr = document.createElement('tr');
                        tr.className = 'admin-table-row';
                        
                        // Row click opens the User Details modal
                        tr.addEventListener('click', (e) => {
                            if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                                openUserDetailsModal(user.id);
                            }
                        });

                        const isStudent = user.role.toLowerCase() === 'student';
                        const details = isStudent ? user.year_level : 'N/A';
                        let roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                        if (user.role.toLowerCase() === 'teacher') {
                            roleLabel = 'Faculty Member';
                        }
                        const statusClass = 'status-' + user.status.toLowerCase();
                        const toggleText = user.status.toLowerCase() === 'active' ? 'Deactivate' : 'Activate';
                        const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;

                        tr.innerHTML = `
                            <td>
                                <div class="user-details-cell">
                                    <span class="user-name-text">${escapeHTML(fullName)}</span>
                                </div>
                            </td>
                            <td><strong>${escapeHTML(user.id_number)}</strong></td>
                            <td><span style="font-weight: 500;">${roleLabel}</span></td>
                            <td>${escapeHTML(details)}</td>
                            <td>${escapeHTML(user.department || 'N/A')}</td>
                            <td><span class="status-badge ${statusClass}">${user.status}</span></td>
                            <td class="action-cell" style="text-align: center;">
                                <div class="action-buttons" style="justify-content: center; gap: 8px;">
                                    <button class="btn-action-toggle" onclick="toggleUserStatus(${user.id})">${toggleText}</button>
                                    <button class="btn-action-delete" onclick="deleteUser(${user.id})"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });

                    // Update Pagination UI
                    paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                    renderPaginationButtons(totalPages);
                }
                updateSummaryStats();
            }

            // Update Summary Stats Section Cards dynamically
            function updateSummaryStats() {
                const totalUsers = users.length;
                const students = users.filter(u => u.role.toLowerCase() === 'student').length;
                const faculty = users.filter(u => u.role.toLowerCase() === 'teacher' || u.role.toLowerCase() === 'faculty' || u.role.toLowerCase() === 'faculty member').length;
                const departments = users.filter(u => u.db_type === 'department' || u.role.toLowerCase().includes('department')).length;
                const active = users.filter(u => u.status.toLowerCase() === 'active').length;
                const deactivated = users.filter(u => u.status.toLowerCase() === 'inactive' || u.status.toLowerCase() === 'deactivated').length;

                const statTotalUsers = document.getElementById('statTotalUsers');
                const statStudents = document.getElementById('statStudents');
                const statFaculty = document.getElementById('statFaculty');
                const statDepartments = document.getElementById('statDepartments');
                const statActive = document.getElementById('statActive');
                const statDeactivated = document.getElementById('statDeactivated');

                if (statTotalUsers) statTotalUsers.textContent = totalUsers;
                if (statStudents) statStudents.textContent = students;
                if (statFaculty) statFaculty.textContent = faculty;
                if (statDepartments) statDepartments.textContent = departments;
                if (statActive) statActive.textContent = active;
                if (statDeactivated) statDeactivated.textContent = deactivated;
            }

            // Render pagination buttons dynamically
            function renderPaginationButtons(totalPages) {
                paginationButtons.innerHTML = '';

                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'btn-page';
                prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
                prevBtn.disabled = currentPage === 1;
                prevBtn.addEventListener('click', () => {
                    currentPage--;
                    renderUsersTable();
                });
                paginationButtons.appendChild(prevBtn);

                // Page number buttons
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderUsersTable();
                    });
                    paginationButtons.appendChild(pageBtn);
                }

                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'btn-page';
                nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.addEventListener('click', () => {
                    currentPage++;
                    renderUsersTable();
                });
                paginationButtons.appendChild(nextBtn);
            }

            // Toggle User Status
            window.toggleUserStatus = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;

                const originalStatus = user.status;
                user.status = originalStatus.toLowerCase() === 'active' ? 'Inactive' : 'Active';
                
                showNotification(
                    user.status === 'Active' ? 'Activated Account' : 'Deactivated Account',
                    `The account for ${user.first_name} ${user.last_name} has been ${user.status.toLowerCase()}.`,
                    'success'
                );
                renderUsersTable();
            };

            // Delete User Account
            window.deleteUser = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;

                const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;
                if (confirm(`Are you sure you want to permanently delete the account of ${fullName}?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'users.php';
                    
                    const actInput = document.createElement('input');
                    actInput.type = 'hidden';
                    actInput.name = 'action';
                    actInput.value = 'delete_user';
                    form.appendChild(actInput);

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'user_id';
                    idInput.value = id;
                    form.appendChild(idInput);

                    const typeInput = document.createElement('input');
                    typeInput.type = 'hidden';
                    typeInput.name = 'user_type';
                    typeInput.value = user.db_type || user.role;
                    form.appendChild(typeInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            };

            let activeModalUser = null;

            // Open User Details Modal
            window.openUserDetailsModal = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;
                activeModalUser = user;

                const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;
                let roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                if (user.role.toLowerCase() === 'teacher') {
                    roleLabel = 'Faculty Member';
                }
                
                // Profile Picture: check database profile_image, avatar, localStorage, or generate fallback avatar
                let avatarUrl = user.profile_image || user.avatar;
                
                // Clear legacy/deleted file paths if present
                if (avatarUrl && avatarUrl.includes('uploads/')) {
                    avatarUrl = null;
                }

                if (!avatarUrl) {
                    const localAvatar = localStorage.getItem('user-avatar-src');
                    if (user.role.toLowerCase() === 'student' && localAvatar && (localAvatar.startsWith('data:') || localAvatar.startsWith('http'))) {
                        avatarUrl = localAvatar;
                    } else {
                        avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=385585&color=fff&size=300&bold=true`;
                    }
                } else if (!avatarUrl.startsWith('http') && !avatarUrl.startsWith('data:') && !avatarUrl.startsWith('../')) {
                    avatarUrl = '../' + avatarUrl.replace(/^\/+/, '');
                }

                const fallbackAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=385585&color=fff&size=300&bold=true`;
                modalUserAvatar.onerror = function() {
                    this.onerror = null;
                    this.src = fallbackAvatar;
                };
                modalUserProfileImg.onerror = function() {
                    this.onerror = null;
                    this.src = fallbackAvatar;
                };

                modalUserAvatar.src = avatarUrl;
                modalUserProfileImg.src = avatarUrl;
                modalUserName.textContent = fullName;
                modalUserRole.textContent = roleLabel;

                // Status Badge
                const statusClass = 'status-' + user.status.toLowerCase();
                modalStatusBadge.textContent = user.status;
                modalStatusBadge.className = 'status-badge ' + statusClass;

                // Form Fields (Full Name, ID Number, Year Level, Email, Address, Date Created, Profile Picture)
                modalFullName.value = fullName;
                modalStudentId.value = user.id_number || 'N/A';
                modalYearLevel.value = user.year_level || 'N/A';
                modalEmail.value = user.email || 'N/A';
                modalRole.value = roleLabel;
                
                if (user.role.toLowerCase() === 'student') {
                    modalDepartment.value = user.department || 'N/A';
                } else if (user.role.toLowerCase() === 'department') {
                    modalDepartment.value = user.department || fullName;
                } else {
                    modalDepartment.value = user.department || 'N/A';
                }

                modalLastOnline.value = user.last_online || 'Offline / Never';
                modalDateCreated.value = user.created_at || 'Database Record';
                modalAddress.value = user.address || 'N/A';

                userDetailsModal.classList.add('show');
            };

            // Admin Profile Picture Upload Event Listeners
            const adminUploadPicBtn = document.getElementById('adminUploadPicBtn');
            const adminProfilePicInput = document.getElementById('adminProfilePicInput');

            if (adminUploadPicBtn && adminProfilePicInput) {
                adminUploadPicBtn.addEventListener('click', () => {
                    adminProfilePicInput.click();
                });

                adminProfilePicInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file && activeModalUser) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'users.php';
                        form.enctype = 'multipart/form-data';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'update_profile_image';
                        form.appendChild(actionInput);

                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'target_user_id';
                        idInput.value = activeModalUser.id;
                        form.appendChild(idInput);

                        const typeInput = document.createElement('input');
                        typeInput.type = 'hidden';
                        typeInput.name = 'target_user_type';
                        typeInput.value = activeModalUser.db_type || activeModalUser.role;
                        form.appendChild(typeInput);

                        const fileInput = e.target.cloneNode(true);
                        fileInput.name = 'admin_profile_img_file';
                        form.appendChild(fileInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // HTML Escaping Helper
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

            // Dark Mode toggler click event
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

            // User dropdown toggler click event
            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });

                document.addEventListener('click', () => {
                    dropdownMenu.classList.remove('show');
                });
            }

            // Form Submissions & Validations
            // Add Student Submit Handler
            studentForm.addEventListener('submit', (e) => {
                const idNumber = document.getElementById('studIdNumber').value.trim();
                const email = document.getElementById('studEmail').value.trim();

                // Validate Student ID (exactly 8 digits)
                if (!/^\d{8}$/.test(idNumber)) {
                    e.preventDefault();
                    showNotification('Validation Error', 'Student ID must be exactly 8 digits.', 'error');
                    return;
                }

                // Validate if email or ID already exists in current loaded database users
                const exists = users.some(u => u.id_number.toLowerCase() === idNumber.toLowerCase() || u.email.toLowerCase() === email.toLowerCase());
                if (exists) {
                    e.preventDefault();
                    showNotification('Registration Error', 'A user with this ID number or email already exists.', 'error');
                    return;
                }
            });

            // Add Department Submit Handler
            departmentForm.addEventListener('submit', (e) => {
                const emailEl = document.getElementById('deptEmail');
                const empIdEl = document.getElementById('deptEmployeeId');

                const email = emailEl ? emailEl.value.trim() : '';
                const employeeId = empIdEl ? empIdEl.value.trim() : '';

                // Validate if email or employee ID already exists
                const exists = users.some(u => 
                    (u.email && u.email.toLowerCase() === email.toLowerCase()) || 
                    (u.id_number && u.id_number.toLowerCase() === employeeId.toLowerCase())
                );
                if (exists) {
                    e.preventDefault();
                    showNotification('Registration Error', 'A department account with this Email or Employee ID already exists.', 'error');
                    return;
                }
            });

            // Listeners for inputs
            searchInput.addEventListener('input', () => {
                currentPage = 1;
                renderUsersTable();
            });

            filterRole.addEventListener('change', () => {
                currentPage = 1;
                renderUsersTable();
            });

            // Open/Close Modals
            addStudentBtn.addEventListener('click', () => {
                studentModal.classList.add('show');
            });

            closeStudentModalBtn.addEventListener('click', () => {
                studentModal.classList.remove('show');
                studentForm.reset();
            });

            addDepartmentBtn.addEventListener('click', () => {
                departmentModal.classList.add('show');
            });

            closeDeptModalBtn.addEventListener('click', () => {
                departmentModal.classList.remove('show');
                departmentForm.reset();
            });

            // Close modals when clicking backdrop
            studentModal.addEventListener('click', (e) => {
                if (e.target === studentModal) {
                    studentModal.classList.remove('show');
                    studentForm.reset();
                }
            });

            departmentModal.addEventListener('click', (e) => {
                if (e.target === departmentModal) {
                    departmentModal.classList.remove('show');
                    departmentForm.reset();
                }
            });

            // Close User Details Modal listeners
            closeUserDetailsBtn.addEventListener('click', () => {
                userDetailsModal.classList.remove('show');
            });
            modalCloseDetailsBtn.addEventListener('click', () => {
                userDetailsModal.classList.remove('show');
            });
            userDetailsModal.addEventListener('click', (e) => {
                if (e.target === userDetailsModal) {
                    userDetailsModal.classList.remove('show');
                }
            });

            // Initial render
            renderUsersTable();
        });
    </script>
</body>
</html>
