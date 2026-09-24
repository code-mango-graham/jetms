<?php
// Shared enrollment rules used by enrollment.php, payment.php and subject.php.

// Tuition and what has already been paid, with the enrollment row locked so two
// people recording payments at the same moment cannot both squeeze under the limit.
// Call inside a transaction. $excludePaymentId leaves one payment out (when editing it).
// Returns null when the enrollment does not exist.
function enrollment_money_locked($conn, $enrollment_id, $excludePaymentId = 0) {
    $stmt = mysqli_prepare($conn, "SELECT tuition_fee FROM tbl_enrollment WHERE enrollment_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
    mysqli_stmt_execute($stmt);
    $enr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$enr) {
        return null;
    }
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount), 0) AS paid FROM tbl_payment WHERE enrollment_id = ? AND payment_id <> ?");
    mysqli_stmt_bind_param($stmt, "ii", $enrollment_id, $excludePaymentId);
    mysqli_stmt_execute($stmt);
    $paid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ['tuition' => (float) $enr['tuition_fee'], 'paid_others' => (float) $paid['paid']];
}

function enrollment_overpay_message($tuition, $paidOthers) {
    $room = max(0, round($tuition - $paidOthers, 2));
    return "This would be more than the tuition. Tuition is " . number_format($tuition, 2)
        . ", already paid " . number_format($paidOthers, 2)
        . ", so at most " . number_format($room, 2) . " can be recorded.";
}

// Has this student recorded grades in this school year + section (optionally for one subject)?
// Grades belong to the class (subject + section), not to the enrollment row, so moving the
// student or removing the subject would strand them.
function enrollment_has_grades($conn, $student_id, $schoolyear_id, $section_id, $subject_id = 0) {
    $subjSql1 = $subject_id > 0 ? ' AND c.subject_id = ?' : '';
    $subjSql2 = $subject_id > 0 ? ' AND c2.subject_id = ?' : '';
    $sql = "
        SELECT
          (SELECT COUNT(*) FROM tbl_grade g
             JOIN tbl_grade_component gc ON gc.component_id = g.component_id
             JOIN tbl_class c ON c.class_id = gc.class_id
            WHERE g.student_id = ? AND g.score IS NOT NULL AND c.schoolyear_id = ? AND c.section_id = ?$subjSql1)
        + (SELECT COUNT(*) FROM tbl_final_grade f
             JOIN tbl_class c2 ON c2.class_id = f.class_id
            WHERE f.student_id = ? AND f.final_grade IS NOT NULL AND c2.schoolyear_id = ? AND c2.section_id = ?$subjSql2)
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if ($subject_id > 0) {
        mysqli_stmt_bind_param($stmt, "iiiiiiii", $student_id, $schoolyear_id, $section_id, $subject_id, $student_id, $schoolyear_id, $section_id, $subject_id);
    } else {
        mysqli_stmt_bind_param($stmt, "iiiiii", $student_id, $schoolyear_id, $section_id, $student_id, $schoolyear_id, $section_id);
    }
    mysqli_stmt_execute($stmt);
    $count = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    return $count > 0;
}

// Levels with a fixed curriculum (allows_subject_selection = 0) give every student ALL of the
// level's subjects. When a subject is added to the level later, this attaches it to the students
// already enrolled (current school year, not completed). Safe to run repeatedly.
// $enrollment_id limits it to one enrollment. Returns how many subject rows were added.
function enrollment_sync_fixed_subjects($conn, $enrollment_id = 0) {
    $only = $enrollment_id > 0 ? ' AND e.enrollment_id = ' . (int) $enrollment_id : '';
    mysqli_query($conn, "
        INSERT INTO tbl_enrollment_subject (enrollment_id, subject_id, subject_name, subject_code)
        SELECT e.enrollment_id, s.subject_id, s.subject_name, s.subject_code
        FROM tbl_enrollment e
        JOIN tbl_schoolyear y ON y.schoolyear_id = e.schoolyear_id AND y.status = 'active'
        JOIN tbl_level l ON l.level_id = e.level_id AND l.allows_subject_selection = 0
        JOIN tbl_subject s ON s.level_id = e.level_id AND s.subject_remarks = 1
        WHERE e.status <> 'completed'$only
          AND NOT EXISTS (
              SELECT 1 FROM tbl_enrollment_subject es
              WHERE es.enrollment_id = e.enrollment_id AND es.subject_id = s.subject_id
          )
    ");
    return max(0, mysqli_affected_rows($conn));
}
