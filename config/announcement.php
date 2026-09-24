<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
if (isset($_POST['action']) && in_array($_POST['action'], ['load', 'get', 'unread_count', 'mark_viewed'], true)) {
    require_login();
}

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

// Saves every file under $_FILES[$fileInputName] (a multi-file input, name="x[]")
// and returns the list of stored filenames. Throws on the first invalid file.
function handleAnnouncementPhotosUpload($fileInputName) {
    if (!isset($_FILES[$fileInputName]) || !is_array($_FILES[$fileInputName]['name'])) {
        return [];
    }

    $files = $_FILES[$fileInputName];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $uploadDir = __DIR__ . '/../assets/img/announcements';

    $saved = [];

    for ($i = 0; $i < count($files['name']); $i++) {
        $error = (int) $files['error'][$i];

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception('Failed to upload one of the photos');
        }

        $tmpName = $files['tmp_name'][$i];
        $mime = mime_content_type($tmpName);

        if (!isset($allowed[$mime]) || !is_real_image($tmpName)) {
            throw new Exception('Only JPG, PNG, and WEBP photos are allowed');
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new Exception('Unable to create upload directory');
        }

        $filename = 'announcement_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new Exception('Unable to save one of the photos');
        }

        $saved[] = $filename;
    }

    return $saved;
}

function attachPhotos($conn, &$announcements) {
    if (empty($announcements)) {
        return;
    }

    $ids = array_map(function ($a) { return (int) $a['announcement_id']; }, $announcements);
    $photosByAnnouncement = [];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = mysqli_prepare($conn, "SELECT photo_id, announcement_id, photo FROM tbl_announcement_photo WHERE announcement_id IN ($placeholders) ORDER BY photo_id ASC");
    mysqli_stmt_bind_param($stmt, $types, ...$ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $photosByAnnouncement[$row['announcement_id']][] = $row;
    }
    mysqli_stmt_close($stmt);

    foreach ($announcements as &$a) {
        $a['photos'] = $photosByAnnouncement[$a['announcement_id']] ?? [];
    }
}

