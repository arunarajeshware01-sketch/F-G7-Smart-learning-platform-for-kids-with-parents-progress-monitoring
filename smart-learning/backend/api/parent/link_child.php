<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('parent');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$parentId    = $_SESSION['user_id'];
$studentEmail = trim($d['student_email'] ?? '');

if (!$studentEmail) respond(false, 'Student email is required.', [], 422);

$student = $pdo->prepare("SELECT id, parent_email FROM students WHERE email = ?");
$student->execute([$studentEmail]);
$student = $student->fetch();
if (!$student) respond(false, 'No student found with that email.', [], 404);

$parentEmail = $pdo->prepare("SELECT email FROM parents WHERE id = ?");
$parentEmail->execute([$parentId]);
$parentEmail = $parentEmail->fetch()['email'];

if (strcasecmp($student['parent_email'], $parentEmail) !== 0) {
    respond(false, 'This student did not register with your email as the parent contact.', [], 403);
}

$pdo->prepare("UPDATE students SET parent_id = ? WHERE id = ?")->execute([$parentId, $student['id']]);
respond(true, 'Child account linked successfully.');
