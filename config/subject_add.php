<?php
include '../config.php';

header('Content-Type: application/json');

$subject_id   = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : '';
$subject_name = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
$subject_code = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
$level_id     = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

// =======================
// VALIDATION
// =======================

if ($subject_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Subject name is required"
    ]);
    exit;
}

if ($level_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Level is required"
    ]);
    exit;
}

// =======================
// INSERT OR UPDATE
// =======================

if (empty($subject_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_subject (subject_name, subject_code, level_id) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($save, "ssi", $subject_name, $subject_code, $level_id);
} else {
    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_subject SET subject_name = ?, subject_code = ? WHERE subject_id = ?");
    mysqli_stmt_bind_param($save, "ssi", $subject_name, $subject_code, $subject_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save subject"
    ]);
    exit;
}

mysqli_stmt_close($save);

// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);
?>
