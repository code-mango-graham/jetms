<?php
// =====================================================================
// JETMS core settings, loaded by every page and endpoint.
//
// Server-specific values (database login, timezone, folders...) do NOT
// belong in this file. Copy config.local.sample.php to config.local.php
// on each server and edit that one; it is never overwritten by updates.
// =====================================================================

$__local = __DIR__ . '/config.local.php';
if (is_file($__local)) {
    require $__local;
}

// ---- Database defaults (local XAMPP). Real servers set these in config.local.php.
if (!isset($host))     { $host = 'localhost'; }
if (!isset($username)) { $username = 'root'; }
if (!isset($password)) { $password = ''; }
if (!isset($database)) { $database = 'db_jetmsis'; }

// ---- Optional settings (define any of these in config.local.php) --------
defined('APP_DEBUG')                || define('APP_DEBUG', false);          // true only while developing: shows PHP errors
defined('APP_TIMEZONE')             || define('APP_TIMEZONE', 'Asia/Manila'); // used by PHP AND MySQL so dates agree on any host
defined('SEC_IDLE_TIMEOUT')         || define('SEC_IDLE_TIMEOUT', 7200);     // seconds of inactivity before login expires
defined('SEC_FORCE_PASSWORD_CHANGE') || define('SEC_FORCE_PASSWORD_CHANGE', true); // make people replace the default "admin" password
defined('KIOSK_ALLOWED_IPS')        || define('KIOSK_ALLOWED_IPS', []);      // e.g. ['203.0.113.7']; empty = anyone may use the gate kiosk
// BACKUP_DIR, BACKUP_MYSQLDUMP, BACKUP_MYSQL, BACKUP_FORCE_PHP may also be defined.

// ---- Errors: never show internals to visitors --------------------------
mysqli_report(MYSQLI_REPORT_OFF); // PHP 8.1+ throws by default; the code below expects false-returns
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// ---- Time: one timezone for PHP and MySQL ------------------------------
date_default_timezone_set(APP_TIMEZONE);

// ---- Browser-facing protections (skipped on the command line) ----------
if (php_sapi_name() !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');

    // CSRF: every state-changing request is a POST sent by our own jQuery code,
    // which adds this header. A forged cross-site form or image cannot add it, and
    // a cross-site script cannot without a CORS pre-flight that we never approve.
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $xrw = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
        $originOk = true;
        if (!empty($_SERVER['HTTP_ORIGIN']) && !empty($_SERVER['HTTP_HOST'])) {
            $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
            $thisHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
            $originOk = ($originHost !== null && strcasecmp($originHost, $thisHost) === 0);
        }
        if (strcasecmp($xrw, 'XMLHttpRequest') !== 0 || !$originOk) {
            http_response_code(403);
            header('Content-Type: application/json');
            exit(json_encode(['status' => 'error', 'message' => 'Request blocked (invalid origin).']));
        }
    }

    // Text typed into forms never legitimately needs angle brackets or double
    // quotes, and stripping them here means no stored value can ever break out
    // of the HTML the screens build from it. Password fields are left untouched.
    if (!function_exists('sec_clean_input')) {
    function sec_clean_input(array &$data) {
        foreach ($data as $k => &$v) {
            if (is_array($v)) {
                sec_clean_input($v);
            } elseif (is_string($v) && !preg_match('/pass/i', (string) $k)) {
                $v = str_replace('"', "'", str_replace(['<', '>'], '', $v));
            }
        }
    }
    }
    sec_clean_input($_POST);
}

// ---- Database ----------------------------------------------------------
// Log the real reason, show a generic message.
if (!function_exists('db_fail')) {
function db_fail($detail) {
    error_log('JETMS database error: ' . $detail);
    return 'A database error occurred. Please try again or contact the administrator.';
}
}

$conn = @new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log('JETMS cannot connect to the database: ' . $conn->connect_error);
    if (php_sapi_name() !== 'cli') {
        http_response_code(503);
        header('Content-Type: text/plain');
    }
    exit('The system is temporarily unavailable. Please try again later.');
}

$conn->set_charset('utf8mb4');

// Make MySQL agree with PHP about "now" (CURDATE(), NOW(), TIMESTAMP columns).
$conn->query("SET time_zone = '" . (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P') . "'");
