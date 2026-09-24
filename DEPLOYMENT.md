# Deployment Configuration Checklist

Settings to review/change when moving JETMS off this local XAMPP dev machine onto a production server. Current values below were captured from this dev environment (`php.ini` at `/Applications/XAMPP/xamppfiles/etc/php.ini`) on 2026-09-22.

## Shared-hosting checklist (do these in order)

1. **Create the database** in the host's control panel (cPanel: *MySQL Databases*), a user with a long random password, and give that user *all privileges* on it. Import `database/schema.txt` (phpMyAdmin > Import, or paste it in the SQL tab).
2. **Upload the whole project** to `public_html` (or a sub-folder). Do not upload `backups/*.sql` or `config.local.php` from your dev machine.
3. **Create `config.local.php`** next to `config.php`: copy `config.local.sample.php`, then fill in the database host / user / password / name from step 1. This is the only file that holds secrets. It is blocked from the web by `.htaccess` and is git-ignored. `config.php` itself no longer contains any credentials.
4. **Check that `.htaccess` is honoured** (the host must allow `AllowOverride`). Open these in a browser, each must show **403 Forbidden** (or 404), never file contents:
   - `https://yoursite/config.local.php`
   - `https://yoursite/config/security.php`
   - `https://yoursite/backups/jetms_weekly_x.sql`
   - `https://yoursite/database/schema.txt`
   - `https://yoursite/cron/weekly_backup.php`
   If any of them downloads or shows content, the host ignores `.htaccess`: ask the host to enable it, or move `backups/` outside the web root with `BACKUP_DIR` and delete `database/`.
