<?php
session_start();
require_once __DIR__ . '/db.php';

// If user is already authenticated, redirect to their corresponding dashboard
if (!empty($_SESSION['admin_id'])) {
    header("Location: admin/admindashboard.php");
    exit;
} elseif (!empty($_SESSION['dept_acc_id'])) {
    header("Location: departments/departmentdashboard.php");
    exit;
} elseif (!empty($_SESSION['user_id'])) {
    header("Location: user/userdashboard.php");
    exit;
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'deactivated') {
    $error = 'Your account has been deactivated by the Administrator. Please contact support.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = 'Please enter both email/username and password.';
    } else {
        // 1. Check User Account (Student / Faculty)
        $stmtUser = $conn->prepare("SELECT u.*, 
                                           s.first_name AS s_fname, s.last_name AS s_lname,
                                           f.first_name AS f_fname, f.last_name AS f_lname
                                    FROM user_account u
                                    LEFT JOIN student s ON u.user_id = s.user_id
                                    LEFT JOIN faculty_member f ON u.user_id = f.user_id
                                    WHERE u.email = ?");
        $stmtUser->bind_param("s", $login_input);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();

        if ($resUser && $user = $resUser->fetch_assoc()) {
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                if (!empty($user['status']) && strtolower($user['status']) === 'deactivated') {
                    $error = 'Your account has been deactivated by the Administrator.';
                } else {
                    session_unset();
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = ($user['role'] === 'Student') 
                        ? trim(($user['s_fname'] ?? '') . ' ' . ($user['s_lname'] ?? ''))
                        : trim(($user['f_fname'] ?? '') . ' ' . ($user['f_lname'] ?? ''));
                    $_SESSION['email']     = $user['email'];

                    $stmtUpdOnline = $conn->prepare("UPDATE user_account SET last_online = NOW() WHERE user_id = ?");
                    if ($stmtUpdOnline) {
                        $stmtUpdOnline->bind_param("i", $user['user_id']);
                        $stmtUpdOnline->execute();
                    }

                    header("Location: user/userdashboard.php");
                    exit;
                }
            }
        }

        // 2. Check Admin Account
        $stmtAdmin = $conn->prepare("SELECT * FROM admin WHERE username = ? OR email = ?");
        $stmtAdmin->bind_param("ss", $login_input, $login_input);
        $stmtAdmin->execute();
        $resAdmin = $stmtAdmin->get_result();

        if ($resAdmin && $admin = $resAdmin->fetch_assoc()) {
            if (password_verify($password, $admin['password']) || $password === $admin['password']) {
                session_unset();
                session_regenerate_id(true);
                $_SESSION['admin_id']          = $admin['admin_id'];
                $_SESSION['admin_name']        = $admin['name'];
                $_SESSION['admin_email']       = !empty($admin['email']) ? $admin['email'] : $admin['username'];
                $_SESSION['admin_username']    = $admin['username'];
                $_SESSION['admin_employee_id'] = $admin['employee_id'] ?? '';
                $_SESSION['admin_role']        = 'Admin';

                header("Location: admin/admindashboard.php");
                exit;
            }
        }

        // 3. Check Department Account
        $stmtDept = $conn->prepare("SELECT * FROM department_account WHERE email = ?");
        $stmtDept->bind_param("s", $login_input);
        $stmtDept->execute();
        $resDept = $stmtDept->get_result();

        if ($resDept && $dept = $resDept->fetch_assoc()) {
            if (password_verify($password, $dept['password']) || $password === $dept['password']) {
                if (!empty($dept['status']) && strtolower($dept['status']) === 'deactivated') {
                    $error = 'This department account has been deactivated by the Administrator.';
                } else {
                    session_unset();
                    session_regenerate_id(true);
                    $_SESSION['dept_acc_id']   = $dept['dept_acc_id'];
                    $_SESSION['department_id'] = $dept['department_id'];
                    $_SESSION['dept_name']     = $dept['full_name'];
                    $_SESSION['dept_email']    = $dept['email'] ?? '';
                    $_SESSION['dept_role']     = !empty($dept['role']) ? $dept['role'] : 'Department';

                    $stmtUpdOnline = $conn->prepare("UPDATE department_account SET last_online = NOW() WHERE dept_acc_id = ?");
                    if ($stmtUpdOnline) {
                        $stmtUpdOnline->bind_param("i", $dept['dept_acc_id']);
                        $stmtUpdOnline->execute();
                    }

                    header("Location: departments/departmentdashboard.php");
                    exit;
                }
            }
        }

        if (empty($error)) {
            $error = 'Invalid email/username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <title>EquipTrack - Login</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/logo_only.png">
    <link rel="apple-touch-icon" href="images/logo_only.png">
    <link rel="stylesheet" href="ccs/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for the eye icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-panel">
            <div class="login-header">
                <h1>Welcome back!</h1>
                <p>Please login to your account.</p>
            </div>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
                <div style="color: #065f46; background: #d1fae5; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #6ee7b7; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-circle-check" style="color: #059669;"></i> Account created successfully! Please log in below.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="color: #ef4444; background: #fee2e2; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #fca5a5;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required>
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" checked>
                        <span class="checkmark"></span>
                        Remember Me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="login-btn">Login</button>
                
                <div class="signup-link">
                    Don't have an account? <a href="registration.php" id="registerLink">Sign up here</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        localStorage.removeItem('user-avatar-src');
        localStorage.removeItem('dept-avatar-src');
        localStorage.removeItem('admin-avatar-src');

        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        if (togglePassword && password) {
            togglePassword.addEventListener('click', function (e) {
                // toggle the type attribute
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                // toggle the eye / eye-slash icon
                this.classList.toggle('fa-eye-slash');
            });
        }

        // Smooth transition to Registration page
        const registerLink = document.getElementById('registerLink');
        if (registerLink) {
            registerLink.addEventListener('click', function(e) {
                e.preventDefault();
                const panel = document.querySelector('.login-panel');
                if (panel) {
                    panel.style.animation = 'slideOutRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                }
                
                setTimeout(() => {
                    window.location.href = this.href;
                }, 400); // Redirect slightly before animation completely finishes for smoother perceived flow
            });
        }

        // Fix missing panel when using browser back button (bfcache restore)
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                const panel = document.querySelector('.login-panel');
                if (panel) {
                    panel.style.animation = ''; // Reset to default CSS entrance animation
                }
            }
        });
    </script>
</body>
</html>
