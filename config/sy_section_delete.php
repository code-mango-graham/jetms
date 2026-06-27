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

$delete = mysqli_prepare($conn, '
    UPDATE tbl_schoolyear_section
    SET section_remarks = 0
    WHERE schoolyear_section_id = ?
');
mysqli_stmt_bind_param($delete, 'i', $schoolyear_section_id);
mysqli_stmt_execute($delete);

if (mysqli_stmt_affected_rows($delete) > 0) {
    mysqli_stmt_close($delete);
    echo json_encode([
        'status' => 'success',
        'message' => 'Section deleted successfully'
    ]);
    exit;
}

mysqli_stmt_close($delete);

echo json_encode([
    'status' => 'error',
    'message' => 'Section not found or already deleted'
]);
