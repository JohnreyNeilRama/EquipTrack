<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <title>EquipTrack - Register</title>
    <link rel="stylesheet" href="ccs/register.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for the eye icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="register-container">
        <div class="register-panel">
            <div class="register-header">
                <h1>Welcome to EquipTrack!</h1>
                <p>Register an account.</p>
            </div>
            
            <form action="registration.php" method="POST" class="register-form">
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
                
                <div class="form-group">
                    <label for="id_number">ID Number</label>
                    <input type="text" id="id_number" name="id_number" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="year_level">Year & Level</label>
                    <select id="year_level" name="year_level" required>
                        <option value="" disabled selected></option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
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
                
                <button type="submit" class="register-btn">Register</button>
                
                <div class="login-link">
                    Already have an account? <a href="login.php" id="loginLink">Login</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye / eye-slash icon
            this.classList.toggle('fa-eye-slash');
        });

        // Smooth transition to Login page
        const loginLink = document.getElementById('loginLink');
        if(loginLink) {
            loginLink.addEventListener('click', function(e) {
                e.preventDefault();
                const panel = document.querySelector('.register-panel');
                panel.style.animation = 'slideOutLeft 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                
                setTimeout(() => {
                    window.location.href = this.href;
                }, 400); // Redirect slightly before animation completely finishes
            });
        }

        // Fix missing panel when using browser back button (bfcache restore)
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                const panel = document.querySelector('.register-panel');
                if (panel) {
                    panel.style.animation = ''; // Reset to default CSS entrance animation
                }
            }
        });
    </script>
</body>
</html>
