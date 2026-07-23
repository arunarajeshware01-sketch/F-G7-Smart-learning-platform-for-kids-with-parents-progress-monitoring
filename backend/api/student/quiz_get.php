<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) respond(false, 'quiz_id is required.', [], 422);

$quiz = $pdo->prepare("SELECT id, title, subject_id, class_level FROM quizzes WHERE id = ?");
$quiz->execute([$quizId]);
$quiz = $quiz->fetch();
if (!$quiz) respond(false, 'Quiz not found.', [], 404);

$q = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM quiz_questions WHERE quiz_id = ?");
$q->execute([$quizId]);

respond(true, 'Quiz loaded.', ['quiz' => $quiz, 'questions' => $q->fetchAll()]);
