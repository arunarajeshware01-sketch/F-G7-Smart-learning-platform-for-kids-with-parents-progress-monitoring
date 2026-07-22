<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query(
        "SELECT p.id, p.name, p.mobile, p.email, p.created_at,
                (SELECT COUNT(*) FROM students s WHERE s.parent_id = p.id) AS children_count
         FROM parents p ORDER BY p.id DESC"
    )->fetchAll();
    respond(true, 'Parents list.', ['parents' => $rows]);
}

if ($method === 'POST') {
    $d = json_input();
    $action = $d['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($d['id'] ?? 0);
        $pdo->prepare("DELETE FROM parents WHERE id = ?")->execute([$id]);
        respond(true, 'Parent deleted.');
    }

    respond(false, 'Unknown action.', [], 400);
}

respond(false, 'Method not allowed.', [], 405);
