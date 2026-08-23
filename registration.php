<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_input = strtolower(trim($_POST['role'] ?? 'student'));
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $id_number  = trim($_POST['id_number'] ?? '');
    $year_level_raw = trim($_POST['year_level'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $password   = $_POST['password'] ?? '';

    $year_level_map = [
        '1' => '1st Year',
        '2' => '2nd Year',
        '3' => '3rd Year',
        '4' => '4th Year'
    ];
    $year_level = $year_level_map[$year_level_raw] ?? ($year_level_raw ?: '1st Year');

    $db_role = ($role_input === 'teacher' || $role_input === 'faculty') ? 'Faculty' : (($role_input === 'admin') ? 'Admin' : 'Student');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($id_number)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($db_role === 'Admin') {
            // Check if admin with this email or username already exists
            $checkAdmin = $conn->prepare("SELECT admin_id FROM admin WHERE email = ? OR username = ?");
            $checkAdmin->bind_param("ss", $email, $email);
            $checkAdmin->execute();
            if ($checkAdmin->get_result()->num_rows > 0) {
                $error = 'An admin account with this email already exists.';
            } else {
                $full_name = trim($first_name . ' ' . $last_name);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insertAdmin = $conn->prepare("INSERT INTO admin (name, username, email, employee_id, password) VALUES (?, ?, ?, ?, ?)");
                $insertAdmin->bind_param("sssss", $full_name, $email, $email, $id_number, $hashed_password);
                if ($insertAdmin->execute()) {
                    $success = 'Admin account created successfully! Redirecting to login page...';
                } else {
                    $error = 'Admin registration failed: ' . $conn->error;
                }
            }
        } else {
            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT user_id FROM user_account WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();

            if ($checkRes->num_rows > 0) {
                $error = 'An account with this email already exists.';
            } else {
                // Fetch default department
                $deptRes = $conn->query("SELECT department_id FROM department LIMIT 1");
                $deptRow = $deptRes->fetch_assoc();
                $department_id = $deptRow ? (int)$deptRow['department_id'] : 1;

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insertUser = $conn->prepare("INSERT INTO user_account (role, email, password, department_id) VALUES (?, ?, ?, ?)");
                $insertUser->bind_param("sssi", $db_role, $email, $hashed_password, $department_id);

                if ($insertUser->execute()) {
                    $user_id = $conn->insert_id;

                    if ($db_role === 'Student') {
                        $insertSub = $conn->prepare("INSERT INTO student (user_id, first_name, last_name, id_number, year_level, address) VALUES (?, ?, ?, ?, ?, ?)");
                        $insertSub->bind_param("isssss", $user_id, $first_name, $last_name, $id_number, $year_level, $address);
                        $insertSub->execute();
                    } else {
                        $license_no = 'TL-' . $id_number;
                        $insertSub = $conn->prepare("INSERT INTO faculty_member (user_id, first_name, last_name, faculty_id_number, teaching_license_no, address) VALUES (?, ?, ?, ?, ?, ?)");
                        $insertSub->bind_param("isssss", $user_id, $first_name, $last_name, $id_number, $license_no, $address);
                        $insertSub->execute();
                    }

                    $success = 'Account created successfully! Redirecting to login page...';
                } else {
                    $error = 'Registration failed: ' . $conn->error;
                }
            }
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
    <title>EquipTrack - Register</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/logo_only.png">
    <link rel="apple-touch-icon" href="images/logo_only.png">
    <link rel="stylesheet" href="ccs/register.css?v=2.2">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for the eye icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php if (!empty($success)): ?>
    <!-- Top Pop-up Notification -->
    <div id="toastSuccess" style="position: fixed; top: 24px; left: 50%; transform: translateX(-50%); z-index: 99999; background: #064e3b; color: #ecfdf5; padding: 14px 24px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; border: 1px solid #10b981; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2); display: flex; align-items: center; gap: 12px; font-family: 'Inter', sans-serif; animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <i class="fa-solid fa-circle-check" style="color: #34d399; font-size: 1.3rem;"></i>
        <span>Account created successfully! Redirecting to login in <strong id="redirectTimer">3</strong> seconds...</span>
    </div>
    <script>
        let seconds = 3;
        const timerElement = document.getElementById('redirectTimer');
        const countdown = setInterval(() => {
            seconds--;
            if (timerElement) timerElement.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php?registered=1';
            }
        }, 1000);
    </script>
<?php endif; ?>
    <div class="register-container">
        <!-- Choose a Role Container -->
        <div class="role-selection-container <?php echo (!empty($error) || $_SERVER['REQUEST_METHOD'] === 'POST') ? 'hidden' : ''; ?>" id="roleSelectionContainer">
            <div class="role-selection-card">
                <a href="landing.php" class="role-close-btn" id="roleCloseBtn" aria-label="Close role selection">
                    <i class="fa-solid fa-xmark"></i>
                </a>
                <h2 class="role-title">What are you?</h2>
                <div class="roles-grid">
                    <div class="role-option" id="roleStudent" role="button" tabindex="0"><span class="role-label">Student</span><div class="role-circle"><img src="images/login_student.jpg" alt="Student"></div></div>
                    <div class="role-option" id="roleTeacher" role="button" tabindex="0"><span class="role-label">Teacher</span><div class="role-circle"><img src="images/login_teacher.jpg" alt="Teacher"></div></div>
                    <div class="role-option" id="roleAdmin" role="button" tabindex="0"><span class="role-label">Admin</span><div class="role-circle" style="background-color: #5C74A8; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-user-shield" style="font-size: 80px; color: #ffffff;"></i></div></div>
                </div>
            </div>
        </div>

        <!-- Registration Form Panel -->
        <div class="register-panel <?php echo (!empty($error) || $_SERVER['REQUEST_METHOD'] === 'POST') ? 'active' : ''; ?>" id="registerPanel">
            <a href="#" class="back-link" id="backToRole">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            
            <div class="register-header">
                <h1 id="registerTitle">Register</h1>
                <p id="registerSubtitle">Enter your credentials to register.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div style="color: #ef4444; background: #fee2e2; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #fca5a5;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="registration.php" method="POST" class="register-form">
                <input type="hidden" id="selected_role" name="role" value="">
                
                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group half-width">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half-width" id="idNumberGroup">
                        <label for="id_number" id="idNumberLabel">ID Number</label>
                        <input type="text" id="id_number" name="id_number" required>
                    </div>
                    <div class="form-group half-width" id="yearLevelGroup">
                        <label for="year_level">Year & Level</label>
                        <select id="year_level" name="year_level" required>
                            <option value="" disabled selected></option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required>
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>
                
                <button type="submit" class="register-btn" id="registerBtn">
                    <span class="btn-text">Register</span>
                    <div class="spinner"></div>
                </button>
                
                <div class="login-link">
                    Already have an account? <a href="login.php" id="loginLink">Login</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // DOM Elements
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const roleSelectionContainer = document.getElementById('roleSelectionContainer');
        const registerPanel = document.getElementById('registerPanel');
        const selectedRoleInput = document.getElementById('selected_role');
        const registerTitle = document.getElementById('registerTitle');
        const registerSubtitle = document.getElementById('registerSubtitle');
        const yearLevelGroup = document.getElementById('yearLevelGroup');
        const yearLevelSelect = document.getElementById('year_level');
        const idNumberLabel = document.getElementById('idNumberLabel');
        const backToRole = document.getElementById('backToRole');
        const roleStudentBtn = document.getElementById('roleStudent');
        const roleTeacherBtn = document.getElementById('roleTeacher');
        const roleAdminBtn = document.getElementById('roleAdmin');
        const loginLink = document.getElementById('loginLink');
        const registerForm = document.querySelector('.register-form');
        const registerBtn = document.getElementById('registerBtn');
        const roleCloseBtn = document.getElementById('roleCloseBtn');

        // Toggle Password Visibility
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function (e) {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        }

        // Role Selection Logic
        function selectRole(role) {
            // Set role value in form
            if (selectedRoleInput) {
                selectedRoleInput.value = role;
            }

            const idInput = document.getElementById('id_number');

            if (role === 'student') {
                if (registerTitle) registerTitle.textContent = 'Register as a Student';
                if (registerSubtitle) registerSubtitle.textContent = 'Enter your student credentials to register.';
                if (idNumberLabel) idNumberLabel.textContent = 'ID Number';
                if (yearLevelGroup) yearLevelGroup.style.display = 'block';
                if (yearLevelSelect) {
                    yearLevelSelect.setAttribute('required', 'required');
                }
                if (idInput) {
                    idInput.placeholder = 'e.g., 20230123';
                    idInput.pattern = '\\d{8}';
                    idInput.title = 'Student ID must be exactly 8 digits.';
                    idInput.maxLength = 8;
                }
            } else if (role === 'teacher') {
                if (registerTitle) registerTitle.textContent = 'Register as a Teacher';
                if (registerSubtitle) registerSubtitle.textContent = 'Enter your teacher credentials to register.';
                if (idNumberLabel) idNumberLabel.textContent = 'ID Number';
                if (yearLevelGroup) yearLevelGroup.style.display = 'none';
                if (yearLevelSelect) {
                    yearLevelSelect.removeAttribute('required');
                    yearLevelSelect.value = ''; // clear selection
                }
                if (idInput) {
                    idInput.placeholder = 'e.g., T-00987';
                    idInput.removeAttribute('pattern');
                    idInput.removeAttribute('title');
                    idInput.removeAttribute('maxlength');
                }
            } else if (role === 'admin') {
                if (registerTitle) registerTitle.textContent = 'Register as an Admin';
                if (registerSubtitle) registerSubtitle.textContent = 'Enter your admin credentials to register.';
                if (idNumberLabel) idNumberLabel.textContent = 'Employee ID';
                if (yearLevelGroup) yearLevelGroup.style.display = 'none';
                if (yearLevelSelect) {
                    yearLevelSelect.removeAttribute('required');
                    yearLevelSelect.value = ''; // clear selection
                }
                if (idInput) {
                    idInput.placeholder = 'e.g., ADM-2026';
                    idInput.removeAttribute('pattern');
                    idInput.removeAttribute('title');
                    idInput.removeAttribute('maxlength');
                }
            }

            // Animate transition
            if (roleSelectionContainer) roleSelectionContainer.classList.add('hidden');
            setTimeout(() => {
                if (registerPanel) registerPanel.classList.add('active');
            }, 100);
        }

        // Event listeners for role choice buttons
        const handleRoleKeydown = (e, role) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectRole(role);
            }
        };

        if (roleStudentBtn) {
            roleStudentBtn.addEventListener('click', () => selectRole('student'));
            roleStudentBtn.addEventListener('keydown', (e) => handleRoleKeydown(e, 'student'));
        }
        
        if (roleTeacherBtn) {
            roleTeacherBtn.addEventListener('click', () => selectRole('teacher'));
            roleTeacherBtn.addEventListener('keydown', (e) => handleRoleKeydown(e, 'teacher'));
        }

        if (roleAdminBtn) {
            roleAdminBtn.addEventListener('click', () => selectRole('admin'));
            roleAdminBtn.addEventListener('keydown', (e) => handleRoleKeydown(e, 'admin'));
        }

        // Back button navigation
        if (backToRole) {
            backToRole.addEventListener('click', function(e) {
                e.preventDefault();
                if (registerPanel) registerPanel.classList.remove('active');
                setTimeout(() => {
                    if (roleSelectionContainer) roleSelectionContainer.classList.remove('hidden');
                }, 300);
            });
        }

        // Smooth transition to Login page
        if (loginLink) {
            loginLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (registerPanel) registerPanel.classList.remove('active');
                
                setTimeout(() => {
                    window.location.href = this.href;
                }, 400);
            });
        }

        // Fix missing panel when using browser back button (bfcache restore)
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                if (registerPanel) registerPanel.classList.remove('active');
                if (roleSelectionContainer) roleSelectionContainer.classList.remove('hidden');
                if (registerBtn) registerBtn.classList.remove('submitting');
                if (registerForm) registerForm.classList.remove('form-submitting');
            }
        });

        // Form submission loading state
        if (registerForm && registerBtn) {
            registerForm.addEventListener('submit', function () {
                if (registerForm.checkValidity()) {
                    registerBtn.classList.add('submitting');
                    registerForm.classList.add('form-submitting');
                }
            });
        }

        // Close button redirect animation
        if (roleCloseBtn) {
            roleCloseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (roleSelectionContainer) roleSelectionContainer.classList.add('hidden');
                setTimeout(() => {
                    window.location.href = this.href;
                }, 500);
            });
        }
    </script>
</body>
</html>
