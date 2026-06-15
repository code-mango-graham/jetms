<?php
include '../config.php';

header('Content-Type: application/json');

$curriculum_id = $_POST['curriculum_id'];
$level_id = $_POST['level_id'];
$level_name = $_POST['level_name'];
$level_type = $_POST['level_type'];


// =======================
// DUPLICATE CHECK
// =======================

if (empty($level_id)) {

    $check = mysqli_query($conn, "
        SELECT *
        FROM tbl_level
        WHERE level_name = '$level_name'
        AND curriculum_id = '$curriculum_id'
    ");

} else {

    $check = mysqli_query($conn, "
        SELECT *
        FROM tbl_level
        WHERE level_name = '$level_name'
        AND level_id != '$level_id'
        AND curriculum_id = '$curriculum_id'
    ");
}

if (mysqli_num_rows($check) > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Level name already exists"
    ]);
    exit;
}


// =======================
// INSERT OR UPDATE
// =======================

if (empty($level_id)) {

    // INSERT
    mysqli_query($conn, "
        INSERT INTO tbl_level (
            curriculum_id,
            level_name,
            level_type
        ) VALUES (
            '$curriculum_id',
            '$level_name',
            '$level_type'
        )
    ");

} else {

    // UPDATE
    mysqli_query($conn, "
        UPDATE tbl_level
        SET
            level_name = '$level_name',
            level_type = '$level_type'
        WHERE level_id = '$level_id'
    ");
}


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);