switch ($action) {

    // =======================
    // LOAD (feed, filtered by the viewer's role)
    // =======================
    case 'load': {
        $role = isset($_SESSION['auth']['role']) ? $_SESSION['auth']['role'] : '';

        $selectBase = "SELECT a.*, adm.photo AS poster_photo FROM tbl_announcement a LEFT JOIN tbl_admin adm ON adm.admin_id = a.admin_id";

        if ($role === 'admin') {
            $sql = "$selectBase WHERE a.announcement_remarks = 1 ORDER BY a.created_at DESC";
        } elseif ($role === 'teacher') {
            $sql = "$selectBase WHERE a.announcement_remarks = 1 AND a.audience IN ('all','teachers') ORDER BY a.created_at DESC";
        } elseif ($role === 'student') {
            $sql = "$selectBase WHERE a.announcement_remarks = 1 AND a.audience IN ('all','students') ORDER BY a.created_at DESC";
        } else {
            $sql = "$selectBase WHERE a.announcement_remarks = 1 AND a.audience = 'all' ORDER BY a.created_at DESC";
        }

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);

        attachPhotos($conn, $data);

        echo json_encode(["data" => $data]);
        break;
    }

    // =======================
    // UNREAD_COUNT (announcements posted since this user last viewed the feed)
    // =======================
    case 'unread_count': {
        if (!isset($_SESSION['auth'])) {
            echo json_encode(["count" => 0]);
            break;
        }

        $role = $_SESSION['auth']['role'];
        $userId = $_SESSION['auth']['id'];

        $viewStmt = mysqli_prepare($conn, "SELECT last_viewed_at FROM tbl_announcement_view WHERE role = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($viewStmt, "si", $role, $userId);
        mysqli_stmt_execute($viewStmt);
        $viewRow = mysqli_fetch_assoc(mysqli_stmt_get_result($viewStmt));
        mysqli_stmt_close($viewStmt);

        // Never viewed before — treat everything currently visible to them as unread
        $lastViewed = $viewRow ? $viewRow['last_viewed_at'] : '1970-01-01 00:00:00';

        if ($role === 'admin') {
            $audienceClause = "1=1";
        } elseif ($role === 'teacher') {
            $audienceClause = "audience IN ('all','teachers')";
        } elseif ($role === 'student') {
            $audienceClause = "audience IN ('all','students')";
        } else {
            $audienceClause = "audience = 'all'";
        }

        $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM tbl_announcement WHERE announcement_remarks = 1 AND $audienceClause AND created_at > ?");
        mysqli_stmt_bind_param($countStmt, "s", $lastViewed);
        mysqli_stmt_execute($countStmt);
        $countRow = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt));
        mysqli_stmt_close($countStmt);

        echo json_encode(["count" => (int) $countRow['cnt']]);
        break;
    }

    // =======================
    // MARK_VIEWED (call when the user actually opens the feed)
    // =======================
    case 'mark_viewed': {
        if (!isset($_SESSION['auth'])) {
            echo json_encode(["status" => "error", "message" => "Not logged in"]);
            break;
        }

        $role = $_SESSION['auth']['role'];
        $userId = $_SESSION['auth']['id'];

        $stmt = mysqli_prepare($conn, "
            INSERT INTO tbl_announcement_view (role, user_id, last_viewed_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_viewed_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, "si", $role, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(["status" => "success"]);
        break;
    }

    // =======================
    // GET (single record, for edit modal)
    // =======================
    case 'get': {
        $id = isset($_POST['announcement_id']) ? (int) $_POST['announcement_id'] : 0;

        $stmt = mysqli_prepare($conn, "SELECT * FROM tbl_announcement WHERE announcement_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$data) {
            echo json_encode(["status" => "error", "message" => "Announcement not found"]);
            break;
        }

        $list = [$data];
        attachPhotos($conn, $list);

        echo json_encode(["status" => "success", "data" => $list[0]]);
        break;
    }

    // =======================
    // ADD or UPDATE (admin only)
    // =======================
    case 'add': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Only admins can post announcements"]);
            break;
        }

        $announcement_id = isset($_POST['announcement_id']) ? trim($_POST['announcement_id']) : '';
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $audience = isset($_POST['audience']) ? trim($_POST['audience']) : 'all';

        if ($title === '' || $content === '') {
            echo json_encode(["status" => "error", "message" => "Title and content are required"]);
            break;
        }

        if (!in_array($audience, ['all', 'teachers', 'students'], true)) {
            $audience = 'all';
        }

        $auditWasNew = empty($announcement_id);
        $auditOld = $auditWasNew ? null : audit_snapshot($conn, 'tbl_announcement', 'announcement_id', $announcement_id);

        try {
            $newPhotos = handleAnnouncementPhotosUpload('photos');
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            break;
        }

        $adminId = $_SESSION['auth']['id'];
        $adminName = $_SESSION['auth']['name'];

        if (empty($announcement_id)) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_announcement (admin_id, admin_name, title, content, audience) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($save, "issss", $adminId, $adminName, $title, $content, $audience);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_announcement SET title = ?, content = ?, audience = ? WHERE announcement_id = ?");
            mysqli_stmt_bind_param($save, "sssi", $title, $content, $audience, $announcement_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode(["status" => "error", "message" => "Failed to save announcement"]);
            break;
        }

        $announcement_id = empty($announcement_id) ? mysqli_insert_id($conn) : (int) $announcement_id;
        mysqli_stmt_close($save);

        if (!empty($newPhotos)) {
            $photoStmt = mysqli_prepare($conn, "INSERT INTO tbl_announcement_photo (announcement_id, photo) VALUES (?, ?)");
            foreach ($newPhotos as $filename) {
                mysqli_stmt_bind_param($photoStmt, "is", $announcement_id, $filename);
                mysqli_stmt_execute($photoStmt);
            }
            mysqli_stmt_close($photoStmt);
        }

        $auditNew = audit_snapshot($conn, 'tbl_announcement', 'announcement_id', $announcement_id);
        audit_log($conn, $auditWasNew ? 'create' : 'update', 'tbl_announcement', $announcement_id, 'Announcement: ' . ($auditNew['title'] ?? ''), $auditOld, $auditNew, !empty($newPhotos) ? ['photos_added' => count($newPhotos)] : null);

        echo json_encode(["status" => "success", "message" => "Announcement posted successfully"]);
        break;
    }

    // =======================
    // DELETE_PHOTO (remove a single photo from an existing post, admin only)
    // =======================
    case 'delete_photo': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Only admins can edit announcements"]);
            break;
        }

        $photo_id = isset($_POST['photo_id']) ? (int) $_POST['photo_id'] : 0;

        $auditOld = audit_snapshot($conn, 'tbl_announcement_photo', 'photo_id', $photo_id);
        $stmt = mysqli_prepare($conn, "DELETE FROM tbl_announcement_photo WHERE photo_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $photo_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'delete', 'tbl_announcement_photo', $photo_id, 'Announcement photo removed (announcement #' . ($auditOld['announcement_id'] ?? '') . ')', $auditOld);
            echo json_encode(["status" => "success", "message" => "Photo removed"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Photo not found"]);
        break;
    }

    // =======================
    // DELETE (archive, admin only)
    // =======================
    case 'delete': {
        if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Only admins can remove announcements"]);
            break;
        }

        $id = isset($_POST['announcement_id']) ? (int) $_POST['announcement_id'] : 0;

        $auditOld = audit_snapshot($conn, 'tbl_announcement', 'announcement_id', $id);
        $stmt = mysqli_prepare($conn, "UPDATE tbl_announcement SET announcement_remarks = 0 WHERE announcement_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'archive', 'tbl_announcement', $id, 'Announcement removed: ' . ($auditOld['title'] ?? ''), $auditOld);
            echo json_encode(["status" => "success", "message" => "Announcement removed"]);
            break;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(["status" => "error", "message" => "Announcement not found"]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
