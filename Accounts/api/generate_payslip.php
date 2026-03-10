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

// Fetch Salary & Employee Details from 'employee_onboarding' table
$query = "SELECT s.*, 
                 CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as name, 
                 e.emp_id_code as emp_code, 
                 e.designation, 
                 e.department,
                 e.joining_date,
                 e.bank_acc_no,
                 e.pan_no,
                 e.pf_no,
                 e.esi_no,
                 e.salary as ctc,
                 e.salary_type
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
$amount_in_words = getIndianCurrency($data['net_salary']);

// Aggregate minor allowances and deductions
$other_allowances = floatval($data['allowance'] ?? 0) + floatval($data['others_earnings'] ?? 0);
$other_deductions = floatval($data['professional_tax'] ?? 0) + floatval($data['labour_welfare'] ?? 0) + floatval($data['others_deductions'] ?? 0);

$total_deductions = floatval($data['tds']) + floatval($data['pf']) + floatval($data['esi']) + floatval($data['leave_deduction']) + $other_deductions;

// Calculate Fixed Gross (Assuming full month without LOP)
$fixed_basic = $data['basic'] + ($data['leave_deduction'] * 0.5); // Approximation for fixed
$fixed_da = $data['da'] + ($data['leave_deduction'] * 0.4);
$fixed_hra = $data['hra'] + ($data['leave_deduction'] * 0.1);
$fixed_gross = $data['gross_salary'] + $data['leave_deduction'];

// Days calculation
$days_in_month = date('t', strtotime($data['salary_month'] . '-01'));
$lop_days = 0;
// Reverse engineer LOP days if a deduction exists
if($data['leave_deduction'] > 0 && $fixed_gross > 0) {
    $per_day = $fixed_gross / $days_in_month;
    $lop_days = round($data['leave_deduction'] / $per_day, 1);
}
$paid_days = $days_in_month - $lop_days;

// Employer Contributions (Usually equal to employee, adjust if different)
$employer_pf = $data['pf'];
$employer_esi = $data['esi'];
$total_benefits = $employer_pf + $employer_esi;

