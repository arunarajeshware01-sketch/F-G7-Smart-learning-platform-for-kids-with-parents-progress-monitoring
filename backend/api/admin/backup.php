<?php
require_once __DIR__ . '/../../includes/common.php';
require_login('admin');

// This uses mysqldump, which ships with XAMPP under mysql/bin.
// On Windows the default path is C:\xampp\mysql\bin\mysqldump.exe
// Adjust MYSQLDUMP_PATH below if your XAMPP is installed elsewhere.

define('MYSQLDUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe'); // Windows default
// define('MYSQLDUMP_PATH', '/opt/lampp/bin/mysqldump'); // Linux/XAMPP example — uncomment if needed

$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backupDir . $filename;

$cmd = escapeshellarg(MYSQLDUMP_PATH) . ' -h ' . escapeshellarg(DB_HOST) .
       ' -u ' . escapeshellarg(DB_USER) .
       (DB_PASS ? ' -p' . escapeshellarg(DB_PASS) : '') .
       ' ' . escapeshellarg(DB_NAME) . ' > ' . escapeshellarg($filepath);

exec($cmd . ' 2>&1', $output, $returnCode);

if ($returnCode !== 0 || !file_exists($filepath)) {
    respond(false, 'Backup failed. Check MYSQLDUMP_PATH in backup.php matches your XAMPP install. Details: ' . implode(' ', $output), [], 500);
}

respond(true, 'Backup created successfully.', ['file' => 'backups/' . $filename]);
