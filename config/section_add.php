<?php
include '../config.php';

header('Content-Type: application/json');

$section_id   = isset($_POST['section_id']) ? trim($_POST['section_id']) : '';
$section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';
$level_id     = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;

// =======================
// VALIDATION
// =======================

if ($section_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Section name is required"
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

if (empty($section_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_section (section_name, level_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($save, "si", $section_name, $level_id);
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
?>
