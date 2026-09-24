<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
include 'backup_lib.php';

require_admin();

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'download') {
    $file = isset($_GET['file']) ? basename($_GET['file']) : '';
    $path = backup_dir() . '/' . $file;
    if (!backup_filename_ok($file) || !is_file($path)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    audit_log($conn, 'backup_download', 'backup', null, "Downloaded backup $file");
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

header('Content-Type: application/json');

switch ($action) {

    case 'list': {
        echo json_encode(["data" => list_backups()]);
        break;
    }

    // Manual backup — password re-confirmation required
    case 'create': {
        $err = confirm_admin_password($conn, isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '', 'manual backup');
        if ($err !== null) {
            echo json_encode(["status" => "error", "message" => $err]);
            break;
        }

        $res = create_backup('manual');
        if (!$res['ok']) {
            audit_log($conn, 'backup', 'backup', null, 'Manual backup FAILED: ' . $res['error']);
            echo json_encode(["status" => "error", "message" => $res['error']]);
            break;
        }
        audit_log($conn, 'backup', 'backup', null, "Manual backup created: {$res['file']}", null, null, ['file' => $res['file'], 'size' => $res['size']]);
        echo json_encode(["status" => "success", "message" => "Backup created: " . $res['file']]);
        break;
    }

    // Restore — password + typed word required; replaces ALL current data
    case 'restore': {
        $file = isset($_POST['file']) ? basename($_POST['file']) : '';
        $typed = isset($_POST['confirm_text']) ? trim($_POST['confirm_text']) : '';

        if ($typed !== 'RESTORE') {
            echo json_encode(["status" => "error", "message" => "Type RESTORE to confirm."]);
            break;
        }
        $err = confirm_admin_password($conn, isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '', "restore $file");
        if ($err !== null) {
            echo json_encode(["status" => "error", "message" => $err]);
            break;
        }

        audit_log($conn, 'restore_start', 'backup', null, "Restore started from $file");
        $res = restore_backup($conn, $file);

        // The restored data may not contain this admin session's account — log in again after.
        if ($res['ok']) {
            audit_log($conn, 'restore', 'backup', null, "Database restored from $file", null, null, ['file' => $file, 'safety_backup' => $res['safety']]);
            $_SESSION = [];
            session_destroy();
            echo json_encode(["status" => "success", "message" => "Database restored from $file. A safety backup of the previous data was saved as {$res['safety']}. Please log in again."]);
        } else {
            audit_log($conn, 'restore_failed', 'backup', null, "Restore from $file FAILED: " . $res['error']);
            echo json_encode(["status" => "error", "message" => $res['error']]);
        }
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
