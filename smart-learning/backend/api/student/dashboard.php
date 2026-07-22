<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

$studentId = $_SESSION['user_id'];

try {

$student = $pdo->prepare("SELECT id, name, class, daily_goal_minutes FROM students WHERE id = ?");
$student->execute([$studentId]);
$student = $student->fetch();

// total_content = how many videos + lessons exist for that subject right now.
// This gives the progress bar a real denominator instead of a guessed multiplier.
// lessons_completed is clamped to total_content: if content was ever deleted
// out from under a student's completions, the display should never show
// something impossible like "3 of 1 completed".
$progress = $pdo->prepare(
    "SELECT s.id AS subject_id, s.name AS subject_name,
            LEAST(
              COALESCE(p.lessons_completed, 0),
              (
                (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) +
                (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.id)
              )
            ) AS lessons_completed,
            COALESCE(p.learning_time_minutes, 0) AS learning_time_minutes,
            (
              (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) +
              (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.id)
            ) AS total_content
     FROM subjects s
     LEFT JOIN progress p ON p.subject_id = s.id AND p.student_id = ?
     ORDER BY s.id"
);
$progress->execute([$studentId]);
$subjects = $progress->fetchAll();

$scores = $pdo->prepare(
    "SELECT qr.score, qr.total_questions, qr.attempted_at, q.title AS quiz_title, sub.name AS subject_name
     FROM quiz_results qr
     JOIN quizzes q ON q.id = qr.quiz_id
     JOIN subjects sub ON sub.id = q.subject_id
     WHERE qr.student_id = ?
     ORDER BY qr.attempted_at DESC LIMIT 10"
);
$scores->execute([$studentId]);

$totalMinutesToday = $pdo->prepare(
    "SELECT COALESCE(SUM(learning_time_minutes),0) AS total FROM progress WHERE student_id = ?"
);
$totalMinutesToday->execute([$studentId]);

// "Continue where you left off": videos + lessons this student hasn't completed yet.
$continueVideos = $pdo->prepare(
    "SELECT v.id AS content_id, 'video' AS content_type, v.title, s.name AS subject_name, s.id AS subject_id
     FROM videos v
     JOIN subjects s ON s.id = v.subject_id
     WHERE NOT EXISTS (
       SELECT 1 FROM content_completions c
       WHERE c.student_id = ? AND c.content_type = 'video' AND c.content_id = v.id
     )
     ORDER BY v.id LIMIT 6"
);
$continueVideos->execute([$studentId]);

$continueLessons = $pdo->prepare(
    "SELECT l.id AS content_id, 'lesson' AS content_type, l.title, s.name AS subject_name, s.id AS subject_id
     FROM lessons l
     JOIN subjects s ON s.id = l.subject_id
     WHERE NOT EXISTS (
       SELECT 1 FROM content_completions c
       WHERE c.student_id = ? AND c.content_type = 'lesson' AND c.content_id = l.id
     )
     ORDER BY l.id LIMIT 6"
);
$continueLessons->execute([$studentId]);

$continueQueue = array_slice(array_merge($continueVideos->fetchAll(), $continueLessons->fetchAll()), 0, 6);

respond(true, 'Dashboard data.', [
    'student'         => $student,
    'subjects'        => $subjects,
    'recent_scores'   => $scores->fetchAll(),
    'total_minutes'   => (int)$totalMinutesToday->fetch()['total'],
    'continue_queue'  => $continueQueue,
]);

} catch (PDOException $e) {
    respond(false, 'Dashboard failed to load: ' . $e->getMessage(), [], 500);
}
