<?php
// ============================================================
// EquipTrack Database Connection & Auto-Migration
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

    // Helper: Ensure department table exists and has updated structure
    $conn->query("CREATE TABLE IF NOT EXISTS department (
        department_id INT AUTO_INCREMENT PRIMARY KEY,
        department_name VARCHAR(255) NOT NULL UNIQUE,
        department_code VARCHAR(50) NOT NULL UNIQUE,
        college VARCHAR(255) NULL,
        department_head VARCHAR(255) NULL,
        profile_image LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $deptColsRes = $conn->query("SHOW COLUMNS FROM department");
    $existingDeptCols = [];
    if ($deptColsRes) {
        while ($col = $deptColsRes->fetch_assoc()) {
            $existingDeptCols[] = $col['Field'];
        }
    }
    if (!in_array('college', $existingDeptCols)) {
        $conn->query("ALTER TABLE department ADD COLUMN college VARCHAR(255) NULL AFTER department_code");
    } else {
        $conn->query("ALTER TABLE department MODIFY COLUMN college VARCHAR(255) NULL");
    }
    if (!in_array('department_head', $existingDeptCols)) {
        $conn->query("ALTER TABLE department ADD COLUMN department_head VARCHAR(255) NULL AFTER college");
    } else {
        $conn->query("ALTER TABLE department MODIFY COLUMN department_head VARCHAR(255) NULL");
    }
    if (!in_array('profile_image', $existingDeptCols)) {
        $conn->query("ALTER TABLE department ADD COLUMN profile_image LONGTEXT NULL AFTER department_head");
    } else {
        $conn->query("ALTER TABLE department MODIFY COLUMN profile_image LONGTEXT NULL");
    }
    if (!in_array('created_at', $existingDeptCols)) {
        $conn->query("ALTER TABLE department ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    // Helper: Ensure at least one default department exists
    $deptCheck = $conn->query("SELECT department_id FROM department LIMIT 1");
    if ($deptCheck && $deptCheck->num_rows === 0) {
        $conn->query("INSERT INTO department (department_name, department_code, college, department_head) 
                      VALUES ('College of Computer Studies', 'CCS', 'College of Computer Studies', 'Dr. Department Head')");
    }

    // Helper: Ensure admin table schema has required columns
    $conn->query("CREATE TABLE IF NOT EXISTS admin (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NULL,
        employee_id VARCHAR(50) NULL,
        profile_image LONGTEXT NULL,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $colsRes = $conn->query("SHOW COLUMNS FROM admin");
    $existingAdminCols = [];
    if ($colsRes) {
        while ($col = $colsRes->fetch_assoc()) {
            $existingAdminCols[] = $col['Field'];
        }
    }
    if (!in_array('email', $existingAdminCols)) {
        $conn->query("ALTER TABLE admin ADD COLUMN email VARCHAR(100) NULL AFTER username");
    }
    if (!in_array('employee_id', $existingAdminCols)) {
        $conn->query("ALTER TABLE admin ADD COLUMN employee_id VARCHAR(50) NULL AFTER email");
    }
    if (!in_array('profile_image', $existingAdminCols)) {
        $conn->query("ALTER TABLE admin ADD COLUMN profile_image LONGTEXT NULL AFTER employee_id");
    } else {
        $conn->query("ALTER TABLE admin MODIFY COLUMN profile_image LONGTEXT NULL");
    }

    // Helper: Ensure a default admin account exists
    $adminCheck = $conn->query("SELECT admin_id FROM admin WHERE username = 'ucedu@gmail.com' OR email = 'ucedu@gmail.com' LIMIT 1");
    if ($adminCheck && $adminCheck->num_rows === 0) {
        $defaultAdminPass = password_hash("admin123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin (name, username, email, employee_id, password) VALUES ('System Admin', 'ucedu@gmail.com', 'ucedu@gmail.com', 'ADM-0001', ?)");
        $stmt->bind_param("s", $defaultAdminPass);
        $stmt->execute();
    } else {
        $conn->query("UPDATE admin SET email = username WHERE email IS NULL OR email = ''");
        $conn->query("UPDATE admin SET employee_id = 'ADM-0001' WHERE employee_id IS NULL OR employee_id = ''");
    }

    // Helper: Ensure department_account table structure exists and has required columns
    $conn->query("CREATE TABLE IF NOT EXISTS department_account (
        dept_acc_id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        employee_id VARCHAR(100) NULL,
        role VARCHAR(100) DEFAULT 'Department Head',
        department_id INT NULL,
        profile_image LONGTEXT NULL,
        last_online DATETIME NULL,
        status VARCHAR(50) DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $deptAccColsRes = $conn->query("SHOW COLUMNS FROM department_account");
    $existingDeptAccCols = [];
    if ($deptAccColsRes) {
        while ($col = $deptAccColsRes->fetch_assoc()) {
            $existingDeptAccCols[] = $col['Field'];
        }
    }
    if (in_array('username', $existingDeptAccCols)) {
        $idxRes = $conn->query("SHOW INDEX FROM department_account WHERE Key_name = 'uq_dept_acc_username'");
        if ($idxRes && $idxRes->num_rows > 0) {
            $conn->query("ALTER TABLE department_account DROP INDEX uq_dept_acc_username");
        }
        $conn->query("ALTER TABLE department_account DROP COLUMN username");
    }
    if (!in_array('profile_image', $existingDeptAccCols)) {
        $conn->query("ALTER TABLE department_account ADD COLUMN profile_image LONGTEXT NULL");
    } else {
        $conn->query("ALTER TABLE department_account MODIFY COLUMN profile_image LONGTEXT NULL");
    }
    if (!in_array('last_online', $existingDeptAccCols)) {
        $conn->query("ALTER TABLE department_account ADD COLUMN last_online DATETIME NULL");
    }
    if (!in_array('status', $existingDeptAccCols)) {
        $conn->query("ALTER TABLE department_account ADD COLUMN status VARCHAR(50) DEFAULT 'Active'");
    }
    if (!in_array('created_at', $existingDeptAccCols)) {
        $conn->query("ALTER TABLE department_account ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    $conn->query("ALTER TABLE department_account MODIFY COLUMN full_name VARCHAR(255) NOT NULL");
    $conn->query("ALTER TABLE department_account MODIFY COLUMN employee_id VARCHAR(100) NULL");
    $conn->query("ALTER TABLE department_account MODIFY COLUMN department_id INT NULL");

    // Sync profile_image from department_account to department table for assigned Department Head
    $conn->query("
        UPDATE department d
        INNER JOIN (
            SELECT da.department_id, da.profile_image
            FROM department_account da
            WHERE da.department_id IS NOT NULL AND da.profile_image IS NOT NULL AND da.profile_image != ''
            ORDER BY (
                CASE 
                    WHEN LOWER(da.role) LIKE '%head%' THEN 1
                    WHEN LOWER(da.role) LIKE '%chair%' THEN 2
                    WHEN LOWER(da.role) LIKE '%dean%' THEN 3
                    ELSE 4 
                END
            ), da.dept_acc_id ASC
        ) da_src ON d.department_id = da_src.department_id
        SET d.profile_image = da_src.profile_image
        WHERE d.profile_image IS NULL OR d.profile_image = ''
    ");

    // Helper: Ensure user_account table structure exists and has required columns
    $conn->query("CREATE TABLE IF NOT EXISTS user_account (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('Student','Faculty') NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        department_id INT NULL,
        profile_image LONGTEXT NULL,
        last_online DATETIME NULL,
        status VARCHAR(50) DEFAULT 'Active',
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $userAccColsRes = $conn->query("SHOW COLUMNS FROM user_account");
    $existingUserAccCols = [];
    if ($userAccColsRes) {
        while ($col = $userAccColsRes->fetch_assoc()) {
            $existingUserAccCols[] = $col['Field'];
        }
    }
    if (!in_array('profile_image', $existingUserAccCols)) {
        $conn->query("ALTER TABLE user_account ADD COLUMN profile_image LONGTEXT NULL");
    } else {
        $conn->query("ALTER TABLE user_account MODIFY COLUMN profile_image LONGTEXT NULL");
    }
    if (!in_array('last_online', $existingUserAccCols)) {
        $conn->query("ALTER TABLE user_account ADD COLUMN last_online DATETIME NULL");
    }
    if (!in_array('status', $existingUserAccCols)) {
        $conn->query("ALTER TABLE user_account ADD COLUMN status VARCHAR(50) DEFAULT 'Active'");
    }
    if (!in_array('date_created', $existingUserAccCols)) {
        $conn->query("ALTER TABLE user_account ADD COLUMN date_created DATETIME DEFAULT CURRENT_TIMESTAMP");
    }
    $conn->query("ALTER TABLE user_account MODIFY COLUMN department_id INT NULL");

    // Helper: Ensure student table exists
    $conn->query("CREATE TABLE IF NOT EXISTS student (
        user_id INT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        id_number VARCHAR(20) NOT NULL UNIQUE,
        year_level VARCHAR(20) NOT NULL,
        address VARCHAR(150) NOT NULL,
        CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES user_account (user_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Helper: Ensure faculty_member table exists
    $conn->query("CREATE TABLE IF NOT EXISTS faculty_member (
        user_id INT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        faculty_id_number VARCHAR(20) NOT NULL UNIQUE,
        teaching_license_no VARCHAR(30) NOT NULL UNIQUE,
        highest_educational_attainment VARCHAR(100) DEFAULT NULL,
        address VARCHAR(150) NOT NULL,
        CONSTRAINT fk_faculty_user FOREIGN KEY (user_id) REFERENCES user_account (user_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Helper: Ensure equipment_category table exists
    $conn->query("CREATE TABLE IF NOT EXISTS equipment_category (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $eqCatColsRes = $conn->query("SHOW COLUMNS FROM equipment_category");
    $existingEqCatCols = [];
    if ($eqCatColsRes) {
        while ($col = $eqCatColsRes->fetch_assoc()) {
            $existingEqCatCols[] = $col['Field'];
        }
    }
    $conn->query("ALTER TABLE equipment_category MODIFY COLUMN category_name VARCHAR(255) NOT NULL");
    if (!in_array('created_at', $existingEqCatCols)) {
        $conn->query("ALTER TABLE equipment_category ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    // Helper: Ensure equipment table exists
    $conn->query("CREATE TABLE IF NOT EXISTS equipment (
        equipment_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        brand VARCHAR(50) NOT NULL,
        model VARCHAR(50) DEFAULT NULL,
        serial_number VARCHAR(50) NOT NULL UNIQUE,
        image VARCHAR(255) NOT NULL,
        available_qty INT(5) NOT NULL DEFAULT 0,
        total_qty INT(5) NOT NULL,
        status ENUM('Available','Unavailable','On Hold','Under Maintenance') NOT NULL,
        accessories_included VARCHAR(150) DEFAULT NULL,
        category_id INT NOT NULL,
        department_id INT NOT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_equipment_category FOREIGN KEY (category_id) REFERENCES equipment_category (category_id) ON UPDATE CASCADE,
        CONSTRAINT fk_equipment_department FOREIGN KEY (department_id) REFERENCES department (department_id) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Helper: Ensure borrow_request table exists
    $conn->query("CREATE TABLE IF NOT EXISTS borrow_request (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        equipment_id INT NOT NULL,
        quantity INT(5) NOT NULL DEFAULT 1,
        purpose VARCHAR(255) NOT NULL,
        date_requested DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_needed DATE NOT NULL,
        admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        admin_id INT DEFAULT NULL,
        admin_reviewed_at DATETIME DEFAULT NULL,
        dept_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        dept_acc_id INT DEFAULT NULL,
        dept_reviewed_at DATETIME DEFAULT NULL,
        overall_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        CONSTRAINT fk_borrow_request_user FOREIGN KEY (user_id) REFERENCES user_account (user_id) ON UPDATE CASCADE,
        CONSTRAINT fk_borrow_request_equipment FOREIGN KEY (equipment_id) REFERENCES equipment (equipment_id) ON UPDATE CASCADE,
        CONSTRAINT fk_borrow_request_admin FOREIGN KEY (admin_id) REFERENCES admin (admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_borrow_request_dept_account FOREIGN KEY (dept_acc_id) REFERENCES department_account (dept_acc_id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Helper: Ensure borrow_transaction table exists
    $conn->query("CREATE TABLE IF NOT EXISTS borrow_transaction (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL UNIQUE,
        borrow_date DATE NOT NULL,
        due_date DATE NOT NULL,
        return_date DATE DEFAULT NULL,
        condition_on_return VARCHAR(150) DEFAULT NULL,
        status ENUM('Active','Returned','Overdue') NOT NULL DEFAULT 'Active',
        penalty_amount DECIMAL(8,2) DEFAULT 0.00,
        penalty_status ENUM('None','Unpaid','Paid') DEFAULT 'None',
        CONSTRAINT fk_borrow_transaction_request FOREIGN KEY (request_id) REFERENCES borrow_request (request_id) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Helper: Ensure audit_trail table exists
    $conn->query("CREATE TABLE IF NOT EXISTS audit_trail (
        audit_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        admin_id INT DEFAULT NULL,
        dept_acc_id INT DEFAULT NULL,
        action VARCHAR(255) NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_audit_trail_user FOREIGN KEY (user_id) REFERENCES user_account (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_audit_trail_admin FOREIGN KEY (admin_id) REFERENCES admin (admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_audit_trail_dept_account FOREIGN KEY (dept_acc_id) REFERENCES department_account (dept_acc_id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (Throwable $e) {
    die("<div style='font-family: sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px; border: 1px solid #fca5a5;'>
            <h2>Database Connection Error</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please make sure <strong>MySQL</strong> is started in your <strong>XAMPP Control Panel</strong>.</p>
         </div>");
}
?>
