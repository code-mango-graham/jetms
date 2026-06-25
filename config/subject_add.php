<?php
include '../config.php';

header('Content-Type: application/json');

$level_id = $_POST['level_id'];
$subject_id = $_POST['subject_id'];
$subject_name = $_POST['subject_name'];
$subject_code = $_POST['subject_code'];

// =======================
// DUPLICATE CHECK
// =======================

if (empty($subject_id)) {

    // INSERT - check if subject already exists
    $check = mysqli_query($conn, "
        SELECT *
        FROM tbl_subject
        WHERE subject_name = '$subject_name'
        AND level_id = '$level_id'
    ");

} else {

    // UPDATE - exclude current record
    $check = mysqli_query($conn, "
        SELECT *
        FROM tbl_subject
        WHERE subject_name = '$subject_name'
        AND subject_id != '$subject_id'
        AND level_id = '$level_id'
    ");
}

if (mysqli_num_rows($check) > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Subject name already exists for this level"
    ]);
    exit;
}


// =======================
// INSERT OR UPDATE
// =======================

if (empty($subject_id)) {

    // INSERT
    $insert = mysqli_query($conn, "
        INSERT INTO tbl_subject (subject_name, subject_code, level_id)
        VALUES ('$subject_name', '$subject_code', '$level_id')
    ");

    if ($insert) {
        echo json_encode([
            "status" => "success",
            "message" => "Subject added successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Error adding subject"
        ]);
    }

} else {

    // UPDATE
    $update = mysqli_query($conn, "
        UPDATE tbl_subject
        SET subject_name = '$subject_name',
            subject_code = '$subject_code'
        WHERE subject_id = '$subject_id'
        AND level_id = '$level_id'
    ");

    if ($update) {
        echo json_encode([
            "status" => "success",
            "message" => "Subject updated successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Error updating subject"
        ]);
    }

}
?>
