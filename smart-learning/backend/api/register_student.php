<?php
require_once __DIR__ . '/../includes/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$name         = trim($d['name'] ?? '');
$email        = trim($d['email'] ?? '');
$age          = (int)($d['age'] ?? 0);
$class        = trim($d['class'] ?? '');
$parentMobile = trim($d['parent_mobile'] ?? '');
$parentEmail  = trim($d['parent_email'] ?? '');
$password     = $d['password'] ?? '';

if (!$name || !$email || !$age || !$class || !$parentMobile || !$parentEmail || !$password) {
    respond(false, 'All fields are required.', [], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Invalid student email.', [], 422);
if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) respond(false, 'Invalid parent email.', [], 422);
if ($age < 6 || $age > 11) respond(false, 'Age must be between 6 and 11.', [], 422);

try {
    $check = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) respond(false, 'An account with this email already exists.', [], 409);

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Auto-link to an existing parent account if the parent already registered
    // with the same email (fulfils "Link Child Account" for that common case).
    $parentIdStmt = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
    $parentIdStmt->execute([$parentEmail]);
    $parentRow = $parentIdStmt->fetch();
    $parentId = $parentRow ? $parentRow['id'] : null;

    $stmt = $pdo->prepare(
        "INSERT INTO students (name, email, password, age, class, parent_mobile, parent_email, parent_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $email, $hash, $age, $class, $parentMobile, $parentEmail, $parentId]);
    $studentId = $pdo->lastInsertId();

    // Seed a progress row per subject so the dashboard has data to show
    foreach ($pdo->query("SELECT id FROM subjects") as $s) {
        $pdo->prepare("INSERT IGNORE INTO progress (student_id, subject_id) VALUES (?, ?)")
            ->execute([$studentId, $s['id']]);
    }

    respond(true, 'Student account created successfully.', ['student_id' => $studentId]);
} catch (PDOException $e) {
    respond(false, 'Registration failed: ' . $e->getMessage(), [], 500);
}
