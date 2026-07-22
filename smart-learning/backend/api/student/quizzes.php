<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

$studentId = $_SESSION['user_id'];

// One row per quiz, with the subject name, question count, and this
// student's best previous attempt (if any) so the dashboard can show
// every available quiz in one place instead of only inside a subject panel.
$stmt = $pdo->prepare(
    "SELECT q.id, q.title, q.subject_id, sub.name AS subject_name,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
            (SELECT MAX(qr.score) FROM quiz_results qr WHERE qr.quiz_id = q.id AND qr.student_id = ?) AS best_score,
            (SELECT MAX(qr.total_questions) FROM quiz_results qr WHERE qr.quiz_id = q.id AND qr.student_id = ?) AS best_total,
            (SELECT COUNT(*) FROM quiz_results qr WHERE qr.quiz_id = q.id AND qr.student_id = ?) AS attempt_count
     FROM quizzes q
     JOIN subjects sub ON sub.id = q.subject_id
     ORDER BY sub.name, q.id"
);
$stmt->execute([$studentId, $studentId, $studentId]);

respond(true, 'Quizzes loaded.', ['quizzes' => $stmt->fetchAll()]);