5. **Turn on HTTPS** (free Let's Encrypt in cPanel: *SSL/TLS Status* / *AutoSSL*). Once it works, the app automatically marks the session cookie *Secure*. Optionally force HTTPS by uncommenting the redirect lines in the root `.htaccess`.
6. **Make these folders writable** by PHP (permissions 755, or 775 if uploads fail): `assets/img/` (photos) and `backups/`. Better: create a folder *above* `public_html` and set `BACKUP_DIR` to it in `config.local.php`.
7. **First login**: username `admin`, password `admin`. The system now **forces you to choose a new password immediately** (8+ characters, letters and numbers) and will not let you do anything else until you do. Every teacher/student account created with the default password is forced the same way at its first login.
8. **Set the gate kiosk's allowed IP** if you use the attendance kiosk: `define('KIOSK_ALLOWED_IPS', ['your.school.public.ip']);` in `config.local.php`. Without it, anyone on the internet who knows a student's LRN can log an attendance scan (scans are rate-limited to 30/minute per IP either way).
9. **Backups**: run *Settings > Backup & Restore > Back up now* once and confirm it says "created". Shared hosts usually disable `exec()`, so JETMS falls back automatically to a built-in PHP engine (slower on very large databases but needs no special access). To force one engine set `BACKUP_FORCE_PHP` in `config.local.php`. Then add the weekly cron job (cPanel: *Cron Jobs*), see the backups section below.
10. **Timezone**: the app now sets `Asia/Manila` for both PHP and MySQL itself (`APP_TIMEZONE`), so the php.ini/my.cnf change below is no longer required. Note this changes how dates are stored compared with your old dev database: if you move existing data to the host, check that recent `created_at` values still look right.
11. **Delete or protect anything you do not need online**: `docs/`, `PROCESS_FLOWS.txt`, `USER_MANUAL.md`, `DEPLOYMENT.md` describe the internals; remove them from the server if you do not want visitors reading them (`.htaccess` already blocks `.md`/`.txt`/`.sql`/`.log`).

### What the code now protects against (built in, nothing to configure)

| Threat | Protection |
|---|---|
| Anonymous access to data endpoints | Every endpoint requires a login; admin-only actions require the admin role (HTTP 401/403, and blocked attempts are audit-logged as `denied`). |
| Password guessing | Login throttling per account and per IP, and constant-time checks so unknown usernames look the same as wrong passwords. |
| Weak / default passwords | Password policy (8+ chars, letters + numbers, common passwords refused) and a forced change of the default `admin` password. |
| Cross-site request forgery | Every state-changing request must carry the `X-Requested-With` header and a same-site `Origin`. Any script or tool that POSTs to `config/*.php` must send that header. |
| Script injection (XSS) | Angle brackets and double quotes are stripped from everything typed in (except passwords), and the screens escape text again when drawing it. |
| Session theft / fixation | HttpOnly + SameSite cookies, `Secure` on HTTPS, new session id at login, idle timeout (2 hours, `SEC_IDLE_TIMEOUT`). |
| Malicious uploads | Photos must really decode as images, and are re-named by the server. |
| Information leaks | Database errors are written to the server error log, and users only see a generic message. Set `APP_DEBUG` only while developing. |
| Tampering trail | Login records + audit log (Settings > Security Logs). |

## Timezone (now handled by the app; php.ini/my.cnf change is optional)

This dev machine has **two different wrong timezones** that don't even agree with each other, neither of which is the Philippines:

| Source | Current value | Should be |
|---|---|---|
| PHP `date.timezone` (php.ini) | `Europe/Berlin` | `Asia/Manila` |
| MySQL `time_zone` (follows OS) | `SYSTEM` → currently EDT (US Eastern) | `+08:00` (or `Asia/Manila` if the timezone tables are loaded) |

**Why this matters:** `config/attendance.php`, `config/enrollment.php`, and `config/payment.php` all use PHP's `date('Y-m-d')`/`date('Y-m-d H:i:s')` for `enrollment_date`, `payment_date`, `status_date`, and the attendance scan confirmation timestamp — those follow PHP's timezone. Meanwhile `created_at`/`updated_at` columns use MySQL's `DEFAULT CURRENT_TIMESTAMP` — those follow MySQL's timezone. Right now those two clocks disagree by several hours, so a record's "created at" and its own date fields can look inconsistent, and anything logged close to midnight risks landing on the wrong calendar date entirely.

**Update:** `config.php` now calls `date_default_timezone_set()` and `SET time_zone` for every request (setting `APP_TIMEZONE`, default `Asia/Manila`), so PHP and MySQL agree no matter how the host is configured. The steps below are still good practice on your own server, but are no longer required.

**Optional server-level fix:**
1. In `php.ini`: `date.timezone = "Asia/Manila"`
2. In MySQL (`my.cnf` / `my.ini`, `[mysqld]` section): `default-time-zone = "+08:00"`
3. Restart Apache and MySQL after changing either.

## Upload limits

Announcement photos had their old 10MB app-level cap removed (`config/announcement.php`) — student photos still have one (`config/student.php`, also 10MB, left as-is for now). Both are bounded above by these php.ini values regardless of app code:

| Setting | Current (this dev machine) | Recommendation |
|---|---|---|
| `upload_max_filesize` | `40M` | Keep ≥ whatever the largest expected photo is; 8–20M is plenty for phone photos |
| `post_max_size` | `40M` | Must be ≥ `upload_max_filesize` |
| `memory_limit` | `512M` | Fine to keep, or lower to `256M` — this app doesn't need much |

If the production host's PHP defaults are lower (many shared hosts default to 2M/8M), uploads will fail silently or with a generic error until these are raised.

## Session security

| Setting | Current | Recommendation |
|---|---|---|
| `session.cookie_httponly` | set by the app | The app already sets HttpOnly, SameSite=Lax and (on HTTPS) Secure on its own session cookie (`config/session_boot.php`); no php.ini change needed |
| `session.cookie_secure` | automatic | Turns on by itself when the page is served over HTTPS |
| `session.gc_maxlifetime` | `1440` (24 min) | Consider raising to `3600`+ if admins get logged out too quickly during long data-entry sessions |

## Error display

| Setting | Current | Recommendation |
|---|---|---|
| `display_errors` | `Off` | Keep `Off` in production — already correctly set on this dev machine, don't accidentally flip it on |
| `log_errors` | (verify) | Should be `On`, with `error_log` pointing somewhere you'll actually check |

## Already fine, just confirm on the new server

- PHP version: built/tested against **8.0.28**. Anything 8.0+ should work; nothing version-specific beyond that was used.
- `mysqli` extension: required, must be enabled.
- `file_uploads`: must be `On`.
- `short_open_tag`: `Off` is fine — the codebase uses full `<?php` tags throughout.

## Non-php.ini things to check while you're at it

- Database credentials live in `config.local.php` (see the checklist at the top). `config.php` only holds harmless defaults for local XAMPP.
- `assets/img/students/` and `assets/img/announcements/` are created on-demand by upload code (`mkdir` with `0755`) — make sure the web server user can write to `assets/img/` on the new host, or uploads will fail.
- `config.local.php` is the secrets file; keep it out of git and out of any zip you share.

## Backups, restore and the weekly scheduled task

JETMS backs up the database into the `backups/` folder, using `mysqldump` when the server has it and a built-in PHP engine when it does not (both produce ordinary `.sql` files that either engine can restore). Backups are made:
weekly (cron), on demand (Settings > Backup & Restore, password required), automatically
before every school-year activation, and automatically before every restore.

**1. Install the weekly job (cron) — must be done once on every server.** It is *not* installed
by the app, because a web page cannot schedule itself. Run `crontab -e` as the user who owns
the site files and add (adjust both paths):

```
0 2 * * 0 /Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/jetms/cron/weekly_backup.php >> /Applications/XAMPP/xamppfiles/htdocs/jetms/backups/cron.log 2>&1
```

That is every Sunday at 2:00 AM; the newest 12 weekly backups (about 3 months) are kept, older
weekly ones are deleted automatically. Check it worked the next Monday: `backups/cron.log` and
Settings > Security Logs (a `backup` entry by "Scheduled task"). If the machine is off at 2 AM
that week's backup is simply skipped. On macOS, cron may also need Full Disk Access
(System Settings > Privacy & Security). Test it by hand any time with
`php cron/weekly_backup.php`. (Windows: use Task Scheduler to run the same php command weekly.)

**2. Server requirements**
| Need | Why |
|---|---|
| *Optional:* `mysqldump` and `mysql` command-line tools plus PHP `exec()` | Fast path, auto-detected in the usual XAMPP/Linux/Homebrew locations (or set `BACKUP_MYSQLDUMP` / `BACKUP_MYSQL` in `config.local.php`). If missing or blocked (typical on shared hosting) the built-in PHP engine is used automatically; `BACKUP_FORCE_PHP` forces it. |
| PHP execution time | The built-in engine reads every row through PHP; on a large database ask the host to allow a longer `max_execution_time` (the code lifts the limit where the host permits). |
| `backups/` writable by BOTH the web server user and the cron user | It is created with mode 0777 so they can share it (Apache runs as a different account than your cron job). Tighten with a shared group if you prefer. |
| Apache honouring `.htaccess` (`AllowOverride All`) for `backups/` and `cron/` | Keeps the dump files from being downloaded. **After deploying, open `https://yoursite/backups/anything.sql` — it must show 403.** If it doesn't, move `backups/` outside the web root and set `BACKUP_DIR` in `config.local.php`. |

Dumps contain password hashes and personal data: keep them off public storage, and copy them
off the server (Settings > Backup & Restore has a download button) — a backup on the same disk
does not protect you from losing the disk.

**3. Known limits**
- A restore returns the database to *exactly* the chosen moment: everything entered afterwards is
  lost (a `pre_restore` safety backup of the current data is taken first, and reloaded
  automatically if the restore itself fails). Do it outside school hours and log in again afterwards.
- The audit and login logs are preserved through a restore on purpose.
- Backups do not include uploaded photos (`assets/img/...`) — back those folders up separately.
- Backup files carry a random suffix in their name, so the URL cannot be guessed even if the folder were exposed.
- The built-in PHP engine does not save database triggers or stored routines (this database has none).

## Security logging

`tbl_login_log` records every successful login, failed login (with the reason kept internal) and
logout, with IP address and browser. `tbl_audit_log` records every create / edit / archive / delete
across the system with who, when, from which IP, and the before/after values (never passwords).
Both are viewable by Admin only under Settings > Security Logs. Nothing prunes them automatically.
Note that PHP's timezone mismatch documented at the top of this file also applies to these
timestamps.

Admin-only endpoints now reject anonymous and non-admin callers (HTTP 401/403), and each blocked
non-admin attempt is written to the audit log as `denied`. Before this, several endpoints (students,
teachers, school years, accounts...) had no login check at all. The public gate kiosk keeps only
`scan` and today's-scan `load`. Backup, restore and school-year activation each require the admin
to re-enter their password; 5 wrong passwords in 15 minutes locks these confirmations for 15 minutes.
