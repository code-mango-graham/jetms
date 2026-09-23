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
    // MY_HISTORY (session-scoped — the logged-in student's own payment history)
    // =======================
    case 'my_history': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'student') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }
        $student_id = $_SESSION['auth']['id'];

        $stmt = mysqli_prepare($conn, "
            SELECT p.payment_id, p.payment_date, p.amount, p.payment_mode, p.reference_no, e.schoolyear_name
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
    // LEDGER (admin — school-wide payment list, optionally filtered by
    // school year and/or a payment_date range)
    // =======================
    case 'ledger': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $schoolyear_id = isset($_POST['schoolyear_id']) && $_POST['schoolyear_id'] !== '' ? (int) $_POST['schoolyear_id'] : 0;
        $date_from = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? trim($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? trim($_POST['date_to']) : null;

        $where = [];
        $types = '';
        $params = [];

        if ($schoolyear_id > 0) {
            $where[] = "e.schoolyear_id = ?";
            $types .= 'i';
            $params[] = $schoolyear_id;
        }
        if ($date_from !== null) {
            $where[] = "p.payment_date >= ?";
            $types .= 's';
            $params[] = $date_from;
        }
        if ($date_to !== null) {
            $where[] = "p.payment_date <= ?";
            $types .= 's';
            $params[] = $date_to;
        }

        $sql = "
            SELECT p.payment_id, p.payment_date, p.amount, p.payment_mode, p.reference_no, p.admin_name,
                   e.schoolyear_id, e.schoolyear_name, e.level_name, e.section_name,
                   s.student_id, s.lrn, s.first_name, s.last_name
            FROM tbl_payment p
            INNER JOIN tbl_enrollment e ON e.enrollment_id = p.enrollment_id
            INNER JOIN tbl_student s ON s.student_id = e.student_id
        ";
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

        $stmt = mysqli_prepare($conn, $sql);
        if (count($params) > 0) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        $total = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
            $total += (float) $row['amount'];
        }
        mysqli_stmt_close($stmt);

        echo json_encode(["data" => $data, "total" => $total]);
        break;
    }

    // =======================
    // DAILY_TOTALS (admin — payments summed per date, same filters as the ledger)
    // =======================
    case 'daily_totals': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $schoolyear_id = isset($_POST['schoolyear_id']) && $_POST['schoolyear_id'] !== '' ? (int) $_POST['schoolyear_id'] : 0;
        $date_from = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? trim($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? trim($_POST['date_to']) : null;

        $where = [];
        $types = '';
        $params = [];

        if ($schoolyear_id > 0) {
            $where[] = "e.schoolyear_id = ?";
            $types .= 'i';
            $params[] = $schoolyear_id;
        }
        if ($date_from !== null) {
            $where[] = "p.payment_date >= ?";
            $types .= 's';
            $params[] = $date_from;
        }
        if ($date_to !== null) {
            $where[] = "p.payment_date <= ?";
            $types .= 's';
            $params[] = $date_to;
        }

        $sql = "
            SELECT p.payment_date, COUNT(*) AS payment_count, SUM(p.amount) AS total_amount
            FROM tbl_payment p
            INNER JOIN tbl_enrollment e ON e.enrollment_id = p.enrollment_id
        ";
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " GROUP BY p.payment_date ORDER BY p.payment_date DESC";

        $stmt = mysqli_prepare($conn, $sql);
        if (count($params) > 0) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
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

    // =======================
    // GET (single payment, for the edit form)
    // =======================
    case 'get': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $payment_id = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;

        $stmt = mysqli_prepare($conn, "SELECT payment_id, enrollment_id, payment_date, amount, payment_mode, reference_no FROM tbl_payment WHERE payment_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $payment_id);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode(["status" => "error", "message" => "Payment not found"]);
        }
        break;
    }

    // =======================
    // EDIT (update an existing payment — requires a reason, logged to
    // tbl_payment_edit_log with the before/after values)
    // =======================
    case 'edit': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "You must be logged in as admin to edit a payment"]);
            break;
        }

        $payment_id = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;
        $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : '';
        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
        $payment_mode = isset($_POST['payment_mode']) ? trim($_POST['payment_mode']) : '';
        $reference_no = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if ($payment_id <= 0 || $payment_date === '' || $amount <= 0) {
            echo json_encode(["status" => "error", "message" => "Date and a positive amount are required"]);
            break;
        }

        if (!in_array($payment_mode, ['Cash', 'GCash', 'Bank Transfer'], true)) {
            echo json_encode(["status" => "error", "message" => "Please select a valid payment mode"]);
            break;
        }

        if ($reason === '') {
            echo json_encode(["status" => "error", "message" => "A reason for this edit is required"]);
            break;
        }

        $oldStmt = mysqli_prepare($conn, "SELECT payment_date, amount, payment_mode, reference_no FROM tbl_payment WHERE payment_id = ? LIMIT 1");
        mysqli_stmt_bind_param($oldStmt, "i", $payment_id);
        mysqli_stmt_execute($oldStmt);
        $old = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));
        mysqli_stmt_close($oldStmt);

        if (!$old) {
            echo json_encode(["status" => "error", "message" => "Payment not found"]);
            break;
        }

        $refValue = ($reference_no === '') ? null : $reference_no;
        $adminId = $_SESSION['auth']['id'];
        $adminName = $_SESSION['auth']['name'];

        $update = mysqli_prepare($conn, "
            UPDATE tbl_payment SET payment_date = ?, amount = ?, payment_mode = ?, reference_no = ?
            WHERE payment_id = ?
        ");
        mysqli_stmt_bind_param($update, "sdssi", $payment_date, $amount, $payment_mode, $refValue, $payment_id);

        if (!mysqli_stmt_execute($update)) {
            mysqli_stmt_close($update);
            echo json_encode(["status" => "error", "message" => "Failed to update payment"]);
            break;
        }
        mysqli_stmt_close($update);

        $log = mysqli_prepare($conn, "
            INSERT INTO tbl_payment_edit_log
                (payment_id, old_payment_date, old_amount, old_payment_mode, old_reference_no,
                 new_payment_date, new_amount, new_payment_mode, new_reference_no, reason, admin_id, admin_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param(
            $log,
            "isdsssdsssis",
            $payment_id, $old['payment_date'], $old['amount'], $old['payment_mode'], $old['reference_no'],
            $payment_date, $amount, $payment_mode, $refValue, $reason, $adminId, $adminName
        );
        mysqli_stmt_execute($log);
        mysqli_stmt_close($log);

        echo json_encode(["status" => "success", "message" => "Payment updated successfully"]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
