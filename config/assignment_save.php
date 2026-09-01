<?php

include '../config.php';

header('Content-Type: application/json');

$assignment_id = isset($_POST['assignment_id']) ? (int) $_POST['assignment_id'] : 0;
$section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : null;
$schedule_info = isset($_POST['schedule_info']) ? trim($_POST['schedule_info']) : null;
$room_no = isset($_POST['room_no']) ? trim($_POST['room_no']) : null;

$response = [
    "success" => false,
    "message" => "Error saving assignment"
];

if ($section_id > 0 && $subject_id > 0) {
    // Check if record exists
    if ($assignment_id > 0) {
        // Update existing record
        $query = mysqli_prepare($conn, "
            UPDATE tbl_assignments
            SET teacher_id = ?, schedule_info = ?, room_no = ?, updated_at = CURRENT_TIMESTAMP
            WHERE assignment_id = ?
        ");
        
        mysqli_stmt_bind_param($query, "issi", $teacher_id, $schedule_info, $room_no, $assignment_id);
        
        if (mysqli_stmt_execute($query)) {
            $response["success"] = true;
            $response["message"] = "Assignment updated successfully";
        } else {
            $response["message"] = "Error updating assignment: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($query);
    } else {
        // Insert new record
        $query = mysqli_prepare($conn, "
            INSERT INTO tbl_assignments
            (section_id, subject_id, teacher_id, schedule_info, room_no)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        mysqli_stmt_bind_param($query, "iiiss", $section_id, $subject_id, $teacher_id, $schedule_info, $room_no);
        
        if (mysqli_stmt_execute($query)) {
            $response["success"] = true;
            $response["message"] = "Assignment saved successfully";
            $response["assignment_id"] = mysqli_insert_id($conn);
        } else {
            $response["message"] = "Error saving assignment: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($query);
    }
} else {
    $response["message"] = "Missing required fields";
}

echo json_encode($response);
?>
