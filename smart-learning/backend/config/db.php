<?php
// ============================================================
// Database connection settings for XAMPP (default MySQL setup)
// Edit these only if your XAMPP MySQL username/password differ.
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_learning');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP's default MySQL root password is empty

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
