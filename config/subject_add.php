<?php
include '../config.php';

header('Content-Type: application/json');

$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
$subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : '';
$subject_name = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
$subject_code = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';

if ($subject_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Subject name is required"
    ]);
    exit;
}

// =======================
// DUPLICATE CHECK
// =======================

if (empty($subject_id)) {

    // INSERT - check if subject already exists
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_subject WHERE subject_name = ? AND level_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $subject_name, $level_id);

} else {

    // UPDATE - exclude current record
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_subject WHERE subject_name = ? AND subject_id != ? AND level_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "sii", $subject_name, $subject_id, $level_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Subject name already exists for this level"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// INSERT OR UPDATE
// =======================

if (empty($subject_id)) {

    // INSERT
    $insert = mysqli_prepare($conn, "INSERT INTO tbl_subject (subject_name, subject_code, level_id) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insert, "ssi", $subject_name, $subject_code, $level_id);

    if (mysqli_stmt_execute($insert)) {
        mysqli_stmt_close($insert);
        echo json_encode([
            "status" => "success",
            "message" => "Subject added successfully"
        ]);
    } else {
        mysqli_stmt_close($insert);
        echo json_encode([
            "status" => "error",
            "message" => "Error adding subject"
        ]);
    }

} else {

    // UPDATE
    $update = mysqli_prepare($conn, "UPDATE tbl_subject SET subject_name = ?, subject_code = ? WHERE subject_id = ? AND level_id = ?");
    mysqli_stmt_bind_param($update, "ssii", $subject_name, $subject_code, $subject_id, $level_id);

    if (mysqli_stmt_execute($update)) {
        mysqli_stmt_close($update);
        echo json_encode([
            "status" => "success",
            "message" => "Subject updated successfully"
        ]);
    } else {
        mysqli_stmt_close($update);
        echo json_encode([
            "status" => "error",
            "message" => "Error updating subject"
        ]);
    }

}
?>
