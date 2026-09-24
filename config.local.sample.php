<?php
// =====================================================================
// Copy this file to  config.local.php  on the server and fill it in.
// config.local.php holds secrets - never commit it, never share it.
// Anything you leave out falls back to the default in config.php.
// =====================================================================

// ---- Database (from your hosting control panel: MySQL Databases) ----
$host     = 'localhost';
$username = 'cpaneluser_jetms';
$password = 'a-long-random-password';
$database = 'cpaneluser_jetmsis';

// ---- Optional --------------------------------------------------------
// define('APP_TIMEZONE', 'Asia/Manila');          // default already Asia/Manila
// define('APP_DEBUG', false);                      // leave false on a live site
// define('SEC_IDLE_TIMEOUT', 7200);                // seconds idle before auto-logout
// define('SEC_FORCE_PASSWORD_CHANGE', true);       // force replacing the default 'admin' password
// define('KIOSK_ALLOWED_IPS', ['203.0.113.7']);    // school's public IP: only it can use the gate kiosk

// Backups: keep dumps OUTSIDE the public web folder if your host allows it
// (a path above public_html). The folder must be writable by PHP.
// define('BACKUP_DIR', '/home/cpaneluser/jetms_backups');
// define('BACKUP_FORCE_PHP', true);                // use the built-in PHP backup engine (no mysqldump needed)
// define('BACKUP_MYSQLDUMP', '/usr/bin/mysqldump');
// define('BACKUP_MYSQL', '/usr/bin/mysql');
