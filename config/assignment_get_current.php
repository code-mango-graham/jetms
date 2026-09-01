<?php

include '../config.php';

header('Content-Type: application/json');

$section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

$data = null;

if ($section_id > 0 && $subject_id > 0) {
    $query = mysqli_prepare($conn, "
        SELECT
            assignment_id,
            section_id,
            subject_id,
            teacher_id,
            schedule_info,
            room_no
        FROM tbl_assignments
        WHERE section_id = ? AND subject_id = ?
        LIMIT 1
    ");
    
    mysqli_stmt_bind_param($query, "ii", $section_id, $subject_id);
    mysqli_stmt_execute($query);
    
    $result = mysqli_stmt_get_result($query);
    
    if($row = mysqli_fetch_assoc($result)){
        $data = $row;
    }
    
    mysqli_stmt_close($query);
}

echo json_encode([
    "data" => $data
]);
?>
