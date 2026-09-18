<?php
include '../config.php';

header('Content-Type: application/json');

$role = isset($_POST['role']) ? trim($_POST['role']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (!in_array($role, ['admin', 'teacher', 'student'], true) || $username === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Role, username, and password are required"
    ]);
    exit;
}

if ($role === 'admin') {
    $sql = "SELECT admin_id, username, password, first_name, middle_name, last_name, extension_name
            FROM tbl_admin WHERE username = ? AND admin_remarks = 1 LIMIT 1";
} elseif ($role === 'teacher') {
    $sql = "SELECT ta.teacher_account_id, ta.username, ta.password,
                   t.first_name, t.middle_name, t.last_name, t.extension_name
            FROM tbl_teacher_account ta
            INNER JOIN tbl_teacher t ON t.teacher_id = ta.teacher_id
            WHERE ta.username = ? AND ta.account_remarks = 1 AND t.teacher_remarks = 1 LIMIT 1";
} else {
    $sql = "SELECT sa.student_account_id, sa.username, sa.password,
                   s.first_name, s.middle_name, s.last_name, s.extension_name
            FROM tbl_student_account sa
            INNER JOIN tbl_student s ON s.student_id = sa.student_id
            WHERE sa.username = ? AND sa.account_remarks = 1 AND s.student_status != 'archived' LIMIT 1";
}

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = $result ? $result->fetch_assoc() : null;
mysqli_stmt_close($stmt);

if (!$account || !password_verify($password, $account['password'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username or password"
    ]);
    exit;
}

if ($role === 'admin') {
    $userId = $account['admin_id'];
} elseif ($role === 'teacher') {
    $userId = $account['teacher_account_id'];
} else {
    $userId = $account['student_account_id'];
}

$nameParts = array_filter([
    $account['first_name'],
    $account['middle_name'],
    $account['last_name'],
    $account['extension_name']
], function ($part) {
    return $part !== null && trim((string)$part) !== '';
});
$fullName = implode(' ', $nameParts);

session_start();
$_SESSION['auth'] = [
    'role' => $role,
    'id' => $userId,
    'name' => $fullName
];

echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "redirect" => "mainpage.php"
]);
