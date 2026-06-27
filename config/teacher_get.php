<?php
include '../config.php';

header('Content-Type: application/json');

$teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;

$query = mysqli_prepare($conn, "SELECT * FROM tbl_teacher WHERE teacher_id = ? LIMIT 1");
mysqli_stmt_bind_param($query, "i", $teacher_id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Teacher not found"
    ]);
}
?>