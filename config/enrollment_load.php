<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id = isset($_POST['schoolyear_id']) ? (int)$_POST['schoolyear_id'] : 0;

$query = "
    SELECT 
        e.enrollment_id,
        s.lrn,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        l.level_name,
        sys.section_name,
        sy.schoolyear_name,
        COUNT(es.subject_id) as subject_count
    FROM tbl_enrollments e
    JOIN tbl_student s ON e.student_id = s.student_id
    JOIN tbl_schoolyear_section sys ON e.schoolyear_section_id = sys.schoolyear_section_id
    JOIN tbl_schoolyear sy ON sys.schoolyear_id = sy.schoolyear_id
    JOIN tbl_level l ON sys.level_id = l.level_id
    LEFT JOIN tbl_enrollment_subjects es ON e.enrollment_id = es.enrollment_id
    WHERE sy.schoolyear_id = ?
    GROUP BY e.enrollment_id
    ORDER BY s.last_name, s.first_name
";

$result = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($result, "i", $schoolyear_id);
mysqli_stmt_execute($result);
$resultSet = mysqli_stmt_get_result($result);

$data = [];
while ($row = mysqli_fetch_assoc($resultSet)) {
    $data[] = $row;
}

mysqli_stmt_close($result);

echo json_encode([
    "data" => $data
]);
?>
