<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', [], 405);

$d = json_input();
$studentId = $_SESSION['user_id'];
$quizId    = (int)($d['quiz_id'] ?? 0);
$answers   = $d['answers'] ?? []; // { question_id: "A" }

if (!$quizId || !is_array($answers)) respond(false, 'quiz_id and answers are required.', [], 422);

$stmt = $pdo->prepare("SELECT id, correct_option FROM quiz_questions WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

$score = 0;
foreach ($questions as $q) {
    $given = strtoupper($answers[$q['id']] ?? '');
    if ($given === $q['correct_option']) $score++;
}
$total = count($questions);

$pdo->prepare("INSERT INTO quiz_results (student_id, quiz_id, score, total_questions) VALUES (?, ?, ?, ?)")
    ->execute([$studentId, $quizId, $score, $total]);

// Notify the linked parent
$parent = $pdo->prepare("SELECT parent_id FROM students WHERE id = ?");
$parent->execute([$studentId]);
$parentId = $parent->fetch()['parent_id'] ?? null;
if ($parentId) {
    $studentName = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $studentName->execute([$studentId]);
    $sname = $studentName->fetch()['name'];
    $pdo->prepare("INSERT INTO notifications (recipient_type, recipient_id, message) VALUES ('parent', ?, ?)")
        ->execute([$parentId, "$sname scored $score/$total on a quiz."]);
}

respond(true, 'Quiz submitted.', ['score' => $score, 'total' => $total]);
