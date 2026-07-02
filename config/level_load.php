<?php
include '../config.php';

header('Content-Type: application/json');

$curriculum_id = isset($_POST['curriculum_id']) ? (int)$_POST['curriculum_id'] : 0;

if ($curriculum_id) {
    // Load levels for specific curriculum
    $query = mysqli_prepare($conn, "
        SELECT level_id, level_name, level_type, curriculum_id
        FROM tbl_level
        WHERE curriculum_id = ?
        ORDER BY CAST(level_type AS UNSIGNED) ASC, level_name ASC
    ");
    mysqli_stmt_bind_param($query, "i", $curriculum_id);
} else {
    // Load all levels (for enrollment)
    $query = mysqli_prepare($conn, "
        SELECT level_id, level_name, level_type, curriculum_id
        FROM tbl_level
        ORDER BY CAST(level_type AS UNSIGNED) ASC, level_name ASC
    ");
}

mysqli_stmt_execute($query);
$resultSet = mysqli_stmt_get_result($query);

$data = [];
while ($row = mysqli_fetch_assoc($resultSet)) {
    $data[] = $row;
}

mysqli_stmt_close($query);

echo json_encode([
    "data" => $data
]);