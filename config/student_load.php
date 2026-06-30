<?php
include '../config.php';

$response = array('data' => array());

try {
    $sql = "SELECT * FROM tbl_student ORDER BY last_name, first_name";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $response['data'][] = $row;
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
