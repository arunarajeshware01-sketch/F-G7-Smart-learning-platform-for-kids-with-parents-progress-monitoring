<?php
require_once __DIR__ . '/../includes/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$role     = trim($d['role'] ?? '');       // student | parent | admin
$email    = trim($d['email'] ?? '');
$password = $d['password'] ?? '';

if (!in_array($role, ['student', 'parent', 'admin'], true)) respond(false, 'Invalid role.', [], 422);
if (!$email || !$password) respond(false, 'Email and password are required.', [], 422);

$table = ['student' => 'students', 'parent' => 'parents', 'admin' => 'admins'][$role];

try {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        respond(false, 'Incorrect email or password.', [], 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $role;
    $_SESSION['name']    = $user['name'];

    respond(true, 'Login successful.', [
        'role' => $role,
        'name' => $user['name'],
        'id'   => $user['id'],
    ]);
} catch (PDOException $e) {
    respond(false, 'Login failed: ' . $e->getMessage(), [], 500);
}
