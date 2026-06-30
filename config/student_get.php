<?php
include '../config.php';

$response = array('success' => false, 'data' => null);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $student_id = $_POST['student_id'] ?? 0;

    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }

    $sql = "SELECT * FROM tbl_student WHERE student_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $student_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
    } else {
        throw new Exception('Student not found');
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
