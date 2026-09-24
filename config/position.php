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
        // include_id: also return one archived row (a teacher's current value) so the edit form can keep it
        $includeId = isset($_POST['include_id']) ? (int) $_POST['include_id'] : 0;
        $query = mysqli_query($conn, "
            SELECT position_id, position_title, description, position_remarks
            FROM tbl_position
            WHERE position_remarks = 1 OR position_id = $includeId
            ORDER BY position_id ASC
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
        $id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;

        $query = mysqli_prepare($conn, "SELECT position_id, position_title, description FROM tbl_position WHERE position_id = ? LIMIT 1");
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
                "message" => "Position not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no position_id, otherwise update)
    // =======================
    case 'add': {
        $position_id    = isset($_POST['position_id']) ? trim($_POST['position_id']) : '';
        $position_title = isset($_POST['position_title']) ? trim($_POST['position_title']) : '';
        $description    = isset($_POST['description']) ? trim($_POST['description']) : '';

        if ($position_title === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Position title is required"
            ]);
            break;
        }

        if (empty($position_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_position WHERE position_title = ? LIMIT 1");
            mysqli_stmt_bind_param($check, "s", $position_title);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_position WHERE position_title = ? AND position_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "si", $position_title, $position_id);
        }

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "Position title already exists"
            ]);
            break;
        }

        mysqli_stmt_close($check);

        $auditOld = empty($position_id) ? null : audit_snapshot($conn, 'tbl_position', 'position_id', $position_id);
        if (empty($position_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_position (position_title, description, position_remarks) VALUES (?, ?, 1)");
            mysqli_stmt_bind_param($save, "ss", $position_title, $description);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_position SET position_title = ?, description = ? WHERE position_id = ?");
            mysqli_stmt_bind_param($save, "ssi", $position_title, $description, $position_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save position"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        $auditId = empty($position_id) ? mysqli_insert_id($conn) : $position_id;
        $auditNew = audit_snapshot($conn, 'tbl_position', 'position_id', $auditId);
        audit_log($conn, empty($position_id) ? 'create' : 'update', 'tbl_position', $auditId, 'Position: ' . ($auditNew['position_title'] ?? ''), $auditOld, $auditNew);

        echo json_encode([
            "status" => "success",
            "message" => "Saved successfully"
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $position_id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;

        if ($position_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid position ID"
            ]);
            break;
        }

        $auditOld = audit_snapshot($conn, 'tbl_position', 'position_id', $position_id);
        $stmt = mysqli_prepare($conn, "UPDATE tbl_position SET position_remarks = 0 WHERE position_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $position_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($result) {
            audit_log($conn, 'archive', 'tbl_position', $position_id, 'Position archived: ' . ($auditOld['position_title'] ?? ''), $auditOld);
        }

        if ($result) {
            echo json_encode([
                "status" => "success",
                "message" => "Position archived successfully"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Error archiving position"
            ]);
        }
        break;
    }

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action"
        ]);
        break;
}
