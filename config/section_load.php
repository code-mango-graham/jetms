<?php

include '../config.php';

header('Content-Type: application/json');

$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

$data = [];

if ($level_id > 0) {
    $query = mysqli_prepare($conn, "
        SELECT
            section_id,
            section_name,
            level_id
        FROM tbl_section
        WHERE level_id = ?
        ORDER BY section_name ASC
    ");
    
    mysqli_stmt_bind_param($query, "i", $level_id);
    mysqli_stmt_execute($query);
    
    $result = mysqli_stmt_get_result($query);
    
    while($row = mysqli_fetch_assoc($result)){
        $data[] = $row;
    }
    
    mysqli_stmt_close($query);
}

echo json_encode([
    "data" => $data
]);
?>
