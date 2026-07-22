<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

$subjectId = (int)($_GET['subject_id'] ?? 0);
if (!$subjectId) respond(false, 'subject_id is required.', [], 422);

$studentId = $_SESSION['user_id'];

$lessons = $pdo->prepare(
    "SELECT l.id, l.title, l.description, l.content_url,
            EXISTS(SELECT 1 FROM content_completions c
                   WHERE c.student_id = ? AND c.content_type = 'lesson' AND c.content_id = l.id) AS completed
     FROM lessons l WHERE l.subject_id = ? ORDER BY l.id"
);
$lessons->execute([$studentId, $subjectId]);

$videos = $pdo->prepare(
    "SELECT v.id, v.title, v.video_url,
            EXISTS(SELECT 1 FROM content_completions c
                   WHERE c.student_id = ? AND c.content_type = 'video' AND c.content_id = v.id) AS completed
     FROM videos v WHERE v.subject_id = ? ORDER BY v.id"
);
$videos->execute([$studentId, $subjectId]);

$quizzes = $pdo->prepare("SELECT id, title FROM quizzes WHERE subject_id = ? ORDER BY id");
$quizzes->execute([$subjectId]);

respond(true, 'Content loaded.', [
    'lessons' => $lessons->fetchAll(),
    'videos'  => $videos->fetchAll(),
    'quizzes' => $quizzes->fetchAll(),
]);
