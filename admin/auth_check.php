<?php
// Prevent browser caching of protected admin routes
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Function to safely clear session & redirect to login page
function redirect_unauthorized_admin() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        @session_destroy();
    }
    header("Location: ../login.php");
    exit;
}

// 1. Strict Session Check: User MUST have a valid positive integer admin_id and 'Admin' role
if (
    empty($_SESSION['admin_id']) || 
    !is_numeric($_SESSION['admin_id']) || 
    (int)$_SESSION['admin_id'] <= 0 || 
    ($_SESSION['user_role'] ?? '') !== 'Admin'
) {
    redirect_unauthorized_admin();
}

// 2. Database Verification: Check that the admin record actually exists in the database
$admin_id = (int)$_SESSION['admin_id'];
$stmtCheckAdmin = $conn->prepare("SELECT admin_id, name, email, username, employee_id FROM admin WHERE admin_id = ?");
if (!$stmtCheckAdmin) {
    redirect_unauthorized_admin();
}

$stmtCheckAdmin->bind_param("i", $admin_id);
$stmtCheckAdmin->execute();
$adminRes = $stmtCheckAdmin->get_result();

if (!$adminRes || $adminRes->num_rows === 0) {
    // Admin record not found in database
    redirect_unauthorized_admin();
}

// 3. Refresh & Sync Admin Session State
$currentAdminData = $adminRes->fetch_assoc();
$_SESSION['admin_name']        = $currentAdminData['name'];
$_SESSION['admin_email']       = !empty($currentAdminData['email']) ? $currentAdminData['email'] : $currentAdminData['username'];
$_SESSION['admin_username']    = $currentAdminData['username'];
$_SESSION['admin_employee_id'] = $currentAdminData['employee_id'] ?? '';
$_SESSION['user_role']         = 'Admin';

// Variables available to admin page views
$admin_name     = $_SESSION['admin_name'];
$admin_email    = $_SESSION['admin_email'];
$nameParts      = explode(' ', trim($admin_name));
$initials       = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
}
$admin_initials = !empty($initials) ? substr($initials, 0, 2) : 'AD';
