<?php
require_once __DIR__ . '/../includes/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$name     = trim($d['name'] ?? '');
$mobile   = trim($d['mobile'] ?? '');
$email    = trim($d['email'] ?? '');
$password = $d['password'] ?? '';

if (!$name || !$mobile || !$email || !$password) respond(false, 'All fields are required.', [], 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Invalid email address.', [], 422);

try {
    $check = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) respond(false, 'An account with this email already exists.', [], 409);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO parents (name, mobile, email, password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $mobile, $email, $hash]);
    $parentId = $pdo->lastInsertId();

    // Auto-link any student accounts already registered with this parent email
    $pdo->prepare("UPDATE students SET parent_id = ? WHERE parent_email = ? AND parent_id IS NULL")
        ->execute([$parentId, $email]);

    respond(true, 'Parent account created successfully.', ['parent_id' => $parentId]);
} catch (PDOException $e) {
    respond(false, 'Registration failed: ' . $e->getMessage(), [], 500);
}
