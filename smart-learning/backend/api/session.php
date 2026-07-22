<?php
require_once __DIR__ . '/../includes/common.php';

if (isset($_SESSION['user_id'])) {
    respond(true, 'Session active.', [
        'logged_in' => true,
        'role' => $_SESSION['role'],
        'name' => $_SESSION['name'],
        'id'   => $_SESSION['user_id'],
    ]);
}
respond(true, 'No active session.', ['logged_in' => false]);
