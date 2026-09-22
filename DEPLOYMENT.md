# Deployment Configuration Checklist

Settings to review/change when moving JETMS off this local XAMPP dev machine onto a production server. Current values below were captured from this dev environment (`php.ini` at `/Applications/XAMPP/xamppfiles/etc/php.ini`) on 2026-09-22.

## 🔴 Fix before go-live — timezone mismatch

This dev machine has **two different wrong timezones** that don't even agree with each other, neither of which is the Philippines:

| Source | Current value | Should be |
|---|---|---|
| PHP `date.timezone` (php.ini) | `Europe/Berlin` | `Asia/Manila` |
| MySQL `time_zone` (follows OS) | `SYSTEM` → currently EDT (US Eastern) | `+08:00` (or `Asia/Manila` if the timezone tables are loaded) |

**Why this matters:** `config/attendance.php`, `config/enrollment.php`, and `config/payment.php` all use PHP's `date('Y-m-d')`/`date('Y-m-d H:i:s')` for `enrollment_date`, `payment_date`, `status_date`, and the attendance scan confirmation timestamp — those follow PHP's timezone. Meanwhile `created_at`/`updated_at` columns use MySQL's `DEFAULT CURRENT_TIMESTAMP` — those follow MySQL's timezone. Right now those two clocks disagree by several hours, so a record's "created at" and its own date fields can look inconsistent, and anything logged close to midnight risks landing on the wrong calendar date entirely.

**To fix:**
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
| `session.cookie_httponly` | not set | Set to `1` — stops JavaScript from reading the session cookie (defense against XSS-based session theft) |
| `session.cookie_secure` | `0` | Set to `1` **once the site is served over HTTPS** — before that, leave at `0` or login will silently fail (browsers won't send a "secure" cookie over plain HTTP) |
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

- `config.php` (project root) has hardcoded local dev DB credentials (`root` / no password) — update for the production database before going live.
- `assets/img/students/` and `assets/img/announcements/` are created on-demand by upload code (`mkdir` with `0755`) — make sure the web server user can write to `assets/img/` on the new host, or uploads will fail.
- No `.env` file or secrets separation exists in this project — worth keeping in mind if the deployment target expects one.
