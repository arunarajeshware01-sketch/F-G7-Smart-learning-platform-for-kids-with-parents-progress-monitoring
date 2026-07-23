<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query(
        "SELECT c.*,
                (SELECT COUNT(*) FROM students s WHERE s.class = c.name) AS student_count,
                (SELECT COUNT(*) FROM videos v WHERE v.class_level = c.name) AS video_count,
                (SELECT COUNT(*) FROM lessons l WHERE l.class_level = c.name) AS lesson_count,
                (SELECT COUNT(*) FROM quizzes q WHERE q.class_level = c.name) AS quiz_count
         FROM classes c ORDER BY c.sort_order, c.id"
    )->fetchAll();
    respond(true, 'Classes list.', ['classes' => $rows]);
}

if ($method === 'POST') {
    $d = json_input();
    $action = $d['action'] ?? 'update';

    if ($action === 'create') {
        $name = trim($d['name'] ?? '');
        if (!$name) respond(false, 'Class name is required.', [], 422);

        $exists = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
        $exists->execute([$name]);
        if ($exists->fetch()) respond(false, 'A class with that name already exists.', [], 422);

        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM classes")->fetchColumn();

        $pdo->prepare("INSERT INTO classes (name, sort_order) VALUES (?, ?)")
            ->execute([$name, $maxOrder + 1]);

        respond(true, 'Class added.', ['id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'delete') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respond(false, 'id is required.', [], 422);

        $class = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
        $class->execute([$id]);
        $class = $class->fetch();
        if (!$class) respond(false, 'Class not found.', [], 404);

        // Refuse to delete a class that's still in use, so students,
        // videos, lessons, or quizzes never end up pointing at a class
        // level that no longer exists.
        $counts = $pdo->prepare(
            "SELECT
               (SELECT COUNT(*) FROM students WHERE class = ?) AS students,
               (SELECT COUNT(*) FROM videos WHERE class_level = ?) AS videos,
               (SELECT COUNT(*) FROM lessons WHERE class_level = ?) AS lessons,
               (SELECT COUNT(*) FROM quizzes WHERE class_level = ?) AS quizzes"
        );
        $counts->execute([$class['name'], $class['name'], $class['name'], $class['name']]);
        $c = $counts->fetch();
        $total = (int)$c['students'] + (int)$c['videos'] + (int)$c['lessons'] + (int)$c['quizzes'];

        if ($total > 0) {
            respond(false,
                "Can't delete: {$c['students']} student(s) and {$c['videos']}+{$c['lessons']}+{$c['quizzes']} content item(s) are still assigned to this class. Reassign them first.",
                [], 422);
        }

        $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
        respond(true, 'Class deleted.');
    }

    // Default action: rename an existing class. Renaming also updates
    // every student/video/lesson/quiz that references the old name, so
    // nothing goes orphaned.
    $id   = (int)($d['id'] ?? 0);
    $name = trim($d['name'] ?? '');
    if (!$id || !$name) respond(false, 'id and name are required.', [], 422);

    $exists = $pdo->prepare("SELECT id FROM classes WHERE name = ? AND id != ?");
    $exists->execute([$name, $id]);
    if ($exists->fetch()) respond(false, 'A class with that name already exists.', [], 422);

    $old = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $old->execute([$id]);
    $old = $old->fetch();
    if (!$old) respond(false, 'Class not found.', [], 404);
    $oldName = $old['name'];

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE classes SET name = ? WHERE id = ?")->execute([$name, $id]);
        $pdo->prepare("UPDATE students SET class = ? WHERE class = ?")->execute([$name, $oldName]);
        $pdo->prepare("UPDATE videos SET class_level = ? WHERE class_level = ?")->execute([$name, $oldName]);
        $pdo->prepare("UPDATE lessons SET class_level = ? WHERE class_level = ?")->execute([$name, $oldName]);
        $pdo->prepare("UPDATE quizzes SET class_level = ? WHERE class_level = ?")->execute([$name, $oldName]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        respond(false, 'Failed to rename class: ' . $e->getMessage(), [], 500);
    }

    respond(true, 'Class renamed.');
}

respond(false, 'Method not allowed.', [], 405);
