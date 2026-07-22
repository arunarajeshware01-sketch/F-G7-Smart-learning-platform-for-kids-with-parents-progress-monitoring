<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Include content counts so the admin can see what's tied to a subject
    // before renaming or deleting it.
    $rows = $pdo->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) AS video_count,
                (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.id) AS lesson_count,
                (SELECT COUNT(*) FROM quizzes q WHERE q.subject_id = s.id) AS quiz_count
         FROM subjects s ORDER BY s.id"
    )->fetchAll();
    respond(true, 'Subjects list.', ['subjects' => $rows]);
}

if ($method === 'POST') {
    $d = json_input();
    $action = $d['action'] ?? 'update'; // defaults to 'update' so old id+name callers still work

    if ($action === 'create') {
        $name = trim($d['name'] ?? '');
        if (!$name) respond(false, 'Subject name is required.', [], 422);

        $exists = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
        $exists->execute([$name]);
        if ($exists->fetch()) respond(false, 'A subject with that name already exists.', [], 422);

        $pdo->prepare("INSERT INTO subjects (name) VALUES (?)")->execute([$name]);
        respond(true, 'Subject added.', ['id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'delete') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respond(false, 'id is required.', [], 422);

        // Subjects cascade-delete their videos/lessons/quizzes at the DB
        // level, so refuse to delete one that still has content — force
        // the admin to remove/reassign that content first instead of
        // silently wiping it out.
        $counts = $pdo->prepare(
            "SELECT
               (SELECT COUNT(*) FROM videos  WHERE subject_id = ?) AS videos,
               (SELECT COUNT(*) FROM lessons WHERE subject_id = ?) AS lessons,
               (SELECT COUNT(*) FROM quizzes WHERE subject_id = ?) AS quizzes"
        );
        $counts->execute([$id, $id, $id]);
        $c = $counts->fetch();
        $total = (int)$c['videos'] + (int)$c['lessons'] + (int)$c['quizzes'];

        if ($total > 0) {
            respond(false,
                "Can't delete: this subject still has {$c['videos']} video(s), {$c['lessons']} lesson(s), and {$c['quizzes']} quiz(zes). Remove that content first.",
                [], 422);
        }

        $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
        respond(true, 'Subject deleted.');
    }

    // Default action: rename an existing subject.
    $id   = (int)($d['id'] ?? 0);
    $name = trim($d['name'] ?? '');
    if (!$id || !$name) respond(false, 'id and name are required.', [], 422);

    $exists = $pdo->prepare("SELECT id FROM subjects WHERE name = ? AND id != ?");
    $exists->execute([$name, $id]);
    if ($exists->fetch()) respond(false, 'A subject with that name already exists.', [], 422);

    $pdo->prepare("UPDATE subjects SET name = ? WHERE id = ?")->execute([$name, $id]);
    respond(true, 'Subject updated.');
}

respond(false, 'Method not allowed.', [], 405);
