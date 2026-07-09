<?php
include '../config.php';

header('Content-Type: application/json');

$id = $_POST['office_id'] ?? '';

if (empty($id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Office ID is required"
    ]);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT office_id, office_name, office_description FROM tbl_office WHERE office_id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Office not found"
    ]);
}
?>