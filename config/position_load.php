<?php

include '../config.php';

header('Content-Type: application/json');

$query = mysqli_query($conn,"
SELECT position_id, position_title, description
FROM tbl_position
WHERE position_remarks = 1
ORDER BY position_id ASC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>
