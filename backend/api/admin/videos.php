<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query(
        "SELECT v.id, v.title, v.video_url, v.class_level, v.subject_id, s.name AS subject_name
         FROM videos v JOIN subjects s ON s.id = v.subject_id ORDER BY v.id DESC"
    )->fetchAll();
    respond(true, 'Videos list.', ['videos' => $rows]);
}

if ($method === 'POST') {
    // Delete/update actions arrive as JSON
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $d = json_input();
        $action = $d['action'] ?? '';

        if ($action === 'delete') {
            $videoId = (int)($d['id'] ?? 0);
            if ($videoId) {
                $video = $pdo->prepare("SELECT subject_id FROM videos WHERE id = ?");
                $video->execute([$videoId]);
                $video = $video->fetch();

                if ($video) {
                    // Every student who had this video marked "completed" needs
                    // their lessons_completed count decremented, otherwise the
                    // progress bar keeps counting content that no longer exists
                    // (e.g. showing "3 of 1 completed").
                    $completedStudents = $pdo->prepare(
                        "SELECT student_id FROM content_completions WHERE content_type = 'video' AND content_id = ?"
                    );
                    $completedStudents->execute([$videoId]);
                    $studentIds = $completedStudents->fetchAll(PDO::FETCH_COLUMN);

                    if ($studentIds) {
                        $dec = $pdo->prepare(
                            "UPDATE progress SET lessons_completed = GREATEST(lessons_completed - 1, 0)
                             WHERE student_id = ? AND subject_id = ?"
                        );
                        foreach ($studentIds as $sid) {
                            $dec->execute([$sid, $video['subject_id']]);
                        }
                    }

                    $pdo->prepare("DELETE FROM content_completions WHERE content_type = 'video' AND content_id = ?")
                        ->execute([$videoId]);
                }

                $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$videoId]);
            }
            respond(true, 'Video deleted.');
        }

        if ($action === 'update') {
            // Edits title/subject/class only — replacing the file itself
            // requires deleting and re-uploading.
            $id         = (int)($d['id'] ?? 0);
            $subjectId  = (int)($d['subject_id'] ?? 0);
            $classLevel = trim($d['class_level'] ?? '');
            $title      = trim($d['title'] ?? '');
            if (!$id || !$subjectId || !$classLevel || !$title) {
                respond(false, 'id, subject_id, class_level and title are required.', [], 422);
            }
            $pdo->prepare("UPDATE videos SET subject_id = ?, class_level = ?, title = ? WHERE id = ?")
                ->execute([$subjectId, $classLevel, $title, $id]);
            respond(true, 'Video details updated.');
        }

        respond(false, 'Unknown action.', [], 400);
    }

    // Otherwise this is a multipart file upload:
    // fields: subject_id, class_level, title, video (file)
    $subjectId  = (int)($_POST['subject_id'] ?? 0);
    $classLevel = trim($_POST['class_level'] ?? '');
    $title      = trim($_POST['title'] ?? '');

    if (!$subjectId || !$classLevel || !$title) respond(false, 'subject_id, class_level and title are required.', [], 422);
    if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'A valid video file is required.', [], 422);
    }

    $allowed = ['mp4', 'webm', 'mov'];
    $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) respond(false, 'Allowed video formats: mp4, webm, mov.', [], 422);

    $filename = 'video_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir  = __DIR__ . '/../../uploads/videos/';
    $destPath = $destDir . $filename;

    if (!move_uploaded_file($_FILES['video']['tmp_name'], $destPath)) {
        respond(false, 'Failed to save the uploaded file.', [], 500);
    }

    $publicUrl = 'uploads/videos/' . $filename; // relative to /backend/

    $pdo->prepare("INSERT INTO videos (subject_id, class_level, title, video_url, uploaded_by) VALUES (?, ?, ?, ?, ?)")
        ->execute([$subjectId, $classLevel, $title, $publicUrl, $_SESSION['user_id']]);

    respond(true, 'Video uploaded successfully.', ['url' => $publicUrl]);
}

respond(false, 'Method not allowed.', [], 405);
