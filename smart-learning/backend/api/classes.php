<?php
require_once __DIR__ . '/../includes/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(false, 'Method not allowed.', [], 405);

$rows = $pdo->query("SELECT name FROM classes ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN);
respond(true, 'Classes list.', ['classes' => $rows]);
