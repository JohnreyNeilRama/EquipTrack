<?php
require_once __DIR__ . '/db.php';

// Ensure admin table schema has required columns
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

$conn->query("UPDATE admin SET email = username WHERE email IS NULL OR email = ''");
$conn->query("UPDATE admin SET employee_id = 'ADM-0001' WHERE employee_id IS NULL OR employee_id = ''");

echo "=== UPDATED ADMIN TABLE SCHEMA ===\n";
$res = $conn->query("SHOW COLUMNS FROM admin");
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "=== ADMIN CONTENT ===\n";
$resAdmin = $conn->query("SELECT * FROM admin");
print_r($resAdmin->fetch_all(MYSQLI_ASSOC));
