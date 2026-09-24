<?php
// Weekly database backup. Run from cron (command line only), e.g. Sundays 2 AM:
//   0 2 * * 0 /path/to/php /path/to/jetms/cron/weekly_backup.php >> /path/to/jetms/backups/cron.log 2>&1
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../config/security.php';
require __DIR__ . '/../config/backup_lib.php';

$stamp = date('Y-m-d H:i:s');
$res = create_backup('weekly');

if (!$res['ok']) {
    audit_log($conn, 'backup', 'backup', null, 'Weekly backup FAILED: ' . $res['error']);
    fwrite(STDERR, "[$stamp] Weekly backup FAILED: {$res['error']}\n");
    exit(1);
}

$removed = prune_weekly_backups();
audit_log($conn, 'backup', 'backup', null, "Weekly backup created: {$res['file']}", null, null,
    ['file' => $res['file'], 'size' => $res['size'], 'old_weekly_backups_removed' => $removed]);
echo "[$stamp] Weekly backup OK: {$res['file']} (" . number_format($res['size']) . " bytes), pruned " . count($removed) . " old\n";
