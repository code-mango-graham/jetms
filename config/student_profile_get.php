<?php
include '../config.php';

header('Content-Type: application/json');

$response = array('success' => false, 'data' => null);

try {
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

    if ($student_id <= 0) {
        throw new Exception('Student ID is required');
    }

    $sql = "SELECT * FROM tbl_student WHERE student_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $student_id);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Student not found');
    }

    $response['success'] = true;
    $response['data'] = $result->fetch_assoc();

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
