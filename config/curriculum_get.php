<?php
include '../config.php';

$id = $_POST['curriculum_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_curriculum
WHERE curriculum_id = '$id'
");

echo json_encode(mysqli_fetch_assoc($query));
?>