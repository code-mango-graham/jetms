<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
require_admin(['scan', 'load']);

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

// The gate kiosk is public by design, so it gets its own guards:
//  1) optional IP allow-list (KIOSK_ALLOWED_IPS in config.local.php - the school's public IP)
//  2) a signed-in admin always passes (for the in-app "Open Gate Kiosk" button)
if (in_array($action, ['scan', 'load'], true) && KIOSK_ALLOWED_IPS
    && !(isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'admin')
    && !in_array(sec_client_ip(), KIOSK_ALLOWED_IPS, true)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "The gate kiosk is not available from this location."]);
    exit;
}

switch ($action) {

    // =======================
    // SCAN (gate trigger — manual LRN entry today, RFID later via the same input)
    // =======================
    case 'scan': {
        $lrn = isset($_POST['lrn']) ? trim($_POST['lrn']) : '';
        $source = (isset($_POST['source']) && $_POST['source'] === 'rfid') ? 'rfid' : 'manual';

        if ($lrn === '') {
            echo json_encode(["status" => "error", "message" => "Please scan or enter an LRN"]);
            break;
        }
        if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $lrn)) {
            echo json_encode(["status" => "error", "message" => "That does not look like a valid LRN"]);
            break;
        }

        // Anyone can reach this page, so slow down guessing: at most 30 scan attempts a minute per address.
        $recent = mysqli_prepare($conn, "SELECT COUNT(*) FROM tbl_audit_log WHERE action IN ('scan', 'scan_failed') AND ip_address = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
        $scanIp = sec_client_ip();
        mysqli_stmt_bind_param($recent, "s", $scanIp);
        mysqli_stmt_execute($recent);
        $recentCount = (int) mysqli_fetch_row(mysqli_stmt_get_result($recent))[0];
        mysqli_stmt_close($recent);
        if ($recentCount >= 30) {
            http_response_code(429);
            echo json_encode(["status" => "error", "message" => "Too many scans. Please wait a moment."]);
            break;
        }

        $stmt = mysqli_prepare($conn, "SELECT student_id, first_name, middle_name, last_name, extension_name, student_photo, student_status FROM tbl_student WHERE lrn = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $lrn);
        mysqli_stmt_execute($stmt);
        $student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$student) {
            audit_log($conn, 'scan_failed', 'tbl_attendance_log', null, 'Gate scan with an unknown LRN');
            echo json_encode(["status" => "error", "message" => "No student found with that LRN"]);
            break;
        }

        if ($student['student_status'] === 'archived') {
            echo json_encode(["status" => "error", "message" => "This student record is archived"]);
            break;
        }

        $nameParts = array_filter([
            $student['first_name'], $student['middle_name'], $student['last_name'], $student['extension_name']
        ], function ($p) { return $p !== null && trim((string) $p) !== ''; });
        $studentName = implode(' ', $nameParts);

        // Determine in/out by this student's most recent scan today
        $lastStmt = mysqli_prepare($conn, "SELECT log_type FROM tbl_attendance_log WHERE student_id = ? AND DATE(log_time) = CURDATE() ORDER BY log_time DESC, log_id DESC LIMIT 1");
        mysqli_stmt_bind_param($lastStmt, "i", $student['student_id']);
        mysqli_stmt_execute($lastStmt);
        $lastLog = mysqli_fetch_assoc(mysqli_stmt_get_result($lastStmt));
        mysqli_stmt_close($lastStmt);

        $logType = (!$lastLog || $lastLog['log_type'] === 'out') ? 'in' : 'out';

        $insert = mysqli_prepare($conn, "INSERT INTO tbl_attendance_log (student_id, lrn, student_name, log_type, source) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert, "issss", $student['student_id'], $lrn, $studentName, $logType, $source);

        if (!mysqli_stmt_execute($insert)) {
            mysqli_stmt_close($insert);
            echo json_encode(["status" => "error", "message" => "Failed to log attendance"]);
            break;
        }
        $newLogId = mysqli_insert_id($conn);
        mysqli_stmt_close($insert);
        audit_log($conn, 'scan', 'tbl_attendance_log', $newLogId, 'Gate scan (' . $logType . '): ' . $studentName . ' [LRN ' . $lrn . ']', null, null, ['source' => $source]);

        echo json_encode([
            "status" => "success",
            "message" => "Logged successfully",
            "data" => [
                "student_name" => $studentName,
                "student_photo" => $student['student_photo'],
                "log_type" => $logType,
                "log_time" => date('Y-m-d H:i:s')
            ]
        ]);
        break;
    }

    // =======================
    // LOAD (today's log by default, or a specific date)
    // =======================
    case 'load': {
        $date = isset($_POST['date']) && $_POST['date'] !== '' ? trim($_POST['date']) : date('Y-m-d');

        $stmt = mysqli_prepare($conn, "
            SELECT log_id, lrn, student_name, log_type, log_time, source
            FROM tbl_attendance_log
            WHERE DATE(log_time) = ?
            ORDER BY log_time DESC, log_id DESC
        ");
        mysqli_stmt_bind_param($stmt, "s", $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(["data" => $data]);
        break;
    }

    // =======================
    // STUDENT_HISTORY (every attendance log for one student, any date)
    // =======================
    case 'student_history': {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT log_id, log_type, log_time, source
            FROM tbl_attendance_log
            WHERE student_id = ?
            ORDER BY log_time DESC, log_id DESC
        ");
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(["data" => $data]);
        break;
    }

    // =======================
    // DELETE (correct a mistaken scan — hard delete, this is just a log)
    // =======================
    case 'delete': {
        $log_id = isset($_POST['log_id']) ? (int) $_POST['log_id'] : 0;

        $auditOld = audit_snapshot($conn, 'tbl_attendance_log', 'log_id', $log_id);
        $stmt = mysqli_prepare($conn, "DELETE FROM tbl_attendance_log WHERE log_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $log_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'delete', 'tbl_attendance_log', $log_id, 'Attendance entry deleted: ' . ($auditOld['student_name'] ?? '') . ' (' . ($auditOld['log_type'] ?? '') . ')', $auditOld);
            echo json_encode(["status" => "success", "message" => "Log entry removed"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Log entry not found"]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
