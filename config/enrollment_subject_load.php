<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id = isset($_POST['schoolyear_id']) ? (int)$_POST['schoolyear_id'] : 0;
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;

if (!$schoolyear_id || !$section_id) {
    echo json_encode([
        "data" => []
    ]);
    exit;
}

// $section_id is actually the schoolyear_section_id from the form
$schoolyear_section_id = $section_id;

// Get subjects assigned to this school year section from tbl_schoolyear_section_subject
$query = mysqli_prepare($conn, "
    SELECT DISTINCT 
        s.subject_id, 
        s.subject_code, 
        s.subject_name,
        sss.teacher_id,
        sss.schedule_info,
        sss.room_no
    FROM tbl_subject s
    INNER JOIN tbl_schoolyear_section_subject sss ON s.subject_id = sss.subject_id
    WHERE sss.schoolyear_section_id = ?
    ORDER BY s.subject_code ASC
");

mysqli_stmt_bind_param($query, "i", $schoolyear_section_id);
mysqli_stmt_execute($query);
$resultSet = mysqli_stmt_get_result($query);

$data = [];
while ($row = mysqli_fetch_assoc($resultSet)) {
    $data[] = $row;
}

mysqli_stmt_close($query);

echo json_encode([
    "data" => $data
]);
?>
