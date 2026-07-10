<?php
include '../config.php';

header('Content-Type: application/json');

$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

if ($level_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid level id'
    ]);
    exit;
}

$delete = mysqli_prepare($conn, 'DELETE FROM tbl_level WHERE level_id = ?');
mysqli_stmt_bind_param($delete, 'i', $level_id);
mysqli_stmt_execute($delete);

if (mysqli_stmt_affected_rows($delete) > 0) {
    mysqli_stmt_close($delete);
    echo json_encode([
        'status' => 'success',
        'message' => 'Level deleted successfully'
    ]);
    exit;
}

mysqli_stmt_close($delete);

echo json_encode([
    'status' => 'error',
    'message' => 'Level not found'
]);
?>
