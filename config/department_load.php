<?php

include '../config.php';

$query = mysqli_query($conn,"
SELECT *
FROM tbl_department
ORDER BY department_id ASC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>