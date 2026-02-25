<?php
error_reporting(0);
header('Content-Type: application/json');
$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $month = $_POST['salary_month'] ?? '';
    
    $credit_status = $_POST['credit_status'] ?? 'Pending';
    $credit_date = !empty($_POST['credit_date']) ? $_POST['credit_date'] . ' ' . date('H:i:s') : null;
    if ($credit_status === 'Pending') { $credit_date = null; }

    // Earnings
    $basic = floatval($_POST['basic'] ?? 0);
    $da = floatval($_POST['da'] ?? 0);
    $hra = floatval($_POST['hra'] ?? 0);
    $conveyance = floatval($_POST['conveyance'] ?? 0);
    $allowance = floatval($_POST['allowance'] ?? 0);
    $medical = floatval($_POST['medical'] ?? 0);
    $others_earnings = floatval($_POST['others_earnings'] ?? 0);
    
    // Deductions
    $tds = floatval($_POST['tds'] ?? 0);
    $esi = floatval($_POST['esi'] ?? 0);
    $pf = floatval($_POST['pf'] ?? 0);
    $leave_deduction = floatval($_POST['leave_deduction'] ?? 0);
    $professional_tax = floatval($_POST['professional_tax'] ?? 0);
    $labour_welfare = floatval($_POST['labour_welfare'] ?? 0);
    $others_deductions = floatval($_POST['others_deductions'] ?? 0);

    $gross = $basic + $da + $hra + $conveyance + $allowance + $medical + $others_earnings;
    $deduct = $tds + $esi + $pf + $leave_deduction + $professional_tax + $labour_welfare + $others_deductions;
    $net = $gross - $deduct;

    $sql = "UPDATE employee_salary SET 
            user_id=?, salary_month=?, credit_status=?, credit_date=?, approval_status='Pending',
            basic=?, da=?, hra=?, conveyance=?, allowance=?, medical=?, others_earnings=?, 
            tds=?, esi=?, pf=?, leave_deduction=?, professional_tax=?, labour_welfare=?, others_deductions=?, 
            gross_salary=?, total_deductions=?, net_salary=?
            WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssddddddddddddddddi", 
        $user_id, $month, $credit_status, $credit_date, 
        $basic, $da, $hra, $conveyance, $allowance, $medical, $others_earnings,
        $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions,
        $gross, $deduct, $net, $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}
?>