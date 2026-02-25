<?php
error_reporting(0);
header('Content-Type: application/json');

$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }
if(!isset($conn)) { echo json_encode(['success'=>false, 'message'=>'DB Error']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $salary_month = trim($_POST['salary_month'] ?? '');
    
    // Status & Date Variables
    $credit_status = $_POST['credit_status'] ?? 'Pending';
    $credit_date = !empty($_POST['credit_date']) ? $_POST['credit_date'] . ' ' . date('H:i:s') : null;

    if ($credit_status === 'Credited' && empty($credit_date)) {
        $credit_date = date('Y-m-d H:i:s'); // Default to right now if somehow empty
    } elseif ($credit_status === 'Pending') {
        $credit_date = null; // Ensure null if pending
    }

    // Earnings
    $basic = floatval($_POST['basic'] ?? 0);
    $da = floatval($_POST['da'] ?? 0);
    $hra = floatval($_POST['hra'] ?? 0);
    $allowance = floatval($_POST['allowance'] ?? 0);
    $medical = floatval($_POST['medical'] ?? 0);
    $conveyance = floatval($_POST['conveyance'] ?? 0);
    $others_earnings = floatval($_POST['others_earnings'] ?? 0);
    
    // Deductions
    $tds = floatval($_POST['tds'] ?? 0);
    $esi = floatval($_POST['esi'] ?? 0);
    $pf = floatval($_POST['pf'] ?? 0);
    $leave_deduction = floatval($_POST['leave_deduction'] ?? 0);
    $professional_tax = floatval($_POST['professional_tax'] ?? 0);
    $labour_welfare = floatval($_POST['labour_welfare'] ?? 0);
    $others_deductions = floatval($_POST['others_deductions'] ?? 0);

    if (!$user_id || empty($salary_month)) {
        echo json_encode(['success' => false, 'message' => 'Employee and Salary Month are required.']); exit;
    }

    $gross_salary = $basic + $da + $hra + $allowance + $medical + $conveyance + $others_earnings;
    $total_deductions = $tds + $esi + $pf + $leave_deduction + $professional_tax + $labour_welfare + $others_deductions;
    $net_salary = $gross_salary - $total_deductions;

    $stmt = $conn->prepare("INSERT INTO employee_salary 
        (user_id, salary_month, basic, da, hra, allowance, medical, conveyance, others_earnings, 
         tds, esi, pf, leave_deduction, professional_tax, labour_welfare, others_deductions, 
         gross_salary, total_deductions, net_salary, credit_status, credit_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
    $stmt->bind_param("isdddddddddddddddddss", 
        $user_id, $salary_month, $basic, $da, $hra, $allowance, $medical, $conveyance, $others_earnings,
        $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions,
        $gross_salary, $total_deductions, $net_salary, $credit_status, $credit_date
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Salary record added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}
?>