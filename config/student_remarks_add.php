<?php
include '../config.php';

$response = array('success' => false, 'message' => '');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $student_id = $_POST['student_id'] ?? 0;
    $schoolyear_id = $_POST['schoolyear_id'] ?? 0;
    $remark_text = $_POST['remark_text'] ?? '';
    $remarks_status_description = $_POST['remarks_status_description'] ?? '';

    if (empty($student_id) || empty($schoolyear_id)) {
        throw new Exception('Student ID and School Year ID are required');
    }

    // Check if remark already exists for this student and school year
    $check_sql = "SELECT remark_id FROM tbl_student_remarks WHERE student_id = ? AND schoolyear_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $check_stmt->bind_param("ii", $student_id, $schoolyear_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Update existing remark
        $remark_status = 'Updated';
        $sql = "UPDATE tbl_student_remarks SET 
            remark_text = ?, remarks_status_description = ?, remark_status = ?
            WHERE student_id = ? AND schoolyear_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("sssii", $remark_text, $remarks_status_description, $remark_status, $student_id, $schoolyear_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $message = 'Student remark updated successfully';
    } else {
        // Insert new remark
        $remark_status = 'New';
        $sql = "INSERT INTO tbl_student_remarks (student_id, schoolyear_id, remark_status, remark_text, remarks_status_description)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("iisss", $student_id, $schoolyear_id, $remark_status, $remark_text, $remarks_status_description);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $message = 'Student remark added successfully';
    }

    $response['success'] = true;
    $response['message'] = $message;
    $stmt->close();
    $check_stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
