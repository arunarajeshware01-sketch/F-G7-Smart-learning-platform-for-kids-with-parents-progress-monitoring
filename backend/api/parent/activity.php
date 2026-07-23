<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('parent');

$parentId  = $_SESSION['user_id'];
$studentId = (int)($_GET['student_id'] ?? 0);
$range     = $_GET['range'] ?? 'week'; // 'week' | 'month'

$own = $pdo->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
$own->execute([$studentId, $parentId]);
if (!$own->fetch()) respond(false, 'Student not found or not linked to your account.', [], 404);

// Pull every logged day in the relevant window, then fill in the gaps
// in PHP so days/weeks with zero activity still show up as 0 (not missing).
$daysBack = $range === 'month' ? 28 : 7;

$rows = $pdo->prepare(
    "SELECT activity_date, minutes FROM daily_activity
     WHERE student_id = ? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     ORDER BY activity_date"
);
$rows->execute([$studentId, $daysBack - 1]);

$byDate = [];
foreach ($rows->fetchAll() as $r) {
    $byDate[$r['activity_date']] = (int)$r['minutes'];
}

$points = [];

if ($range === 'week') {
    // Last 7 days, oldest to newest, ending today.
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $points[] = [
            'label'   => date('D', strtotime($date)), // Mon, Tue, ...
            'minutes' => $byDate[$date] ?? 0,
        ];
    }
} else {
    // Last 4 weeks, bucketed, oldest to newest.
    for ($w = 3; $w >= 0; $w--) {
        $bucketStart = strtotime(-(($w + 1) * 7 - 1) . ' day');
        $bucketEnd   = strtotime(-($w * 7) . ' day');
        $sum = 0;
        for ($d = $bucketStart; $d <= $bucketEnd; $d += 86400) {
            $date = date('Y-m-d', $d);
            $sum += $byDate[$date] ?? 0;
        }
        $points[] = [
            'label'   => 'Wk ' . (4 - $w),
            'minutes' => $sum,
        ];
    }
}

respond(true, 'Activity loaded.', ['range' => $range, 'points' => $points]);
