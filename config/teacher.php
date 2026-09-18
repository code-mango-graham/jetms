<?php
include '../config.php';

header('Content-Type: application/json');

function isUsernameTaken($conn, $username) {
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
    $taken = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);
    return $taken;
}

function createTeacherAccount($conn, $teacher_id, $email) {
    if ($email === null || $email === '') {
        return ' Login account was NOT created (teacher has no email on file).';
    }

    if (isUsernameTaken($conn, $email)) {
        return ' Login account was NOT created (username already exists).';
    }

    $passwordHash = password_hash('admin', PASSWORD_BCRYPT);
    $save = mysqli_prepare($conn, "INSERT INTO tbl_teacher_account (teacher_id, username, password) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($save, "iss", $teacher_id, $email, $passwordHash);
    mysqli_stmt_execute($save);
    mysqli_stmt_close($save);

    return " Login account created (username: {$email}, password: admin).";
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (DataTable list)
    // =======================
    case 'load': {
        $query = mysqli_query($conn, "
            SELECT
                t.teacher_id,
                t.first_name,
                t.middle_name,
                t.last_name,
                t.extension_name,
                t.nick_name,
                t.gender,
                t.birthday,
                t.phone_number,
                t.email,
                t.date_hired,
                p.position_title,
                o.office_name
            FROM tbl_teacher t
            LEFT JOIN tbl_position p ON p.position_id = t.position_id
            LEFT JOIN tbl_office o ON o.office_id = t.department_id
            WHERE t.teacher_remarks = 1
            ORDER BY t.teacher_id DESC
        ");

        $data = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $data[] = $row;
        }

        echo json_encode(["data" => $data]);
        break;
    }

    // =======================
    // GET (single record, for edit modal)
    // =======================
    case 'get': {
        $teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;

        $query = mysqli_prepare($conn, "SELECT * FROM tbl_teacher WHERE teacher_id = ? LIMIT 1");
        mysqli_stmt_bind_param($query, "i", $teacher_id);
        mysqli_stmt_execute($query);

        $result = mysqli_stmt_get_result($query);
        $data = mysqli_fetch_assoc($result);

        mysqli_stmt_close($query);

        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Teacher not found"
            ]);
        }
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no teacher_id, otherwise update)
    // =======================
    case 'add': {
        $teacher_id = isset($_POST['teacher_id']) ? trim($_POST['teacher_id']) : '';
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $extension_name = isset($_POST['extension_name']) ? trim($_POST['extension_name']) : '';
        $nick_name = isset($_POST['nick_name']) ? trim($_POST['nick_name']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
        $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $position_id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;
        $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
        $date_hired = isset($_POST['date_hired']) ? trim($_POST['date_hired']) : '';

        if ($first_name === '' || $last_name === '' || $gender === '' || $birthday === '' || $position_id <= 0 || $department_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Please complete the required teacher fields"
            ]);
            break;
        }

        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid gender value"
            ]);
            break;
        }

        if ($email !== '') {
            if (empty($teacher_id)) {
                $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_teacher WHERE email = ? LIMIT 1");
                mysqli_stmt_bind_param($check, "s", $email);
            } else {
                $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_teacher WHERE email = ? AND teacher_id != ? LIMIT 1");
                mysqli_stmt_bind_param($check, "si", $email, $teacher_id);
            }

            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                mysqli_stmt_close($check);
                echo json_encode([
                    "status" => "error",
                    "message" => "Email already exists"
                ]);
                break;
            }

            mysqli_stmt_close($check);
        }

        $emailValue = ($email === '') ? null : $email;
        $dateHiredValue = ($date_hired === '') ? null : $date_hired;
        $middleNameValue = ($middle_name === '') ? null : $middle_name;
        $extensionNameValue = ($extension_name === '') ? null : $extension_name;
        $nickNameValue = ($nick_name === '') ? null : $nick_name;
        $phoneNumberValue = ($phone_number === '') ? null : $phone_number;

        $isNewTeacher = empty($teacher_id);

        if ($isNewTeacher) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_teacher (first_name, middle_name, last_name, extension_name, nick_name, gender, birthday, phone_number, email, position_id, department_id, date_hired) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''))");
            mysqli_stmt_bind_param($save, "sssssssssiis", $first_name, $middleNameValue, $last_name, $extensionNameValue, $nickNameValue, $gender, $birthday, $phoneNumberValue, $email, $position_id, $department_id, $date_hired);
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_teacher SET first_name = ?, middle_name = ?, last_name = ?, extension_name = ?, nick_name = ?, gender = ?, birthday = ?, phone_number = ?, email = NULLIF(?, ''), position_id = ?, department_id = ?, date_hired = NULLIF(?, '') WHERE teacher_id = ?");
            mysqli_stmt_bind_param($save, "sssssssssiisi", $first_name, $middleNameValue, $last_name, $extensionNameValue, $nickNameValue, $gender, $birthday, $phoneNumberValue, $email, $position_id, $department_id, $date_hired, $teacher_id);
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save teacher"
            ]);
            break;
        }

        if ($isNewTeacher) {
            $teacher_id = mysqli_insert_id($conn);
        }

        mysqli_stmt_close($save);

        $accountMessage = '';
        if ($isNewTeacher) {
            $accountMessage = createTeacherAccount($conn, $teacher_id, $emailValue);
        }

        echo json_encode([
            "status" => "success",
            "message" => "Saved successfully." . $accountMessage
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;

        if ($teacher_id <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid teacher ID"
            ]);
            break;
        }

        $update = mysqli_prepare($conn, "UPDATE tbl_teacher SET teacher_remarks = 0 WHERE teacher_id = ?");
        mysqli_stmt_bind_param($update, "i", $teacher_id);
        mysqli_stmt_execute($update);

        if (mysqli_stmt_affected_rows($update) > 0) {
            mysqli_stmt_close($update);
            echo json_encode([
                "status" => "success",
                "message" => "Teacher archived successfully"
            ]);
            break;
        }

        mysqli_stmt_close($update);

        echo json_encode([
            "status" => "error",
            "message" => "Teacher not found or already archived"
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
