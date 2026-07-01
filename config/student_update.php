<?php
include '../config.php';

header('Content-Type: application/json');

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
$student_status = isset($_POST['student_status']) ? trim($_POST['student_status']) : 'active';

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
// UPDATE
// =======================

$save = mysqli_prepare($conn, "UPDATE tbl_student SET
    lrn = ?, last_name = ?, first_name = ?, middle_name = ?, extension_name = ?,
    middle_initial = ?, birthday = ?, sex = ?, cp_no = ?, spoken_language = ?,
    fb_name = ?, esc_id_no = ?, former_school = ?, father_name = ?,
    mother_name = ?, contact_person = ?, contact_fb_name = ?, street_name = ?,
    barangay = ?, municipality = ?, province = ?, contact_cp_no = ?,
    student_status = ?
    WHERE student_id = ?");
mysqli_stmt_bind_param($save, "sssssssssssssssssssssssi",
    $lrn, $last_name, $first_name, $middle_name, $extension_name,
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
        "message" => "Failed to update student"
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
