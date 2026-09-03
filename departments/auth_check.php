<?php
// Prevent browser caching of protected department routes
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Function to safely clear department session keys & redirect to login page
function redirect_unauthorized_dept() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['dept_acc_id']);
        unset($_SESSION['dept_name']);
        unset($_SESSION['dept_email']);
        unset($_SESSION['dept_role']);
    }
    header("Location: ../login.php");
    exit;
}

// 1. Strict Session Check: Department user MUST have a valid dept_acc_id
if (
    empty($_SESSION['dept_acc_id']) || 
    !is_numeric($_SESSION['dept_acc_id']) || 
    (int)$_SESSION['dept_acc_id'] <= 0
) {
    redirect_unauthorized_dept();
}

// 2. Database Verification: Check that the department account record actually exists and load assigned department details
$dept_acc_id = (int)$_SESSION['dept_acc_id'];
$stmtCheckDept = $conn->prepare("
    SELECT da.dept_acc_id, da.email, da.full_name, da.role, da.department_id, da.profile_image,
           d.department_name, d.department_code
    FROM department_account da
    LEFT JOIN department d ON da.department_id = d.department_id
    WHERE da.dept_acc_id = ?
");
if (!$stmtCheckDept) {
    redirect_unauthorized_dept();
}

$stmtCheckDept->bind_param("i", $dept_acc_id);
$stmtCheckDept->execute();
$deptRes = $stmtCheckDept->get_result();

if (!$deptRes || $deptRes->num_rows === 0) {
    redirect_unauthorized_dept();
}

// 3. Refresh & Sync Department Session State
$currentDeptData = $deptRes->fetch_assoc();
$dept_name  = !empty($currentDeptData['full_name']) ? $currentDeptData['full_name'] : 'Department';
$dept_email = !empty($currentDeptData['email']) ? $currentDeptData['email'] : 'department@equiptrack.edu';
$dept_id    = !empty($currentDeptData['department_id']) ? (int)$currentDeptData['department_id'] : 0;
$assigned_dept_name  = !empty($currentDeptData['department_name']) ? $currentDeptData['department_name'] : 'Department Office';
$assigned_dept_code  = !empty($currentDeptData['department_code']) ? $currentDeptData['department_code'] : '';
$dept_profile_image  = !empty($currentDeptData['profile_image']) ? $currentDeptData['profile_image'] : null;

unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_username'], $_SESSION['admin_employee_id'], $_SESSION['admin_role']);
unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name'], $_SESSION['email']);

$_SESSION['dept_acc_id']       = $currentDeptData['dept_acc_id'];
$_SESSION['department_id']     = $dept_id;
$_SESSION['dept_name']         = $dept_name;
$_SESSION['dept_email']        = $dept_email;
$_SESSION['dept_role']         = !empty($currentDeptData['role']) ? $currentDeptData['role'] : 'Department';
$_SESSION['dept_profile_image'] = $dept_profile_image;

// Update last_online timestamp in database
$stmtUpdOnline = $conn->prepare("UPDATE department_account SET last_online = NOW() WHERE dept_acc_id = ?");
if ($stmtUpdOnline) {
    $stmtUpdOnline->bind_param("i", $dept_acc_id);
    $stmtUpdOnline->execute();
}

// Compute department initials for avatar
$nameParts = explode(' ', trim($dept_name));
$initials  = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
}
$dept_initials = !empty($initials) ? substr($initials, 0, 2) : 'DP';

