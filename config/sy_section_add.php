<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_section_id = isset($_POST['schoolyear_section_id']) ? (int) $_POST['schoolyear_section_id'] : 0;
$schoolyear_id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;
$level_id = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
$section_name = isset($_POST['section_name']) ? trim($_POST['section_name']) : '';

if ($schoolyear_id <= 0 || $level_id <= 0 || $section_name === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'School year, level, and section name are required'
    ]);
    exit;
}

if ($schoolyear_section_id <= 0) {
    $check = mysqli_prepare($conn, '
        SELECT 1
        FROM tbl_schoolyear_section
        WHERE schoolyear_id = ? AND level_id = ? AND section_name = ? AND section_remarks = 1
        LIMIT 1
    ');
    mysqli_stmt_bind_param($check, 'iis', $schoolyear_id, $level_id, $section_name);
} else {
    $check = mysqli_prepare($conn, '
        SELECT 1
        FROM tbl_schoolyear_section
        WHERE schoolyear_id = ? AND level_id = ? AND section_name = ? AND schoolyear_section_id != ? AND section_remarks = 1
        LIMIT 1
    ');
    mysqli_stmt_bind_param($check, 'iisi', $schoolyear_id, $level_id, $section_name, $schoolyear_section_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        'status' => 'error',
        'message' => 'Section name already exists for this level and school year'
    ]);
    exit;
}

mysqli_stmt_close($check);

if ($schoolyear_section_id <= 0) {
    $save = mysqli_prepare($conn, '
        INSERT INTO tbl_schoolyear_section (schoolyear_id, level_id, section_name)
        VALUES (?, ?, ?)
    ');
    mysqli_stmt_bind_param($save, 'iis', $schoolyear_id, $level_id, $section_name);
} else {
    $save = mysqli_prepare($conn, '
        UPDATE tbl_schoolyear_section
        SET section_name = ?
        WHERE schoolyear_section_id = ?
    ');
    mysqli_stmt_bind_param($save, 'si', $section_name, $schoolyear_section_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save section'
    ]);
    exit;
}

mysqli_stmt_close($save);

echo json_encode([
    'status' => 'success',
    'message' => 'Saved successfully'
]);
