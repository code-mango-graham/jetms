<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id   = $_POST['schoolyear_id'];
$schoolyear_name = $_POST['schoolyear_name'];
$year_start      = $_POST['year_start'];
$year_end        = $_POST['year_end'];
$status          = $_POST['status'];


// =======================
// VALIDATION
// =======================

if($year_end <= $year_start){
    echo json_encode([
        "status" => "error",
        "message" => "Year End must be greater than Year Start"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if(empty($schoolyear_id)){

    // INSERT
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_schoolyear
        WHERE year_start = '$year_start'
        AND year_end = '$year_end'
    ");

}else{

    // UPDATE (exclude current record)
    $check = mysqli_query($conn,"
        SELECT *
        FROM tbl_schoolyear
        WHERE year_start = '$year_start'
        AND year_end = '$year_end'
        AND schoolyear_id != '$schoolyear_id'
    ");
}

if(mysqli_num_rows($check) > 0){
    echo json_encode([
        "status" => "error",
        "message" => "School Year already exists"
    ]);
    exit;
}


// =======================
// ONLY ONE ACTIVE SCHOOL YEAR
// =======================

if($status == 1){
    mysqli_query($conn,"
        UPDATE tbl_schoolyear
        SET status = 0
    ");
}


// =======================
// INSERT OR UPDATE
// =======================

if(empty($schoolyear_id)){

    // INSERT
    mysqli_query($conn,"
        INSERT INTO tbl_schoolyear
        (
            schoolyear_name,
            year_start,
            year_end,
            status
        )
        VALUES
        (
            '$schoolyear_name',
            '$year_start',
            '$year_end',
            '$status'
        )
    ");

}else{

    // UPDATE
    mysqli_query($conn,"
        UPDATE tbl_schoolyear
        SET
            schoolyear_name = '$schoolyear_name',
            year_start = '$year_start',
            year_end = '$year_end',
            status = '$status'
        WHERE schoolyear_id = '$schoolyear_id'
    ");
}


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);