<?php
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (DataTable list)
    // =======================
    case 'load': {
        $query = mysqli_query($conn, "
            SELECT office_id, office_name, office_description
            FROM tbl_office
            WHERE office_remarks = 1
            ORDER BY office_id ASC
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
        $id = $_POST['office_id'] ?? '';

        if (empty($id)) {
            echo json_encode([
                "status" => "error",
                "message" => "Office ID is required"
            ]);
            break;
        }

        $stmt = mysqli_prepare($conn, "SELECT office_id, office_name, office_description FROM tbl_office WHERE office_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Office not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no office_id, otherwise update)
    // =======================
    case 'add': {
        $office_id   = isset($_POST['office_id']) ? trim($_POST['office_id']) : '';
        $office_name = isset($_POST['office_name']) ? trim($_POST['office_name']) : '';
        $office_description = isset($_POST['office_description']) ? trim($_POST['office_description']) : '';

        if ($office_name === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Office name is required"
            ]);
            break;
        }

        if (empty($office_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_office WHERE office_name = ? LIMIT 1");
            mysqli_stmt_bind_param($check, "s", $office_name);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_office WHERE office_name = ? AND office_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "si", $office_name, $office_id);
        }

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "Office name already exists"
            ]);
            break;
        }

        mysqli_stmt_close($check);

        if (empty($office_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_office (office_name, office_description) VALUES (?, ?)");
            mysqli_stmt_bind_param($save, "ss", $office_name, $office_description);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_office SET office_name = ?, office_description = ? WHERE office_id = ?");
            mysqli_stmt_bind_param($save, "ssi", $office_name, $office_description, $office_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save office"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        echo json_encode([
            "status" => "success",
            "message" => "Office saved successfully"
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $office_id = isset($_POST['office_id']) ? (int) $_POST['office_id'] : 0;

        if ($office_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid office ID"
            ]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE tbl_office SET office_remarks = 0 WHERE office_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $office_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            echo json_encode([
                "status" => "success",
                "message" => "Office archived successfully"
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "Office not found or already archived"
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
