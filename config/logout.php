<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';

if (isset($_SESSION['auth'])) {
    login_log($conn, 'logout', $_SESSION['auth']['role'], isset($_SESSION['auth']['username']) ? $_SESSION['auth']['username'] : $_SESSION['auth']['name'], isset($_SESSION['auth']['ref_id']) ? (int) $_SESSION['auth']['ref_id'] : null);
}
$_SESSION = [];
session_destroy();
header('Location: ../index.html');
exit;
