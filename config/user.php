<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';
include 'security.php';
// update_my_photo does its own "any logged-in user" check; account management is admin-only.
if (!isset($_POST['action']) || $_POST['action'] !== 'update_my_photo') {
    require_admin();
}

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // UPDATE_MY_PHOTO (self-service — any logged-in role updates their own avatar)
    // =======================
    case 'update_my_photo': {
        if (!isset($_SESSION['auth'])) {
            echo json_encode(["status" => "error", "message" => "You must be logged in"]);
            break;
        }

        if (!isset($_FILES['photo']) || (int) $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(["status" => "error", "message" => "Please choose a photo"]);
            break;
        }

        $file = $_FILES['photo'];
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["status" => "error", "message" => "Failed to upload photo"]);
            break;
        }

        $mime = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime]) || !is_real_image($file['tmp_name'])) {
            echo json_encode(["status" => "error", "message" => "Only JPG, PNG, and WEBP photos are allowed"]);
            break;
        }

        $role = $_SESSION['auth']['role'];
        $id = $_SESSION['auth']['ref_id'];

        if ($role === 'admin') {
            $table = 'tbl_admin';
            $idCol = 'admin_id';
            $photoColumn = 'photo';
            $uploadDir = __DIR__ . '/../assets/img/admins';
        } elseif ($role === 'student') {
            $table = 'tbl_student';
            $idCol = 'student_id';
            $photoColumn = 'student_photo';
            $uploadDir = __DIR__ . '/../assets/img/students';
        } else {
            // tbl_teacher has no photo column yet — add one before enabling this for teachers.
            echo json_encode(["status" => "error", "message" => "Profile photos aren't available for teachers yet"]);
            break;
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            echo json_encode(["status" => "error", "message" => "Unable to create upload directory"]);
            break;
        }

        $filename = $role . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
            echo json_encode(["status" => "error", "message" => "Unable to save photo"]);
            break;
        }

        $stmt = mysqli_prepare($conn, "UPDATE $table SET $photoColumn = ? WHERE $idCol = ?");
        mysqli_stmt_bind_param($stmt, "si", $filename, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        audit_log($conn, 'update', $table, $id, 'Profile photo changed (' . $role . ')', null, null, ['photo' => $filename]);

        echo json_encode(["status" => "success", "message" => "Profile photo updated", "photo" => $filename, "role" => $role]);
        break;
    }

    // =======================
    // LOAD (DataTable list, unioned across all account tables)
    // =======================
    case 'load': {
        $response = array('data' => array());

        try {
            $sql = "
                SELECT 'admin' AS role, admin_id AS account_id, NULL AS linked_id, username,
                       TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, extension_name)) AS full_name,
                       created_at
                FROM tbl_admin WHERE admin_remarks = 1
                UNION ALL
                SELECT 'teacher' AS role, ta.teacher_account_id AS account_id, ta.teacher_id AS linked_id, ta.username,
                       TRIM(CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name, t.extension_name)) AS full_name,
                       ta.created_at
                FROM tbl_teacher_account ta
                INNER JOIN tbl_teacher t ON t.teacher_id = ta.teacher_id
                WHERE ta.account_remarks = 1
                UNION ALL
                SELECT 'student' AS role, sa.student_account_id AS account_id, sa.student_id AS linked_id, sa.username,
                       TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.extension_name)) AS full_name,
                       sa.created_at
                FROM tbl_student_account sa
                INNER JOIN tbl_student s ON s.student_id = sa.student_id
                WHERE sa.account_remarks = 1
                ORDER BY role, full_name
            ";

            $result = $conn->query($sql);

            if (!$result) {
                throw new Exception(db_fail('Query: ' . $conn->error));
            }

            while ($row = $result->fetch_assoc()) {
                $response['data'][] = $row;
            }
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }

        echo json_encode($response);
        break;
    }

    // =======================
    // ADD (create a login account)
    // =======================
    case 'add': {
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (!in_array($role, ['admin', 'teacher', 'student'], true) || $username === '' || $password === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Role, username, and password are required"
            ]);
            break;
        }

        $check = mysqli_prepare($conn, "
            SELECT 1 FROM (
                SELECT username FROM tbl_admin WHERE admin_remarks = 1
                UNION ALL SELECT username FROM tbl_teacher_account WHERE account_remarks = 1
                UNION ALL SELECT username FROM tbl_student_account WHERE account_remarks = 1
            ) AS accounts WHERE username = ? LIMIT 1
        ");
        mysqli_stmt_bind_param($check, "s", $username);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            echo json_encode([
                "status" => "error",
                "message" => "Username already exists"
            ]);
            break;
        }
        mysqli_stmt_close($check);

        $policyError = password_policy_error($password, $username);
        if ($policyError !== null) {
            echo json_encode(["status" => "error", "message" => $policyError]);
            break;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        if ($role === 'admin') {
            $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
            $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
            $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
            $extension_name = isset($_POST['extension_name']) ? trim($_POST['extension_name']) : '';

            if ($first_name === '' || $last_name === '') {
                echo json_encode([
                    "status" => "error",
                    "message" => "First name and last name are required"
                ]);
                break;
            }

            $save = mysqli_prepare($conn, "INSERT INTO tbl_admin (username, password, first_name, middle_name, last_name, extension_name) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($save, "ssssss", $username, $passwordHash, $first_name, $middle_name, $last_name, $extension_name);
        } else {
            $linked_id = isset($_POST['linked_id']) ? (int)$_POST['linked_id'] : 0;

            if ($linked_id <= 0) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Please select a " . $role
                ]);
                break;
            }

            $table = $role === 'teacher' ? 'tbl_teacher_account' : 'tbl_student_account';
            $idCol = $role === 'teacher' ? 'teacher_account_id' : 'student_account_id';
            $linkCol = $role === 'teacher' ? 'teacher_id' : 'student_id';

            $existing = mysqli_prepare($conn, "SELECT $idCol, account_remarks FROM $table WHERE $linkCol = ? LIMIT 1");
            mysqli_stmt_bind_param($existing, "i", $linked_id);
            mysqli_stmt_execute($existing);
            $existingRow = mysqli_stmt_get_result($existing)->fetch_assoc();
            mysqli_stmt_close($existing);

            if ($existingRow && (int)$existingRow['account_remarks'] === 1) {
                echo json_encode([
                    "status" => "error",
                    "message" => ucfirst($role) . " already has an account"
                ]);
                break;
            }

            if ($existingRow) {
                // A removed account already exists for this person; reactivate it instead of
                // inserting a duplicate row (linked_id is UNIQUE per account table).
                $save = mysqli_prepare($conn, "UPDATE $table SET username = ?, password = ?, account_remarks = 1 WHERE $idCol = ?");
                mysqli_stmt_bind_param($save, "ssi", $username, $passwordHash, $existingRow[$idCol]);
            } else {
                $save = mysqli_prepare($conn, "INSERT INTO $table ($linkCol, username, password) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($save, "iss", $linked_id, $username, $passwordHash);
            }
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create account"
            ]);
            break;
        }

        mysqli_stmt_close($save);

        $auditEntity = $role === 'admin' ? 'tbl_admin' : $table;
        $auditId = (isset($existingRow) && $existingRow) ? (int) $existingRow[$idCol] : mysqli_insert_id($conn);
        audit_log($conn, 'create', $auditEntity, $auditId, 'Login account created: ' . $role . ' "' . $username . '"' . ((isset($existingRow) && $existingRow) ? ' (re-activated existing account)' : ''));

        echo json_encode([
            "status" => "success",
            "message" => "Account created successfully"
        ]);
        break;
    }

    // =======================
    // DELETE (archive an account)
    // =======================
    case 'delete': {
        $response = array('success' => false, 'message' => '');

        try {
            $role = isset($_POST['role']) ? trim($_POST['role']) : '';
            $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;

            if (!in_array($role, ['admin', 'teacher', 'student'], true) || $account_id <= 0) {
                throw new Exception('Invalid request');
            }

            if ($role === 'admin') {
                $sql = "UPDATE tbl_admin SET admin_remarks = 0 WHERE admin_id = ?";
            } elseif ($role === 'teacher') {
                $sql = "UPDATE tbl_teacher_account SET account_remarks = 0 WHERE teacher_account_id = ?";
            } else {
                $sql = "UPDATE tbl_student_account SET account_remarks = 0 WHERE student_account_id = ?";
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception(db_fail('Prepare: ' . $conn->error));
            }

            $stmt->bind_param("i", $account_id);

            if (!$stmt->execute()) {
                throw new Exception(db_fail('Execute: ' . $stmt->error));
            }

            if ($stmt->affected_rows > 0) {
                audit_log($conn, 'archive', $role === 'admin' ? 'tbl_admin' : ($role === 'teacher' ? 'tbl_teacher_account' : 'tbl_student_account'), $account_id, 'Login account removed (' . $role . ' account #' . $account_id . ')');
                $response['success'] = true;
                $response['message'] = 'Account removed successfully';
            } else {
                throw new Exception('Account not found');
            }

            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        break;
    }

    // =======================
    // RESET PASSWORD (admin-triggered)
    // =======================
    case 'reset_password': {
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $account_id = isset($_POST['account_id']) ? (int) $_POST['account_id'] : 0;
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (!in_array($role, ['admin', 'teacher', 'student'], true) || $account_id <= 0 || $password === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Role, account, and new password are required"
            ]);
            break;
        }

        $policyError = password_policy_error($password);
        if ($policyError !== null) {
            echo json_encode(["status" => "error", "message" => $policyError]);
            break;
        }

        if ($role === 'admin') {
            $sql = "UPDATE tbl_admin SET password = ? WHERE admin_id = ?";
        } elseif ($role === 'teacher') {
            $sql = "UPDATE tbl_teacher_account SET password = ? WHERE teacher_account_id = ?";
        } else {
            $sql = "UPDATE tbl_student_account SET password = ? WHERE student_account_id = ?";
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $passwordHash, $account_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            audit_log($conn, 'password_reset', $role === 'admin' ? 'tbl_admin' : ($role === 'teacher' ? 'tbl_teacher_account' : 'tbl_student_account'), $account_id, 'Password reset by admin for ' . $role . ' account #' . $account_id);
            echo json_encode([
                "status" => "success",
                "message" => "Password reset successfully"
            ]);
            break;
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "error",
            "message" => "Account not found"
        ]);
        break;
    }

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action"
        ]);
        break;
}
