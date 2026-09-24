<?php
// Single place where sessions are started, with safe cookie settings.
if (php_sapi_name() === 'cli' || session_status() !== PHP_SESSION_NONE) {
    return;
}

$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.use_strict_mode', '1');   // refuse session ids the server did not create
ini_set('session.use_only_cookies', '1');  // never accept ids from the URL
ini_set('session.cookie_httponly', '1');   // JavaScript can never read the cookie
session_name('JETMSSESS');                 // own name, so it never clashes with another app on the same host
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $__https,                 // sent over HTTPS only once the site has a certificate
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (isset($_SESSION['auth'])) {
    $idle = defined('SEC_IDLE_TIMEOUT') ? SEC_IDLE_TIMEOUT : 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idle) {
        $_SESSION = [];
        session_destroy();
        session_start();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Someone still on the default password may only change it (or log out).
if (isset($_SESSION['auth']['must_change']) && $_SESSION['auth']['must_change']
    && isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/config/') !== false
    && !in_array(basename($_SERVER['SCRIPT_NAME']), ['change_password.php', 'logout.php', 'auth_login.php'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'error', 'must_change' => true, 'message' => 'You must change your default password first.']));
}
