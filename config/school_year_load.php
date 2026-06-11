<?php

include '../config.php';

$query = mysqli_query($conn,"
SELECT *
FROM tbl_schoolyear
ORDER BY year_start DESC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>