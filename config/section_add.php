<?php
include '../config.php';

header('Content-Type: application/json');

$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
$section_id = isset($_POST['section_id']) ? trim($_POST['section_id']) : '';
$section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';

if ($section_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Section name is required"
    ]);
    exit;
}

// =======================
// DUPLICATE CHECK
// =======================
// DUPLICATE CHECK
// =======================

if (empty($section_id)) {
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_section WHERE section_name = ? AND level_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $section_name, $level_id);

} else {
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_section WHERE section_name = ? AND section_id != ? AND level_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "sii", $section_name, $section_id, $level_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Section name already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// INSERT OR UPDATE
// =======================

if (empty($section_id)) {

    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_section (level_id, section_name) VALUES (?, ?)");
    mysqli_stmt_bind_param($save, "is", $level_id, $section_name);

} else {

    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_section SET section_name = ? WHERE section_id = ?");
    mysqli_stmt_bind_param($save, "si", $section_name, $section_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save section"
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