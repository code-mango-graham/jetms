<?php
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (DataTable list, with section/subject counts)
    // =======================
    case 'load': {
        $query = mysqli_query($conn, "
            SELECT
                l.level_id,
                l.level_name,
                l.level_order,
                l.allows_subject_selection,
                (SELECT COUNT(*) FROM tbl_section s WHERE s.level_id = l.level_id AND s.section_remarks = 1) AS section_count,
                (SELECT COUNT(*) FROM tbl_subject sub WHERE sub.level_id = l.level_id AND sub.subject_remarks = 1) AS subject_count
            FROM tbl_level l
            WHERE l.level_remarks = 1
            ORDER BY l.level_order ASC
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
        $id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

        $query = mysqli_prepare($conn, "SELECT level_id, level_name, level_order, allows_subject_selection FROM tbl_level WHERE level_id = ? LIMIT 1");
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
                "message" => "Level not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no level_id, otherwise update)
    // =======================
    case 'add': {
        $level_id = isset($_POST['level_id']) ? trim($_POST['level_id']) : '';
        $level_name = isset($_POST['level_name']) ? trim($_POST['level_name']) : '';
        $level_order = isset($_POST['level_order']) ? (int) $_POST['level_order'] : 0;
        $allows_subject_selection = (isset($_POST['allows_subject_selection']) && $_POST['allows_subject_selection'] == '1') ? 1 : 0;

        if ($level_name === '' || $level_order <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Level name and order are required"
            ]);
            break;
        }

        if (empty($level_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_level WHERE level_name = ? LIMIT 1");
            mysqli_stmt_bind_param($check, "s", $level_name);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_level WHERE level_name = ? AND level_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "si", $level_name, $level_id);
        }

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "Level name already exists"
            ]);
            break;
        }

        mysqli_stmt_close($check);

        if (empty($level_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_level (level_name, level_order, allows_subject_selection) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($save, "sii", $level_name, $level_order, $allows_subject_selection);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_level SET level_name = ?, level_order = ?, allows_subject_selection = ? WHERE level_id = ?");
            mysqli_stmt_bind_param($save, "siii", $level_name, $level_order, $allows_subject_selection, $level_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save level"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        echo json_encode([
            "status" => "success",
            "message" => "Level saved successfully"
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

        if ($level_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid level"
            ]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE tbl_level SET level_remarks = 0 WHERE level_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $level_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                "status" => "success",
                "message" => "Level archived successfully"
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "Level not found or already archived"
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
