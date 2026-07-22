<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('parent');

$parentId  = $_SESSION['user_id'];
$studentId = (int)($_GET['student_id'] ?? 0);
$period    = $_GET['period'] ?? 'weekly'; // daily | weekly | monthly

$own = $pdo->prepare("SELECT id, name FROM students WHERE id = ? AND parent_id = ?");
$own->execute([$studentId, $parentId]);
$student = $own->fetch();
if (!$student) respond(false, 'Student not found or not linked to your account.', [], 404);

$interval = ['daily' => '1 DAY', 'weekly' => '7 DAY', 'monthly' => '30 DAY'][$period] ?? '7 DAY';

$quizzes = $pdo->prepare(
    "SELECT q.title AS quiz_title, sub.name AS subject_name, qr.score, qr.total_questions, qr.attempted_at
     FROM quiz_results qr
     JOIN quizzes q ON q.id = qr.quiz_id
     JOIN subjects sub ON sub.id = q.subject_id
     WHERE qr.student_id = ? AND qr.attempted_at >= DATE_SUB(NOW(), INTERVAL $interval)
     ORDER BY qr.attempted_at DESC"
);
$quizzes->execute([$studentId]);

$progress = $pdo->prepare(
    "SELECT s.name AS subject_name, p.lessons_completed, p.learning_time_minutes
     FROM progress p JOIN subjects s ON s.id = p.subject_id WHERE p.student_id = ?"
);
$progress->execute([$studentId]);

respond(true, ucfirst($period) . ' report generated.', [
    'student'  => $student,
    'period'   => $period,
    'quizzes'  => $quizzes->fetchAll(),
    'progress' => $progress->fetchAll(),
]);
