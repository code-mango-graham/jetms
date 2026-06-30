<?php
include '../config.php';

$response = array('data' => array());

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $student_id = $_POST['student_id'] ?? 0;
    $schoolyear_id = $_POST['schoolyear_id'] ?? null;

    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }

    if ($schoolyear_id) {
        $sql = "SELECT sr.* FROM tbl_student_remarks sr
                WHERE sr.student_id = ? AND sr.schoolyear_id = ?
                ORDER BY sr.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("ii", $student_id, $schoolyear_id);
    } else {
        $sql = "SELECT sr.* FROM tbl_student_remarks sr
                WHERE sr.student_id = ?
                ORDER BY sr.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $student_id);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $response['data'][] = $row;
    }

    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
