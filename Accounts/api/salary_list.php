<?php
error_reporting(0);
header('Content-Type: application/json');

$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }
if(!isset($conn)) { echo json_encode(['success'=>false, 'message'=>'DB Error']); exit; }

$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_clauses = ["s.salary_month = ?"];
$params = [$month_filter];
$types = "s";

if (!empty($status_filter)) {
    $where_clauses[] = "s.credit_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch List by matching user_id between salary and profiles
$query = "SELECT s.id, s.salary_month, s.net_salary, s.credit_status, s.credit_date, 
                 p.emp_id_code as emp_code, p.full_name as name, p.designation, p.email, 
                 p.phone, p.joining_date, p.profile_img
          FROM employee_salary s
          LEFT JOIN employee_profiles p ON s.user_id = p.user_id
          WHERE $where_sql
          ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if(!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $salaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]); exit;
}

// Fetch Summary
$summary_stmt = $conn->prepare("SELECT 
    SUM(gross_salary) as total_payroll,
    SUM(CASE WHEN credit_status = 'Credited' THEN net_salary ELSE 0 END) as total_credited,
    SUM(CASE WHEN credit_status = 'Pending' THEN net_salary ELSE 0 END) as total_pending,
    SUM(total_deductions) as total_deductions
    FROM employee_salary WHERE salary_month = ?");
$summary_stmt->bind_param("s", $month_filter);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

echo json_encode([
    'success' => true, 
    'data' => $salaries,
    'summary' => [
        'total_payroll' => $summary['total_payroll'] ?? 0,
        'total_credited' => $summary['total_credited'] ?? 0,
        'total_pending' => $summary['total_pending'] ?? 0,
        'total_deductions' => $summary['total_deductions'] ?? 0
    ]
]);
?>