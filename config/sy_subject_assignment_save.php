<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_section_id = isset($_POST['schoolyear_section_id']) ? (int) $_POST['schoolyear_section_id'] : 0;
$subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$teacher_id = isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '' ? (int) $_POST['teacher_id'] : null;
$schedule_info = isset($_POST['schedule_info']) ? trim($_POST['schedule_info']) : '';
$room_no = isset($_POST['room_no']) ? trim($_POST['room_no']) : '';

if ($schoolyear_section_id <= 0 || $subject_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid section or subject'
    ]);
    exit;
}

$teacherValue = $teacher_id !== null && $teacher_id > 0 ? $teacher_id : 0;
$scheduleValue = $schedule_info !== '' ? $schedule_info : '';
$roomValue = $room_no !== '' ? $room_no : '';

$save = mysqli_prepare($conn, '
    INSERT INTO tbl_schoolyear_section_subject
        (schoolyear_section_id, subject_id, teacher_id, schedule_info, room_no)
    VALUES
        (?, ?, NULLIF(?, 0), NULLIF(?, \'\'), NULLIF(?, \'\'))
    ON DUPLICATE KEY UPDATE
        teacher_id = NULLIF(VALUES(teacher_id), 0),
        schedule_info = NULLIF(VALUES(schedule_info), \'\'),
        room_no = NULLIF(VALUES(room_no), \'\')
');

mysqli_stmt_bind_param(
    $save,
    'iiiss',
    $schoolyear_section_id,
    $subject_id,
    $teacherValue,
    $scheduleValue,
    $roomValue
);

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save assignment'
    ]);
    exit;
}

mysqli_stmt_close($save);

echo json_encode([
    'status' => 'success',
    'message' => 'Assignment saved successfully'
]);
