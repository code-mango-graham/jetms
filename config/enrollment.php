<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

function getActiveSchoolYear($conn) {
    $result = mysqli_query($conn, "SELECT schoolyear_id, schoolyear_name FROM tbl_schoolyear WHERE status = 'active' LIMIT 1");
    return $result ? mysqli_fetch_assoc($result) : null;
}

function fetchEnrollmentDetail($conn, $enrollment_id) {
    $stmt = mysqli_prepare($conn, "
        SELECT e.*, s.lrn, s.last_name, s.first_name,
            COALESCE((SELECT SUM(p.amount) FROM tbl_payment p WHERE p.enrollment_id = e.enrollment_id), 0) AS total_paid
        FROM tbl_enrollment e
        INNER JOIN tbl_student s ON s.student_id = e.student_id
        WHERE e.enrollment_id = ? LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $enrollment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$enrollment) {
        return null;
    }

    $enrollment['balance'] = $enrollment['tuition_fee'] - $enrollment['total_paid'];

    $subStmt = mysqli_prepare($conn, "SELECT subject_name, subject_code FROM tbl_enrollment_subject WHERE enrollment_id = ? ORDER BY subject_name");
    mysqli_stmt_bind_param($subStmt, "i", $enrollment_id);
    mysqli_stmt_execute($subStmt);
    $subResult = mysqli_stmt_get_result($subStmt);
    $subjects = [];
    while ($row = mysqli_fetch_assoc($subResult)) {
        $subjects[] = $row;
    }
    mysqli_stmt_close($subStmt);
    $enrollment['subjects'] = $subjects;

    return $enrollment;
}

