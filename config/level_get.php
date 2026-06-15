<?php
include '../config.php';

$id = $_POST['level_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_level
WHERE level_id = '$id'
");

echo json_encode(mysqli_fetch_assoc($query));
?>