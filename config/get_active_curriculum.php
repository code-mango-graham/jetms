<?php
include '../config.php';

$query = "SELECT * FROM tbl_curriculum WHERE status = 1 LIMIT 1";
$result = mysqli_query($conn, $query);

$data = mysqli_fetch_assoc($result);

echo json_encode($data);
?>