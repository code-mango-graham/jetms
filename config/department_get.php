<?php
include '../config.php';

$id = $_POST['department_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_department
WHERE department_id = '$id'
");

echo json_encode(mysqli_fetch_assoc($query));
?>