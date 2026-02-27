<?php
// api/salary_add.php
error_reporting(0); // Prevent PHP warnings from breaking JSON output
header('Content-Type: application/json');

// Connect to Database
$dbPath = '../include/db_connect.php';
if(file_exists($dbPath)) { require_once $dbPath; } 
elseif(file_exists('../../include/db_connect.php')) { require_once '../../include/db_connect.php'; }

if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// 1. Get Form Data
$user_id = (int)($_POST['user_id'] ?? 0);
$salary_month = $_POST['salary_month'] ?? '';
$credit_status = $_POST['credit_status'] ?? 'Pending';
$credit_date = !empty($_POST['credit_date']) ? $_POST['credit_date'] : null;

// 2. Get Earnings
$basic = (float)($_POST['basic'] ?? 0);
$da = (float)($_POST['da'] ?? 0);
$hra = (float)($_POST['hra'] ?? 0);
$conveyance = (float)($_POST['conveyance'] ?? 0);
$allowance = (float)($_POST['allowance'] ?? 0);
$medical = (float)($_POST['medical'] ?? 0);
$others_earnings = (float)($_POST['others_earnings'] ?? 0);

// 3. Get Deductions
$tds = (float)($_POST['tds'] ?? 0);
$esi = (float)($_POST['esi'] ?? 0);
$pf = (float)($_POST['pf'] ?? 0);
$leave_deduction = (float)($_POST['leave_deduction'] ?? 0);
$professional_tax = (float)($_POST['professional_tax'] ?? 0);
$labour_welfare = (float)($_POST['labour_welfare'] ?? 0);
$others_deductions = (float)($_POST['others_deductions'] ?? 0);

// 4. Calculate Gross and Net
$gross_salary = $basic + $da + $hra + $conveyance + $allowance + $medical + $others_earnings;
$total_deductions = $tds + $esi + $pf + $leave_deduction + $professional_tax + $labour_welfare + $others_deductions;
$net_salary = $gross_salary - $total_deductions;

$approval_status = 'Pending'; // Default to pending so CFO can approve

// 5. Insert into Database
$stmt = $conn->prepare("INSERT INTO employee_salary (user_id, salary_month, basic, da, hra, conveyance, allowance, medical, others_earnings, tds, esi, pf, leave_deduction, professional_tax, labour_welfare, others_deductions, gross_salary, net_salary, credit_status, credit_date, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if ($stmt) {
    $stmt->bind_param("isddddddddddddddddsss", 
        $user_id, $salary_month, 
        $basic, $da, $hra, $conveyance, $allowance, $medical, $others_earnings,
        $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions,
        $gross_salary, $net_salary, $credit_status, $credit_date, $approval_status
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Salary generated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
}
?>