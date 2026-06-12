<?php
include '../config.php';

header('Content-Type: application/json');

$department_id   = $_POST['department_id'];
$department_name = $_POST['department_name'];


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if(empty($department_id)){

    // INSERT
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_department
        WHERE department_name = '$department_name'
    ");

}else{

    // UPDATE (exclude current record)
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_department
        WHERE department_name = '$department_name'
        AND department_id != '$department_id'
    ");
}

if(mysqli_num_rows($check) > 0){
    echo json_encode([
        "status" => "error",
        "message" => "Department name already exists"
    ]);
    exit;
}


// =======================
// INSERT OR UPDATE
// =======================

if(empty($department_id)){

    // INSERT
    mysqli_query($conn,"
        INSERT INTO tbl_department
        (
            department_name
        )
        VALUES
        (
            '$department_name'
        )
    ");

}else{

    // UPDATE
    mysqli_query($conn,"
        UPDATE tbl_department
        SET
            department_name = '$department_name'
        WHERE department_id = '$department_id'
    ");
}


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);