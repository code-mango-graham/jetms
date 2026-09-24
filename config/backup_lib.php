<?php
// Database backup / restore helpers.
//
// Two engines, chosen automatically:
//   cli - the mysqldump / mysql command-line tools (needs exec() + the tools;
//         override paths with BACKUP_MYSQLDUMP / BACKUP_MYSQL in config.local.php)
//   php - a built-in dumper/importer that needs nothing but PHP, for shared hosting
//         where exec() is disabled. Used when the tools are missing, when
//         BACKUP_FORCE_PHP is set, or when the cli dump/import fails.
// Both read and write ordinary .sql files, so a file from either restores with either.

const BACKUP_TYPES = ['manual', 'weekly', 'pre_activate', 'pre_restore'];
const BACKUP_KEEP_WEEKLY = 12;          // ~3 months of weekly backups
const BACKUP_LOG_TABLES = ['tbl_audit_log', 'tbl_login_log']; // survive a restore

function backup_dir() {
    $dir = defined('BACKUP_DIR') ? BACKUP_DIR : dirname(__DIR__) . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
        @chmod($dir, 0777); // web server and cron user are different accounts
    }
    // Dumps contain password hashes and personal data: never web-accessible.
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        @chmod($ht, 0666);
    }
    $idx = $dir . '/index.html';
    if (!file_exists($idx)) {
        @file_put_contents($idx, '');
        @chmod($idx, 0666);
    }
    return $dir;
}

function backup_find_binary($name, $constName) {
    if (defined($constName)) {
        return constant($constName);
    }
    $candidates = [
        "/Applications/XAMPP/xamppfiles/bin/$name",
        "/opt/lampp/bin/$name",
        "/usr/local/bin/$name",
        "/opt/homebrew/bin/$name",
        "/usr/bin/$name",
        "C:\\xampp\\mysql\\bin\\$name.exe",
    ];
    foreach ($candidates as $c) {
        if (is_file($c) && is_executable($c)) {
            return $c;
        }
    }
    return null;
}

function backup_cli_available() {
    if (defined('BACKUP_FORCE_PHP') && BACKUP_FORCE_PHP) {
        return false;
    }
    return function_exists('exec')
        && backup_find_binary('mysqldump', 'BACKUP_MYSQLDUMP')
        && backup_find_binary('mysql', 'BACKUP_MYSQL');
}

// Credentials go in a private temp file instead of the command line, where
// other users on the machine could read them from the process list.
function backup_credentials_file() {
    global $host, $username, $password;
    $path = @tempnam(backup_dir(), 'jetms_cnf_');
    if ($path === false || $path === '') {
        return null;
    }
    chmod($path, 0600);
    $content = "[client]\nuser=\"" . addcslashes($username, '"\\') . "\"\n";
    if ($password !== '') {
        $content .= "password=\"" . addcslashes($password, '"\\') . "\"\n";
    }
    $content .= "host=\"" . addcslashes($host, '"\\') . "\"\n";
    file_put_contents($path, $content);
    return $path;
}

function backup_filename_ok($name) {
    return (bool) preg_match('/^jetms_(manual|weekly|pre_activate|pre_restore)_\d{8}_\d{6}(_[a-f0-9]{8})?\.sql$/', $name);
}

