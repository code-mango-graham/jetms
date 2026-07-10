<?php
include '../config.php';

header('Content-Type: application/json');

$section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

if ($section_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid section id'
    ]);
    exit;
}

$delete = mysqli_prepare($conn, 'DELETE FROM tbl_section WHERE section_id = ?');
mysqli_stmt_bind_param($delete, 'i', $section_id);
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
    'message' => 'Section not found'
]);
?>
