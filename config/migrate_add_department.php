<?php
include '../config.php';

// Add department_id column if it doesn't exist
$checkColumn = mysqli_query($conn, "
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'tbl_teacher' 
    AND COLUMN_NAME = 'department_id'
");

if(mysqli_num_rows($checkColumn) == 0){
    $alter = mysqli_query($conn, "
        ALTER TABLE tbl_teacher 
        ADD COLUMN department_id INT 
        AFTER position_id,
        ADD CONSTRAINT fk_teacher_department
            FOREIGN KEY (department_id)
            REFERENCES tbl_department(department_id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
    ");
    
    if($alter){
        echo json_encode([
            "status" => "success",
            "message" => "department_id column added successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        "status" => "success",
        "message" => "department_id column already exists"
    ]);
}
?>
