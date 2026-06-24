<?php
include '../config.php';

$level_id = $_POST['level_id'];

$query = mysqli_query($conn, "
    SELECT *
    FROM tbl_section
    WHERE level_id = '$level_id'
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>