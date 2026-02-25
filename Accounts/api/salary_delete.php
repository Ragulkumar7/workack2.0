<?php
require_once '../db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$salary_id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$salary_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Salary ID.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM employee_salary WHERE id = ?");
$stmt->bind_param("i", $salary_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Salary record deleted.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
}
$stmt->close();
?>