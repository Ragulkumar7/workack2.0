<?php
error_reporting(0);
header('Content-Type: application/json');

// Connect to Database
$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }
if(!isset($conn)) { echo json_encode(['success'=>false, 'message'=>'Database Connection Failed']); exit; }

$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_clauses = ["s.salary_month = ?"];
$params = [$month_filter];
$types = "s";

if (!empty($status_filter)) {
    $where_clauses[] = "s.approval_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

// CRITICAL FIX: Joined with 'employee_onboarding' using 'e.id = s.user_id'
// Merged first_name and last_name into 'name' for the UI
$query = "SELECT s.*, 
                 e.emp_id_code as emp_code, 
                 CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as name, 
                 e.designation, 
                 e.email, 
                 e.profile_img
          FROM employee_salary s
          LEFT JOIN employee_onboarding e ON s.user_id = e.id
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

// Safely calculate summaries
$summary_stmt = $conn->prepare("SELECT 
    IFNULL(SUM(gross_salary), 0) as total_payroll,
    IFNULL(SUM(CASE WHEN credit_status = 'Credited' THEN net_salary ELSE 0 END), 0) as total_credited,
    IFNULL(SUM(CASE WHEN approval_status = 'Pending' THEN net_salary ELSE 0 END), 0) as total_pending,
    IFNULL(SUM(total_deductions), 0) as total_deductions
    FROM employee_salary WHERE salary_month = ?");
$summary_stmt->bind_param("s", $month_filter);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

echo json_encode(['success' => true, 'data' => $salaries, 'summary' => $summary]);
?>