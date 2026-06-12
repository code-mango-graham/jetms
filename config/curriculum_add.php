<?php
include '../config.php';

header('Content-Type: application/json');

$curriculum_id   = $_POST['curriculum_id'];
$curriculum_name = $_POST['curriculum_name'];
$description      = $_POST['description'];
$status          = $_POST['status'];


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if(empty($curriculum_id)){

    // INSERT
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_curriculum
        WHERE curriculum_name = '$curriculum_name'
    ");

}else{

    // UPDATE (exclude current record)
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_curriculum
        WHERE curriculum_name = '$curriculum_name'
        AND curriculum_id != '$curriculum_id'
    ");
}

if(mysqli_num_rows($check) > 0){
    echo json_encode([
        "status" => "error",
        "message" => "Curriculum name already exists"
    ]);
    exit;
}


// =======================
// ONLY ONE ACTIVE CURRICULUM
// =======================

if($status == 1){
    mysqli_query($conn,"
        UPDATE tbl_curriculum
        SET status = 0
    ");
}


// =======================
// INSERT OR UPDATE
// =======================

if(empty($curriculum_id)){

    // INSERT
    mysqli_query($conn,"
        INSERT INTO tbl_curriculum
        (
            curriculum_name,
            description,
            status
        )
        VALUES
        (
            '$curriculum_name',
            '$description',
            '$status'
        )
    ");

}else{

    // UPDATE
    mysqli_query($conn,"
        UPDATE tbl_curriculum
        SET
            curriculum_name = '$curriculum_name',
            description = '$description',
            status = '$status'
        WHERE curriculum_id = '$curriculum_id'
    ");
}


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);