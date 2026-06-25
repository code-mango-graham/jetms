<?php
include '../config.php';

$id = $_POST['curriculum_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_curriculum
WHERE curriculum_id = '$id'
");

$data = mysqli_fetch_assoc($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Curriculum not found"
    ]);
}
?>