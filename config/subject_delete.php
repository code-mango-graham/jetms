<?php
include '../config.php';

header('Content-Type: application/json');

$subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

if ($subject_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid subject id'
    ]);
    exit;
}

$delete = mysqli_prepare($conn, 'DELETE FROM tbl_subject WHERE subject_id = ?');
mysqli_stmt_bind_param($delete, 'i', $subject_id);
mysqli_stmt_execute($delete);

if (mysqli_stmt_affected_rows($delete) > 0) {
    mysqli_stmt_close($delete);
    echo json_encode([
        'status' => 'success',
        'message' => 'Subject deleted successfully'
    ]);
    exit;
}

mysqli_stmt_close($delete);

echo json_encode([
    'status' => 'error',
    'message' => 'Subject not found'
]);
?>
