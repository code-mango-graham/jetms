<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_section_id = isset($_POST['schoolyear_section_id']) ? (int) $_POST['schoolyear_section_id'] : 0;
$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

if ($schoolyear_section_id <= 0 || $level_id <= 0) {
    echo json_encode(['data' => []]);
    exit;
}

$query = mysqli_prepare($conn, '
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        map.teacher_id,
        map.schedule_info,
        map.room_no
    FROM tbl_subject s
    LEFT JOIN tbl_schoolyear_section_subject map
        ON map.subject_id = s.subject_id
        AND map.schoolyear_section_id = ?
    WHERE s.level_id = ?
    ORDER BY s.subject_name ASC
');
mysqli_stmt_bind_param($query, 'ii', $schoolyear_section_id, $level_id);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

mysqli_stmt_close($query);

echo json_encode(['data' => $data]);
