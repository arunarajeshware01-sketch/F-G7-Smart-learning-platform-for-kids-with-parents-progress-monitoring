<?php
// ============================================================
// Shared helpers included by every API endpoint.
// ============================================================

// Allow the front-end (served from a different folder/port during
// development) to call these APIs and to send/receive cookies+JSON.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// These endpoints return live, per-request data (progress, notifications,
// content lists) — never let the browser cache a stale copy of them.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once __DIR__ . '/../config/db.php';

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(bool $success, string $message, array $extra = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function require_login(string $role): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        respond(false, 'Not authorized. Please log in as ' . $role . '.', [], 401);
    }
}
