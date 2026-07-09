<?php
include '../config.php';

header('Content-Type: application/json');

$query = mysqli_query($conn, "
    SELECT
        t.teacher_id,
        t.first_name,
        t.middle_name,
        t.last_name,
        t.extension_name,
        t.nick_name,
        t.gender,
        t.birthday,
        t.phone_number,
        t.email,
        t.date_hired,
        p.position_title,
        o.office_name
    FROM tbl_teacher t
    LEFT JOIN tbl_position p ON p.position_id = t.position_id
    LEFT JOIN tbl_office o ON o.office_id = t.department_id
    WHERE t.teacher_remarks = 1
    ORDER BY t.teacher_id DESC
");

$data = [];

while ($row = mysqli_fetch_assoc($query)) {
    $data[] = $row;
}

echo json_encode([
    "data" => $data
]);
?>