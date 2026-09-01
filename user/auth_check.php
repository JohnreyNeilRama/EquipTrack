<?php
// Prevent browser caching of protected user routes
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Function to safely clear user session keys & redirect to login page
function redirect_unauthorized_user() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        unset($_SESSION['user_name']);
        unset($_SESSION['email']);
    }
    header("Location: ../login.php");
    exit;
}

// 1. Strict Session Check: User MUST have a valid positive integer user_id
if (
    empty($_SESSION['user_id']) || 
    !is_numeric($_SESSION['user_id']) || 
    (int)$_SESSION['user_id'] <= 0
) {
    redirect_unauthorized_user();
}

// 2. Database Verification: Check that the user record actually exists in the database
$user_id = (int)$_SESSION['user_id'];
$stmtCheckUser = $conn->prepare("SELECT u.user_id, u.email, u.role, u.profile_image, u.status,
                                        s.first_name AS s_fname, s.last_name AS s_lname, s.year_level AS s_year_level, s.address AS s_address,
                                        f.first_name AS f_fname, f.last_name AS f_lname, f.address AS f_address
                                 FROM user_account u
                                 LEFT JOIN student s ON u.user_id = s.user_id
                                 LEFT JOIN faculty_member f ON u.user_id = f.user_id
                                 WHERE u.user_id = ?");
if (!$stmtCheckUser) {
    redirect_unauthorized_user();
}

$stmtCheckUser->bind_param("i", $user_id);
$stmtCheckUser->execute();
$userRes = $stmtCheckUser->get_result();

if (!$userRes || $userRes->num_rows === 0) {
    // User record not found in database
    redirect_unauthorized_user();
}

// 3. Refresh & Sync User Session State
$currentUserData = $userRes->fetch_assoc();
if (!in_array($currentUserData['role'], ['Student', 'Faculty'], true)) {
    redirect_unauthorized_user();
}

// Block deactivated accounts
if (!empty($currentUserData['status']) && strtolower($currentUserData['status']) === 'deactivated') {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=deactivated");
    exit;
}

$fetched_first = ($currentUserData['role'] === 'Student') 
    ? ($currentUserData['s_fname'] ?? '') 
    : ($currentUserData['f_fname'] ?? '');
$fetched_last = ($currentUserData['role'] === 'Student') 
    ? ($currentUserData['s_lname'] ?? '') 
    : ($currentUserData['f_lname'] ?? '');

$first_name = !empty($fetched_first) ? $fetched_first : 'User';
$last_name  = !empty($fetched_last) ? $fetched_last : '';
$full_name  = trim($first_name . ' ' . $last_name);
if (empty($full_name)) {
    $full_name = 'User';
}

$user_role          = $currentUserData['role'];
$user_email         = !empty($currentUserData['email']) ? $currentUserData['email'] : 'user@equiptrack.edu';
$user_profile_image = !empty($currentUserData['profile_image']) ? $currentUserData['profile_image'] : null;
$user_year_level    = ($currentUserData['role'] === 'Student') ? (!empty($currentUserData['s_year_level']) ? $currentUserData['s_year_level'] : 'N/A') : 'N/A';
$user_address       = ($currentUserData['role'] === 'Student') ? ($currentUserData['s_address'] ?? '') : ($currentUserData['f_address'] ?? '');

unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_username'], $_SESSION['admin_employee_id'], $_SESSION['admin_role']);
unset($_SESSION['dept_acc_id'], $_SESSION['dept_name'], $_SESSION['dept_email'], $_SESSION['dept_role']);

$_SESSION['user_id']       = $currentUserData['user_id'];
$_SESSION['user_role']     = $user_role;
$_SESSION['user_name']     = $full_name;
$_SESSION['email']         = $user_email;
$_SESSION['profile_image'] = $user_profile_image;
$_SESSION['year_level']    = $user_year_level;
$_SESSION['address']       = $user_address;

// Update last_online timestamp in database
$stmtUpdOnline = $conn->prepare("UPDATE user_account SET last_online = NOW() WHERE user_id = ?");
if ($stmtUpdOnline) {
    $stmtUpdOnline->bind_param("i", $user_id);
    $stmtUpdOnline->execute();
}

// Compute user initials for avatar
$nameParts = explode(' ', trim($full_name));
$initials  = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
}
$user_initials = !empty($initials) ? substr($initials, 0, 2) : 'US';

