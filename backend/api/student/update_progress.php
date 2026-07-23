<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$studentId   = $_SESSION['user_id'];
$subjectId   = (int)($d['subject_id'] ?? 0);
$minutesSpent = max(0, (int)($d['minutes'] ?? 0));
$contentType = $d['content_type'] ?? null;   // 'lesson' | 'video' | null
$contentId   = (int)($d['content_id'] ?? 0);

if (!$subjectId) respond(false, 'subject_id is required.', [], 422);

$firstTimeCompletion = false;

// Only credit "lessons completed" once per piece of content, so rewatching
// the same video repeatedly doesn't keep inflating the count.
if ($contentType && $contentId) {
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO content_completions (student_id, content_type, content_id) VALUES (?, ?, ?)"
    );
    $stmt->execute([$studentId, $contentType, $contentId]);
    $firstTimeCompletion = $stmt->rowCount() > 0;
} else {
    // No specific content reference given — treat as a one-off manual credit.
    $firstTimeCompletion = true;
}

$pdo->prepare(
    "INSERT INTO progress (student_id, subject_id, lessons_completed, learning_time_minutes)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        lessons_completed = lessons_completed + VALUES(lessons_completed),
        learning_time_minutes = learning_time_minutes + VALUES(learning_time_minutes)"
)->execute([$studentId, $subjectId, $firstTimeCompletion ? 1 : 0, $minutesSpent]);

// Log today's minutes so the parent dashboard's "Learning activity" chart
// has real day-by-day data instead of nothing to show.
if ($minutesSpent > 0) {
    $pdo->prepare(
        "INSERT INTO daily_activity (student_id, activity_date, minutes)
         VALUES (?, CURDATE(), ?)
         ON DUPLICATE KEY UPDATE minutes = minutes + VALUES(minutes)"
    )->execute([$studentId, $minutesSpent]);
}

respond(true, 'Progress updated.', ['counted_as_new_lesson' => $firstTimeCompletion]);
