<?php
include '../config.php';

header('Content-Type: application/json');

$enrollment_id = isset($_POST['enrollment_id']) ? (int)$_POST['enrollment_id'] : 0;

if (!$enrollment_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid enrollment ID"
    ]);
    exit;
}

// Delete enrollment subjects first
$deleteSubjects = mysqli_prepare($conn, "DELETE FROM tbl_enrollment_subjects WHERE enrollment_id = ?");
mysqli_stmt_bind_param($deleteSubjects, "i", $enrollment_id);
mysqli_stmt_execute($deleteSubjects);
mysqli_stmt_close($deleteSubjects);

// Delete enrollment
$deleteEnroll = mysqli_prepare($conn, "DELETE FROM tbl_enrollments WHERE enrollment_id = ?");
mysqli_stmt_bind_param($deleteEnroll, "i", $enrollment_id);

if (mysqli_stmt_execute($deleteEnroll)) {
    echo json_encode([
        "status" => "success",
        "message" => "Enrollment deleted successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete enrollment"
    ]);
}

mysqli_stmt_close($deleteEnroll);
