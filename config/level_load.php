<?php
include '../config.php';

$curriculum_id = $_POST['curriculum_id'];

$query = mysqli_query($conn, "
    SELECT *
    FROM tbl_level
    WHERE curriculum_id = '$curriculum_id'
    ORDER BY CAST(level_type AS UNSIGNED) ASC, level_name ASC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>