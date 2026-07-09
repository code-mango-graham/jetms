<?php

include '../config.php';

header('Content-Type: application/json');

$query = mysqli_query($conn,"
SELECT office_id, office_name, office_description
FROM tbl_office
ORDER BY office_id ASC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>