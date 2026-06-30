<?php
include '../config.php';

$response = array('success' => false, 'message' => '');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $student_id = $_POST['student_id'] ?? 0;
    $last_name = $_POST['last_name'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $extension_name = $_POST['extension_name'] ?? '';
    $middle_initial = $_POST['middle_initial'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $cp_no = $_POST['cp_no'] ?? '';
    $spoken_language = $_POST['spoken_language'] ?? '';
    $fb_name = $_POST['fb_name'] ?? '';
    $esc_id_no = $_POST['esc_id_no'] ?? '';
    $former_school = $_POST['former_school'] ?? '';
    $father_name = $_POST['father_name'] ?? '';
    $mother_name = $_POST['mother_name'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $contact_fb_name = $_POST['contact_fb_name'] ?? '';
    $street_name = $_POST['street_name'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $municipality = $_POST['municipality'] ?? '';
    $province = $_POST['province'] ?? '';
    $contact_cp_no = $_POST['contact_cp_no'] ?? '';
    $student_status = $_POST['student_status'] ?? 'active';

    if (empty($student_id) || empty($last_name) || empty($first_name)) {
        throw new Exception('Student ID, last name and first name are required');
    }

    $sql = "UPDATE tbl_student SET
        last_name = ?, first_name = ?, middle_name = ?, extension_name = ?,
        middle_initial = ?, birthday = ?, sex = ?, cp_no = ?, spoken_language = ?,
        fb_name = ?, esc_id_no = ?, former_school = ?, father_name = ?,
        mother_name = ?, contact_person = ?, contact_fb_name = ?, street_name = ?,
        barangay = ?, municipality = ?, province = ?, contact_cp_no = ?,
        student_status = ?
        WHERE student_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        "sssssssssssssssssssssi",
        $last_name, $first_name, $middle_name, $extension_name,
        $middle_initial, $birthday, $sex, $cp_no, $spoken_language,
        $fb_name, $esc_id_no, $former_school, $father_name,
        $mother_name, $contact_person, $contact_fb_name, $street_name,
        $barangay, $municipality, $province, $contact_cp_no,
        $student_status, $student_id
    );

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Student updated successfully';
    } else {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
