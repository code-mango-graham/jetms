<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id   = isset($_POST['schoolyear_id']) ? trim($_POST['schoolyear_id']) : '';
$schoolyear_name = isset($_POST['schoolyear_name']) ? trim($_POST['schoolyear_name']) : '';
$year_start      = isset($_POST['year_start']) ? (int) $_POST['year_start'] : 0;
$year_end        = isset($_POST['year_end']) ? (int) $_POST['year_end'] : 0;
$status          = isset($_POST['status']) ? (int) $_POST['status'] : 0;
$curriculum_id   = isset($_POST['curriculum_id']) ? (int) $_POST['curriculum_id'] : 0;

if ($curriculum_id <= 0) {
    $activeCurriculum = mysqli_query($conn, "SELECT curriculum_id FROM tbl_curriculum WHERE status = 1 LIMIT 1");
    if ($activeCurriculum && mysqli_num_rows($activeCurriculum) > 0) {
        $activeRow = mysqli_fetch_assoc($activeCurriculum);
        $curriculum_id = (int) $activeRow['curriculum_id'];
    }
}

if ($curriculum_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Please set an active curriculum first"
    ]);
    exit;
}

if ($schoolyear_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "School Year name is required"
    ]);
    exit;
}


// =======================
// VALIDATION
// =======================

if ($year_end <= $year_start) {
    echo json_encode([
        "status" => "error",
        "message" => "Year End must be greater than Year Start"
    ]);
    exit;
}


// =======================
// DUPLICATE CHECK
// (SAFE FOR ADD + EDIT)
// =======================

if (empty($schoolyear_id)) {
    // INSERT duplicate check
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_schoolyear WHERE year_start = ? AND year_end = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "ii", $year_start, $year_end);
} else {
    // UPDATE duplicate check (exclude current record)
    $check = mysqli_prepare($conn, "SELECT 1 FROM tbl_schoolyear WHERE year_start = ? AND year_end = ? AND schoolyear_id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "iii", $year_start, $year_end, $schoolyear_id);
}

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo json_encode([
        "status" => "error",
        "message" => "School Year already exists"
    ]);
    exit;
}

mysqli_stmt_close($check);


// =======================
// ONLY ONE ACTIVE SCHOOL YEAR
// =======================

if ($status === 1) {
    mysqli_query($conn,"
        UPDATE tbl_schoolyear
        SET status = 0
    ");
}


// =======================
// INSERT OR UPDATE
// =======================

if (empty($schoolyear_id)) {
    // INSERT
    $save = mysqli_prepare($conn, "INSERT INTO tbl_schoolyear (schoolyear_name, curriculum_id, year_start, year_end, status) VALUES (?, NULLIF(?, 0), ?, ?, ?)");
    mysqli_stmt_bind_param($save, "siiii", $schoolyear_name, $curriculum_id, $year_start, $year_end, $status);
} else {
    // UPDATE
    $save = mysqli_prepare($conn, "UPDATE tbl_schoolyear SET schoolyear_name = ?, curriculum_id = NULLIF(?, 0), year_start = ?, year_end = ?, status = ? WHERE schoolyear_id = ?");
    mysqli_stmt_bind_param($save, "siiiii", $schoolyear_name, $curriculum_id, $year_start, $year_end, $status, $schoolyear_id);
}

if (!mysqli_stmt_execute($save)) {
    mysqli_stmt_close($save);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save school year"
    ]);
    exit;
}

mysqli_stmt_close($save);


// =======================
// RESPONSE
// =======================

echo json_encode([
    "status" => "success",
    "message" => "Saved successfully"
]);