$earned_ctc = $data['gross_salary'] + $total_benefits;
$fixed_ctc = $fixed_gross + $total_benefits;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?= htmlspecialchars($data['name']) ?> - <?= $month_formatted ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { 
            font-family: 'Times New Roman', Times, serif; /* Classic professional font */
            background: #e2e8f0; 
            margin: 0; 
            padding: 20px 0; 
            color: #000;
        }
        
        .top-nav { max-width: 900px; margin: 0 auto 15px auto; display: flex; justify-content: flex-start; }
        .btn-back { background: #fff; border: 1px solid #94a3b8; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; text-decoration: none; color: #000;}
        .btn-back:hover { background: #f1f5f9; }

        .payslip-wrapper { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #fff; 
            padding: 30px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            box-sizing: border-box;
            border: 1px solid #ccc;
        }
        
        /* Header Section */
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; position: relative; }
        .header img { position: absolute; left: 0; top: 0; height: 60px; }
        .header h2 { margin: 0 0 5px 0; font-size: 20px; font-weight: bold; }
        .header p { margin: 2px 0; font-size: 12px; }
        .header h3 { margin: 10px 0 0 0; font-size: 14px; font-weight: bold; text-transform: uppercase; }

        /* Meta Information Table */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px; }
        .meta-table td { padding: 4px 8px; vertical-align: top; }
        .meta-table .label { width: 20%; font-weight: normal; }
        .meta-table .value { width: 30%; }

        /* Main Salary Table */
        .salary-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 0; }
        .salary-table th, .salary-table td { border: 1px solid #000; padding: 6px 8px; }
        .salary-table th { font-weight: bold; text-align: left; }
        .salary-table .right-align { text-align: right; }
        .salary-table .border-none { border-top: none; border-bottom: none; }
        .salary-table .border-top-none { border-top: none; }
        .salary-table .border-bottom-none { border-bottom: none; }

        /* Footer Section */
        .footer-table { width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #000; border-top: none; margin-bottom: 15px;}
        .footer-table td { padding: 6px 8px; }
        .footer-table .label { width: 25%; }
        .footer-table .val { width: 25%; }
        
        .disclaimer { text-align: center; font-size: 11px; margin-top: 20px; font-style: italic; }

        /* Action Buttons */
        .action-buttons { text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center; margin-bottom: 40px;}
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; transition: 0.2s;}
        .btn-print { background: #1e293b; color: #fff; }
        .btn-download { background: #f97316; color: #fff; }
        .btn:hover { opacity: 0.9; }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff; padding: 0; margin: 0; }
            .payslip-wrapper { box-shadow: none; border: none; padding: 0; }
            .top-nav, .action-buttons { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="javascript:void(0)" class="btn-back" onclick="goBack()">← Back</a>
    </div>

    <div class="payslip-wrapper" id="payslip-content">
        
        <div class="header">
            <img src="../assets/logo.png" alt="Logo" onerror="this.style.display='none'">
            <h2>NEOERA INFOTECH</h2>
            <p>9/96 h, Post, Village Nagar, SSKulam, Coimbatore, Tamil Nadu-641107</p>
            <h3>PAYSLIP FOR THE MONTH OF <?= strtoupper($month_formatted) ?></h3>
        </div>

        <table class="meta-table">
            <tr>
                <td class="label">Employee Code :</td>
                <td class="value"><?= htmlspecialchars($data['emp_code'] ?: 'N/A') ?></td>
                <td class="label">First Name :</td>
                <td class="value"><?= htmlspecialchars($data['name']) ?></td>
            </tr>
            <tr>
                <td class="label">Designation :</td>
                <td class="value"><?= htmlspecialchars($data['designation'] ?: 'N/A') ?></td>
                <td class="label">Department :</td>
                <td class="value"><?= htmlspecialchars($data['department'] ?: 'N/A') ?></td>
            </tr>
            <tr>
                <td class="label">Date of Joining :</td>
                <td class="value"><?= !empty($data['joining_date']) ? date('d/m/Y', strtotime($data['joining_date'])) : 'N/A' ?></td>
                <td class="label">PF ACCOUNT NO. :</td>
                <td class="value"><?= htmlspecialchars($data['pf_no'] ?: 'N/A') ?></td>
            </tr>
            <tr>
                <td class="label">Bank Account Number :</td>
                <td class="value"><?= htmlspecialchars($data['bank_acc_no'] ?: 'N/A') ?></td>
                <td class="label">ESI No. :</td>
                <td class="value"><?= htmlspecialchars($data['esi_no'] ?: 'N/A') ?></td>
            </tr>
            <tr>
                <td class="label">MONTH DAYS :</td>
                <td class="value"><?= number_format($days_in_month, 2) ?></td>
                <td class="label">PAID DAYS :</td>
                <td class="value"><?= number_format($paid_days, 2) ?></td>
            </tr>
            <tr>
                <td class="label">PAN/GIR No.(TDS) :</td>
                <td class="value"><?= htmlspecialchars($data['pan_no'] ?: 'N/A') ?></td>
                <td class="label"></td>
                <td class="value"></td>
            </tr>
        </table>

        <table class="salary-table">
            <tr>
                <th style="width: 35%;">EARNINGS</th>
                <th style="width: 15%; text-align: right;">FIXED</th>
                <th style="width: 15%; text-align: right;">EARNED</th>
                <th style="width: 20%;">DEDUCTIONS</th>
                <th style="width: 15%; text-align: right;">AMOUNT</th>
            </tr>
            <tr>
                <td class="border-bottom-none">BASIC</td>
                <td class="border-bottom-none right-align"><?= number_format($fixed_basic, 2) ?></td>
                <td class="border-bottom-none right-align"><?= number_format($data['basic'], 2) ?></td>
                <td class="border-bottom-none">ESI</td>
                <td class="border-bottom-none right-align"><?= number_format($data['esi'], 2) ?></td>
            </tr>
            <tr>
                <td class="border-none">DA</td>
                <td class="border-none right-align"><?= number_format($fixed_da, 2) ?></td>
                <td class="border-none right-align"><?= number_format($data['da'], 2) ?></td>
                <td class="border-none">PF AMOUNT</td>
                <td class="border-none right-align"><?= number_format($data['pf'], 2) ?></td>
            </tr>
            <tr>
                <td class="border-none">HRA</td>
                <td class="border-none right-align"><?= number_format($fixed_hra, 2) ?></td>
                <td class="border-none right-align"><?= number_format($data['hra'], 2) ?></td>
                <td class="border-none">TDS</td>
                <td class="border-none right-align"><?= number_format($data['tds'], 2) ?></td>
            </tr>
            <tr>
                <td class="border-none">MEDICAL ALLOWANCE</td>
                <td class="border-none right-align"><?= number_format($data['medical'], 2) ?></td>
                <td class="border-none right-align"><?= number_format($data['medical'], 2) ?></td>
                <td class="border-none">LOSS OF PAY (LOP)</td>
                <td class="border-none right-align"><?= number_format($data['leave_deduction'], 2) ?></td>
            </tr>
            <tr>
                <td class="border-top-none">OTHER ALLOWANCE</td>
                <td class="border-top-none right-align"><?= number_format($other_allowances, 2) ?></td>
                <td class="border-top-none right-align"><?= number_format($other_allowances, 2) ?></td>
                <td class="border-top-none">OTHER DEDUCTIONS</td>
                <td class="border-top-none right-align"><?= number_format($other_deductions, 2) ?></td>
            </tr>
            <tr>
                <th>TOTAL GROSS PAY</th>
                <th class="right-align"><?= number_format($fixed_gross, 2) ?></th>
                <th class="right-align"><?= number_format($data['gross_salary'], 2) ?></th>
                <th>DEDUCTION TOTAL</th>
                <th class="right-align"><?= number_format($total_deductions, 2) ?></th>
            </tr>
            <tr>
                <th colspan="2">NET PAY :</th>
                <th colspan="3" class="right-align" style="font-size: 14px;"><?= number_format($data['net_salary'], 2) ?></th>
            </tr>
            <tr>
                <td colspan="5" style="text-align: center; font-weight: bold;">(Rupees <?= $amount_in_words ?>)</td>
            </tr>
        </table>

        <table class="footer-table">
            <tr>
                <td class="label">PF EMPLOYER :</td>
                <td class="val"><?= number_format($employer_pf, 2) ?></td>
                <td class="label">ESI EMPLOYER :</td>
                <td class="val right-align"><?= number_format($employer_esi, 2) ?></td>
            </tr>
            <tr>
                <td class="label">FIXED CTC :</td>
                <td class="val"><?= number_format($fixed_ctc, 2) ?></td>
                <td class="label">TOTAL BENEFITS :</td>
                <td class="val right-align"><?= number_format($total_benefits, 2) ?></td>
            </tr>
            <tr>
                <td class="label">EARNED CTC :</td>
                <td class="val"><?= number_format($earned_ctc, 2) ?></td>
                <td colspan="2"></td>
            </tr>
        </table>

        <div class="disclaimer">
            This is computer generated payslip. Hence signature not required.
        </div>

    </div>

    <div class="action-buttons">
        <button class="btn btn-print" onclick="window.print()">Print Payslip</button>
        <button class="btn btn-download" id="downloadBtn" onclick="downloadPDF()">Download PDF</button>
    </div>

    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        }

        function downloadPDF() {
            var btn = document.getElementById('downloadBtn');
            var originalText = btn.innerHTML;
            btn.innerHTML = 'Generating...';
            btn.disabled = true;

            var element = document.getElementById('payslip-content');
            var employeeName = "<?= htmlspecialchars($data['name']) ?>".replace(/\s+/g, '_');
            var month = "<?= str_replace(' ', '_', $month_formatted) ?>";
            var filename = "Payslip_" + employeeName + "_" + month + ".pdf";

            var opt = {
                margin:       [10, 10, 10, 10],
                filename:     filename,
                image:        { type: 'jpeg', quality: 1.0 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>