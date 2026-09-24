<?php
include '../config.php';

$response = array('data' => array());

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $provCode = $_POST['prov_code'] ?? '';

    if (empty($provCode)) {
        echo json_encode($response);
        exit;
    }

    $sql = "SELECT DISTINCT citymunCode, citymunDesc, provCode FROM refcitymun WHERE provCode = ? ORDER BY citymunDesc";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception(db_fail('Prepare: ' . $conn->error));
    }

    $stmt->bind_param("s", $provCode);
    
    if (!$stmt->execute()) {
        throw new Exception(db_fail('Execute: ' . $stmt->error));
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
