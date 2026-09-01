<?php

include '../config.php';

header('Content-Type: application/json');

$section_id = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$assignments = isset($_POST['assignments']) ? $_POST['assignments'] : [];

$response = [
    "success" => false,
    "message" => "Error saving assignments",
    "saved_count" => 0,
    "errors" => []
];

if ($section_id > 0 && !empty($assignments)) {
    $saved_count = 0;
    $error_count = 0;
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($assignments as $assignment) {
            $assignment_id = isset($assignment['assignment_id']) ? (int) $assignment['assignment_id'] : 0;
            $subject_id = isset($assignment['subject_id']) ? (int) $assignment['subject_id'] : 0;
            $teacher_id = isset($assignment['teacher_id']) ? (int) $assignment['teacher_id'] : null;
            $schedule_info = isset($assignment['schedule_info']) ? trim($assignment['schedule_info']) : null;
            $room_no = isset($assignment['room_no']) ? trim($assignment['room_no']) : null;
            
            if ($subject_id > 0) {
                if ($assignment_id > 0) {
                    // Update existing
                    $query = mysqli_prepare($conn, "
                        UPDATE tbl_assignments
                        SET teacher_id = ?, schedule_info = ?, room_no = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE assignment_id = ? AND section_id = ?
                    ");
                    
                    mysqli_stmt_bind_param($query, "issii", $teacher_id, $schedule_info, $room_no, $assignment_id, $section_id);
                    
                    if (mysqli_stmt_execute($query)) {
                        $saved_count++;
                    } else {
                        $error_count++;
                        $response["errors"][] = "Error updating subject ID $subject_id";
                    }
                    
                    mysqli_stmt_close($query);
                } else {
                    // Insert new
                    $query = mysqli_prepare($conn, "
                        INSERT INTO tbl_assignments
                        (section_id, subject_id, teacher_id, schedule_info, room_no)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    mysqli_stmt_bind_param($query, "iiiss", $section_id, $subject_id, $teacher_id, $schedule_info, $room_no);
                    
                    if (mysqli_stmt_execute($query)) {
                        $saved_count++;
                    } else {
                        $error_count++;
                        $response["errors"][] = "Error saving subject ID $subject_id";
                    }
                    
                    mysqli_stmt_close($query);
                }
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        $response["success"] = true;
        $response["message"] = "Assignments saved successfully. Saved: $saved_count";
        $response["saved_count"] = $saved_count;
        
        if ($error_count > 0) {
            $response["message"] .= " (Errors: $error_count)";
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response["message"] = "Transaction failed: " . $e->getMessage();
    }
} else {
    $response["message"] = "Missing section_id or assignments data";
}

echo json_encode($response);
?>
