<?php
include '../config.php';

header('Content-Type: application/json');

$curriculum_id = isset($_POST['curriculum_id']) ? (int) $_POST['curriculum_id'] : 0;
$level_id = isset($_POST['level_id']) ? trim($_POST['level_id']) : '';
$level_name = isset($_POST['level_name']) ? trim($_POST['level_name']) : '';
$level_type = isset($_POST['level_type']) ? trim($_POST['level_type']) : '';

if ($level_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Level name is required"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// =======================

if (empty($level_id)) {
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_level WHERE level_name = ? AND curriculum_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $level_name, $curriculum_id);

} else {
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_level WHERE level_name = ? AND level_id != ? AND curriculum_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "sii", $level_name, $level_id, $curriculum_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Level name already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// INSERT OR UPDATE
// =======================

if (empty($level_id)) {

    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_level (curriculum_id, level_name, level_type) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($save, "iss", $curriculum_id, $level_name, $level_type);

} else {

    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_level SET level_name = ?, level_type = ? WHERE level_id = ?");
    mysqli_stmt_bind_param($save, "ssi", $level_name, $level_type, $level_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save level"
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