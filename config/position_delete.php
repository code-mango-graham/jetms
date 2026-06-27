<?php
include '../config.php';

header('Content-Type: application/json');

$position_id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;

if ($position_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid position ID"
    ]);
    exit;
}

// Archive instead of hard delete
$result = mysqli_query($conn,"
    UPDATE tbl_position
    SET position_remarks = 0
    WHERE position_id = '$position_id'
");

if($result){
    echo json_encode([
        "status" => "success",
        "message" => "Position archived successfully"
    ]);
}else{
    echo json_encode([
        "status" => "error",
        "message" => "Error archiving position"
    ]);
}
?>
