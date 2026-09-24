<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';

require_admin();
header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';
$date_from = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? $_POST['date_from'] . ' 00:00:00' : null;
$date_to = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? $_POST['date_to'] . ' 23:59:59' : null;
$LIMIT = 5000; // newest rows; narrow with the date filters for older history

function run_log_query($conn, $sql, $dateCol, $date_from, $date_to, $limit) {
    $where = [];
    $types = '';
    $params = [];
    if ($date_from !== null) { $where[] = "$dateCol >= ?"; $types .= 's'; $params[] = $date_from; }
    if ($date_to !== null)   { $where[] = "$dateCol <= ?"; $types .= 's'; $params[] = $date_to; }
    $sql .= $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $sql .= " ORDER BY log_id DESC LIMIT " . (int) $limit;
    $stmt = mysqli_prepare($conn, $sql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

switch ($action) {
    case 'audit_load':
        echo json_encode(["data" => run_log_query($conn,
            "SELECT log_id, created_at, actor_role, actor_id, actor_name, action, entity, entity_id, summary, details, ip_address FROM tbl_audit_log",
            'created_at', $date_from, $date_to, $LIMIT)]);
        break;

    case 'login_load':
        echo json_encode(["data" => run_log_query($conn,
            "SELECT log_id, created_at, event, role_attempted, username, user_ref_id, failure_reason, ip_address, user_agent FROM tbl_login_log",
            'created_at', $date_from, $date_to, $LIMIT)]);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
