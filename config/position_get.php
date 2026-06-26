<?php
include '../config.php';

$id = $_POST['position_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_position
WHERE position_id = '$id'
");

$data = mysqli_fetch_assoc($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Position not found"
    ]);
}
?>
