<?php
include '../config.php';

header('Content-Type: application/json');

$level_id   = isset($_POST['level_id']) ? trim($_POST['level_id']) : '';
$level_name = isset($_POST['level_name']) ? trim($_POST['level_name']) : '';
$level_type = isset($_POST['level_type']) ? trim($_POST['level_type']) : '';

// =======================
// VALIDATION
// =======================

if ($level_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Level name is required"
    ]);
    exit;
}

if ($level_type === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Level type is required"
    ]);
    exit;
}

// =======================
// DUPLICATE CHECK
// =======================

if (empty($level_id)) {
    // INSERT duplicate check
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_level WHERE level_name = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $level_name);
} else {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_level WHERE level_name = ? AND level_id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $level_name, $level_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Level already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);

// =======================
// INSERT OR UPDATE
// =======================

if (empty($level_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_level (level_name, level_type) VALUES (?, ?)");
    mysqli_stmt_bind_param($save, "ss", $level_name, $level_type);
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
?>
