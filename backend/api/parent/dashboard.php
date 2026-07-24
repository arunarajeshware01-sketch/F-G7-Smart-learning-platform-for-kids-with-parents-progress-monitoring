<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('parent');

$parentId = $_SESSION['user_id'];

$children = $pdo->prepare("SELECT id, name, class FROM students WHERE parent_id = ?");
$children->execute([$parentId]);
$children = $children->fetchAll();

foreach ($children as &$child) {
    $prog = $pdo->prepare(
        "SELECT s.name AS subject_name, p.lessons_completed, p.learning_time_minutes
         FROM progress p JOIN subjects s ON s.id = p.subject_id
         WHERE p.student_id = ?"
    );
    $prog->execute([$child['id']]);
    $child['subject_progress'] = $prog->fetchAll();

    $quiz = $pdo->prepare(
        "SELECT qr.score, qr.total_questions, qr.attempted_at, q.title AS quiz_title
         FROM quiz_results qr JOIN quizzes q ON q.id = qr.quiz_id
         WHERE qr.student_id = ? ORDER BY qr.attempted_at DESC LIMIT 10"
    );
    $quiz->execute([$child['id']]);
    $child['quiz_results'] = $quiz->fetchAll();
}
unset($child);

$notif = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE recipient_type='parent' AND recipient_id=? ORDER BY created_at DESC LIMIT 20");
$notif->execute([$parentId]);

respond(true, 'Parent dashboard data.', [
    'children'      => $children,
    'notifications' => $notif->fetchAll(),
]);
