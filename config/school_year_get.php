<?php
include '../config.php';

$id = $_POST['schoolyear_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_schoolyear
WHERE schoolyear_id = '$id'
");

echo json_encode(mysqli_fetch_assoc($query));
?>