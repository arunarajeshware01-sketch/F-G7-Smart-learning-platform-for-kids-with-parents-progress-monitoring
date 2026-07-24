<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);

    if ($quizId) {
        // Full detail for one quiz, including its questions, for editing.
        $quiz = $pdo->prepare(
            "SELECT q.id, q.title, q.subject_id, q.class_level, s.name AS subject_name
             FROM quizzes q JOIN subjects s ON s.id = q.subject_id WHERE q.id = ?"
        );
        $quiz->execute([$quizId]);
        $quiz = $quiz->fetch();
        if (!$quiz) respond(false, 'Quiz not found.', [], 404);

        $qs = $pdo->prepare(
            "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option
             FROM quiz_questions WHERE quiz_id = ? ORDER BY id"
        );
        $qs->execute([$quizId]);

        respond(true, 'Quiz loaded.', ['quiz' => $quiz, 'questions' => $qs->fetchAll()]);
    }

    $rows = $pdo->query(
        "SELECT q.id, q.title, q.class_level, q.subject_id, s.name AS subject_name,
                (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
                (SELECT COUNT(*) FROM quiz_results qr WHERE qr.quiz_id = q.id) AS attempt_count
         FROM quizzes q JOIN subjects s ON s.id = q.subject_id ORDER BY q.id DESC"
    )->fetchAll();
    respond(true, 'Quizzes list.', ['quizzes' => $rows]);
}

if ($method === 'POST') {
    $d = json_input();
    $action = $d['action'] ?? 'create';

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM quizzes WHERE id = ?")->execute([(int)($d['id'] ?? 0)]);
        respond(true, 'Quiz deleted.');
    }

    if ($action === 'update') {
        // Edits an existing quiz: metadata, and the full question set is
        // replaced wholesale (simplest way to guarantee no orphaned rows
        // or stale correct-answers left behind from a previous version).
        $quizId     = (int)($d['id'] ?? 0);
        $subjectId  = (int)($d['subject_id'] ?? 0);
        $classLevel = trim($d['class_level'] ?? '');
        $title      = trim($d['title'] ?? '');
        $questions  = $d['questions'] ?? [];

        if (!$quizId) respond(false, 'Quiz id is required.', [], 422);
        if (!$subjectId || !$classLevel || !$title || !is_array($questions) || count($questions) === 0) {
            respond(false, 'subject_id, class_level, title and at least one question are required.', [], 422);
        }

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE quizzes SET subject_id = ?, class_level = ?, title = ? WHERE id = ?")
                ->execute([$subjectId, $classLevel, $title, $quizId]);

            $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$quizId]);

            $qStmt = $pdo->prepare(
                "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($questions as $q) {
                $correct = strtoupper(trim($q['correct_option'] ?? ''));
                if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                    throw new Exception('Each question needs a correct_option of A, B, C or D.');
                }
                $qStmt->execute([
                    $quizId,
                    trim($q['question_text'] ?? ''),
                    trim($q['option_a'] ?? ''),
                    trim($q['option_b'] ?? ''),
                    trim($q['option_c'] ?? ''),
                    trim($q['option_d'] ?? ''),
                    $correct,
                ]);
            }

            $pdo->commit();
            respond(true, 'Quiz updated successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            respond(false, 'Failed to update quiz: ' . $e->getMessage(), [], 500);
        }
    }

    // action === 'create'
    // Expected payload:
    // { subject_id, class_level, title, questions: [ { question_text, option_a..d, correct_option } ] }
    $subjectId  = (int)($d['subject_id'] ?? 0);
    $classLevel = trim($d['class_level'] ?? '');
    $title      = trim($d['title'] ?? '');
    $questions  = $d['questions'] ?? [];

    if (!$subjectId || !$classLevel || !$title || !is_array($questions) || count($questions) === 0) {
        respond(false, 'subject_id, class_level, title and at least one question are required.', [], 422);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("INSERT INTO quizzes (subject_id, class_level, title, created_by) VALUES (?, ?, ?, ?)")
            ->execute([$subjectId, $classLevel, $title, $_SESSION['user_id']]);
        $quizId = $pdo->lastInsertId();

        $qStmt = $pdo->prepare(
            "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($questions as $q) {
            $correct = strtoupper(trim($q['correct_option'] ?? ''));
            if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                throw new Exception('Each question needs a correct_option of A, B, C or D.');
            }
            $qStmt->execute([
                $quizId,
                trim($q['question_text'] ?? ''),
                trim($q['option_a'] ?? ''),
                trim($q['option_b'] ?? ''),
                trim($q['option_c'] ?? ''),
                trim($q['option_d'] ?? ''),
                $correct,
            ]);
        }

        $pdo->commit();
        respond(true, 'Quiz created successfully.', ['quiz_id' => $quizId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        respond(false, 'Failed to create quiz: ' . $e->getMessage(), [], 500);
    }
}

respond(false, 'Method not allowed.', [], 405);
