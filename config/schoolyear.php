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
            echo json_encode([
                "status" => "error",
                "message" => "Invalid school year"
            ]);
            break;
        }

        // Capture whichever school year is active right now (if any, and if it's not
        // the one we're about to (re)activate) so its still-enrolled students can be
        // marked completed once the new year takes over.
        $previous = mysqli_fetch_assoc(mysqli_query($conn, "SELECT schoolyear_id FROM tbl_schoolyear WHERE status = 'active' LIMIT 1"));
        $previousYearId = ($previous && (int) $previous['schoolyear_id'] !== $schoolyear_id) ? (int) $previous['schoolyear_id'] : null;

        mysqli_query($conn, "UPDATE tbl_schoolyear SET status = 'inactive'");

        $stmt = mysqli_prepare($conn, "UPDATE tbl_schoolyear SET status = 'active' WHERE schoolyear_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $schoolyear_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
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

            $message = 'School year activated';
            if ($completedCount > 0) {
                $message .= ". {$completedCount} student(s) from the previous school year marked completed.";
            }

            echo json_encode([
                "status" => "success",
                "message" => $message
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "School year not found"
        ]);
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

        $stmt = mysqli_prepare($conn, "DELETE FROM tbl_schoolyear WHERE schoolyear_id = ? AND status = 'inactive'");
        mysqli_stmt_bind_param($stmt, "i", $schoolyear_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
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
