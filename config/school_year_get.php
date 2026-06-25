<?php
include '../config.php';

$id = $_POST['schoolyear_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_schoolyear
WHERE schoolyear_id = '$id'
");

$data = mysqli_fetch_assoc($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "School year not found"
    ]);
}
?>