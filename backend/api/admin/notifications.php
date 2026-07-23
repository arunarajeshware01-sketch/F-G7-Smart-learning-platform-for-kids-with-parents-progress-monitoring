<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100")->fetchAll();
    respond(true, 'Notifications list.', ['notifications' => $rows]);
}

if ($method === 'POST') {
    $d = json_input();
    $recipientType = $d['recipient_type'] ?? ''; // student | parent | all_parents | all_students
    $recipientId   = (int)($d['recipient_id'] ?? 0);
    $message       = trim($d['message'] ?? '');

    if (!$message) respond(false, 'Message is required.', [], 422);

    if ($recipientType === 'all_parents') {
        $ids = $pdo->query("SELECT id FROM parents")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $pdo->prepare("INSERT INTO notifications (recipient_type, recipient_id, message) VALUES ('parent', ?, ?)")->execute([$id, $message]);
        }
        respond(true, 'Notification sent to all parents.');
    }

    if ($recipientType === 'all_students') {
        $ids = $pdo->query("SELECT id FROM students")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $pdo->prepare("INSERT INTO notifications (recipient_type, recipient_id, message) VALUES ('student', ?, ?)")->execute([$id, $message]);
        }
        respond(true, 'Notification sent to all students.');
    }

    if (in_array($recipientType, ['student', 'parent'], true) && $recipientId) {
        $pdo->prepare("INSERT INTO notifications (recipient_type, recipient_id, message) VALUES (?, ?, ?)")
            ->execute([$recipientType, $recipientId, $message]);
        respond(true, 'Notification sent.');
    }

    respond(false, 'Invalid recipient.', [], 422);
}

respond(false, 'Method not allowed.', [], 405);
