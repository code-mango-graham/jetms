<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

function classBelongsToTeacher($conn, $class_id) {
    if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'teacher') {
        return false;
    }
    $stmt = mysqli_prepare($conn, "SELECT teacher_id FROM tbl_class WHERE class_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row && (int) $row['teacher_id'] === (int) $_SESSION['auth']['id'];
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // COMPONENTS (quiz/activity/exam list for a class + quarter)
    // =======================
    case 'components': {
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $quarter = isset($_POST['quarter']) ? $_POST['quarter'] : 'Q1';

        if (!classBelongsToTeacher($conn, $class_id) && !(isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'admin')) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT component_id, component_type, title, max_score, date_given
            FROM tbl_grade_component
            WHERE class_id = ? AND quarter = ? AND component_remarks = 1
            ORDER BY date_given ASC, component_id ASC
        ");
        mysqli_stmt_bind_param($stmt, "is", $class_id, $quarter);
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
    // COMPONENT_ADD
    // =======================
    case 'component_add': {
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $quarter = isset($_POST['quarter']) ? $_POST['quarter'] : '';
        $component_type = isset($_POST['component_type']) ? $_POST['component_type'] : '';
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $max_score = isset($_POST['max_score']) ? (float) $_POST['max_score'] : 0;
        $date_given = isset($_POST['date_given']) && $_POST['date_given'] !== '' ? $_POST['date_given'] : null;

        if (!classBelongsToTeacher($conn, $class_id)) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true) || !in_array($component_type, ['quiz', 'activity', 'exam'], true) || $title === '' || $max_score <= 0) {
            echo json_encode(["status" => "error", "message" => "All fields are required and max score must be greater than 0"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO tbl_grade_component (class_id, quarter, component_type, title, max_score, date_given)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "isssds", $class_id, $quarter, $component_type, $title, $max_score, $date_given);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(["status" => "success", "message" => "Component added"]);
        break;
    }

    // =======================
    // COMPONENT_DELETE
    // =======================
    case 'component_delete': {
        $component_id = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;

        $classRow = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT c.class_id FROM tbl_grade_component gc
            JOIN tbl_class c ON c.class_id = gc.class_id
            WHERE gc.component_id = " . (int) $component_id
        ));

        if (!$classRow || !classBelongsToTeacher($conn, $classRow['class_id'])) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE tbl_grade_component SET component_remarks = 0 WHERE component_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $component_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(["status" => "success", "message" => "Component removed"]);
        break;
    }

    // =======================
    // SCORES (roster + each student's score for every active component in a quarter)
    // =======================
    case 'scores': {
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $quarter = isset($_POST['quarter']) ? $_POST['quarter'] : 'Q1';

        if (!classBelongsToTeacher($conn, $class_id) && !(isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'admin')) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $classStmt = mysqli_prepare($conn, "SELECT section_id, schoolyear_id, subject_id FROM tbl_class WHERE class_id = ? LIMIT 1");
        mysqli_stmt_bind_param($classStmt, "i", $class_id);
        mysqli_stmt_execute($classStmt);
        $classRow = mysqli_fetch_assoc(mysqli_stmt_get_result($classStmt));
        mysqli_stmt_close($classStmt);

        if (!$classRow) {
            echo json_encode(["status" => "error", "message" => "Class not found"]);
            break;
        }

        $rosterStmt = mysqli_prepare($conn, "
            SELECT s.student_id, s.lrn, s.first_name, s.last_name
            FROM tbl_enrollment e
            JOIN tbl_student s ON s.student_id = e.student_id
            JOIN tbl_enrollment_subject es ON es.enrollment_id = e.enrollment_id
            WHERE e.section_id = ? AND e.schoolyear_id = ? AND e.status = 'enrolled' AND es.subject_id = ?
            ORDER BY s.last_name ASC, s.first_name ASC
        ");
        mysqli_stmt_bind_param($rosterStmt, "iii", $classRow['section_id'], $classRow['schoolyear_id'], $classRow['subject_id']);
        mysqli_stmt_execute($rosterStmt);
        $rosterResult = mysqli_stmt_get_result($rosterStmt);
        $roster = [];
        while ($row = mysqli_fetch_assoc($rosterResult)) {
            $roster[] = $row;
        }
        mysqli_stmt_close($rosterStmt);

        $compStmt = mysqli_prepare($conn, "SELECT component_id, title, max_score, component_type FROM tbl_grade_component WHERE class_id = ? AND quarter = ? AND component_remarks = 1 ORDER BY date_given ASC, component_id ASC");
        mysqli_stmt_bind_param($compStmt, "is", $class_id, $quarter);
        mysqli_stmt_execute($compStmt);
        $compResult = mysqli_stmt_get_result($compStmt);
        $components = [];
        while ($row = mysqli_fetch_assoc($compResult)) {
            $components[] = $row;
        }
        mysqli_stmt_close($compStmt);

        $scoreMap = [];
        if (count($components) > 0) {
            $componentIds = array_map(function ($c) { return (int) $c['component_id']; }, $components);
            $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
            $types = str_repeat('i', count($componentIds));
            $scoreStmt = mysqli_prepare($conn, "SELECT component_id, student_id, score FROM tbl_grade WHERE component_id IN ($placeholders)");
            mysqli_stmt_bind_param($scoreStmt, $types, ...$componentIds);
            mysqli_stmt_execute($scoreStmt);
            $scoreResult = mysqli_stmt_get_result($scoreStmt);
            while ($row = mysqli_fetch_assoc($scoreResult)) {
                $scoreMap[$row['component_id'] . '_' . $row['student_id']] = $row['score'];
            }
            mysqli_stmt_close($scoreStmt);
        }

        $finalStmt = mysqli_prepare($conn, "SELECT student_id, final_grade FROM tbl_final_grade WHERE class_id = ? AND quarter = ?");
        mysqli_stmt_bind_param($finalStmt, "is", $class_id, $quarter);
        mysqli_stmt_execute($finalStmt);
        $finalResult = mysqli_stmt_get_result($finalStmt);
        $finalMap = [];
        while ($row = mysqli_fetch_assoc($finalResult)) {
            $finalMap[$row['student_id']] = $row['final_grade'];
        }
        mysqli_stmt_close($finalStmt);

        foreach ($roster as &$student) {
            $student['scores'] = [];
            foreach ($components as $comp) {
                $key = $comp['component_id'] . '_' . $student['student_id'];
                $student['scores'][$comp['component_id']] = isset($scoreMap[$key]) ? $scoreMap[$key] : null;
            }
            $student['final_grade'] = isset($finalMap[$student['student_id']]) ? $finalMap[$student['student_id']] : null;
        }
        unset($student);

        echo json_encode(["components" => $components, "roster" => $roster]);
        break;
    }

    // =======================
    // SCORE_SAVE (upsert one student's score for one component — editing an
    // already-recorded score requires a reason, logged to tbl_grade_edit_log)
    // =======================
    case 'score_save': {
        $component_id = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $score = ($_POST['score'] ?? '') === '' ? null : (float) $_POST['score'];
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        $classRow = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT c.class_id, gc.max_score FROM tbl_grade_component gc
            JOIN tbl_class c ON c.class_id = gc.class_id
            WHERE gc.component_id = " . (int) $component_id
        ));

        if (!$classRow || !classBelongsToTeacher($conn, $classRow['class_id'])) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        if ($score !== null && ($score < 0 || $score > (float) $classRow['max_score'])) {
            echo json_encode(["status" => "error", "message" => "Score must be between 0 and " . $classRow['max_score']]);
            break;
        }

        $existingStmt = mysqli_prepare($conn, "SELECT grade_id, score FROM tbl_grade WHERE component_id = ? AND student_id = ? LIMIT 1");
        mysqli_stmt_bind_param($existingStmt, "ii", $component_id, $student_id);
        mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        mysqli_stmt_close($existingStmt);

        $isEdit = $existing && $existing['score'] !== null && (float) $existing['score'] !== $score;

        if ($isEdit && $reason === '') {
            echo json_encode(["status" => "error", "message" => "A reason is required when changing an already-recorded score"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO tbl_grade (component_id, student_id, score)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE score = VALUES(score)
        ");
        mysqli_stmt_bind_param($stmt, "iid", $component_id, $student_id, $score);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($isEdit) {
            $gradeId = $existing['grade_id'];
            $teacherId = $_SESSION['auth']['id'];
            $teacherName = $_SESSION['auth']['name'];
            $log = mysqli_prepare($conn, "
                INSERT INTO tbl_grade_edit_log (grade_id, old_score, new_score, reason, teacher_id, teacher_name)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($log, "iddsis", $gradeId, $existing['score'], $score, $reason, $teacherId, $teacherName);
            mysqli_stmt_execute($log);
            mysqli_stmt_close($log);
        }

        echo json_encode(["status" => "success"]);
        break;
    }

    // =======================
    // FINAL_GRADE_SAVE (upsert one student's final grade for a quarter —
    // editing an already-released final grade requires a reason, logged to
    // tbl_final_grade_edit_log)
    // =======================
    case 'final_grade_save': {
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $quarter = isset($_POST['quarter']) ? $_POST['quarter'] : '';
        $final_grade = ($_POST['final_grade'] ?? '') === '' ? null : (float) $_POST['final_grade'];
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if (!classBelongsToTeacher($conn, $class_id)) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            echo json_encode(["status" => "error", "message" => "Invalid quarter"]);
            break;
        }

        if ($final_grade !== null && ($final_grade < 0 || $final_grade > 100)) {
            echo json_encode(["status" => "error", "message" => "Final grade must be between 0 and 100"]);
            break;
        }

        $existingStmt = mysqli_prepare($conn, "SELECT final_grade_id, final_grade FROM tbl_final_grade WHERE class_id = ? AND student_id = ? AND quarter = ? LIMIT 1");
        mysqli_stmt_bind_param($existingStmt, "iis", $class_id, $student_id, $quarter);
        mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        mysqli_stmt_close($existingStmt);

        $isEdit = $existing && $existing['final_grade'] !== null && (float) $existing['final_grade'] !== $final_grade;

        if ($isEdit && $reason === '') {
            echo json_encode(["status" => "error", "message" => "A reason is required when changing an already-released final grade"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO tbl_final_grade (class_id, student_id, quarter, final_grade)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE final_grade = VALUES(final_grade)
        ");
        mysqli_stmt_bind_param($stmt, "iisd", $class_id, $student_id, $quarter, $final_grade);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($isEdit) {
            $finalGradeId = $existing['final_grade_id'];
            $teacherId = $_SESSION['auth']['id'];
            $teacherName = $_SESSION['auth']['name'];
            $log = mysqli_prepare($conn, "
                INSERT INTO tbl_final_grade_edit_log (final_grade_id, old_final_grade, new_final_grade, reason, teacher_id, teacher_name)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($log, "iddsis", $finalGradeId, $existing['final_grade'], $final_grade, $reason, $teacherId, $teacherName);
            mysqli_stmt_execute($log);
            mysqli_stmt_close($log);
        }

        echo json_encode(["status" => "success"]);
        break;
    }

    // =======================
    // STUDENT_SUBJECTS (session-scoped — this student's current subjects + assigned teacher)
    // =======================
    case 'student_subjects': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'student') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }
        $student_id = $_SESSION['auth']['id'];

        $stmt = mysqli_prepare($conn, "
            SELECT es.subject_id, es.subject_name, es.subject_code,
                   e.section_name, e.schoolyear_name,
                   c.class_id, c.teacher_name
            FROM tbl_enrollment e
            JOIN tbl_enrollment_subject es ON es.enrollment_id = e.enrollment_id
            LEFT JOIN tbl_class c ON c.section_id = e.section_id AND c.schoolyear_id = e.schoolyear_id AND c.subject_id = es.subject_id AND c.class_remarks = 1
            WHERE e.student_id = ? AND e.status = 'enrolled'
            ORDER BY es.subject_name ASC
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
    // STUDENT_GRADES (session-scoped — this student's component scores + final grades, one subject at a time)
    // =======================
    case 'student_grades': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'student') {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }
        $student_id = $_SESSION['auth']['id'];
        $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

        // Confirm this class actually corresponds to one of the student's own enrolled subjects
        $ownStmt = mysqli_prepare($conn, "
            SELECT c.class_id FROM tbl_class c
            JOIN tbl_enrollment e ON e.section_id = c.section_id AND e.schoolyear_id = c.schoolyear_id
            JOIN tbl_enrollment_subject es ON es.enrollment_id = e.enrollment_id AND es.subject_id = c.subject_id
            WHERE c.class_id = ? AND e.student_id = ? AND e.status = 'enrolled'
            LIMIT 1
        ");
        mysqli_stmt_bind_param($ownStmt, "ii", $class_id, $student_id);
        mysqli_stmt_execute($ownStmt);
        $owns = mysqli_fetch_assoc(mysqli_stmt_get_result($ownStmt));
        mysqli_stmt_close($ownStmt);

        if (!$owns) {
            echo json_encode(["status" => "error", "message" => "Not authorized"]);
            break;
        }

        $compStmt = mysqli_prepare($conn, "
            SELECT gc.component_id, gc.quarter, gc.component_type, gc.title, gc.max_score, g.score
            FROM tbl_grade_component gc
            LEFT JOIN tbl_grade g ON g.component_id = gc.component_id AND g.student_id = ?
            WHERE gc.class_id = ? AND gc.component_remarks = 1
            ORDER BY FIELD(gc.quarter,'Q1','Q2','Q3','Q4'), gc.date_given ASC
        ");
        mysqli_stmt_bind_param($compStmt, "ii", $student_id, $class_id);
        mysqli_stmt_execute($compStmt);
        $compResult = mysqli_stmt_get_result($compStmt);
        $components = [];
        while ($row = mysqli_fetch_assoc($compResult)) {
            $components[] = $row;
        }
        mysqli_stmt_close($compStmt);

        $finalStmt = mysqli_prepare($conn, "SELECT quarter, final_grade FROM tbl_final_grade WHERE class_id = ? AND student_id = ?");
        mysqli_stmt_bind_param($finalStmt, "ii", $class_id, $student_id);
        mysqli_stmt_execute($finalStmt);
        $finalResult = mysqli_stmt_get_result($finalStmt);
        $finals = [];
        while ($row = mysqli_fetch_assoc($finalResult)) {
            $finals[] = $row;
        }
        mysqli_stmt_close($finalStmt);

        echo json_encode(["components" => $components, "final_grades" => $finals]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
