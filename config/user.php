<?php
include '../config.php';

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

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
                throw new Exception('Query failed: ' . $conn->error);
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
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param("i", $account_id);

            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
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

        if (strlen($password) < 4) {
            echo json_encode([
                "status" => "error",
                "message" => "Password must be at least 4 characters"
            ]);
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
