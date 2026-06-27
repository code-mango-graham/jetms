<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_section_id = isset($_POST['schoolyear_section_id']) ? (int) $_POST['schoolyear_section_id'] : 0;

if ($schoolyear_section_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid section id'
    ]);
    exit;
}

$query = mysqli_prepare($conn, '
    SELECT schoolyear_section_id, schoolyear_id, level_id, section_name
    FROM tbl_schoolyear_section
    WHERE schoolyear_section_id = ?
    LIMIT 1
');
mysqli_stmt_bind_param($query, 'i', $schoolyear_section_id);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($query);

if ($data) {
    echo json_encode($data);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Section not found'
]);
