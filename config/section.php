<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
require_admin();

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (sections for a given level)
    // =======================
    case 'load': {
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT section_id, level_id, section_name
            FROM tbl_section
            WHERE level_id = ? AND section_remarks = 1
            ORDER BY section_name ASC
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
        $id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $query = mysqli_prepare($conn, "SELECT section_id, level_id, section_name FROM tbl_section WHERE section_id = ? LIMIT 1");
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
                "message" => "Section not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no section_id, otherwise update)
    // =======================
    case 'add': {
        $section_id = isset($_POST['section_id']) ? trim($_POST['section_id']) : '';
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
        $section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';

        if ($level_id <= 0 || $section_name === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Level and section name are required"
            ]);
            break;
        }

        if (empty($section_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_section WHERE level_id = ? AND section_name = ? LIMIT 1");
            mysqli_stmt_bind_param($check, "is", $level_id, $section_name);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_section WHERE level_id = ? AND section_name = ? AND section_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "isi", $level_id, $section_name, $section_id);
        }

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "Section already exists for this level"
            ]);
            break;
        }

        mysqli_stmt_close($check);

        $auditOld = empty($section_id) ? null : audit_snapshot($conn, 'tbl_section', 'section_id', $section_id);
        if (empty($section_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_section (level_id, section_name) VALUES (?, ?)");
            mysqli_stmt_bind_param($save, "is", $level_id, $section_name);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_section SET section_name = ? WHERE section_id = ?");
            mysqli_stmt_bind_param($save, "si", $section_name, $section_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save section"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        $auditId = empty($section_id) ? mysqli_insert_id($conn) : $section_id;
        $auditNew = audit_snapshot($conn, 'tbl_section', 'section_id', $auditId);
        audit_log($conn, empty($section_id) ? 'create' : 'update', 'tbl_section', $auditId, 'Section: ' . ($auditNew['section_name'] ?? ''), $auditOld, $auditNew);

        echo json_encode([
            "status" => "success",
            "message" => "Section saved successfully"
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        if ($section_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid section"
            ]);
            break;
        }

        $auditOld = audit_snapshot($conn, 'tbl_section', 'section_id', $section_id);
        $stmt = mysqli_prepare($conn, "UPDATE tbl_section SET section_remarks = 0 WHERE section_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $section_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'archive', 'tbl_section', $section_id, 'Section archived: ' . ($auditOld['section_name'] ?? ''), $auditOld);
            echo json_encode([
                "status" => "success",
                "message" => "Section removed successfully"
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "Section not found or already removed"
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
