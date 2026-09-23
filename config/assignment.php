<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (DataTable list — current active school year's assignments)
    // =======================
    case 'load': {
        $query = mysqli_query($conn, "
            SELECT
                c.class_id,
                c.schoolyear_name,
                c.level_name,
                c.section_name,
                c.subject_name,
                c.subject_code,
                c.teacher_name,
                c.teacher_id,
                sy.status AS schoolyear_status
            FROM tbl_class c
            LEFT JOIN tbl_schoolyear sy ON sy.schoolyear_id = c.schoolyear_id
            WHERE c.class_remarks = 1
            ORDER BY c.created_at DESC
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
        $id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

        $stmt = mysqli_prepare($conn, "
            SELECT class_id, schoolyear_id, level_id, section_id, subject_id, teacher_id
            FROM tbl_class WHERE class_id = ? LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode(["status" => "error", "message" => "Assignment not found"]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no class_id, otherwise update)
    // =======================
    case 'add': {
        $class_id = isset($_POST['class_id']) ? trim($_POST['class_id']) : '';
        $level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
        $section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
        $teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;

        if ($level_id <= 0 || $section_id <= 0 || $subject_id <= 0 || $teacher_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Level, section, subject, and teacher are all required"]);
            break;
        }

        // Active school year required
        $syRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT schoolyear_id, schoolyear_name FROM tbl_schoolyear WHERE status = 'active' LIMIT 1"));
        if (!$syRow) {
            echo json_encode(["status" => "error", "message" => "No active school year. Activate a school year first."]);
            break;
        }
        $schoolyear_id = $syRow['schoolyear_id'];
        $schoolyear_name = $syRow['schoolyear_name'];

        $levelRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT level_name FROM tbl_level WHERE level_id = " . (int) $level_id));
        $sectionRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT section_name FROM tbl_section WHERE section_id = " . (int) $section_id));
        $subjectRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT subject_name, subject_code FROM tbl_subject WHERE subject_id = " . (int) $subject_id));
        $teacherRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name FROM tbl_teacher WHERE teacher_id = " . (int) $teacher_id));

        if (!$levelRow || !$sectionRow || !$subjectRow || !$teacherRow) {
            echo json_encode(["status" => "error", "message" => "Selected level, section, subject, or teacher no longer exists"]);
            break;
        }

        $level_name = $levelRow['level_name'];
        $section_name = $sectionRow['section_name'];
        $subject_name = $subjectRow['subject_name'];
        $subject_code = $subjectRow['subject_code'];
        $teacher_name = $teacherRow['last_name'] . ', ' . $teacherRow['first_name'];

        // Uniqueness: one teacher-subject-section assignment per school year
        if (empty($class_id)) {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_class WHERE schoolyear_id = ? AND subject_id = ? AND section_id = ? AND class_remarks = 1 LIMIT 1");
            mysqli_stmt_bind_param($check, "iii", $schoolyear_id, $subject_id, $section_id);
        } else {
            $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_class WHERE schoolyear_id = ? AND subject_id = ? AND section_id = ? AND class_remarks = 1 AND class_id != ? LIMIT 1");
            mysqli_stmt_bind_param($check, "iiii", $schoolyear_id, $subject_id, $section_id, $class_id);
        }
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode(["status" => "error", "message" => "This subject is already assigned for that section this school year"]);
            break;
        }
        mysqli_stmt_close($check);

        if (empty($class_id)) {
            $save = mysqli_prepare($conn, "
                INSERT INTO tbl_class
                    (schoolyear_id, schoolyear_name, level_id, level_name, section_id, section_name, subject_id, subject_name, subject_code, teacher_id, teacher_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param(
                $save,
                "isisisissis",
                $schoolyear_id, $schoolyear_name, $level_id, $level_name, $section_id, $section_name, $subject_id, $subject_name, $subject_code, $teacher_id, $teacher_name
            );
        } else {
            $save = mysqli_prepare($conn, "
                UPDATE tbl_class SET
                    level_id = ?, level_name = ?, section_id = ?, section_name = ?,
                    subject_id = ?, subject_name = ?, subject_code = ?,
                    teacher_id = ?, teacher_name = ?
                WHERE class_id = ?
            ");
            mysqli_stmt_bind_param(
                $save,
                "isisissisi",
                $level_id, $level_name, $section_id, $section_name, $subject_id, $subject_name, $subject_code, $teacher_id, $teacher_name, $class_id
            );
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode(["status" => "error", "message" => "Failed to save assignment"]);
            break;
        }
        mysqli_stmt_close($save);

        echo json_encode(["status" => "success", "message" => "Assignment saved successfully"]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

        if ($class_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid assignment"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE tbl_class SET class_remarks = 0 WHERE class_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            echo json_encode(["status" => "success", "message" => "Assignment removed successfully"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Assignment not found or already removed"]);
        break;
    }

    // =======================
    // TEACHER_CLASSES (a given teacher's assignments — used by "My Classes")
    // =======================
    case 'teacher_classes': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'teacher') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }
        $teacher_id = $_SESSION['auth']['id'];

        $stmt = mysqli_prepare($conn, "
            SELECT c.class_id, c.schoolyear_name, c.level_name, c.section_name, c.subject_name, c.subject_code,
                   (SELECT COUNT(*) FROM tbl_enrollment e
                       WHERE e.section_id = (SELECT section_id FROM tbl_class WHERE class_id = c.class_id)
                       AND e.status = 'enrolled') AS student_count
            FROM tbl_class c
            WHERE c.teacher_id = ? AND c.class_remarks = 1
            ORDER BY c.level_name ASC, c.section_name ASC, c.subject_name ASC
        ");
        mysqli_stmt_bind_param($stmt, "i", $teacher_id);
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
    // ROSTER (students enrolled in a class's section, who also take that subject)
    // =======================
    case 'roster': {
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

        $classRow = mysqli_fetch_assoc(mysqli_prepared_get($conn, "SELECT * FROM tbl_class WHERE class_id = ?", "i", [$class_id]));
        if (!$classRow) {
            echo json_encode(["status" => "error", "message" => "Class not found"]);
            break;
        }

        // Authorization: teachers may only view their own class rosters
        if (isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'teacher' && (int) $classRow['teacher_id'] !== (int) $_SESSION['auth']['id']) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT s.student_id, s.lrn, s.first_name, s.last_name, e.enrollment_id
            FROM tbl_enrollment e
            JOIN tbl_student s ON s.student_id = e.student_id
            JOIN tbl_enrollment_subject es ON es.enrollment_id = e.enrollment_id
            WHERE e.section_id = ? AND e.schoolyear_id = ? AND e.status = 'enrolled' AND es.subject_id = ?
            ORDER BY s.last_name ASC, s.first_name ASC
        ");
        mysqli_stmt_bind_param($stmt, "iii", $classRow['section_id'], $classRow['schoolyear_id'], $classRow['subject_id']);
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

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}

// Small helper used only by the 'roster' action above
function mysqli_prepared_get($conn, $sql, $types, $params) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
