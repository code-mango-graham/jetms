<?php
include '../config.php';

header('Content-Type: application/json');

$schoolyear_id = isset($_POST['schoolyear_id']) ? (int) $_POST['schoolyear_id'] : 0;

if ($schoolyear_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid school year id',
        'data' => []
    ]);
    exit;
}

$metaQuery = mysqli_prepare($conn, '
    SELECT
        sy.schoolyear_id,
        sy.schoolyear_name,
        sy.curriculum_id,
        c.curriculum_name
    FROM tbl_schoolyear sy
    LEFT JOIN tbl_curriculum c ON c.curriculum_id = sy.curriculum_id
    WHERE sy.schoolyear_id = ?
    LIMIT 1
');
mysqli_stmt_bind_param($metaQuery, 'i', $schoolyear_id);
mysqli_stmt_execute($metaQuery);
$metaResult = mysqli_stmt_get_result($metaQuery);
$meta = mysqli_fetch_assoc($metaResult);
mysqli_stmt_close($metaQuery);

if (!$meta) {
    echo json_encode([
        'status' => 'error',
        'message' => 'School year not found',
        'data' => []
    ]);
    exit;
}

$curriculum_id = isset($meta['curriculum_id']) ? (int) $meta['curriculum_id'] : 0;

if ($curriculum_id <= 0) {
    $activeQuery = mysqli_query($conn, 'SELECT curriculum_id, curriculum_name FROM tbl_curriculum WHERE status = 1 LIMIT 1');
    if ($activeQuery && mysqli_num_rows($activeQuery) > 0) {
        $active = mysqli_fetch_assoc($activeQuery);
        $curriculum_id = (int) $active['curriculum_id'];
        $meta['curriculum_name'] = $active['curriculum_name'];
    }
}

$data = [];

if ($curriculum_id > 0) {
    $levelQuery = mysqli_prepare($conn, '
        SELECT level_id, curriculum_id, level_name, level_type
        FROM tbl_level
        WHERE curriculum_id = ?
        ORDER BY level_name ASC
    ');
    mysqli_stmt_bind_param($levelQuery, 'i', $curriculum_id);
    mysqli_stmt_execute($levelQuery);
    $levelResult = mysqli_stmt_get_result($levelQuery);

    while ($row = mysqli_fetch_assoc($levelResult)) {
        $data[] = $row;
    }

    mysqli_stmt_close($levelQuery);
}

echo json_encode([
    'status' => 'success',
    'schoolyear_id' => $meta['schoolyear_id'],
    'schoolyear_name' => $meta['schoolyear_name'],
    'curriculum_id' => $curriculum_id,
    'curriculum_name' => $meta['curriculum_name'] ?? 'Not linked',
    'data' => $data
]);
