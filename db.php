<?php
// ============================================================
// EquipTrack Database Connection
// ============================================================

$host     = "localhost";
$username = "root";
$password = "";
$database = "equiptrack";

// Disable raw uncaught exceptions for graceful error handling
mysqli_report(MYSQLI_REPORT_OFF);

try {
    // Create connection (MySQLi)
    $conn = @new mysqli($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("<div style='font-family: sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px; border: 1px solid #fca5a5;'>
                <h2>Database Connection Failed</h2>
                <p><strong>Error:</strong> Unable to connect to MySQL server.</p>
                <p>Please make sure <strong>MySQL</strong> is started in your <strong>XAMPP Control Panel</strong>.</p>
             </div>");
    }

    // Set character set to utf8mb4 matching database schema
    $conn->set_charset("utf8mb4");

    // Optional: PDO Connection (Use $pdo if preferred over $conn)
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        $pdo = null;
    }

    // Helper: Ensure at least one default department exists for user registration
    $deptCheck = $conn->query("SELECT department_id FROM department LIMIT 1");
    if ($deptCheck && $deptCheck->num_rows === 0) {
        $conn->query("INSERT INTO department (department_name, department_code, college, department_head) 
                      VALUES ('College of Computer Studies', 'CCS', 'College of Computer Studies', 'Dr. Department Head')");
    }

    // Helper: Ensure admin table schema has required columns (email, employee_id)
    $colsRes = $conn->query("SHOW COLUMNS FROM admin");
    $existingCols = [];
    if ($colsRes) {
        while ($col = $colsRes->fetch_assoc()) {
            $existingCols[] = $col['Field'];
        }
    }
    if (!in_array('email', $existingCols)) {
        $conn->query("ALTER TABLE admin ADD COLUMN email VARCHAR(100) NULL AFTER username");
    }
    if (!in_array('employee_id', $existingCols)) {
        $conn->query("ALTER TABLE admin ADD COLUMN employee_id VARCHAR(50) NULL AFTER email");
    }

    // Helper: Ensure a default admin account exists if table is empty (ucedu@gmail.com / admin123)
    $adminCheck = $conn->query("SELECT admin_id FROM admin WHERE username = 'ucedu@gmail.com' OR email = 'ucedu@gmail.com' LIMIT 1");
    if ($adminCheck && $adminCheck->num_rows === 0) {
        $defaultAdminPass = password_hash("admin123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin (name, username, email, employee_id, password) VALUES ('System Admin', 'ucedu@gmail.com', 'ucedu@gmail.com', 'ADM-0001', ?)");
        $stmt->bind_param("s", $defaultAdminPass);
        $stmt->execute();
    } else {
        // Backfill missing fields for existing admin records if needed
        $conn->query("UPDATE admin SET email = username WHERE email IS NULL OR email = ''");
        $conn->query("UPDATE admin SET employee_id = 'ADM-0001' WHERE employee_id IS NULL OR employee_id = ''");
    }
} catch (Throwable $e) {
    die("<div style='font-family: sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px; border: 1px solid #fca5a5;'>
            <h2>Database Connection Error</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please make sure <strong>MySQL</strong> is started in your <strong>XAMPP Control Panel</strong>.</p>
         </div>");
}
?>
