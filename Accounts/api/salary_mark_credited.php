<?php
error_reporting(0);
header('Content-Type: application/json');

$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }
if(!isset($conn)) { echo json_encode(['success'=>false, 'message'=>'DB Error']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$salary_id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
$transaction_ref = htmlspecialchars(strip_tags($data['transaction_reference'] ?? ''));

if (!$salary_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Salary ID.']);
    exit;
}

// Update the credit_status and record exactly when it was clicked (NOW)
$stmt = $conn->prepare("UPDATE employee_salary SET credit_status = 'Credited', credit_date = NOW(), transaction_reference = ? WHERE id = ?");
$stmt->bind_param("si", $transaction_ref, $salary_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Salary marked as credited.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
}
$stmt->close();
?>