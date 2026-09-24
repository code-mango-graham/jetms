<?php
include '../config.php';
include 'security.php';

header('Content-Type: application/json');

$role = isset($_POST['role']) ? trim($_POST['role']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (!in_array($role, ['admin', 'teacher', 'student'], true) || $username === '' || $password === '') {
    if ($username !== '') {
        login_log($conn, 'login_failed', $role, $username, null, 'missing role or password');
    }
    echo json_encode([
        "status" => "error",
        "message" => "Role, username, and password are required"
    ]);
    exit;
}

// Throttle guessing: 5 failures for the same name from the same address, or
// 25 failures from one address (any name), within 15 minutes -> wait it out.
$ip = sec_client_ip();
$pairFails = (int) mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM tbl_login_log WHERE event='login_failed' AND created_at > (NOW() - INTERVAL 15 MINUTE) AND ip_address = '" . mysqli_real_escape_string($conn, $ip) . "' AND username = '" . mysqli_real_escape_string($conn, $username) . "'"))[0];
$ipFails = (int) mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM tbl_login_log WHERE event='login_failed' AND created_at > (NOW() - INTERVAL 15 MINUTE) AND ip_address = '" . mysqli_real_escape_string($conn, $ip) . "'"))[0];
if ($pairFails >= 5 || $ipFails >= 25) {
    http_response_code(429);
    login_log($conn, 'login_failed', $role, $username, null, 'blocked: too many recent failures');
    echo json_encode(["status" => "error", "message" => "Too many failed attempts. Please wait 15 minutes and try again."]);
    exit;
}

if ($role === 'admin') {
    $sql = "SELECT admin_id, username, password, first_name, middle_name, last_name, extension_name
            FROM tbl_admin WHERE username = ? AND admin_remarks = 1 LIMIT 1";
} elseif ($role === 'teacher') {
    $sql = "SELECT ta.teacher_account_id, ta.teacher_id, ta.username, ta.password,
                   t.first_name, t.middle_name, t.last_name, t.extension_name
            FROM tbl_teacher_account ta
            INNER JOIN tbl_teacher t ON t.teacher_id = ta.teacher_id
            WHERE ta.username = ? AND ta.account_remarks = 1 AND t.teacher_remarks = 1 LIMIT 1";
} else {
    $sql = "SELECT sa.student_account_id, sa.student_id, sa.username, sa.password,
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

if (!$account) {
    password_verify($password, '$2y$10$abcdefghijklmnopqrstuuMhvGLvDs1yjJRZ4QYeVwAQTNCeqBkiy'); // dummy hash: keep timing similar
}
if (!$account || !password_verify($password, $account['password'])) {
    // The reason is kept in the log only; the user always sees the same message.
    login_log($conn, 'login_failed', $role, $username, null, !$account ? 'unknown or inactive account' : 'wrong password');
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username or password"
    ]);
    exit;
}

// 'id' = the login-account row id (used for password/photo/announcement-view),
// 'ref_id' = the real person id (admin_id / teacher_id / student_id) that every
// data query must use — the two id sequences drift apart as soon as one
// teacher/student exists without an account.
if ($role === 'admin') {
    $userId = $account['admin_id'];
    $refId = $account['admin_id'];
} elseif ($role === 'teacher') {
    $userId = $account['teacher_account_id'];
    $refId = $account['teacher_id'];
} else {
    $userId = $account['student_account_id'];
    $refId = $account['student_id'];
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

require_once __DIR__ . '/session_boot.php';
session_regenerate_id(true); // new session id at login (prevents session fixation)
// Still using the well-known default? They must replace it before doing anything else.
$mustChange = (SEC_FORCE_PASSWORD_CHANGE && $password === 'admin');
$_SESSION['auth'] = [
    'must_change' => $mustChange,
    'role' => $role,
    'id' => $userId,
    'ref_id' => $refId,
    'username' => $username,
    'name' => $fullName
];

login_log($conn, 'login_success', $role, $username, $refId);

echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "redirect" => "mainpage.php",
    "must_change" => $mustChange
]);
