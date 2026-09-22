<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (payments for a given enrollment)
    // =======================
    case 'load': {
        $enrollment_id = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT payment_id, payment_date, amount, payment_mode, reference_no, admin_name
            FROM tbl_payment
            WHERE enrollment_id = ?
            ORDER BY payment_date DESC, payment_id DESC
        ");
        mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
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
    // STUDENT_HISTORY (all payments for a student, across every school year)
    // =======================
    case 'student_history': {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT p.payment_id, p.payment_date, p.amount, p.payment_mode, p.reference_no, p.admin_name, e.schoolyear_name
            FROM tbl_payment p
            INNER JOIN tbl_enrollment e ON e.enrollment_id = p.enrollment_id
            WHERE e.student_id = ?
            ORDER BY p.payment_date DESC, p.payment_id DESC
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
    // ADD (record a new payment)
    // =======================
    case 'add': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "You must be logged in as admin to record a payment"]);
            break;
        }

        $enrollment_id = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;
        $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : '';
        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
        $payment_mode = isset($_POST['payment_mode']) ? trim($_POST['payment_mode']) : '';
        $reference_no = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';

        if ($enrollment_id <= 0 || $payment_date === '' || $amount <= 0) {
            echo json_encode(["status" => "error", "message" => "Date and a positive amount are required"]);
            break;
        }

        if (!in_array($payment_mode, ['Cash', 'GCash', 'Bank Transfer'], true)) {
            echo json_encode(["status" => "error", "message" => "Please select a valid payment mode"]);
            break;
        }

        $check = mysqli_prepare($conn, "SELECT enrollment_id FROM tbl_enrollment WHERE enrollment_id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "i", $enrollment_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) === 0) {
            mysqli_stmt_close($check);
            echo json_encode(["status" => "error", "message" => "Enrollment not found"]);
            break;
        }
        mysqli_stmt_close($check);

        $adminId = $_SESSION['auth']['id'];
        $adminName = $_SESSION['auth']['name'];
        $refValue = ($reference_no === '') ? null : $reference_no;

        $save = mysqli_prepare($conn, "
            INSERT INTO tbl_payment (enrollment_id, payment_date, amount, payment_mode, reference_no, admin_id, admin_name)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($save, "isdssis", $enrollment_id, $payment_date, $amount, $payment_mode, $refValue, $adminId, $adminName);

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode(["status" => "error", "message" => "Failed to record payment"]);
            break;
        }

        mysqli_stmt_close($save);

        echo json_encode(["status" => "success", "message" => "Payment recorded successfully"]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
