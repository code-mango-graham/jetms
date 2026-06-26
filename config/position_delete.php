<?php
include '../config.php';

header('Content-Type: application/json');

$position_id = $_POST['position_id'];

// Check if position is used in tbl_teacher
$check = mysqli_query($conn,"
    SELECT *
    FROM tbl_teacher
    WHERE position_id = '$position_id'
");

if(mysqli_num_rows($check) > 0){
    echo json_encode([
        "status" => "error",
        "message" => "Cannot delete this position. It is already assigned to teachers."
    ]);
    exit;
}

// DELETE
$result = mysqli_query($conn,"
    DELETE FROM tbl_position
    WHERE position_id = '$position_id'
");

if($result){
    echo json_encode([
        "status" => "success",
        "message" => "Position deleted successfully"
    ]);
}else{
    echo json_encode([
        "status" => "error",
        "message" => "Error deleting position"
    ]);
}
?>
