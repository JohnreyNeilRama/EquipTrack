<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack</title>
    <meta name="description" content="A digital and centralized platform for managing school equipment borrowing and returning.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="ccs/landing.css">
</head>
<body>

    <nav>
        <a href="landing.php" class="logo">
            <i class="fa-solid fa-boxes-stacked"></i>
            EquipTrack
        </a>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="login.php" class="btn-outline">Log In</a>
            <a href="registration.php" class="btn-primary">Sign Up</a>
        </div>
    </nav>

    <section id="home" class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Streamline Your School's Equipment Management</h1>
            <p class="hero-description">
                Say goodbye to lost records and manual logbooks. EquipTrack provides a digital, centralized platform to request, monitor, and manage school equipment with ease and transparency.
            </p>
            <div class="hero-actions">
                <a href="registration.php" class="btn-primary">Get Started</a>
                <a href="#features" class="btn-outline">Learn More</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="images/hero_illustration.png" alt="EquipTrack Platform Illustration">
        </div>
    </section>

    <section id="features" class="features">
        <h2 class="section-title">Why Choose EquipTrack?</h2>
        <p class="section-subtitle">Automate your processes to reduce errors, improve accountability, and ensure that school resources are properly utilized and returned on time.</p>
        
        <div class="features-grid">
            <div class="feature-card">
                <i class="fa-solid fa-cloud feature-icon"></i>
                <h3>Centralized Tracking</h3>
                <p>Monitor all school equipment from a single, cloud-based dashboard accessible anywhere, anytime.</p>
            </div>
            
            <div class="feature-card">
                <i class="fa-solid fa-bolt feature-icon"></i>
                <h3>Real-time Availability</h3>
                <p>Check the exact status of laptops, projectors, and tools instantly before making a request.</p>
            </div>
            
            <div class="feature-card">
                <i class="fa-solid fa-file-signature feature-icon"></i>
                <h3>Automated Logs</h3>
                <p>Replace paper forms. Automatically generate digital logs for every borrowed and returned item.</p>
            </div>
            
            <div class="feature-card">
                <i class="fa-solid fa-shield-halved feature-icon"></i>
                <h3>Better Accountability</h3>
                <p>Keep track of who borrowed what and when it is due to ensure resources are handled responsibly.</p>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <a href="landing.php" class="footer-logo">
                <i class="fa-solid fa-boxes-stacked"></i> EquipTrack
            </a>
            <div class="footer-links">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="login.php">Login</a>
                <a href="registration.php">Sign Up</a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2026 EquipTrack System. All Rights Reserved.
        </div>
    </footer>

</body>
</html>
