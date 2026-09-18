<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth'])) {
    echo json_encode([
        "status" => "error",
        "message" => "You must be logged in to change your password"
    ]);
    exit;
}

$auth = $_SESSION['auth'];
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

if ($current_password === '' || $new_password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Current and new password are required"
    ]);
    exit;
}

if (strlen($new_password) < 4) {
    echo json_encode([
        "status" => "error",
        "message" => "New password must be at least 4 characters"
    ]);
    exit;
}

if ($auth['role'] === 'admin') {
    $table = 'tbl_admin';
    $idCol = 'admin_id';
} elseif ($auth['role'] === 'teacher') {
    $table = 'tbl_teacher_account';
    $idCol = 'teacher_account_id';
} else {
    $table = 'tbl_student_account';
    $idCol = 'student_account_id';
}

$stmt = mysqli_prepare($conn, "SELECT password FROM $table WHERE $idCol = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $auth['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? $result->fetch_assoc() : null;
mysqli_stmt_close($stmt);

if (!$row || !password_verify($current_password, $row['password'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Current password is incorrect"
    ]);
    exit;
}

$newHash = password_hash($new_password, PASSWORD_BCRYPT);

$update = mysqli_prepare($conn, "UPDATE $table SET password = ? WHERE $idCol = ?");
mysqli_stmt_bind_param($update, "si", $newHash, $auth['id']);
mysqli_stmt_execute($update);
mysqli_stmt_close($update);

echo json_encode([
    "status" => "success",
    "message" => "Password changed successfully"
]);
