<?php
include '../config.php';

$response = array('data' => array());

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $citymunCode = $_POST['citymun_code'] ?? '';

    if (empty($citymunCode)) {
        echo json_encode($response);
        exit;
    }

    $sql = "SELECT DISTINCT brgyCode, brgyDesc, citymunCode FROM refbrgy WHERE citymunCode = ? ORDER BY brgyDesc";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("s", $citymunCode);
    
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
