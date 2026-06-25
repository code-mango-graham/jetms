<?php
include '../config.php';

$id = $_POST['department_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_department
WHERE department_id = '$id'
");

$data = mysqli_fetch_assoc($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Department not found"
    ]);
}
?>