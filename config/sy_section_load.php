<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;
$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

if ($schoolyear_id <= 0 || $level_id <= 0) {
    echo json_encode(['data' => []]);
    exit;
}

$query = mysqli_prepare($conn, '
    SELECT schoolyear_section_id, schoolyear_id, level_id, section_name
    FROM tbl_schoolyear_section
    WHERE schoolyear_id = ? AND level_id = ? AND section_remarks = 1
    ORDER BY section_name ASC
');
mysqli_stmt_bind_param($query, 'ii', $schoolyear_id, $level_id);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

mysqli_stmt_close($query);

echo json_encode(['data' => $data]);
