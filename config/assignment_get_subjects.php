<?php

include '../config.php';

header('Content-Type: application/json');

$section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

$data = [];

if ($section_id > 0) {
    // Get level from section
    $level_query = mysqli_prepare($conn, "SELECT level_id FROM tbl_section WHERE section_id = ? LIMIT 1");
    mysqli_stmt_bind_param($level_query, "i", $section_id);
    mysqli_stmt_execute($level_query);
    $level_result = mysqli_stmt_get_result($level_query);
    $level_row = mysqli_fetch_assoc($level_result);
    mysqli_stmt_close($level_query);
    
    if ($level_row) {
        $level_id = $level_row['level_id'];
        
        $query = mysqli_prepare($conn, "
            SELECT
                subject_id,
                subject_name,
                subject_code
            FROM tbl_subject
            WHERE level_id = ?
            ORDER BY subject_name ASC
        ");
        
        mysqli_stmt_bind_param($query, "i", $level_id);
        mysqli_stmt_execute($query);
        
        $result = mysqli_stmt_get_result($query);
        
        while($row = mysqli_fetch_assoc($result)){
            $data[] = $row;
        }
        
        mysqli_stmt_close($query);
    }
}

echo json_encode([
    "data" => $data
]);
?>
