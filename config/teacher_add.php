<?php
include '../config.php';

header('Content-Type: application/json');

$teacher_id = isset($_POST['teacher_id']) ? trim($_POST['teacher_id']) : '';
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$extension_name = isset($_POST['extension_name']) ? trim($_POST['extension_name']) : '';
$nick_name = isset($_POST['nick_name']) ? trim($_POST['nick_name']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$position_id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;
$department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
$date_hired = isset($_POST['date_hired']) ? trim($_POST['date_hired']) : '';

if ($first_name === '' || $last_name === '' || $gender === '' || $birthday === '' || $position_id <= 0 || $department_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Please complete the required teacher fields"
    ]);
    exit;
}

if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid gender value"
    ]);
    exit;
}

if ($email !== '') {
    if (empty($teacher_id)) {
        $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_teacher WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $email);
    } else {
        $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_teacher WHERE email = ? AND teacher_id != ? LIMIT 1");
        mysqli_stmt_bind_param($check, "si", $email, $teacher_id);
    }

    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) > 0) {
        mysqli_stmt_close($check);
        echo json_encode([
            "status" => "error",
            "message" => "Email already exists"
        ]);
        exit;
    }

    mysqli_stmt_close($check);
}

$emailValue = ($email === '') ? null : $email;
$dateHiredValue = ($date_hired === '') ? null : $date_hired;
$middleNameValue = ($middle_name === '') ? null : $middle_name;
$extensionNameValue = ($extension_name === '') ? null : $extension_name;
$nickNameValue = ($nick_name === '') ? null : $nick_name;
$phoneNumberValue = ($phone_number === '') ? null : $phone_number;

if (empty($teacher_id)) {
    $save = mysqli_prepare($conn, "INSERT INTO tbl_teacher (first_name, middle_name, last_name, extension_name, nick_name, gender, birthday, phone_number, email, position_id, department_id, date_hired) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''))");
    mysqli_stmt_bind_param($save, "sssssssssiis", $first_name, $middleNameValue, $last_name, $extensionNameValue, $nickNameValue, $gender, $birthday, $phoneNumberValue, $email, $position_id, $department_id, $date_hired);
} else {
    $save = mysqli_prepare($conn, "UPDATE tbl_teacher SET first_name = ?, middle_name = ?, last_name = ?, extension_name = ?, nick_name = ?, gender = ?, birthday = ?, phone_number = ?, email = NULLIF(?, ''), position_id = ?, department_id = ?, date_hired = NULLIF(?, '') WHERE teacher_id = ?");
    mysqli_stmt_bind_param($save, "sssssssssiisi", $first_name, $middleNameValue, $last_name, $extensionNameValue, $nickNameValue, $gender, $birthday, $phoneNumberValue, $email, $position_id, $department_id, $date_hired, $teacher_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save teacher"
    ]);
    exit;
}

mysqli_stmt_close($save);

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);
?>