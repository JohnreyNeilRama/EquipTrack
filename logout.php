<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if present
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

// Destroy session
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Logging Out...</title>
<script>
    localStorage.removeItem('user-avatar-src');
    localStorage.removeItem('dept-avatar-src');
    localStorage.removeItem('admin-avatar-src');
    window.location.href = 'login.php';
</script>
</head>
<body></body>
</html>

