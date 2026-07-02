<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id = isset($_POST['schoolyear_id']) ? (int)$_POST['schoolyear_id'] : 0;
$level_id = isset($_POST['level_id']) ? (int)$_POST['level_id'] : 0;

if (!$schoolyear_id || !$level_id) {
    echo json_encode([
        "data" => []
    ]);
    exit;
}

// Get sections from school year setup for this level
$query = mysqli_prepare($conn, "
    SELECT schoolyear_section_id, section_name, level_id, schoolyear_id
    FROM tbl_schoolyear_section
    WHERE schoolyear_id = ? AND level_id = ?
    ORDER BY section_name ASC
");

mysqli_stmt_bind_param($query, "ii", $schoolyear_id, $level_id);
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
?>
