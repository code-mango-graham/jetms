<?php
include '../config.php';

header('Content-Type: application/json');

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

// =======================
// VALIDATION
// =======================

if (empty($student_id) || $last_name === '' || $first_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Student ID, last name and first name are required"
    ]);
    exit;
}


// =======================
// DUPLICATE LRN CHECK
// =======================

if (!empty($lrn)) {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_student WHERE lrn = ? AND student_id != ? LIMIT 1");
        mysqli_stmt_bind_param($check, "si", $lrn, $student_id);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) > 0) {
        mysqli_stmt_close($check);
        echo json_encode([
            "status" => "error",
            "message" => "LRN already exists"
        ]);
        exit;
    }

    mysqli_stmt_close($check);
}


// =======================
// UPDATE
// =======================

try {
    $student_photo = handleStudentPhotoUpload('student_photo', $existing_photo);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}

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
    exit;
}
mysqli_stmt_bind_param($save, "ssssssssssssssssssssssssi",
    $lrn, $last_name, $first_name, $student_photo, $middle_name, $extension_name,
    $middle_initial, $birthday, $sex, $cp_no, $spoken_language,
    $fb_name, $esc_id_no, $former_school, $father_name,
    $mother_name, $contact_person, $contact_fb_name, $street_name,
    $barangay, $municipality, $province, $contact_cp_no,
    $student_status, $student_id
);

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save student"
    ]);
    exit;
}

mysqli_stmt_close($save);


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Student updated successfully",
    "student_id" => $student_id
]);
