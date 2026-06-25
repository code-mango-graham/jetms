<?php
include '../config.php';

header('Content-Type: application/json');

$level_id = $_POST['level_id'];
$section_id = $_POST['section_id'];
$section_name = $_POST['section_name'];

// =======================
// DUPLICATE CHECK
// =======================
// DUPLICATE CHECK
// =======================

if (empty($section_id)) {

    $check = mysqli_query($conn, "
        SELECT *
        FROM tbl_section
        WHERE section_name = '$section_name'
        AND level_id = '$level_id'
    ");

} else {

    $check = mysqli_query($conn, "
        SELECT *
        FROM tbl_section
        WHERE section_name = '$section_name'
        AND section_id != '$section_id'
        AND level_id = '$level_id'
    ");
}

if (mysqli_num_rows($check) > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Section name already exists"
    ]);
    exit;
}


// =======================
// INSERT OR UPDATE
// =======================

if (empty($section_id)) {

    // INSERT
    mysqli_query($conn, "
        INSERT INTO tbl_section (
            level_id,
            section_name
        ) VALUES (
            '$level_id',
            '$section_name'
        )
    ");

} else {

    // UPDATE
    mysqli_query($conn, "
        UPDATE tbl_section
        SET
            section_name = '$section_name'
        WHERE section_id = '$section_id'
    ");
}


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);