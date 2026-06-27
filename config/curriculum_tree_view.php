<?php
include '../config.php';

header('Content-Type: application/json');

$curriculum_id = isset($_GET['curriculum_id']) ? (int) $_GET['curriculum_id'] : 0;

if ($curriculum_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid curriculum id'
    ]);
    exit;
}

$curriculumQuery = mysqli_prepare($conn, '
    SELECT curriculum_id, curriculum_name, description, status
    FROM tbl_curriculum
    WHERE curriculum_id = ?
    LIMIT 1
');
mysqli_stmt_bind_param($curriculumQuery, 'i', $curriculum_id);
mysqli_stmt_execute($curriculumQuery);
$curriculumResult = mysqli_stmt_get_result($curriculumQuery);
$curriculum = mysqli_fetch_assoc($curriculumResult);
mysqli_stmt_close($curriculumQuery);

if (!$curriculum) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Curriculum not found'
    ]);
    exit;
}

$treeQuery = mysqli_prepare($conn, '
    SELECT
        l.level_id,
        l.level_name,
        l.level_type,
        s.subject_id,
        s.subject_code,
        s.subject_name
    FROM tbl_level l
    LEFT JOIN tbl_subject s ON s.level_id = l.level_id
    WHERE l.curriculum_id = ?
    ORDER BY CAST(l.level_type AS UNSIGNED) ASC, l.level_name ASC, s.subject_name ASC
');
mysqli_stmt_bind_param($treeQuery, 'i', $curriculum_id);
mysqli_stmt_execute($treeQuery);
$treeResult = mysqli_stmt_get_result($treeQuery);

$levels = [];

while ($row = mysqli_fetch_assoc($treeResult)) {
    $level_id = (int) $row['level_id'];

    if (!isset($levels[$level_id])) {
        $levels[$level_id] = [
            'level_id' => $level_id,
            'level_name' => $row['level_name'],
            'level_type' => $row['level_type'],
            'subjects' => []
        ];
    }

    if (!empty($row['subject_id'])) {
        $levels[$level_id]['subjects'][] = [
            'subject_id' => (int) $row['subject_id'],
            'subject_code' => $row['subject_code'],
            'subject_name' => $row['subject_name']
        ];
    }
}

mysqli_stmt_close($treeQuery);

echo json_encode([
    'status' => 'success',
    'curriculum' => $curriculum,
    'levels' => array_values($levels)
]);
