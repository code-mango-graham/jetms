<?php
include '../config.php';

header('Content-Type: application/json');

$office_id   = isset($_POST['office_id']) ? trim($_POST['office_id']) : '';
$office_name = isset($_POST['office_name']) ? trim($_POST['office_name']) : '';
$office_description = isset($_POST['office_description']) ? trim($_POST['office_description']) : '';

if ($office_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Office name is required"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if (empty($office_id)) {
    // INSERT duplicate check
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_office WHERE office_name = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $office_name);
} else {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_office WHERE office_name = ? AND office_id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $office_name, $office_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Office name already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// INSERT OR UPDATE
// =======================

if (empty($office_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_office (office_name, office_description) VALUES (?, ?)");
    mysqli_stmt_bind_param($save, "ss", $office_name, $office_description);
} else {
    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_office SET office_name = ?, office_description = ? WHERE office_id = ?");
    mysqli_stmt_bind_param($save, "ssi", $office_name, $office_description, $office_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save office"
    ]);
    exit;
}

mysqli_stmt_close($save);


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Office saved successfully"
]);