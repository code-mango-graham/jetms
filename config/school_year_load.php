<?php

include '../config.php';

header('Content-Type: application/json');

$query = mysqli_query($conn, "
    SELECT
        sy.schoolyear_id,
        sy.schoolyear_name,
        sy.year_start,
        sy.year_end,
        sy.status,
        sy.schoolyear_remarks,
        sy.curriculum_id,
        c.curriculum_name
    FROM tbl_schoolyear sy
    LEFT JOIN tbl_curriculum c ON c.curriculum_id = sy.curriculum_id
    ORDER BY sy.year_start DESC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    if (!isset($row['curriculum_name']) || $row['curriculum_name'] === null) {
        $row['curriculum_name'] = 'Not linked';
    }
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>