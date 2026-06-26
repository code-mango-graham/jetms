<?php
include '../config.php';

header('Content-Type: application/json');

$position_id    = $_POST['position_id'] ?? '';
$position_title = $_POST['position_title'];
$description    = $_POST['description'];


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if(empty($position_id)){

    // INSERT
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_position
        WHERE position_title = '$position_title'
    ");

}else{

    // UPDATE (exclude current record)
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_position
        WHERE position_title = '$position_title'
        AND position_id != '$position_id'
    ");
}

if(mysqli_num_rows($check) > 0){
    echo json_encode([
        "status" => "error",
        "message" => "Position title already exists"
    ]);
    exit;
}


// =======================
// INSERT OR UPDATE
// =======================

if(empty($position_id)){

    // INSERT
    mysqli_query($conn,"
        INSERT INTO tbl_position
        (
            position_title,
            description
        )
        VALUES
        (
            '$position_title',
            '$description'
        )
    ");

}else{

    // UPDATE
    mysqli_query($conn,"
        UPDATE tbl_position
        SET
            position_title = '$position_title',
            description = '$description'
        WHERE position_id = '$position_id'
    ");
}


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);
?>
