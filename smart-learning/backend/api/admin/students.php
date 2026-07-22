<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT id, name, email, age, class, parent_mobile, parent_email, parent_id, created_at FROM students ORDER BY id DESC")->fetchAll();
    respond(true, 'Students list.', ['students' => $rows]);
}

if ($method === 'POST') { // used with {"action":"delete","id":..} or {"action":"update", ...}
    $d = json_input();
    $action = $d['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($d['id'] ?? 0);
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        respond(true, 'Student deleted.');
    }

    if ($action === 'create') {
        $name  = trim($d['name'] ?? '');
        $email = trim($d['email'] ?? '');
        $age   = (int)($d['age'] ?? 0);
        $class = trim($d['class'] ?? '');
        $parentMobile = trim($d['parent_mobile'] ?? '');
        $parentEmail  = trim($d['parent_email'] ?? '');
        $password = $d['password'] ?? '';

        if (!$name || !$email || !$age || !$class || !$parentMobile || !$parentEmail || !$password) {
            respond(false, 'All fields are required.', [], 422);
        }
        $check = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) respond(false, 'A student with this email already exists.', [], 409);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare(
            "INSERT INTO students (name, email, password, age, class, parent_mobile, parent_email)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$name, $email, $hash, $age, $class, $parentMobile, $parentEmail]);
        $studentId = $pdo->lastInsertId();

        foreach ($pdo->query("SELECT id FROM subjects") as $s) {
            $pdo->prepare("INSERT IGNORE INTO progress (student_id, subject_id) VALUES (?, ?)")
                ->execute([$studentId, $s['id']]);
        }

        respond(true, 'Student added.', ['student_id' => $studentId]);
    }

    if ($action === 'update') {
        $id = (int)($d['id'] ?? 0);
        $name = trim($d['name'] ?? '');
        $class = trim($d['class'] ?? '');
        if (!$id || !$name || !$class) respond(false, 'id, name and class are required.', [], 422);
        $pdo->prepare("UPDATE students SET name = ?, class = ? WHERE id = ?")->execute([$name, $class, $id]);
        respond(true, 'Student updated.');
    }

    respond(false, 'Unknown action.', [], 400);
}

respond(false, 'Method not allowed.', [], 405);
