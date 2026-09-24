<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
require_admin();

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (DataTable list)
    // =======================
    case 'load': {
        $query = mysqli_query($conn, "
            SELECT schoolyear_id, schoolyear_name, status
            FROM tbl_schoolyear
            ORDER BY schoolyear_name DESC
        ");

        $data = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $data[] = $row;
        }

        echo json_encode(["data" => $data]);
        break;
    }

    // =======================
    // GET (single record, for edit modal)
    // =======================
    case 'get': {
        $id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;

        $query = mysqli_prepare($conn, "SELECT schoolyear_id, schoolyear_name, status FROM tbl_schoolyear WHERE schoolyear_id = ? LIMIT 1");
        mysqli_stmt_bind_param($query, "i", $id);
        mysqli_stmt_execute($query);

        $result = mysqli_stmt_get_result($query);
        $data = mysqli_fetch_assoc($result);

        mysqli_stmt_close($query);

        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "School year not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no schoolyear_id, otherwise update)
    // =======================
    case 'add': {
        $schoolyear_id = isset($_POST['schoolyear_id']) ? trim($_POST['schoolyear_id']) : '';
        $schoolyear_name = isset($_POST['schoolyear_name']) ? trim($_POST['schoolyear_name']) : '';

        if ($schoolyear_name === '') {
            echo json_encode([
                "status" => "error",
                "message" => "School year name is required"
            ]);
            break;
        }

        // Format YYYY-YYYY with consecutive years (the activation guard compares these names).
        if (!preg_match('/^(\d{4})-(\d{4})$/', $schoolyear_name, $ym) || (int) $ym[2] !== (int) $ym[1] + 1) {
            echo json_encode(["status" => "error", "message" => "Use the format 2026-2027 (two consecutive years)"]);
            break;
        }

        if (empty($schoolyear_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_schoolyear WHERE schoolyear_name = ? LIMIT 1");
            mysqli_stmt_bind_param($check, "s", $schoolyear_name);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_schoolyear WHERE schoolyear_name = ? AND schoolyear_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "si", $schoolyear_name, $schoolyear_id);
        }

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "School year already exists"
            ]);
            break;
        }

        mysqli_stmt_close($check);

        $auditOld = empty($schoolyear_id) ? null : audit_snapshot($conn, 'tbl_schoolyear', 'schoolyear_id', $schoolyear_id);
        if (empty($schoolyear_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_schoolyear (schoolyear_name) VALUES (?)");
            mysqli_stmt_bind_param($save, "s", $schoolyear_name);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_schoolyear SET schoolyear_name = ? WHERE schoolyear_id = ?");
            mysqli_stmt_bind_param($save, "si", $schoolyear_name, $schoolyear_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save school year"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        $auditId = empty($schoolyear_id) ? mysqli_insert_id($conn) : $schoolyear_id;
        $auditNew = audit_snapshot($conn, 'tbl_schoolyear', 'schoolyear_id', $auditId);
        audit_log($conn, empty($schoolyear_id) ? 'create' : 'update', 'tbl_schoolyear', $auditId, 'School year: ' . ($auditNew['schoolyear_name'] ?? ''), $auditOld, $auditNew);

        echo json_encode([
            "status" => "success",
            "message" => "School year saved successfully"
        ]);
        break;
    }

    // =======================
    // ACTIVATE (only one school year can be active at a time)
    // =======================
    case 'activate': {
        $schoolyear_id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;

        if ($schoolyear_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid school year"]);
            break;
        }

        // Verify the target exists BEFORE touching anything.
        $target = audit_snapshot($conn, 'tbl_schoolyear', 'schoolyear_id', $schoolyear_id);
        if (!$target) {
            echo json_encode(["status" => "error", "message" => "School year not found"]);
            break;
        }
        if ($target['status'] === 'active') {
            echo json_encode(["status" => "error", "message" => "That school year is already active"]);
            break;
        }

        // Going BACK to an earlier year would flip the current year's students to
        // 'completed' and leave nobody enrolled anywhere. Refuse; a backup restore is the way back.
        $currentActive = mysqli_fetch_assoc(mysqli_query($conn, "SELECT schoolyear_name FROM tbl_schoolyear WHERE status = 'active' LIMIT 1"));
        if ($currentActive && strcmp($target['schoolyear_name'], $currentActive['schoolyear_name']) < 0) {
            echo json_encode(["status" => "error", "message" => "You cannot activate " . $target['schoolyear_name'] . " because it is earlier than the current school year (" . $currentActive['schoolyear_name'] . "). If this was a mistake, restore a backup instead."]);
            break;
        }

        // High-impact change: the admin must re-enter their password.
        $pwError = confirm_admin_password($conn, isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '', 'activate school year ' . $target['schoolyear_name']);
        if ($pwError !== null) {
            echo json_encode(["status" => "error", "message" => $pwError]);
            break;
        }

        // Safety net: snapshot the whole database first. No backup, no activation.
        include_once 'backup_lib.php';
        $backup = create_backup('pre_activate');
        if (!$backup['ok']) {
            audit_log($conn, 'activate_failed', 'tbl_schoolyear', $schoolyear_id, 'Activation of ' . $target['schoolyear_name'] . ' cancelled - automatic backup failed: ' . $backup['error']);
            echo json_encode(["status" => "error", "message" => "Activation cancelled - the automatic backup could not be created (" . $backup['error'] . ")."]);
            break;
        }

        // Capture whichever school year is active right now so its still-enrolled
        // students can be marked completed once the new year takes over.
        $previous = mysqli_fetch_assoc(mysqli_query($conn, "SELECT schoolyear_id, schoolyear_name FROM tbl_schoolyear WHERE status = 'active' LIMIT 1"));
        $previousYearId = $previous ? (int) $previous['schoolyear_id'] : null;

        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "UPDATE tbl_schoolyear SET status = 'inactive'");

            $stmt = mysqli_prepare($conn, "UPDATE tbl_schoolyear SET status = 'active' WHERE schoolyear_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $schoolyear_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $completedCount = 0;
            if ($previousYearId) {
                $today = date('Y-m-d');
                $complete = mysqli_prepare($conn, "UPDATE tbl_enrollment SET status = 'completed', status_date = ? WHERE schoolyear_id = ? AND status = 'enrolled'");
                mysqli_stmt_bind_param($complete, "si", $today, $previousYearId);
                mysqli_stmt_execute($complete);
                $completedCount = mysqli_stmt_affected_rows($complete);
                mysqli_stmt_close($complete);
            }
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            audit_log($conn, 'activate_failed', 'tbl_schoolyear', $schoolyear_id, 'Activation of ' . $target['schoolyear_name'] . ' failed and was rolled back: ' . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Activation failed and was rolled back. Nothing was changed."]);
            break;
        }

        audit_log($conn, 'activate', 'tbl_schoolyear', $schoolyear_id,
            'School year activated: ' . $target['schoolyear_name'] . ($previous ? ' (was ' . $previous['schoolyear_name'] . ')' : ''),
            null, null,
            ['previous_year' => $previous ? $previous['schoolyear_name'] : null, 'students_marked_completed' => $completedCount, 'pre_activate_backup' => $backup['file']]);

        $message = 'School year activated. A backup of the previous data was saved first (' . $backup['file'] . ')';
        if ($completedCount > 0) {
            $message .= ". {$completedCount} student(s) from the previous school year marked completed.";
        }
        echo json_encode(["status" => "success", "message" => $message]);
        break;
    }

    // =======================
    // DELETE (hard delete — no soft-delete flag on this table)
    // =======================
    case 'delete': {
        $schoolyear_id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;

        if ($schoolyear_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid school year"
            ]);
            break;
        }

        $used = mysqli_fetch_row(mysqli_query($conn, "SELECT
            (SELECT COUNT(*) FROM tbl_enrollment WHERE schoolyear_id = " . (int) $schoolyear_id . ") +
            (SELECT COUNT(*) FROM tbl_class WHERE schoolyear_id = " . (int) $schoolyear_id . ")"))[0];
        if ((int) $used > 0) {
            echo json_encode(["status" => "error", "message" => "This school year has enrollments or class assignments, so it cannot be deleted. Past years are kept as history."]);
            break;
        }

        $auditOld = audit_snapshot($conn, 'tbl_schoolyear', 'schoolyear_id', $schoolyear_id);
        $stmt = mysqli_prepare($conn, "DELETE FROM tbl_schoolyear WHERE schoolyear_id = ? AND status = 'inactive'");
        mysqli_stmt_bind_param($stmt, "i", $schoolyear_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'delete', 'tbl_schoolyear', $schoolyear_id, 'School year deleted: ' . ($auditOld['schoolyear_name'] ?? ''), $auditOld);
            echo json_encode([
                "status" => "success",
                "message" => "School year deleted successfully"
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "Cannot delete the active school year"
        ]);
        break;
    }

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action"
        ]);
        break;
}