// The built-in engine reads times in UTC (like mysqldump); put the session back afterwards.
function backup_reapply_timezone($conn) {
    $conn->query("SET time_zone = '" . (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P') . "'");
}

function backup_file_complete($path) {
    if (!is_file($path) || filesize($path) < 500) {
        return false;
    }
    $tail = (string) @file_get_contents($path, false, null, max(0, filesize($path) - 200));
    return strpos($tail, 'Dump completed') !== false;
}

// =====================================================================
// Built-in PHP engine
// =====================================================================

// Writes a dump to $path. $onlyTables limits it; $insertIgnore/$noCreate are used
// to set the security logs aside during a restore. Returns null on success or an error string.
function backup_php_dump($conn, $path, $onlyTables = null, $insertIgnore = false, $noCreate = false) {
    global $host, $database;
    @set_time_limit(0);
    $fh = @fopen($path, 'wb');
    if (!$fh) {
        return 'Could not write the backup file';
    }

    mysqli_query($conn, "SET time_zone = '+00:00'");

    fwrite($fh, "-- MariaDB dump (JETMS built-in PHP backup engine)\n-- Host: $host    Database: $database\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($fh, "/*!40101 SET NAMES utf8mb4 */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n/*!40014 SET FOREIGN_KEY_CHECKS=0 */;\n/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n");

    $tables = [];
    $res = mysqli_query($conn, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    if (!$res) {
        fclose($fh);
        backup_reapply_timezone($conn);
        return 'Could not read the table list';
    }
    while ($r = mysqli_fetch_row($res)) {
        if ($onlyTables === null || in_array($r[0], $onlyTables, true)) {
            $tables[] = $r[0];
        }
    }
    mysqli_free_result($res);

    $numericTypes = [MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24,
        MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL, MYSQLI_TYPE_YEAR];

    foreach ($tables as $t) {
        $qt = '`' . str_replace('`', '``', $t) . '`';
        fwrite($fh, "--\n-- Table structure for table $qt\n--\n\n");

        if (!$noCreate) {
            $cr = mysqli_query($conn, "SHOW CREATE TABLE $qt");
            $crRow = $cr ? mysqli_fetch_row($cr) : null;
            if (!$crRow) {
                fclose($fh);
                backup_reapply_timezone($conn);
                return "Could not read the structure of $t";
            }
            mysqli_free_result($cr);
            fwrite($fh, "DROP TABLE IF EXISTS $qt;\n" . $crRow[1] . ";\n\n");
        }

        $data = mysqli_query($conn, "SELECT * FROM $qt", MYSQLI_USE_RESULT);
        if (!$data) {
            fclose($fh);
            backup_reapply_timezone($conn);
            return "Could not read the rows of $t";
        }
        $cols = [];
        $isNum = [];
        foreach (mysqli_fetch_fields($data) as $f) {
            $cols[] = '`' . str_replace('`', '``', $f->name) . '`';
            $isNum[] = in_array($f->type, $numericTypes, true);
        }
        $head = 'INSERT ' . ($insertIgnore ? 'IGNORE ' : '') . "INTO $qt (" . implode(',', $cols) . ') VALUES ';

        $batch = [];
        while ($row = mysqli_fetch_row($data)) {
            $vals = [];
            foreach ($row as $i => $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif ($isNum[$i]) {
                    $vals[] = $v;
                } else {
                    $vals[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
                }
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 100) {
                fwrite($fh, $head . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($fh, $head . implode(",\n", $batch) . ";\n");
        }
        mysqli_free_result($data);
        fwrite($fh, "\n");
    }

    fwrite($fh, "/*!40014 SET FOREIGN_KEY_CHECKS=1 */;\n\n-- Dump completed on " . date('Y-m-d H:i:s') . "\n");
    fclose($fh);
    backup_reapply_timezone($conn);
    return null;
}

// Runs every statement in a .sql file. Quote-aware: semicolons or line breaks
// inside text values do not split a statement. Returns null on success or an error string.
function backup_php_import($conn, $path) {
    @set_time_limit(0);
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return 'Could not read the backup file';
    }
    $run = function ($sql) use ($conn) {
        if (trim($sql) === '') {
            return null;
        }
        if (!mysqli_query($conn, $sql)) {
            return mysqli_error($conn) . ' -- near: ' . substr(trim($sql), 0, 80);
        }
        return null;
    };

    $stmt = '';
    $quote = null;
    while (($line = fgets($fh)) !== false) {
        if ($quote === null && $stmt === '') {
            $t = ltrim($line);
            if ($t === '' || substr($t, 0, 2) === '--' || $t[0] === '#') {
                continue; // whole-line comment or blank
            }
        }
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($quote !== null) {
                if ($c === '\\' && $quote !== '`') {
                    $stmt .= $c;
                    if (++$i < $len) {
                        $stmt .= $line[$i];
                    }
                    continue;
                }
                if ($c === $quote) {
                    if ($i + 1 < $len && $line[$i + 1] === $quote) {
                        $stmt .= $c . $c;
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                $stmt .= $c;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $stmt .= $c;
                continue;
            }
            if ($c === ';') {
                $err = $run($stmt);
                $stmt = '';
                if ($err !== null) {
                    fclose($fh);
                    backup_reapply_timezone($conn);
                    return $err;
                }
                continue;
            }
            $stmt .= $c;
        }
    }
    fclose($fh);
    $err = $run($stmt);
    backup_reapply_timezone($conn);
    return $err;
}

// =====================================================================
// Public API
// =====================================================================

// Returns ['ok'=>bool, 'file'=>name, 'size'=>bytes, 'engine'=>cli|php, 'error'=>string]
function create_backup($type) {
    global $conn, $database;
    if (!in_array($type, BACKUP_TYPES, true)) {
        return ['ok' => false, 'error' => 'Invalid backup type'];
    }
    $dir = backup_dir();
    if (!is_writable($dir)) {
        return ['ok' => false, 'error' => 'Backup folder is not writable'];
    }

    // Random suffix: if the folder is ever exposed, file names cannot be guessed.
    $file = 'jetms_' . $type . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
    $path = $dir . '/' . $file;
    $cliError = null;

    if (backup_cli_available()) {
        $bin = backup_find_binary('mysqldump', 'BACKUP_MYSQLDUMP');
        $cnf = backup_credentials_file();
        $errFile = @tempnam($dir, 'jetms_err_');
        if ($cnf && $errFile) {
            $cmd = escapeshellarg($bin) . ' --defaults-extra-file=' . escapeshellarg($cnf)
                . ' --single-transaction --triggers --default-character-set=utf8mb4 '
                . escapeshellarg($database) . ' > ' . escapeshellarg($path) . ' 2> ' . escapeshellarg($errFile);
            exec($cmd, $out, $code);
            $err = trim((string) @file_get_contents($errFile));
            @unlink($cnf);
            @unlink($errFile);
            if ($code === 0 && backup_file_complete($path)) {
                @chmod($path, 0666);
                return ['ok' => true, 'file' => $file, 'size' => filesize($path), 'engine' => 'cli'];
            }
            $cliError = $err !== '' ? $err : 'mysqldump exited with code ' . $code;
            @unlink($path);
        } else {
            if ($cnf) { @unlink($cnf); }
            if ($errFile) { @unlink($errFile); }
        }
        // fall through to the built-in engine
    }

    $err = backup_php_dump($conn, $path);
    if ($err !== null || !backup_file_complete($path)) {
        @unlink($path);
        return ['ok' => false, 'error' => 'Backup failed: ' . ($err !== null ? $err : 'incomplete file')
            . ($cliError ? ' (command-line tool also failed: ' . substr($cliError, 0, 160) . ')' : '')];
    }
    @chmod($path, 0666);
    return ['ok' => true, 'file' => $file, 'size' => filesize($path), 'engine' => 'php'];
}

function prune_weekly_backups($keep = BACKUP_KEEP_WEEKLY) {
    $files = glob(backup_dir() . '/jetms_weekly_*.sql') ?: [];
    rsort($files); // names embed the timestamp, newest first
    $removed = [];
    foreach (array_slice($files, $keep) as $f) {
        if (@unlink($f)) {
            $removed[] = basename($f);
        }
    }
    return $removed;
}

function list_backups() {
    $files = glob(backup_dir() . '/jetms_*.sql') ?: [];
    rsort($files);
    $out = [];
    foreach ($files as $f) {
        $name = basename($f);
        if (!backup_filename_ok($name)) {
            continue;
        }
        preg_match('/^jetms_(manual|weekly|pre_activate|pre_restore)_/', $name, $m);
        $out[] = [
            'file' => $name,
            'type' => $m[1],
            'size' => filesize($f),
            'created_at' => date('Y-m-d H:i:s', filemtime($f))
        ];
    }
    return $out;
}

// Loads a .sql file with whichever engine is available. Returns [bool ok, string message].
function backup_load_file($conn, $sqlPath, $cnf) {
    global $database;
    $cliMsg = '';
    if ($cnf && backup_cli_available()) {
        $mysql = backup_find_binary('mysql', 'BACKUP_MYSQL');
        $out = [];
        exec(escapeshellarg($mysql) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --default-character-set=utf8mb4 ' . escapeshellarg($database)
            . ' < ' . escapeshellarg($sqlPath) . ' 2>&1', $out, $code);
        if ($code === 0) {
            return [true, ''];
        }
        $cliMsg = implode("\n", $out);
    }
    $err = backup_php_import($conn, $sqlPath);
    if ($err === null) {
        return [true, ''];
    }
    return [false, $err . ($cliMsg !== '' ? ' | ' . substr($cliMsg, 0, 120) : '')];
}

// Empties the schema (except the security logs) so the result is exactly the backup.
function backup_drop_all_but_logs($conn) {
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    $res = mysqli_query($conn, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    $names = [];
    while ($r = mysqli_fetch_row($res)) {
        $names[] = $r[0];
    }
    mysqli_free_result($res);
    foreach ($names as $n) {
        if (!in_array($n, BACKUP_LOG_TABLES, true)) {
            mysqli_query($conn, 'DROP TABLE `' . str_replace('`', '', $n) . '`');
        }
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
}

// Puts the database back exactly as it was in the chosen backup. A safety
// backup of the current data is taken first; if the import fails, that safety
// backup is loaded again. Audit/login logs are always preserved.
function restore_backup($conn, $file) {
    global $database;
    if (!backup_filename_ok($file)) {
        return ['ok' => false, 'error' => 'Invalid backup file name'];
    }
    $path = backup_dir() . '/' . $file;
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'Backup file not found'];
    }
    $head = (string) @file_get_contents($path, false, null, 0, 4000);
    if (strpos($head, 'MySQL dump') === false && strpos($head, 'MariaDB dump') === false) {
        return ['ok' => false, 'error' => 'That file does not look like a database backup'];
    }

    $safety = create_backup('pre_restore');
    if (!$safety['ok']) {
        return ['ok' => false, 'error' => 'Restore cancelled - could not take a safety backup first (' . $safety['error'] . ')'];
    }

    $cnf = backup_cli_available() ? backup_credentials_file() : null;
    $logsFile = @tempnam(backup_dir(), 'jetms_logs_');
    if (!$logsFile) {
        if ($cnf) { @unlink($cnf); }
        return ['ok' => false, 'error' => 'Could not create temporary files in the backup folder - restore cancelled', 'safety' => $safety['file']];
    }

    // Keep the current audit/login rows aside so a restore cannot erase its own trail.
    $kept = false;
    if ($cnf) {
        $dump = backup_find_binary('mysqldump', 'BACKUP_MYSQLDUMP');
        exec(escapeshellarg($dump) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --no-create-info --insert-ignore --complete-insert --skip-triggers '
            . escapeshellarg($database) . ' ' . implode(' ', array_map('escapeshellarg', BACKUP_LOG_TABLES))
            . ' > ' . escapeshellarg($logsFile) . ' 2>&1', $o1, $c1);
        $kept = ($c1 === 0);
    }
    if (!$kept) {
        $kept = (backup_php_dump($conn, $logsFile, BACKUP_LOG_TABLES, true, true) === null);
    }
    if (!$kept) {
        if ($cnf) { @unlink($cnf); }
        @unlink($logsFile);
        return ['ok' => false, 'error' => 'Could not preserve the security logs - restore cancelled', 'safety' => $safety['file']];
    }

    backup_drop_all_but_logs($conn);
    list($ok, $msg) = backup_load_file($conn, $path, $cnf);

    $rolledBack = false;
    if (!$ok) {
        backup_drop_all_but_logs($conn);
        list($rolledBack, ) = backup_load_file($conn, backup_dir() . '/' . $safety['file'], $cnf);
    }

    // Put the newer log rows back (INSERT IGNORE: rows already there are skipped).
    backup_load_file($conn, $logsFile, $cnf);

    if ($cnf) { @unlink($cnf); }
    @unlink($logsFile);

    if (!$ok) {
        return ['ok' => false, 'error' => 'Restore failed' . ($rolledBack ? ' - your previous data was put back automatically' : ' - AND the automatic rollback failed, use the pre_restore backup ' . $safety['file']) . '. ' . substr($msg, 0, 300), 'safety' => $safety['file']];
    }
    return ['ok' => true, 'safety' => $safety['file']];
}
