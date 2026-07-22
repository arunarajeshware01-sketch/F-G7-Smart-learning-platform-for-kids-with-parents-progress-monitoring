<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('student');

$studentId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->prepare(
        "SELECT id, message, is_read, created_at
         FROM notifications
         WHERE recipient_type = 'student' AND recipient_id = ?
         ORDER BY created_at DESC
         LIMIT 50"
    );
    $rows->execute([$studentId]);
    $notifications = $rows->fetchAll();

    $unread = $pdo->prepare(
        "SELECT COUNT(*) AS c FROM notifications
         WHERE recipient_type = 'student' AND recipient_id = ? AND is_read = 0"
    );
    $unread->execute([$studentId]);

    respond(true, 'Notifications loaded.', [
        'notifications' => $notifications,
        'unread_count'  => (int)$unread->fetch()['c'],
    ]);
}

if ($method === 'POST') {
    $d = json_input();

    // Mark all as read
    if (!empty($d['mark_all'])) {
        $pdo->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE recipient_type = 'student' AND recipient_id = ?"
        )->execute([$studentId]);
        respond(true, 'All notifications marked as read.');
    }

    // Mark a single notification as read
    $id = (int)($d['id'] ?? 0);
    if ($id) {
        $pdo->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE id = ? AND recipient_type = 'student' AND recipient_id = ?"
        )->execute([$id, $studentId]);
        respond(true, 'Notification marked as read.');
    }

    respond(false, 'Nothing to update.', [], 422);
}

respond(false, 'Method not allowed.', [], 405);
