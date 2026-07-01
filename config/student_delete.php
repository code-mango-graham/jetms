<?php
include '../config.php';

header('Content-Type: application/json');

$response = array('success' => false, 'message' => '');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

    if ($student_id <= 0) {
        throw new Exception('Invalid student ID');
    }

    // Archive the student by setting status to archived
    $sql = "UPDATE tbl_student SET student_status = 'archived' WHERE student_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $student_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Student archived successfully';
    } else {
        throw new Exception('Student not found');
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>