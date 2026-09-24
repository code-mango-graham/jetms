<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
include 'enrollment_lib.php';
require_admin();

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (subjects offered for a given level)
    // =======================
    case 'load': {
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT subject_id, level_id, subject_name, subject_code
            FROM tbl_subject
            WHERE level_id = ? AND subject_remarks = 1
            ORDER BY subject_name ASC
        ");
        mysqli_stmt_bind_param($stmt, "i", $level_id);
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
    // GET (single record, for edit)
    // =======================
    case 'get': {
        $id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

        $query = mysqli_prepare($conn, "SELECT subject_id, level_id, subject_name, subject_code FROM tbl_subject WHERE subject_id = ? LIMIT 1");
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
                "message" => "Subject not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no subject_id, otherwise update)
    // =======================
    case 'add': {
        $subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : '';
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
        $subject_name = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
        $subject_code = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';

        if ($level_id <= 0 || $subject_name === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Level and subject name are required"
            ]);
            break;
        }

        if (empty($subject_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_subject WHERE level_id = ? AND subject_name = ? LIMIT 1");
            mysqli_stmt_bind_param($check, "is", $level_id, $subject_name);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_subject WHERE level_id = ? AND subject_name = ? AND subject_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "isi", $level_id, $subject_name, $subject_id);
        }

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "Subject already exists for this level"
            ]);
            break;
        }

        mysqli_stmt_close($check);

        $subjectCodeValue = ($subject_code === '') ? null : $subject_code;

        $auditOld = empty($subject_id) ? null : audit_snapshot($conn, 'tbl_subject', 'subject_id', $subject_id);
        if (empty($subject_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_subject (level_id, subject_name, subject_code) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($save, "iss", $level_id, $subject_name, $subjectCodeValue);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_subject SET subject_name = ?, subject_code = ? WHERE subject_id = ?");
            mysqli_stmt_bind_param($save, "ssi", $subject_name, $subjectCodeValue, $subject_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save subject"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        $auditId = empty($subject_id) ? mysqli_insert_id($conn) : $subject_id;
        // A subject added to a fixed-curriculum level also goes to students already enrolled in it.
        $attached = enrollment_sync_fixed_subjects($conn);
        $auditNew = audit_snapshot($conn, 'tbl_subject', 'subject_id', $auditId);
        audit_log($conn, empty($subject_id) ? 'create' : 'update', 'tbl_subject', $auditId, 'Subject: ' . ($auditNew['subject_name'] ?? ''), $auditOld, $auditNew);

        echo json_encode([
            "status" => "success",
            "message" => "Subject saved successfully" . ($attached > 0 ? " and added to $attached already-enrolled student(s)." : "")
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

        if ($subject_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid subject"
            ]);
            break;
        }

        $auditOld = audit_snapshot($conn, 'tbl_subject', 'subject_id', $subject_id);
        $stmt = mysqli_prepare($conn, "UPDATE tbl_subject SET subject_remarks = 0 WHERE subject_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $subject_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'archive', 'tbl_subject', $subject_id, 'Subject archived: ' . ($auditOld['subject_name'] ?? ''), $auditOld);
            echo json_encode([
                "status" => "success",
                "message" => "Subject removed successfully"
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "Subject not found or already removed"
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
