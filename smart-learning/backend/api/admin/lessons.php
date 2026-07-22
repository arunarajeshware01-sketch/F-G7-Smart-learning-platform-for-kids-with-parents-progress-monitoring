<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query(
        "SELECT l.id, l.title, l.description, l.content_url, l.class_level,
                l.subject_id, s.name AS subject_name
         FROM lessons l JOIN subjects s ON s.id = l.subject_id ORDER BY l.id DESC"
    )->fetchAll();
    respond(true, 'Lessons list.', ['lessons' => $rows]);
}

if ($method === 'POST') {
    $d = json_input();
    $action = $d['action'] ?? 'create';

    if ($action === 'delete') {
        $lessonId = (int)($d['id'] ?? 0);
        if ($lessonId) {
            $lesson = $pdo->prepare("SELECT subject_id FROM lessons WHERE id = ?");
            $lesson->execute([$lessonId]);
            $lesson = $lesson->fetch();

            if ($lesson) {
                // Same cleanup as video deletion: don't let students keep
                // "credit" for a lesson that no longer exists.
                $completedStudents = $pdo->prepare(
                    "SELECT student_id FROM content_completions WHERE content_type = 'lesson' AND content_id = ?"
                );
                $completedStudents->execute([$lessonId]);
                $studentIds = $completedStudents->fetchAll(PDO::FETCH_COLUMN);

                if ($studentIds) {
                    $dec = $pdo->prepare(
                        "UPDATE progress SET lessons_completed = GREATEST(lessons_completed - 1, 0)
                         WHERE student_id = ? AND subject_id = ?"
                    );
                    foreach ($studentIds as $sid) {
                        $dec->execute([$sid, $lesson['subject_id']]);
                    }
                }

                $pdo->prepare("DELETE FROM content_completions WHERE content_type = 'lesson' AND content_id = ?")
                    ->execute([$lessonId]);
            }

            $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$lessonId]);
        }
        respond(true, 'Lesson deleted.');
    }

    $subjectId  = (int)($d['subject_id'] ?? 0);
    $classLevel = trim($d['class_level'] ?? '');
    $title      = trim($d['title'] ?? '');
    $description = trim($d['description'] ?? '');
    $contentUrl = trim($d['content_url'] ?? ''); // link to an uploaded file or external resource

    if (!$subjectId || !$classLevel || !$title) respond(false, 'subject_id, class_level and title are required.', [], 422);

    if ($action === 'update') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respond(false, 'id is required to update a lesson.', [], 422);
        $pdo->prepare(
            "UPDATE lessons SET subject_id = ?, class_level = ?, title = ?, description = ?, content_url = ? WHERE id = ?"
        )->execute([$subjectId, $classLevel, $title, $description, $contentUrl, $id]);
        respond(true, 'Lesson updated.');
    }

    $pdo->prepare("INSERT INTO lessons (subject_id, class_level, title, description, content_url) VALUES (?, ?, ?, ?, ?)")
        ->execute([$subjectId, $classLevel, $title, $description, $contentUrl]);

    respond(true, 'Lesson/study material added.');
}

respond(false, 'Method not allowed.', [], 405);
