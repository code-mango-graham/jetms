<?php
include '../config.php';

header('Content-Type: application/json');

$department_id   = isset($_POST['department_id']) ? trim($_POST['department_id']) : '';
$department_name = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';

if ($department_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Department name is required"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if (empty($department_id)) {
    // INSERT duplicate check
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_department WHERE department_name = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $department_name);
} else {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_department WHERE department_name = ? AND department_id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $department_name, $department_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Department name already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// INSERT OR UPDATE
// =======================

if (empty($department_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_department (department_name) VALUES (?)");
    mysqli_stmt_bind_param($save, "s", $department_name);
} else {
    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_department SET department_name = ? WHERE department_id = ?");
    mysqli_stmt_bind_param($save, "si", $department_name, $department_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save department"
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