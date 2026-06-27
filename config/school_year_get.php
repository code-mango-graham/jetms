<?php
include '../config.php';

header('Content-Type: application/json');

$id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid school year id"
    ]);
    exit;
}

$query = mysqli_prepare($conn, "
    SELECT
        sy.schoolyear_id,
        sy.schoolyear_name,
        sy.curriculum_id,
        sy.year_start,
        sy.year_end,
        sy.status,
        c.curriculum_name
    FROM tbl_schoolyear sy
    LEFT JOIN tbl_curriculum c ON c.curriculum_id = sy.curriculum_id
    WHERE sy.schoolyear_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($query, "i", $id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

if ($data && (!isset($data['curriculum_name']) || $data['curriculum_name'] === null)) {
    $data['curriculum_name'] = 'Not linked';
}

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "School year not found"
    ]);
}
?>