<?php
include '../config.php';

$id = $_POST['section_id'];

$query = mysqli_query($conn,"
SELECT *
FROM tbl_section
WHERE section_id = '$id'
");

$data = mysqli_fetch_assoc($query);

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Section not found"
    ]);
}
?>