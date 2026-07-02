<?php
include '../config.php';

header('Content-Type: application/json');

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$schoolyear_id = isset($_POST['schoolyear_id']) ? (int)$_POST['schoolyear_id'] : 0;
$schoolyear_section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$subject_ids = isset($_POST['subject_ids']) ? (array)$_POST['subject_ids'] : [];

// Validation
if (!$student_id || !$schoolyear_id || !$schoolyear_section_id || empty($subject_ids)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required information"
    ]);
    exit;
}

// Verify schoolyear_section exists
$verifySySection = mysqli_prepare($conn, "
    SELECT schoolyear_section_id FROM tbl_schoolyear_section 
    WHERE schoolyear_section_id = ? AND schoolyear_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($verifySySection, "ii", $schoolyear_section_id, $schoolyear_id);
mysqli_stmt_execute($verifySySection);
mysqli_stmt_store_result($verifySySection);

if (mysqli_stmt_num_rows($verifySySection) == 0) {
    mysqli_stmt_close($verifySySection);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid section for this school year"
    ]);
    exit;
}
mysqli_stmt_close($verifySySection);

// Check if student is already enrolled in this section for this school year
$checkEnroll = mysqli_prepare($conn, "
    SELECT enrollment_id FROM tbl_enrollments 
    WHERE student_id = ? AND schoolyear_section_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($checkEnroll, "ii", $student_id, $schoolyear_section_id);
mysqli_stmt_execute($checkEnroll);
mysqli_stmt_store_result($checkEnroll);

if (mysqli_stmt_num_rows($checkEnroll) > 0) {
    mysqli_stmt_close($checkEnroll);
    echo json_encode([
        "status" => "error",
        "message" => "Student is already enrolled in this section"
    ]);
    exit;
}
mysqli_stmt_close($checkEnroll);

// Insert enrollment
$insertEnroll = mysqli_prepare($conn, "
    INSERT INTO tbl_enrollments (student_id, schoolyear_section_id) 
    VALUES (?, ?)
");
mysqli_stmt_bind_param($insertEnroll, "ii", $student_id, $schoolyear_section_id);

if (!mysqli_stmt_execute($insertEnroll)) {
    mysqli_stmt_close($insertEnroll);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create enrollment"
    ]);
    exit;
}

$enrollment_id = mysqli_insert_id($conn);
mysqli_stmt_close($insertEnroll);

// Insert enrolled subjects
$insertSubject = mysqli_prepare($conn, "
    INSERT INTO tbl_enrollment_subjects (enrollment_id, subject_id) 
    VALUES (?, ?)
");

$success = true;
foreach ($subject_ids as $subject_id) {
    $subject_id = (int)$subject_id;
    mysqli_stmt_bind_param($insertSubject, "ii", $enrollment_id, $subject_id);
    if (!mysqli_stmt_execute($insertSubject)) {
        $success = false;
        break;
    }
}
mysqli_stmt_close($insertSubject);

if ($success) {
    echo json_encode([
        "status" => "success",
        "message" => "Student enrolled successfully with " . count($subject_ids) . " subject(s)",
        "enrollment_id" => $enrollment_id
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save enrolled subjects"
    ]);
}
?>