switch ($action) {

    // =======================
    // LOAD (enrollments for the active school year)
    // =======================
    case 'load': {
        $activeYear = getActiveSchoolYear($conn);

        if (!$activeYear) {
            echo json_encode(["data" => []]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT
                e.enrollment_id, e.student_id, e.level_name, e.section_name,
                e.tuition_fee, e.status, e.remarks,
                s.lrn, s.last_name, s.first_name,
                COALESCE((SELECT SUM(p.amount) FROM tbl_payment p WHERE p.enrollment_id = e.enrollment_id), 0) AS total_paid
            FROM tbl_enrollment e
            INNER JOIN tbl_student s ON s.student_id = e.student_id
            WHERE e.schoolyear_id = ?
            ORDER BY s.last_name, s.first_name
        ");
        mysqli_stmt_bind_param($stmt, "i", $activeYear['schoolyear_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['balance'] = $row['tuition_fee'] - $row['total_paid'];
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(["data" => $data]);
        break;
    }

    // =======================
    // GET (single enrollment detail, incl. subjects)
    // =======================
    case 'get': {
        $enrollment_id = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;
        $enrollment = fetchEnrollmentDetail($conn, $enrollment_id);

        if (!$enrollment) {
            echo json_encode(["status" => "error", "message" => "Enrollment not found"]);
            break;
        }

        echo json_encode(["status" => "success", "data" => $enrollment]);
        break;
    }

    // =======================
    // STUDENT_CURRENT (this student's enrollment for the active school year, if any)
    // =======================
    case 'student_current': {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $activeYear = getActiveSchoolYear($conn);

        if (!$activeYear || $student_id <= 0) {
            echo json_encode(["status" => "success", "data" => null]);
            break;
        }

        $stmt = mysqli_prepare($conn, "SELECT enrollment_id FROM tbl_enrollment WHERE student_id = ? AND schoolyear_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $activeYear['schoolyear_id']);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            echo json_encode(["status" => "success", "data" => null]);
            break;
        }

        $enrollment = fetchEnrollmentDetail($conn, (int) $row['enrollment_id']);
        echo json_encode(["status" => "success", "data" => $enrollment]);
        break;
    }

    // =======================
    // HISTORY (all of a student's enrollments, chronological)
    // =======================
    case 'history': {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT enrollment_id, schoolyear_name, level_name, section_name, tuition_fee, status, remarks, status_date
            FROM tbl_enrollment
            WHERE student_id = ?
            ORDER BY schoolyear_id ASC
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
    // ADD (new enrollment + subjects + optional downpayment)
    // =======================
    case 'add': {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
        $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $tuition_fee = isset($_POST['tuition_fee']) ? (float) $_POST['tuition_fee'] : 0;
        $subject_ids = isset($_POST['subject_ids']) ? $_POST['subject_ids'] : [];

        $downpayment_amount = isset($_POST['downpayment_amount']) ? trim($_POST['downpayment_amount']) : '';
        $payment_mode = isset($_POST['payment_mode']) ? trim($_POST['payment_mode']) : '';
        $reference_no = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';

        if ($student_id <= 0 || $level_id <= 0 || $section_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Student, level, and section are required"]);
            break;
        }

        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "You must be logged in as admin to enroll a student"]);
            break;
        }

        $activeYear = getActiveSchoolYear($conn);
        if (!$activeYear) {
            echo json_encode(["status" => "error", "message" => "No active school year. Please activate one in Settings first."]);
            break;
        }

        // Duplicate enrollment check
        $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_enrollment WHERE student_id = ? AND schoolyear_id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "ii", $student_id, $activeYear['schoolyear_id']);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode(["status" => "error", "message" => "This student is already enrolled for " . $activeYear['schoolyear_name']]);
            break;
        }
        mysqli_stmt_close($check);

        // Snapshot level/section names
        $levelStmt = mysqli_prepare($conn, "SELECT level_name, allows_subject_selection FROM tbl_level WHERE level_id = ? LIMIT 1");
        mysqli_stmt_bind_param($levelStmt, "i", $level_id);
        mysqli_stmt_execute($levelStmt);
        $level = mysqli_fetch_assoc(mysqli_stmt_get_result($levelStmt));
        mysqli_stmt_close($levelStmt);

        $sectionStmt = mysqli_prepare($conn, "SELECT section_name FROM tbl_section WHERE section_id = ? LIMIT 1");
        mysqli_stmt_bind_param($sectionStmt, "i", $section_id);
        mysqli_stmt_execute($sectionStmt);
        $section = mysqli_fetch_assoc(mysqli_stmt_get_result($sectionStmt));
        mysqli_stmt_close($sectionStmt);

        if (!$level || !$section) {
            echo json_encode(["status" => "error", "message" => "Invalid level or section"]);
            break;
        }

        mysqli_begin_transaction($conn);

        try {
            $today = date('Y-m-d');
            $save = mysqli_prepare($conn, "
                INSERT INTO tbl_enrollment (student_id, schoolyear_id, schoolyear_name, level_id, level_name, section_id, section_name, tuition_fee, enrollment_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($save, "iisisisds",
                $student_id, $activeYear['schoolyear_id'], $activeYear['schoolyear_name'],
                $level_id, $level['level_name'], $section_id, $section['section_name'],
                $tuition_fee, $today
            );
            if (!mysqli_stmt_execute($save)) {
                throw new Exception('Failed to save enrollment');
            }
            $enrollment_id = mysqli_insert_id($conn);
            mysqli_stmt_close($save);

            // Determine which subjects to attach
            if ((int)$level['allows_subject_selection'] === 1) {
                $subjectIdList = is_array($subject_ids) ? array_map('intval', $subject_ids) : [];
            } else {
                $subjectIdList = [];
                $allSubj = mysqli_prepare($conn, "SELECT subject_id FROM tbl_subject WHERE level_id = ? AND subject_remarks = 1");
                mysqli_stmt_bind_param($allSubj, "i", $level_id);
                mysqli_stmt_execute($allSubj);
                $allSubjResult = mysqli_stmt_get_result($allSubj);
                while ($row = mysqli_fetch_assoc($allSubjResult)) {
                    $subjectIdList[] = (int) $row['subject_id'];
                }
                mysqli_stmt_close($allSubj);
            }

            if (!empty($subjectIdList)) {
                $subjStmt = mysqli_prepare($conn, "SELECT subject_id, subject_name, subject_code FROM tbl_subject WHERE subject_id = ? LIMIT 1");
                $insStmt = mysqli_prepare($conn, "INSERT INTO tbl_enrollment_subject (enrollment_id, subject_id, subject_name, subject_code) VALUES (?, ?, ?, ?)");

                foreach ($subjectIdList as $sid) {
                    mysqli_stmt_bind_param($subjStmt, "i", $sid);
                    mysqli_stmt_execute($subjStmt);
                    $subjRow = mysqli_fetch_assoc(mysqli_stmt_get_result($subjStmt));
                    if (!$subjRow) {
                        continue;
                    }
                    mysqli_stmt_bind_param($insStmt, "iiss", $enrollment_id, $sid, $subjRow['subject_name'], $subjRow['subject_code']);
                    mysqli_stmt_execute($insStmt);
                }
                mysqli_stmt_close($subjStmt);
                mysqli_stmt_close($insStmt);
            }

            // Optional downpayment
            if ($downpayment_amount !== '' && (float)$downpayment_amount > 0) {
                if (!in_array($payment_mode, ['Cash', 'GCash', 'Bank Transfer'], true)) {
                    throw new Exception('Please select a valid payment mode for the downpayment');
                }

                $adminId = $_SESSION['auth']['id'];
                $adminName = $_SESSION['auth']['name'];
                $amount = (float) $downpayment_amount;
                $refValue = ($reference_no === '') ? null : $reference_no;

                $payStmt = mysqli_prepare($conn, "
                    INSERT INTO tbl_payment (enrollment_id, payment_date, amount, payment_mode, reference_no, admin_id, admin_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($payStmt, "isdssis", $enrollment_id, $today, $amount, $payment_mode, $refValue, $adminId, $adminName);
                if (!mysqli_stmt_execute($payStmt)) {
                    throw new Exception('Failed to save downpayment');
                }
                mysqli_stmt_close($payStmt);
            }

            mysqli_commit($conn);

            echo json_encode([
                "status" => "success",
                "message" => "Student enrolled successfully",
                "enrollment_id" => $enrollment_id
            ]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;
    }

    // =======================
    // CANCEL (hard delete — only if no payments recorded)
    // =======================
    case 'cancel': {
        $enrollment_id = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;

        if ($enrollment_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid enrollment"]);
            break;
        }

        $check = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM tbl_payment WHERE enrollment_id = ?");
        mysqli_stmt_bind_param($check, "i", $enrollment_id);
        mysqli_stmt_execute($check);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ((int) $row['cnt'] > 0) {
            echo json_encode(["status" => "error", "message" => "Cannot cancel — this enrollment already has payments recorded. Use Drop/Transfer instead."]);
            break;
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM tbl_enrollment WHERE enrollment_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            echo json_encode(["status" => "success", "message" => "Enrollment cancelled"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Enrollment not found"]);
        break;
    }

    // =======================
    // DROP / TRANSFER (status change, preserves everything)
    // =======================
    case 'drop_transfer': {
        $enrollment_id = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        if ($enrollment_id <= 0 || !in_array($status, ['dropped', 'transferred'], true)) {
            echo json_encode(["status" => "error", "message" => "Invalid request"]);
            break;
        }

        $today = date('Y-m-d');
        $remarksValue = ($remarks === '') ? null : $remarks;

        $stmt = mysqli_prepare($conn, "UPDATE tbl_enrollment SET status = ?, remarks = ?, status_date = ? WHERE enrollment_id = ? AND status = 'enrolled'");
        mysqli_stmt_bind_param($stmt, "sssi", $status, $remarksValue, $today, $enrollment_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            echo json_encode(["status" => "success", "message" => ucfirst($status) . " recorded"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Enrollment not found or not currently enrolled"]);
        break;
    }

    // =======================
    // REACTIVATE (undo drop/transfer)
    // =======================
    case 'reactivate': {
        $enrollment_id = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;

        if ($enrollment_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid enrollment"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE tbl_enrollment SET status = 'enrolled', remarks = NULL, status_date = NULL WHERE enrollment_id = ? AND status IN ('dropped', 'transferred')");
        mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            echo json_encode(["status" => "success", "message" => "Enrollment reactivated"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Enrollment not found or already active"]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
