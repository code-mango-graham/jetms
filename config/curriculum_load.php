<?php

include '../config.php';

$query = mysqli_query($conn,"
SELECT *
FROM tbl_curriculum
ORDER BY curriculum_name ASC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>