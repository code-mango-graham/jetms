<?php

include '../config.php';

header('Content-Type: application/json');

$query = mysqli_query($conn, "
    SELECT
        level_id,
        level_name,
        level_type,
        created_at,
        updated_at
    FROM tbl_level
    ORDER BY level_id DESC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>
