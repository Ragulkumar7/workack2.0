<?php
// Smart DB Connector to avoid path issues
$paths = ['../include/db_connect.php', '../../include/db_connect.php', '../db_connect.php', '../../db_connect.php'];
foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }
if(!isset($conn)) { 
    die("Database connection not found."); 
}

$salary_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$salary_id) {
    die("Invalid request.");
}

$query = "SELECT s.*, p.full_name as name, p.emp_id_code as emp_code, p.designation, p.joining_date 
          FROM employee_salary s 
          JOIN users u ON s.user_id = u.id 
          LEFT JOIN employee_profiles p ON u.id = p.user_id
          WHERE s.id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $salary_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Payslip not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?= htmlspecialchars($data['name']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; padding: 20px; }
        .payslip-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .details-table, .salary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table td { padding: 8px; border: 1px solid #ddd; }
        .salary-table th, .salary-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .salary-table th { background: #f8f9fa; }
        .total-row { font-weight: bold; background: #eef2f5; }
        .print-btn { background: #f97316; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; display: block; margin: 20px auto; }
        @media print { .print-btn { display: none; } body { background: #fff; } .payslip-container { box-shadow: none; border: none; } }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="header">
            <h2>WorkAck Ltd.</h2>
            <p>Payslip for the month of <?= date('F Y', strtotime($data['salary_month'] . '-01')) ?></p>
        </div>
        <table class="details-table">
            <tr>
                <td><strong>Employee ID:</strong> <?= htmlspecialchars($data['emp_code'] ?? 'N/A') ?></td>
                <td><strong>Name:</strong> <?= htmlspecialchars($data['name']) ?></td>
            </tr>
            <tr>
                <td><strong>Designation:</strong> <?= htmlspecialchars($data['designation'] ?? 'N/A') ?></td>
                <td><strong>Joining Date:</strong> <?= htmlspecialchars($data['joining_date'] ?? 'N/A') ?></td>
            </tr>
        </table>

        <table class="salary-table">
            <thead>
                <tr>
                    <th>Earnings</th>
                    <th>Amount ($)</th>
                    <th>Deductions</th>
                    <th>Amount ($)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic</td><td><?= number_format($data['basic'], 2) ?></td>
                    <td>TDS</td><td><?= number_format($data['tds'], 2) ?></td>
                </tr>
                <tr>
                    <td>DA (40%)</td><td><?= number_format($data['da'], 2) ?></td>
                    <td>ESI</td><td><?= number_format($data['esi'], 2) ?></td>
                </tr>
                <tr>
                    <td>HRA (15%)</td><td><?= number_format($data['hra'], 2) ?></td>
                    <td>PF</td><td><?= number_format($data['pf'], 2) ?></td>
                </tr>
                <tr>
                    <td>Conveyance</td><td><?= number_format($data['conveyance'], 2) ?></td>
                    <td>Leave Deduction</td><td><?= number_format($data['leave_deduction'], 2) ?></td>
                </tr>
                <tr>
                    <td>Allowance</td><td><?= number_format($data['allowance'], 2) ?></td>
                    <td>Prof. Tax</td><td><?= number_format($data['professional_tax'], 2) ?></td>
                </tr>
                <tr>
                    <td>Medical Allowance</td><td><?= number_format($data['medical'], 2) ?></td>
                    <td>Labour Welfare</td><td><?= number_format($data['labour_welfare'], 2) ?></td>
                </tr>
                <tr>
                    <td>Others (Earnings)</td><td><?= number_format($data['others_earnings'], 2) ?></td>
                    <td>Others (Deductions)</td><td><?= number_format($data['others_deductions'], 2) ?></td>
                </tr>
                <tr class="total-row">
                    <td>Gross Earnings</td><td><?= number_format($data['gross_salary'], 2) ?></td>
                    <td>Total Deductions</td><td><?= number_format($data['total_deductions'], 2) ?></td>
                </tr>
                <tr class="total-row" style="background:#d4edda;">
                    <td colspan="3" style="text-align:right;">Net Payable Salary</td>
                    <td>$<?= number_format($data['net_salary'], 2) ?></td>
                </tr>
            </tbody>
        </table>
        <p style="text-align:center; color: #555;">Status: <strong><?= htmlspecialchars($data['credit_status']) ?></strong></p>
        <button class="print-btn" onclick="window.print()">Print Payslip</button>
    </div>
</body>
</html>