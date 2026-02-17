<?php
// cfo_payroll.php
include '../sidebars.php'; 
include '../header.php';

// --- MOCK PAYROLL DATA FOR CURRENT MONTH (Feb 2026) ---
$payroll_summary = [
    'total_gross' => 850000,
    'total_deductions' => 45000, // PF, ESI, TDS
    'net_payout' => 805000,
    'headcount' => 24,
    'status' => 'Pending Approval' // Can be 'Approved', 'Pending Approval'
];

$dept_breakdown = [
    ['dept' => 'IT & Development', 'headcount' => 12, 'amount' => 420000],
    ['margin' => 'Management & HR', 'headcount' => 4, 'amount' => 210000],
    ['dept' => 'Sales & Marketing', 'headcount' => 6, 'amount' => 150000],
    ['dept' => 'Support Staff', 'headcount' => 2, 'amount' => 25000],
];

// Employee level preview
$employee_payroll = [
    ['emp_id' => 'IGS4001', 'name' => 'Caro', 'dept' => 'IT & Dev', 'net' => 50000, 'bank' => 'HDFC', 'acc' => '****3421'],
    ['emp_id' => 'IGS2030', 'name' => 'Aisha', 'dept' => 'Management', 'net' => 55000, 'bank' => 'SBI', 'acc' => '****8890'],
    ['emp_id' => 'IGS4022', 'name' => 'Rajesh', 'dept' => 'Sales', 'net' => 35000, 'bank' => 'ICICI', 'acc' => '****1122'],
    ['emp_id' => 'IGS4055', 'name' => 'Vasanth', 'dept' => 'IT & Dev', 'net' => 45000, 'bank' => 'Canara', 'acc' => '****7765'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFO Payroll & Disbursements</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --theme-color: #1b5a5a;
            --theme-light: #e0f2f1;
            --bg-body: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --primary-width: 95px;
        }

        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        .main-content { margin-left: var(--primary-width); width: calc(100% - var(--primary-width)); padding: 24px; min-height: 100vh; transition: all 0.3s ease; }

        /* Header Area */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        .header-text h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        .header-text p { font-size: 13px; color: var(--text-muted); margin: 4px 0 0; }
        
        .header-actions { display: flex; gap: 10px; }
        .btn-action { background: var(--success); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-action:hover { background: #059669; transform: translateY(-2px); }
        .btn-secondary { background: white; color: var(--text-main); border: 1px solid var(--border); box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .btn-secondary:hover { background: #f8fafc; transform: translateY(-2px); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: relative; overflow: hidden; }
        .kpi-card > div { position: relative; z-index: 2; }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .kpi-value { font-size: 24px; font-weight: 800; color: var(--text-main); }
        
        /* Darker Watermark Icons */
        .kpi-icon-bg { position: absolute; right: -15px; bottom: -20px; font-size: 120px; opacity: 0.4; pointer-events: none; z-index: 1; }

        /* Dashboard Split */
        .dashboard-split { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 24px; }
        .dashboard-card { background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; flex-direction: column; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
        .card-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--theme-color); display: flex; align-items: center; gap: 8px; }

        /* Tables */
        .table-responsive { overflow-x: auto; width: 100%; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 500px; }
        .data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border); background: #f8fafc; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--text-main); vertical-align: middle; }
        .amt-col { text-align: right; font-weight: 700; color: var(--theme-color); }

        .status-banner { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .status-text { font-size: 14px; font-weight: 600; color: #b45309; display: flex; align-items: center; gap: 8px; }

        @media (max-width: 1024px) { .dashboard-split { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; width: 100%; padding: 15px; } 
            .header-actions { width: 100%; justify-content: flex-start; margin-top: 10px; flex-wrap: wrap; }
            .status-banner { flex-direction: column; align-items: flex-start; gap: 15px; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="page-header">
        <div class="header-text">
            <h1>Payroll & Disbursements</h1>
            <p>Review and authorize monthly salary payouts for Feb 2026</p>
        </div>
        <div class="header-actions">
            <button class="btn-action btn-secondary"><i class="ph ph-file-pdf"></i> View Payroll Register</button>
            <button class="btn-action" id="masterApproveBtn" onclick="approvePayroll()"><i class="ph ph-bank"></i> Authorize Bank Payout</button>
        </div>
    </div>

    <div class="status-banner" id="approvalBanner">
        <div class="status-text"><i class="ph-fill ph-warning-circle" style="font-size: 20px;"></i> Action Required: Feb 2026 Payroll is drafted by HR/Accounts and pending your final authorization.</div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid var(--theme-color);">
            <div>
                <div class="kpi-label">Net Payout Required</div>
                <div class="kpi-value">₹<?= number_format($payroll_summary['net_payout']) ?></div>
            </div>
            <i class="ph-fill ph-wallet kpi-icon-bg" style="color: var(--theme-color);"></i>
        </div>
        
        <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
            <div>
                <div class="kpi-label">Total Gross Salary</div>
                <div class="kpi-value">₹<?= number_format($payroll_summary['total_gross']) ?></div>
            </div>
            <i class="ph-fill ph-coins kpi-icon-bg" style="color: #3b82f6;"></i>
        </div>

        <div class="kpi-card" style="border-top: 4px solid var(--danger);">
            <div>
                <div class="kpi-label">Total Deductions (PF/Tax)</div>
                <div class="kpi-value">₹<?= number_format($payroll_summary['total_deductions']) ?></div>
            </div>
            <i class="ph-fill ph-scissors kpi-icon-bg" style="color: var(--danger);"></i>
        </div>

        <div class="kpi-card" style="border-top: 4px solid var(--warning);">
            <div>
                <div class="kpi-label">Total Headcount</div>
                <div class="kpi-value"><?= $payroll_summary['headcount'] ?> Employees</div>
            </div>
            <i class="ph-fill ph-users-three kpi-icon-bg" style="color: var(--warning);"></i>
        </div>
    </div>

    <div class="dashboard-split">
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-chart-pie-slice"></i> Dept. Expense Allocation</h3>
            </div>
            <div style="flex-grow: 1; min-height: 250px; position: relative;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="ph ph-users"></i> Employee Payout Preview</h3>
                <a href="#" style="font-size:12px; color:var(--theme-color); text-decoration:none; font-weight: 600;">View All <?= $payroll_summary['headcount'] ?></a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Emp ID & Name</th>
                            <th>Department</th>
                            <th>Bank Details</th>
                            <th class="amt-col">Net Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($employee_payroll as $emp): ?>
                        <tr>
                            <td><strong><?= $emp['name'] ?></strong><br><small style="color:var(--text-muted);"><?= $emp['emp_id'] ?></small></td>
                            <td><?= $emp['dept'] ?></td>
                            <td><?= $emp['bank'] ?><br><small style="color:var(--text-muted);"><?= $emp['acc'] ?></small></td>
                            <td class="amt-col">₹<?= number_format($emp['net']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<script>
    // --- DEPARTMENT DOUGHNUT CHART ---
    const ctxDept = document.getElementById('deptChart').getContext('2d');
    new Chart(ctxDept, {
        type: 'doughnut',
        data: {
            labels: ['IT & Dev', 'Management', 'Sales', 'Support'],
            datasets: [{
                data: [420000, 210000, 150000, 25000],
                backgroundColor: ['#1b5a5a', '#f59e0b', '#3b82f6', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15, font: { size: 11 } } }
            }
        }
    });

    // --- APPROVAL LOGIC ---
    function approvePayroll() {
        if(confirm("Are you sure you want to authorize the bank payout of ₹<?= number_format($payroll_summary['net_payout']) ?>? This action will mark payslips as PAID.")) {
            
            // Visual Updates to UI
            const btn = document.getElementById('masterApproveBtn');
            btn.style.backgroundColor = '#64748b';
            btn.innerHTML = '<i class="ph ph-check-circle"></i> Disbursed';
            btn.disabled = true;

            const banner = document.getElementById('approvalBanner');
            banner.style.backgroundColor = '#dcfce7';
            banner.style.borderLeftColor = '#10b981';
            banner.innerHTML = '<div class="status-text" style="color: #15803d;"><i class="ph-fill ph-check-circle" style="font-size: 20px;"></i> Payroll Approved & Sent to Bank for Disbursal.</div>';
            
            alert("Success! Payroll authorized. In a live system, this triggers API calls to the bank gateway.");
        }
    }
</script>

</body>
</html>