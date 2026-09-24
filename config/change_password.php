<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';

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

$policyError = password_policy_error($new_password, isset($auth['username']) ? $auth['username'] : '');
if ($policyError !== null) {
    echo json_encode(["status" => "error", "message" => $policyError]);
    exit;
}
if ($new_password === $current_password) {
    echo json_encode(["status" => "error", "message" => "The new password must be different from the current one"]);
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
    audit_log($conn, 'auth_failed', 'password_change', $auth['id'], 'Wrong current password while trying to change own password');
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
$_SESSION['auth']['must_change'] = false;
audit_log($conn, 'password_change', $table, $auth['id'], 'User changed their own password');

echo json_encode([
    "status" => "success",
    "message" => "Password changed successfully"
]);
