<?php
include '../config.php';

header('Content-Type: application/json');

$curriculum_id   = isset($_POST['curriculum_id']) ? trim($_POST['curriculum_id']) : '';
$curriculum_name = isset($_POST['curriculum_name']) ? trim($_POST['curriculum_name']) : '';
$description     = isset($_POST['description']) ? trim($_POST['description']) : '';
$status          = isset($_POST['status']) ? (int) $_POST['status'] : 0;

if ($curriculum_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Curriculum name is required"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if (empty($curriculum_id)) {
    // INSERT duplicate check
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_curriculum WHERE curriculum_name = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $curriculum_name);
} else {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_curriculum WHERE curriculum_name = ? AND curriculum_id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $curriculum_name, $curriculum_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Curriculum name already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// ONLY ONE ACTIVE CURRICULUM
// =======================

if ($status === 1) {
    mysqli_query($conn,"
        UPDATE tbl_curriculum
        SET status = 0
    ");
}


// =======================
// INSERT OR UPDATE
// =======================

if (empty($curriculum_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_curriculum (curriculum_name, description, status) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($save, "ssi", $curriculum_name, $description, $status);
} else {
    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_curriculum SET curriculum_name = ?, description = ?, status = ? WHERE curriculum_id = ?");
    mysqli_stmt_bind_param($save, "ssii", $curriculum_name, $description, $status, $curriculum_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save curriculum"
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