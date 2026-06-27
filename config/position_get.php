<?php
include '../config.php';

header('Content-Type: application/json');

$id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;

$query = mysqli_prepare($conn, "SELECT position_id, position_title, description FROM tbl_position WHERE position_id = ? LIMIT 1");
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
        "message" => "Position not found"
    ]);
}
?>
