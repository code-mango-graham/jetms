<?php
include '../config.php';

header('Content-Type: application/json');

$teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;

if ($teacher_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid teacher ID"
    ]);
    exit;
}

$update = mysqli_prepare($conn, "UPDATE tbl_teacher SET teacher_remarks = 0 WHERE teacher_id = ?");
mysqli_stmt_bind_param($update, "i", $teacher_id);
mysqli_stmt_execute($update);

if (mysqli_stmt_affected_rows($update) > 0) {
    mysqli_stmt_close($update);
    echo json_encode([
        "status" => "success",
        "message" => "Teacher archived successfully"
    ]);
    exit;
}

mysqli_stmt_close($update);

echo json_encode([
    "status" => "error",
    "message" => "Teacher not found or already archived"
]);
?>