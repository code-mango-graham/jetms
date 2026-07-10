<?php
include '../config.php';

header('Content-Type: application/json');

$id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid subject id"
    ]);
    exit;
}

$query = mysqli_prepare($conn, "
    SELECT
        subject_id,
        subject_name,
        subject_code,
        level_id
    FROM tbl_subject
    WHERE subject_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($query, "i", $id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Subject not found"
    ]);
}
?>
