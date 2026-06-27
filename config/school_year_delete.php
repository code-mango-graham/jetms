<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;

if ($schoolyear_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid school year id'
    ]);
    exit;
}

$delete = mysqli_prepare($conn, 'DELETE FROM tbl_schoolyear WHERE schoolyear_id = ?');

// Clean related mapping records first when foreign keys are not yet configured.
$cleanupAssignments = mysqli_prepare($conn, '
    DELETE map
    FROM tbl_schoolyear_section_subject map
    INNER JOIN tbl_schoolyear_section sec ON sec.schoolyear_section_id = map.schoolyear_section_id
    WHERE sec.schoolyear_id = ?
');
mysqli_stmt_bind_param($cleanupAssignments, 'i', $schoolyear_id);
mysqli_stmt_execute($cleanupAssignments);
mysqli_stmt_close($cleanupAssignments);

$cleanupSections = mysqli_prepare($conn, 'DELETE FROM tbl_schoolyear_section WHERE schoolyear_id = ?');
mysqli_stmt_bind_param($cleanupSections, 'i', $schoolyear_id);
mysqli_stmt_execute($cleanupSections);
mysqli_stmt_close($cleanupSections);

mysqli_stmt_bind_param($delete, 'i', $schoolyear_id);
mysqli_stmt_execute($delete);

if (mysqli_stmt_affected_rows($delete) > 0) {
    mysqli_stmt_close($delete);
    echo json_encode([
        'status' => 'success',
        'message' => 'School year deleted successfully'
    ]);
    exit;
}

mysqli_stmt_close($delete);

echo json_encode([
    'status' => 'error',
    'message' => 'School year not found'
]);
