<?php
// Prevent PHP warnings from breaking JSON output
error_reporting(0);
header('Content-Type: application/json');

// Smart DB Connector to avoid path issues
$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php', '../../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }

if(!isset($conn)) { 
    echo json_encode(['success'=>false, 'message'=>'Database connection not found.']); exit; 
}

// Fetch only active employees who have a linked user_id
$query = "SELECT user_id, emp_id_code, full_name 
          FROM employee_profiles 
          WHERE status = 'Active' AND user_id IS NOT NULL 
          ORDER BY full_name ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    exit;
}

$employees = mysqli_fetch_all($result, MYSQLI_ASSOC);
echo json_encode(['success' => true, 'data' => $employees]);
?>