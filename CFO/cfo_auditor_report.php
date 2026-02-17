<?php
// cfo_auditor_report.php
include '../sidebars.php'; 
include '../header.php';

// Mock Aggregated Ledger Data for Auditor
$financial_year = "2025-2026";
$report_date = date('d F, Y');

$revenue = [
    ['account' => 'Software Sales', 'amount' => 12500000],
    ['account' => 'Consulting Fees', 'amount' => 2500000],
];
$total_revenue = 15000000;

$cogs = [
    ['account' => 'Server & Cloud Hosting', 'amount' => 2500000],
    ['account' => 'Third-Party APIs', 'amount' => 2000000],
];
$total_cogs = 4500000;

$opex = [
    ['account' => 'Employee Payroll', 'amount' => 3500000],
    ['account' => 'Office Rent', 'amount' => 1200000],
    ['account' => 'Marketing & Ads', 'amount' => 800000],
    ['account' => 'Legal & Compliance', 'amount' => 500000],
];
$total_opex = 6000000;

$net_profit = $total_revenue - $total_cogs - $total_opex;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Reports - CFO</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --theme-color: #1b5a5a;
            --bg-body: #f3f4f6;
            --text-main: #1e293b;
            --border: #e2e8f0;
            --primary-width: 95px;
        }

        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); margin: 0; padding: 0; }
        .main-content { margin-left: var(--primary-width); padding: 24px; min-height: 100vh; transition: all 0.3s ease; }

        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: var(--theme-color); margin: 0; }
        
        .btn-export { background: #0f172a; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-export:hover { background: #334155; }

        /* A4 Document Styling */
        .document-wrapper { background: white; max-width: 800px; margin: 0 auto; padding: 40px; border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .doc-header { text-align: center; border-bottom: 2px solid var(--theme-color); padding-bottom: 20px; margin-bottom: 30px; }
        .doc-header h2 { margin: 0 0 5px 0; font-size: 28px; text-transform: uppercase; letter-spacing: 1px; }
        
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px; }
        .report-table th { text-align: left; padding: 10px; border-bottom: 1px solid #cbd5e1; color: #64748b; font-size: 12px; text-transform: uppercase; }
        .report-table td { padding: 10px; border-bottom: 1px dashed #e2e8f0; }
        .report-table .section-title { font-weight: 800; background: #f8fafc; color: var(--theme-color); border-bottom: 1px solid #cbd5e1; padding-top: 15px; }
        .report-table .amt { text-align: right; font-variant-numeric: tabular-nums; }
        
        .total-row td { font-weight: 800; border-top: 2px solid #94a3b8; border-bottom: 2px double #94a3b8; font-size: 15px; }

        .signature-area { display: flex; justify-content: space-between; margin-top: 80px; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-top: 1px solid #000; margin-top: 50px; padding-top: 10px; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Auditor Compliance Report</h1>
            <p style="color: #64748b; margin-top: 5px;">Certified Profit & Loss Statement</p>
        </div>
        <div>
            <button class="btn-export" onclick="downloadPDF()"><i class="ph ph-file-pdf"></i> Generate Certified PDF</button>
        </div>
    </div>

    <div class="document-wrapper" id="pdfDocument">
        <div class="doc-header">
            <h2>Neoera Infotech</h2>
            <p style="margin: 0; color: #64748b;">Statement of Profit & Loss</p>
            <p style="margin: 5px 0 0 0; font-weight: 600;">For the Financial Year: <?= $financial_year ?></p>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th>Particulars</th>
                    <th>Note No.</th>
                    <th class="amt">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="3" class="section-title">I. REVENUE FROM OPERATIONS</td></tr>
                <?php foreach($revenue as $index => $item): ?>
                <tr>
                    <td><?= $item['account'] ?></td>
                    <td style="text-align: center; color: #94a3b8;"><?= $index + 1 ?></td>
                    <td class="amt"><?= number_format($item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row" style="color: #059669;">
                    <td colspan="2">Total Revenue (I)</td>
                    <td class="amt"><?= number_format($total_revenue, 2) ?></td>
                </tr>

                <tr><td colspan="3" class="section-title">II. EXPENSES</td></tr>
                
                <tr><td colspan="3" style="font-style: italic; color: #64748b; padding-left: 15px;">Cost of Goods Sold (COGS)</td></tr>
                <?php foreach($cogs as $item): ?>
                <tr><td style="padding-left: 20px;"><?= $item['account'] ?></td><td></td><td class="amt"><?= number_format($item['amount'], 2) ?></td></tr>
                <?php endforeach; ?>

                <tr><td colspan="3" style="font-style: italic; color: #64748b; padding-left: 15px;">Operating Expenses</td></tr>
                <?php foreach($opex as $item): ?>
                <tr><td style="padding-left: 20px;"><?= $item['account'] ?></td><td></td><td class="amt"><?= number_format($item['amount'], 2) ?></td></tr>
                <?php endforeach; ?>

                <tr class="total-row" style="color: #dc2626;">
                    <td colspan="2">Total Expenses (II)</td>
                    <td class="amt"><?= number_format($total_cogs + $total_opex, 2) ?></td>
                </tr>

                <tr><td colspan="3" style="height: 30px;"></td></tr>
                <tr class="total-row" style="font-size: 18px; color: var(--theme-color);">
                    <td colspan="2">III. PROFIT BEFORE TAX (I - II)</td>
                    <td class="amt">₹<?= number_format($net_profit, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <p style="font-size: 12px; color: #64748b; text-align: justify; line-height: 1.6;">
            <strong>Auditor's Note:</strong> The accompanying notes form an integral part of these financial statements. This statement has been prepared in accordance with the Generally Accepted Accounting Principles (GAAP) and reconciles with the company's general ledger as of <?= $report_date ?>.
        </p>

        <div class="signature-area">
            <div class="sig-box">
                <div class="sig-line">Chief Financial Officer (CFO)</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">Chartered Accountant / Auditor</div>
            </div>
        </div>
    </div>
</main>

<script>
    // Converts the HTML document div directly into a downloadable PDF
    function downloadPDF() {
        const element = document.getElementById('pdfDocument');
        const opt = {
            margin:       0.5,
            filename:     'Auditor_Report_Neoera_2025_2026.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }
</script>

</body>
</html>