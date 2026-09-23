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

function createStudentAccount($conn, $student_id, $lrn) {
    if ($lrn === null || $lrn === '') {
        return ' Login account was NOT created (student has no LRN on file).';
    }

    if (isUsernameTaken($conn, $lrn)) {
        return ' Login account was NOT created (username already exists).';
    }

    $passwordHash = password_hash('admin', PASSWORD_BCRYPT);
    $save = mysqli_prepare($conn, "INSERT INTO tbl_student_account (student_id, username, password) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($save, "iss", $student_id, $lrn, $passwordHash);
    mysqli_stmt_execute($save);
    mysqli_stmt_close($save);

    return " Login account created (username: {$lrn}, password: admin).";
}

function handleStudentPhotoUpload($fileInputName, $existingPhoto = '') {
    if (!isset($_FILES[$fileInputName]) || !is_array($_FILES[$fileInputName])) {
        return $existingPhoto;
    }

    $file = $_FILES[$fileInputName];
    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPhoto;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Photo exceeds PHP upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE => 'Photo exceeds form upload size limit',
            UPLOAD_ERR_PARTIAL => 'Photo upload was incomplete',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload directory',
            UPLOAD_ERR_CANT_WRITE => 'Server cannot write uploaded file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension'
        ];
        $err = (int)$file['error'];
        throw new Exception($uploadErrors[$err] ?? 'Failed to upload student photo');
    }

    if ((int)$file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Student photo must be 10MB or less');
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception('Only JPG, PNG, and WEBP photos are allowed');
    }

    $uploadDir = __DIR__ . '/../assets/img/students';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Unable to create upload directory');
    }

    $filename = 'student_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $targetPath = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Unable to save student photo');
    }

    return $filename;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // LOAD (DataTable list)
    // =======================
    case 'load': {
        $response = array('data' => array());

        try {
            $sql = "SELECT * FROM tbl_student WHERE student_status != 'archived' ORDER BY last_name, first_name";
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
    // GET (single record, for edit modal)
    // =======================
    case 'get': {
        $response = array('success' => false, 'data' => null);

        try {
            $student_id = $_POST['student_id'] ?? 0;

            if (empty($student_id)) {
                throw new Exception('Student ID is required');
            }

            $stmt = $conn->prepare("SELECT * FROM tbl_student WHERE student_id = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param("i", $student_id);

            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response['success'] = true;
                $response['data'] = $result->fetch_assoc();
            } else {
                throw new Exception('Student not found');
            }

            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        break;
    }

    // =======================
    // PROFILE (read-only public profile view)
    // =======================
    case 'profile': {
        $response = array('success' => false, 'data' => null);

        try {
            $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

            if ($student_id <= 0) {
                throw new Exception('Student ID is required');
            }

            $stmt = $conn->prepare("SELECT * FROM tbl_student WHERE student_id = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param('i', $student_id);

            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Student not found');
            }

            $response['success'] = true;
            $response['data'] = $result->fetch_assoc();

            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        break;
    }

    // =======================
    // ADD or UPDATE (insert when no student_id, otherwise update)
    // =======================
    case 'add': {
        $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
        $lrn = isset($_POST['lrn']) ? trim($_POST['lrn']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $extension_name = isset($_POST['extension_name']) ? trim($_POST['extension_name']) : '';
        $middle_initial = isset($_POST['middle_initial']) ? trim($_POST['middle_initial']) : '';
        $birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
        $sex = isset($_POST['sex']) ? trim($_POST['sex']) : '';
        $cp_no = isset($_POST['cp_no']) ? trim($_POST['cp_no']) : '';
        $spoken_language = isset($_POST['spoken_language']) ? trim($_POST['spoken_language']) : '';
        $fb_name = isset($_POST['fb_name']) ? trim($_POST['fb_name']) : '';
        $esc_id_no = isset($_POST['esc_id_no']) ? trim($_POST['esc_id_no']) : '';
        $former_school = isset($_POST['former_school']) ? trim($_POST['former_school']) : '';
        $father_name = isset($_POST['father_name']) ? trim($_POST['father_name']) : '';
        $mother_name = isset($_POST['mother_name']) ? trim($_POST['mother_name']) : '';
        $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
        $contact_fb_name = isset($_POST['contact_fb_name']) ? trim($_POST['contact_fb_name']) : '';
        $street_name = isset($_POST['street_name']) ? trim($_POST['street_name']) : '';
        $barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
        $municipality = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
        $province = isset($_POST['province']) ? trim($_POST['province']) : '';
        $contact_cp_no = isset($_POST['contact_cp_no']) ? trim($_POST['contact_cp_no']) : '';
        $student_status = isset($_POST['student_status']) ? trim($_POST['student_status']) : '';
        $existing_photo = isset($_POST['existing_photo']) ? trim($_POST['existing_photo']) : '';

        if ($birthday === '') {
            $birthday = null;
        }

        if ($sex === '') {
            $sex = null;
        }

        if ($last_name === '' || $first_name === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Last name and first name are required"
            ]);
            break;
        }

        if ($cp_no !== '' && !preg_match('/^[0-9]{11}$/', $cp_no)) {
            echo json_encode([
                "status" => "error",
                "message" => "CP No. must be exactly 11 digits"
            ]);
            break;
        }

        if ($contact_cp_no !== '' && !preg_match('/^[0-9]{11}$/', $contact_cp_no)) {
            echo json_encode([
                "status" => "error",
                "message" => "Contact Number must be exactly 11 digits"
            ]);
            break;
        }

        // Duplicate LRN check (safe for add + edit)
        if (!empty($lrn)) {
            if (empty($student_id)) {
                $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_student WHERE lrn = ? LIMIT 1");
                mysqli_stmt_bind_param($check, "s", $lrn);
            } else {
                $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_student WHERE lrn = ? AND student_id != ? LIMIT 1");
                mysqli_stmt_bind_param($check, "si", $lrn, $student_id);
            }

            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                mysqli_stmt_close($check);
                echo json_encode([
                    "status" => "error",
                    "message" => "LRN already exists"
                ]);
                break;
            }

            mysqli_stmt_close($check);
        }

        try {
            $student_photo = handleStudentPhotoUpload('student_photo', $existing_photo);
        } catch (Exception $e) {
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
            break;
        }

        $isNewStudent = empty($student_id);

        if ($isNewStudent) {
            $save = mysqli_prepare($conn, "INSERT INTO tbl_student (
                lrn, last_name, first_name, student_photo, middle_name, extension_name, middle_initial,
                birthday, sex, cp_no, spoken_language, fb_name, esc_id_no, former_school,
                father_name, mother_name, contact_person, contact_fb_name, street_name,
                barangay, municipality, province, contact_cp_no, student_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$save) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Unable to save student"
                ]);
                break;
            }
            mysqli_stmt_bind_param($save, "ssssssssssssssssssssssss",
                $lrn, $last_name, $first_name, $student_photo, $middle_name, $extension_name, $middle_initial,
                $birthday, $sex, $cp_no, $spoken_language, $fb_name, $esc_id_no, $former_school,
                $father_name, $mother_name, $contact_person, $contact_fb_name, $street_name,
                $barangay, $municipality, $province, $contact_cp_no, $student_status
            );
        } else {
            $save = mysqli_prepare($conn, "UPDATE tbl_student SET
                lrn = ?, last_name = ?, first_name = ?, student_photo = ?, middle_name = ?, extension_name = ?,
                middle_initial = ?, birthday = ?, sex = ?, cp_no = ?, spoken_language = ?,
                fb_name = ?, esc_id_no = ?, former_school = ?, father_name = ?,
                mother_name = ?, contact_person = ?, contact_fb_name = ?, street_name = ?,
                barangay = ?, municipality = ?, province = ?, contact_cp_no = ?,
                student_status = ?
                WHERE student_id = ?");
            if (!$save) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Unable to save student"
                ]);
                break;
            }
            mysqli_stmt_bind_param($save, "ssssssssssssssssssssssssi",
                $lrn, $last_name, $first_name, $student_photo, $middle_name, $extension_name,
                $middle_initial, $birthday, $sex, $cp_no, $spoken_language,
                $fb_name, $esc_id_no, $former_school, $father_name,
                $mother_name, $contact_person, $contact_fb_name, $street_name,
                $barangay, $municipality, $province, $contact_cp_no,
                $student_status, $student_id
            );
        }

        if (!mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save student"
            ]);
            break;
        }

        if ($isNewStudent) {
            $student_id = mysqli_insert_id($conn);
        }

        mysqli_stmt_close($save);

        $accountMessage = '';
        if ($isNewStudent) {
            $accountMessage = createStudentAccount($conn, $student_id, $lrn);
        }

        echo json_encode([
            "status" => "success",
            "message" => "Student saved successfully." . $accountMessage,
            "student_id" => $student_id
        ]);
        break;
    }

    // =======================
    // DELETE (archive)
    // =======================
    case 'delete': {
        $response = array('success' => false, 'message' => '');

        try {
            $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

            if ($student_id <= 0) {
                throw new Exception('Invalid student ID');
            }

            $stmt = $conn->prepare("UPDATE tbl_student SET student_status = 'archived' WHERE student_id = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param("i", $student_id);

            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Student archived successfully';
            } else {
                throw new Exception('Student not found');
            }

            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        break;
    }

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action"
        ]);
        break;
}
