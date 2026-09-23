<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (events within a date range — used to fill a calendar month view)
    // =======================
    case 'load': {
        if (!isset($_SESSION['auth'])) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $range_start = isset($_POST['range_start']) ? trim($_POST['range_start']) : '';
        $range_end = isset($_POST['range_end']) ? trim($_POST['range_end']) : '';

        if ($range_start === '' || $range_end === '') {
            echo json_encode(["status" => "error", "message" => "range_start and range_end are required"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT event_id, title, description, event_type, start_date, end_date, admin_name
            FROM tbl_event
            WHERE event_remarks = 1
              AND start_date <= ?
              AND COALESCE(end_date, start_date) >= ?
            ORDER BY start_date ASC
        ");
        mysqli_stmt_bind_param($stmt, "ss", $range_end, $range_start);
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
    // UPCOMING (next N events from today — used by the Dashboard widget)
    // =======================
    case 'upcoming': {
        if (!isset($_SESSION['auth'])) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 5;
        if ($limit <= 0 || $limit > 20) {
            $limit = 5;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT event_id, title, event_type, start_date, end_date
            FROM tbl_event
            WHERE event_remarks = 1 AND COALESCE(end_date, start_date) >= CURDATE()
            ORDER BY start_date ASC
            LIMIT ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $limit);
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
    // GET (single event, for edit)
    // =======================
    case 'get': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        $stmt = mysqli_prepare($conn, "SELECT event_id, title, description, event_type, start_date, end_date FROM tbl_event WHERE event_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode(["status" => "error", "message" => "Event not found"]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no event_id, otherwise update)
    // =======================
    case 'add': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Only admins can manage the school calendar"]);
            break;
        }

        $event_id = isset($_POST['event_id']) ? trim($_POST['event_id']) : '';
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $event_type = isset($_POST['event_type']) ? trim($_POST['event_type']) : 'Other';
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';

        if ($title === '' || $start_date === '') {
            echo json_encode(["status" => "error", "message" => "Title and start date are required"]);
            break;
        }

        if (!in_array($event_type, ['Holiday', 'Exam', 'Meeting', 'Deadline', 'Other'], true)) {
            echo json_encode(["status" => "error", "message" => "Invalid event type"]);
            break;
        }

        $endDateValue = ($end_date === '') ? null : $end_date;
        if ($endDateValue !== null && $endDateValue < $start_date) {
            echo json_encode(["status" => "error", "message" => "End date cannot be before the start date"]);
            break;
        }

        $descValue = ($description === '') ? null : $description;
        $adminId = $_SESSION['auth']['id'];
        $adminName = $_SESSION['auth']['name'];

        if (empty($event_id)) {
            $save = mysqli_prepare($conn, "
                INSERT INTO tbl_event (title, description, event_type, start_date, end_date, admin_id, admin_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($save, "sssssis", $title, $descValue, $event_type, $start_date, $endDateValue, $adminId, $adminName);
        } else {
            $save = mysqli_prepare($conn, "
                UPDATE tbl_event SET title = ?, description = ?, event_type = ?, start_date = ?, end_date = ?
                WHERE event_id = ?
            ");
            mysqli_stmt_bind_param($save, "sssssi", $title, $descValue, $event_type, $start_date, $endDateValue, $event_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode(["status" => "error", "message" => "Failed to save event"]);
            break;
        }
        mysqli_stmt_close($save);

        echo json_encode(["status" => "success", "message" => "Event saved successfully"]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Only admins can manage the school calendar"]);
            break;
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if ($event_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid event"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE tbl_event SET event_remarks = 0 WHERE event_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(["status" => "success", "message" => "Event removed successfully"]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
