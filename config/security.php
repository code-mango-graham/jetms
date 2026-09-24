<?php
// Shared security helpers: session/role guards, audit + login logging,
// and admin password re-confirmation for high-impact actions.
// Require AFTER config.php (needs $conn).

function sec_session() {
    if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/session_boot.php';
    }
}

function sec_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : 'cli';
}

function sec_json_exit($status, $message, $httpCode = 200, $extra = []) {
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(["status" => $status, "message" => $message], $extra));
    exit;
}

function sec_actor() {
    sec_session();
    if (php_sapi_name() === 'cli') {
        return ['role' => 'system', 'id' => null, 'name' => 'Scheduled task'];
    }
    if (isset($_SESSION['auth'])) {
        $a = $_SESSION['auth'];
        return [
            'role' => $a['role'],
            'id' => isset($a['ref_id']) ? (int) $a['ref_id'] : (isset($a['id']) ? (int) $a['id'] : null),
            'name' => $a['name']
        ];
    }
    return ['role' => 'anonymous', 'id' => null, 'name' => 'Not logged in'];
}

// Blocks the request unless an admin is logged in. $publicActions lets a file
// keep specific actions open (e.g. the attendance kiosk's "scan").
function require_admin($publicActions = []) {
    sec_session();
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
    if (in_array($action, $publicActions, true)) {
        return;
    }
    if (!isset($_SESSION['auth'])) {
        sec_json_exit("error", "Please log in again.", 401);
    }
    if ($_SESSION['auth']['role'] !== 'admin') {
        global $conn;
        audit_log($conn, 'denied', basename($_SERVER['SCRIPT_NAME']), null, "Blocked: {$_SESSION['auth']['role']} tried admin action '{$action}'");
        sec_json_exit("error", "Not authorized.", 403);
    }
}

function require_login() {
    sec_session();
    if (!isset($_SESSION['auth'])) {
        sec_json_exit("error", "Please log in again.", 401);
    }
}

// The file must actually decode as an image (a script renamed .jpg fails this).
function is_real_image($path) {
    $info = @getimagesize($path);
    return $info !== false && $info[0] > 0 && $info[1] > 0 && $info[0] <= 12000 && $info[1] <= 12000;
}

// A real calendar date in YYYY-MM-DD form (rejects 2026-02-31, 'abc', '0000-00-00').
function valid_date($s) {
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 1990 && (int) $m[1] <= 2100;
}

// Password rules for anything a person chooses or an admin types in.
// (The auto-created default 'admin' password is exempt, but forces a change at first login.)
function password_policy_error($pw, $username = '') {
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    $weak = ['admin', 'password', '12345678', '123456789', 'qwertyui', '11111111', '00000000', 'admin123', 'password1', 'teacher123', 'student123'];
    if (in_array(strtolower($pw), $weak, true)) {
        return 'That password is too easy to guess. Please choose another.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Use both letters and numbers in the password.';
    }
    if ($username !== '' && strcasecmp($pw, $username) === 0) {
        return 'The password cannot be the same as the username.';
    }
    return null;
}

// ---------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------

// Current values of one row for before/after comparison. Anything that looks
// like a password, and bookkeeping timestamps, is never captured.
function audit_snapshot($conn, $table, $idCol, $id) {
    if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $idCol)) {
        return null;
    }
    $id = (int) $id;
    $result = mysqli_query($conn, "SELECT * FROM `$table` WHERE `$idCol` = $id LIMIT 1");
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return null;
    }
    foreach ($row as $k => $v) {
        if (stripos($k, 'password') !== false || in_array($k, ['updated_at', 'created_at'], true)) {
            unset($row[$k]);
        } elseif (is_string($v) && strlen($v) > 200) {
            $row[$k] = substr($v, 0, 200) . '...';
        }
    }
    return $row;
}

function audit_log($conn, $action, $entity, $entityId, $summary, $old = null, $new = null, $extra = null) {
    try {
        $details = [];
        if (is_array($old) && is_array($new)) {
            $changes = [];
            foreach ($new as $k => $v) {
                $before = array_key_exists($k, $old) ? $old[$k] : null;
                if ((string) $before !== (string) $v) {
                    $changes[$k] = ['from' => $before, 'to' => $v];
                }
            }
            if (!$changes && $action === 'update') {
                return; // nothing actually changed
            }
            $details['changes'] = $changes;
        } elseif (is_array($new)) {
            $details['new'] = $new;
        } elseif (is_array($old)) {
            $details['old'] = $old;
        }
        if (is_array($extra)) {
            $details = array_merge($details, $extra);
        }
        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

        $actor = sec_actor();
        $ip = sec_client_ip();
        $summary = mb_substr((string) $summary, 0, 255);
        $entity = mb_substr((string) $entity, 0, 60);
        $entityIdVal = ($entityId === null || $entityId === '') ? null : (int) $entityId;

        $stmt = mysqli_prepare($conn, "
            INSERT INTO tbl_audit_log (actor_role, actor_id, actor_name, action, entity, entity_id, summary, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "sisssisss", $actor['role'], $actor['id'], $actor['name'], $action, $entity, $entityIdVal, $summary, $detailsJson, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// Login records
// ---------------------------------------------------------------------

function login_log($conn, $event, $role, $username, $userRefId = null, $failureReason = null) {
    try {
        $ip = sec_client_ip();
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        $username = $username === null ? null : mb_substr((string) $username, 0, 100);
        $stmt = mysqli_prepare($conn, "
            INSERT INTO tbl_login_log (event, role_attempted, username, user_ref_id, failure_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "sssisss", $event, $role, $username, $userRefId, $failureReason, $ip, $ua);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        error_log('login_log failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// Password re-confirmation for high-impact actions (backup / restore /
// activating a school year). Returns null when OK, otherwise an error string.
// 5 wrong attempts in 15 minutes locks confirmations for that admin.
// ---------------------------------------------------------------------
function confirm_admin_password($conn, $password, $operation) {
    sec_session();
    if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
        return "Not authorized.";
    }
    $adminId = (int) $_SESSION['auth']['id'];

    $recent = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS c FROM tbl_audit_log
        WHERE action = 'auth_failed' AND actor_role = 'admin' AND actor_id = $adminId
          AND created_at > (NOW() - INTERVAL 15 MINUTE)
    "));
    if ((int) $recent['c'] >= 5) {
        audit_log($conn, 'auth_failed', 'password_confirmation', $adminId, "Locked out: too many wrong passwords ($operation)");
        return "Too many wrong passwords. Try again in 15 minutes.";
    }

    if ($password === '' || $password === null) {
        return "Enter your password to confirm this action.";
    }

    $stmt = mysqli_prepare($conn, "SELECT password FROM tbl_admin WHERE admin_id = ? AND admin_remarks = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $adminId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row || !password_verify($password, $row['password'])) {
        audit_log($conn, 'auth_failed', 'password_confirmation', $adminId, "Wrong password on confirmation ($operation)");
        return "Incorrect password.";
    }
    return null;
}
