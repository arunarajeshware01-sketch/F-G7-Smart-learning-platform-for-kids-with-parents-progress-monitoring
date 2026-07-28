<?php
require_once __DIR__ . '/../includes/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$role        = trim($d['role'] ?? '');            // student | parent | admin
$email       = trim($d['email'] ?? '');
$verifyValue = trim($d['verify_value'] ?? '');     // parent mobile (student) / own mobile (parent) / not used for admin
$newPassword = $d['new_password'] ?? '';

if (!in_array($role, ['student', 'parent', 'admin'], true)) respond(false, 'Invalid role.', [], 422);
if (!$email || !$newPassword) respond(false, 'Email and new password are required.', [], 422);
if (strlen($newPassword) < 6) respond(false, 'New password must be at least 6 characters.', [], 422);

try {
    if ($role === 'student') {
        if (!$verifyValue) respond(false, 'Parent mobile number is required to verify your identity.', [], 422);
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND parent_mobile = ?");
        $stmt->execute([$email, $verifyValue]);
        $user = $stmt->fetch();
        $table = 'students';
    } elseif ($role === 'parent') {
        if (!$verifyValue) respond(false, 'Your mobile number is required to verify your identity.', [], 422);
        $stmt = $pdo->prepare("SELECT id FROM parents WHERE email = ? AND mobile = ?");
        $stmt->execute([$email, $verifyValue]);
        $user = $stmt->fetch();
        $table = 'parents';
    } else { // admin
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $table = 'admins';
    }

    if (!$user) {
        respond(false, 'We could not verify those details. Please double-check and try again.', [], 404);
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE $table SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);

    respond(true, 'Password updated. You can now log in with your new password.');
} catch (PDOException $e) {
    respond(false, 'Something went wrong: ' . $e->getMessage(), [], 500);
}
