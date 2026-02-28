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

// FIXED: Fetch Salary & Employee Details from 'employee_onboarding' table
$query = "SELECT s.*, 
                 CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as name, 
                 e.emp_id_code as emp_code, 
                 e.designation, 
                 e.phone, 
                 e.email 
          FROM employee_salary s 
          JOIN employee_onboarding e ON s.user_id = e.id 
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

// Function to convert Number to Indian Rupees in Words
function getIndianCurrency($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety');
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? " and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . 'Only';
}

$month_formatted = date('F Y', strtotime($data['salary_month'] . '-01'));
$payslip_no = "#PS" . str_pad($data['id'], 4, '0', STR_PAD_LEFT);
$amount_in_words = getIndianCurrency($data['net_salary']);

// Aggregate minor allowances and deductions for a cleaner layout
$other_allowances = floatval($data['allowance'] ?? 0) + floatval($data['medical'] ?? 0) + floatval($data['others_earnings'] ?? 0);
$other_deductions = floatval($data['leave_deduction'] ?? 0) + floatval($data['professional_tax'] ?? 0) + floatval($data['labour_welfare'] ?? 0) + floatval($data['others_deductions'] ?? 0);

// FIXED: Calculate total deductions for the UI
$total_deductions = floatval($data['tds']) + floatval($data['pf']) + floatval($data['esi']) + $other_deductions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?= htmlspecialchars($data['name']) ?> - <?= $month_formatted ?></title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        :root {
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --primary: #f97316;
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #cbd5e1; 
            margin: 0; 
            padding: 20px 10px; 
            color: var(--text-main);
        }
        
        /* Top Navigation Bar */
        .top-nav {
            max-width: 900px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: flex-start;
        }
        .btn-back {
            background: #fff;
            color: var(--text-main);
            border: 1px solid #94a3b8;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
            text-decoration: none;
        }
        .btn-back:hover { background: #f1f5f9; }

        /* Main Payslip Container */
        .payslip-wrapper { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #fff; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            box-sizing: border-box;
        }
        
        /* Header Section */
        .top-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 35px; border-bottom: 2px solid var(--bg-light); padding-bottom: 20px;}
        .logo-section h1 { margin: 0; color: var(--text-main); font-size: 26px; display: flex; align-items: center; gap: 8px; font-weight: 800;}
        .logo-section h1 span { color: var(--primary); }
        .logo-section p { margin: 8px 0 0; color: var(--text-muted); font-size: 13px; line-height: 1.6; }
        .meta-details { text-align: right; font-size: 14px; }
        .meta-details .ps-no { font-weight: 800; color: var(--text-main); font-size: 16px;}
        .meta-details .ps-no span { color: var(--primary); }
        
        /* Flexbox Address Section (Replaces Grid to fix html2pdf bug) */
        .address-flex { display: flex; justify-content: space-between; gap: 30px; margin-bottom: 30px; }
        .address-block { flex: 1; }
        .address-block h3 { margin: 0 0 10px; font-size: 12px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        .address-block strong { font-size: 16px; color: var(--text-main); display: block; margin-bottom: 6px; }
        .address-block p { margin: 4px 0; font-size: 13px; color: #475569; }
        
        /* Title */
        .payslip-title { text-align: center; font-size: 16px; font-weight: 800; color: var(--text-main); margin: 30px 0 20px 0; padding: 10px; background: var(--bg-light); border-radius: 6px; border: 1px solid var(--border-color);}
        
        /* Flexbox Tables Section (Replaces Grid to fix html2pdf bug) */
        .tables-flex { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 30px; align-items: flex-start; }
        .table-box { flex: 1; border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; width: 48%; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; font-size: 13px; }
        th { background: var(--bg-light); text-align: left; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color); }
        td { border-bottom: 1px solid var(--border-color); color: #475569; }
        td.amount { text-align: right; font-weight: 600; color: var(--text-main); }
        
        tr:last-child td { border-bottom: none; }
        .total-row { background: var(--bg-light); border-top: 2px solid var(--border-color); }
        .total-row td { font-weight: 800; color: var(--text-main); font-size: 14px; }
        
        /* Net Salary Footer */
        .net-salary-box { padding: 20px; font-size: 14px; color: #475569; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; text-align: center; margin-bottom: 40px;}
        .net-salary-box strong { color: #d97706; font-size: 18px; display: block; margin-bottom: 5px;}
        
        /* Signatures */
        .signature-flex { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px;}
        .sig-box { width: 200px; text-align: center; border-top: 1px solid var(--border-color); font-size: 12px; color: var(--text-muted); padding-top: 8px; font-weight: 600;}

        /* Action Buttons */
        .action-buttons { text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center; margin-bottom: 40px;}
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;}
        .btn-print { background: var(--text-main); color: #fff; }
        .btn-download { background: var(--primary); color: #fff; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn:disabled { opacity: 0.7; cursor: wait; transform: none; }

        /* --- PRINT STYLES --- */
        @media print {
            @page { size: A4 portrait; margin: 10mm; } /* Forces 1 page and removes browser URLs/Dates */
            
            body { background: #fff; padding: 0; margin: 0; }
            .payslip-wrapper { box-shadow: none; padding: 0; max-width: 100%; border: none; }
            
            /* Hide UI components during print */
            .top-nav, .action-buttons { display: none !important; }
            
            /* Ensure layout stays intact during print */
            .address-flex, .tables-flex { display: flex !important; flex-direction: row !important; }
            .address-block, .table-box { width: 48% !important; break-inside: avoid; }
            
            th, .total-row { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .net-salary-box { background: #fffbeb !important; border: 1px solid #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        /* Mobile View */
        @media (max-width: 768px) {
            .tables-flex, .address-flex { flex-direction: column; gap: 20px; }
            .table-box, .address-block { width: 100%; }
            .top-header { flex-direction: column; gap: 15px; align-items: center; text-align: center; }
            .meta-details { text-align: center; }
            .signature-flex { flex-direction: column; align-items: center; gap: 40px; }
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <button class="btn-back" onclick="goBack()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </button>
    </div>

    <div class="payslip-wrapper" id="payslip-content">
        
        <div class="top-header">
            <div class="logo-section">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    <span>Work</span>Ack
                </h1>
                <p>9/96 h, Post, Village Nagar, SSKulam<br>Coimbatore, TN 641107</p>
            </div>
            <div class="meta-details">
                <div class="ps-no">Payslip No <span><?= $payslip_no ?></span></div>
                <div style="margin-top: 8px; color: var(--text-muted);">Salary Month: <strong style="color:var(--text-main);"><?= $month_formatted ?></strong></div>
            </div>
        </div>

        <div class="address-flex">
            <div class="address-block">
                <h3>From</h3>
                <strong>Neoera Infotech</strong>
                <p>9/96 h, Post, Village Nagar, SSKulam</p>
                <p>Coimbatore, TN 641107</p>
                <p>Email: info@neoerait.com</p>
            </div>
            <div class="address-block">
                <h3>To</h3>
                <strong><?= htmlspecialchars($data['name']) ?></strong>
                <p><?= htmlspecialchars($data['designation'] ?? 'Employee') ?></p>
                <p>Emp ID: <?= htmlspecialchars($data['emp_code'] ?? 'N/A') ?></p>
                <p>Email: <?= htmlspecialchars($data['email'] ?? 'N/A') ?></p>
                <p>Phone: <?= htmlspecialchars($data['phone'] ?? 'N/A') ?></p>
            </div>
        </div>

        <div class="payslip-title">
            Payslip for the month of <?= $month_formatted ?>
        </div>

        <div class="tables-flex">
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Earnings</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="amount">₹<?= number_format($data['basic'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>House Rent Allowance (H.R.A.)</td>
                            <td class="amount">₹<?= number_format($data['hra'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Conveyance</td>
                            <td class="amount">₹<?= number_format($data['conveyance'], 2) ?></td>
                        </tr>
                        <?php if($other_allowances > 0): ?>
                        <tr>
                            <td>Other Allowances</td>
                            <td class="amount">₹<?= number_format($other_allowances, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if($other_allowances <= 0): ?>
                        <tr><td style="color:transparent; border:none; user-select:none;">-</td><td style="border:none;"></td></tr>
                        <?php endif; ?>

                        <tr class="total-row">
                            <td>Total Earnings</td>
                            <td class="amount">₹<?= number_format($data['gross_salary'], 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Deductions</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tax Deducted at Source (T.D.S.)</td>
                            <td class="amount">₹<?= number_format($data['tds'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Provident Fund (P.F.)</td>
                            <td class="amount">₹<?= number_format($data['pf'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>ESI</td>
                            <td class="amount">₹<?= number_format($data['esi'], 2) ?></td>
                        </tr>
                        <?php if($other_deductions > 0): ?>
                        <tr>
                            <td>Other Deductions / LOP</td>
                            <td class="amount">₹<?= number_format($other_deductions, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if($other_deductions <= 0): ?>
                        <tr><td style="color:transparent; border:none; user-select:none;">-</td><td style="border:none;"></td></tr>
                        <?php endif; ?>

                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td class="amount">₹<?= number_format($total_deductions, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="net-salary-box">
            <strong>₹<?= number_format($data['net_salary'], 2) ?></strong>
            Net Salary: <?= $amount_in_words ?>
        </div>

        <div class="signature-flex">
            <div class="sig-box">Employer Signature</div>
            <div class="sig-box">Employee Signature</div>
        </div>

    </div>

    <div class="action-buttons">
        <button class="btn btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2-2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Payslip
        </button>
        <button class="btn btn-download" id="downloadBtn" onclick="downloadPDF()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Download PDF
        </button>
    </div>

    <script>
        // Go back safely
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close(); // Closes tab if opened via target="_blank"
            }
        }

        // Direct PDF Download Logic (Fixes the missing data bug)
        function downloadPDF() {
            var btn = document.getElementById('downloadBtn');
            var originalText = btn.innerHTML;
            
            // Show loading state
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> Generating...';
            btn.disabled = true;

            var element = document.getElementById('payslip-content');
            var employeeName = "<?= htmlspecialchars($data['name']) ?>".replace(/\s+/g, '_');
            var month = "<?= str_replace(' ', '_', $month_formatted) ?>";
            var filename = "Payslip_" + employeeName + "_" + month + ".pdf";

            // Options optimized for perfect rendering
            var opt = {
                margin:       [10, 10, 10, 10], // Margin in mm
                filename:     filename,
                image:        { type: 'jpeg', quality: 1.0 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Generate and save
            html2pdf().set(opt).from(element).save().then(function() {
                // Restore button state after download
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>