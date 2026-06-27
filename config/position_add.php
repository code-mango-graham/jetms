<?php
include '../config.php';

header('Content-Type: application/json');

$position_id    = isset($_POST['position_id']) ? trim($_POST['position_id']) : '';
$position_title = isset($_POST['position_title']) ? trim($_POST['position_title']) : '';
$description    = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($position_title === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Position title is required"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if (empty($position_id)) {
    // INSERT duplicate check
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_position WHERE position_title = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $position_title);
} else {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_position WHERE position_title = ? AND position_id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $position_title, $position_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "Position title already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// INSERT OR UPDATE
// =======================

if (empty($position_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_position (position_title, description) VALUES (?, ?)");
    mysqli_stmt_bind_param($save, "ss", $position_title, $description);
} else {
    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_position SET position_title = ?, description = ? WHERE position_id = ?");
    mysqli_stmt_bind_param($save, "ssi", $position_title, $description, $position_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save position"
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
