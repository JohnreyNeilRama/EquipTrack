<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <title>EquipTrack - Login</title>
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
                <p>Please login you acc.</p>
            </div>
            
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
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye / eye-slash icon
            this.classList.toggle('fa-eye-slash');
        });

        // Smooth transition to Registration page
        const registerLink = document.getElementById('registerLink');
        if(registerLink) {
            registerLink.addEventListener('click', function(e) {
                e.preventDefault();
                const panel = document.querySelector('.login-panel');
                panel.style.animation = 'slideOutRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                
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
