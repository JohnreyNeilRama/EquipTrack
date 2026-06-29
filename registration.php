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
    <div class="register-container">
        <!-- Choose a Role Container -->
        <div class="role-selection-container" id="roleSelectionContainer">
            <div class="role-selection-card">
                <a href="landing.php" class="role-close-btn" id="roleCloseBtn" aria-label="Close role selection">
                    <i class="fa-solid fa-xmark"></i>
                </a>
                <h2 class="role-title">What are you?</h2>
                <div class="roles-grid">
                    <div class="role-option" id="roleStudent" role="button" tabindex="0"><span class="role-label">Student</span><div class="role-circle"><img src="images/login_student.jpg" alt="Student"></div></div>
                    <div class="role-option" id="roleTeacher" role="button" tabindex="0"><span class="role-label">Teacher</span><div class="role-circle"><img src="images/login_teacher.jpg" alt="Teacher"></div></div>
                </div>
            </div>
        </div>

        <!-- Registration Form Panel -->
        <div class="register-panel" id="registerPanel">
            <a href="#" class="back-link" id="backToRole">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            
            <div class="register-header">
                <h1 id="registerTitle"></h1>
                <p id="registerSubtitle"></p>
            </div>
            
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
                        <label for="id_number">ID Number</label>
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
        const backToRole = document.getElementById('backToRole');
        const roleStudentBtn = document.getElementById('roleStudent');
        const roleTeacherBtn = document.getElementById('roleTeacher');
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
