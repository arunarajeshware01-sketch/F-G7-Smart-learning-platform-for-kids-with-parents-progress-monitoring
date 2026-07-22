<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$totalStudents = $pdo->query("SELECT COUNT(*) c FROM students")->fetch()['c'];
$totalParents  = $pdo->query("SELECT COUNT(*) c FROM parents")->fetch()['c'];
$totalQuizzes  = $pdo->query("SELECT COUNT(*) c FROM quizzes")->fetch()['c'];
$avgScore      = $pdo->query("SELECT ROUND(AVG(score/total_questions)*100,1) a FROM quiz_results")->fetch()['a'];

$bySubject = $pdo->query(
    "SELECT sub.name AS subject_name,
            COUNT(DISTINCT p.student_id) AS learners,
            COALESCE(SUM(p.lessons_completed),0) AS lessons_completed,
            COALESCE(SUM(p.learning_time_minutes),0) AS total_minutes
     FROM subjects sub LEFT JOIN progress p ON p.subject_id = sub.id
     GROUP BY sub.id"
)->fetchAll();

respond(true, 'Admin report summary.', [
    'total_students' => (int)$totalStudents,
    'total_parents'  => (int)$totalParents,
    'total_quizzes'  => (int)$totalQuizzes,
    'average_score_percent' => $avgScore !== null ? (float)$avgScore : 0,
    'by_subject' => $bySubject,
]);
