<?php 
// 1. INITIALIZE VARIABLES (Fixes "Undefined variable" errors)
// We set these to 0.00 by default so the page can load even without database data.
$total_credit = 0.00;
$total_debit = 0.00;
$net_balance = 0.00;
$total_entries = 0;

// 2. HANDLE FILTERS (Get values from URL or default to empty)
$filter_from = $_GET['from'] ?? date('Y-m-01');
$filter_to = $_GET['to'] ?? date('Y-m-d');
$filter_bank = $_GET['bank'] ?? 'All';
$filter_cat = $_GET['cat'] ?? 'All';

// 3. MOCK DATA CALCULATION (Replace this with your real SQL Queries later)
// For now, we manually set values so you can see the UI working.
$total_credit = 15000.00; 
$total_debit = 5000.00;
$net_balance = $total_credit - $total_debit;
$total_entries = 12;

// 4. HANDLE FORM SUBMISSION (Mock Logic)
$success_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_entry'])) {
    $success_msg = "Entry saved successfully!";
}

// Include your sidebar and header
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Ledger - Workack</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* BRAND COLORS BASED ON LOGO */
            --theme-color: #1b5a5a; /* Deep Teal (Primary) */
            --theme-light: #e0f2f1; /* Light Teal Background */
            --accent-orange: #e67e22; /* Orange from logo */
            
            --bg-body: #f3f4f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.03);
            
            /* Sidebar Variables */
            --primary-sidebar-width: 95px;
            --secondary-sidebar-width: 220px; 
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT --- */
        .main-content {
            margin-left: var(--primary-sidebar-width);
            padding: 30px;
            width: calc(100% - var(--primary-sidebar-width));
            transition: margin-left 0.3s ease, width 0.3s ease;
            min-height: 100vh;
        }
        
        .main-content.main-shifted {
            margin-left: calc(var(--primary-sidebar-width) + var(--secondary-sidebar-width));
            width: calc(100% - (var(--primary-sidebar-width) + var(--secondary-sidebar-width)));
        }

        /* --- HEADER --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { margin: 0; color: var(--theme-color); font-size: 24px; font-weight: 700; }
        .page-header p { color: var(--text-muted); font-size: 13px; margin: 5px 0 0; }

        /* --- SUMMARY CARDS --- */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { 
            background: white; padding: 25px; border-radius: 12px; 
            box-shadow: var(--card-shadow); border-left: 4px solid var(--theme-color); 
            position: relative; overflow: hidden; transition: transform 0.2s;
        }
        .summary-card:hover { transform: translateY(-2px); }
        
        .summary-card.credit { border-left-color: #059669; }
        .summary-card.debit { border-left-color: #dc2626; }
        .summary-card.balance { border-left-color: #3b82f6; }
        
        .summary-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .sum-value { font-size: 24px; font-weight: 800; margin-top: 5px; position: relative; z-index: 1; }
        .sum-icon { position: absolute; bottom: 15px; right: 15px; font-size: 40px; opacity: 0.1; }

        /* --- FILTER TOOLBAR --- */
        .filter-toolbar { 
            background: white; padding: 20px 25px; border-radius: 12px; 
            margin-bottom: 25px; box-shadow: var(--card-shadow); 
            border: 1px solid var(--border-color); 
        }
        .filter-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .date-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .date-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .date-group input, .date-group select { padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 13px; background: #fff; }
        .date-group input:focus, .date-group select:focus { border-color: var(--theme-color); }
        
        /* UPDATED APPLY BUTTON STYLE */
        .btn-apply { 
            padding: 10px 24px; 
            background: var(--theme-color); /* Matches Logo Teal */
            color: white; 
            border: none; border-radius: 8px; 
            cursor: pointer; font-weight: 600; font-size: 13px; 
            display: flex; align-items: center; gap: 8px; 
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(27, 90, 90, 0.2);
        }
        .btn-apply:hover { background: #134e4e; transform: translateY(-1px); }

        .tab-group { display: flex; gap: 5px; background: #f1f5f9; padding: 5px; border-radius: 8px; }
        .btn-filter { 
            padding: 8px 16px; border-radius: 6px; border: none; background: transparent; 
            font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; 
            color: #64748b; transition: 0.2s;
        }
        .btn-filter.active { 
            background: white; color: var(--theme-color); 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-weight: 700;
        }

        /* --- ENTRY FORM --- */
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border-color); margin-bottom: 25px; }
        .card-title { font-size: 16px; font-weight: 700; color: var(--theme-color); margin: 0 0 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9; }
        
        .entry-header-row { margin-bottom: 25px; }
        .entry-header-row label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .entry-header-row input { padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; width: 250px; font-size: 14px; }

        .entry-row-container { 
            display: grid; grid-template-columns: 100px 1fr 1fr 1fr 140px 140px; 
            align-items: center; gap: 15px; background: #f8fafc; 
            padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 15px; 
        }
        .row-label { font-weight: 700; font-size: 12px; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; }
        .entry-row-container input, .entry-row-container select { 
            padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; 
            outline: none; font-size: 13px; width: 100%;
        }
        .inp-amount { text-align: right; font-weight: 600; }
        
        .btn-save-entry { 
            color: white; border: none; padding: 10px; border-radius: 6px; 
            cursor: pointer; font-weight: 700; font-size: 12px; width: 100%; transition: opacity 0.2s;
        }
        .btn-save-entry:hover { opacity: 0.9; }

        /* --- HISTORY TABLE --- */
        .table-responsive { width: 100%; overflow-x: auto; }
        .history-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .history-table th { 
            text-align: left; padding: 15px; background: #f8fafc; 
            color: var(--text-muted); font-size: 11px; font-weight: 700; 
            text-transform: uppercase; border-bottom: 2px solid #e2e8f0; 
        }
        .history-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--text-main); }
        
        .text-credit { color: #059669; font-weight: 600; }
        .text-debit { color: #dc2626; font-weight: 600; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-expense { background: #fee2e2; color: #dc2626; }
        .badge-invoice { background: #d1fae5; color: #059669; }
        
        .action-icon { font-size: 18px; color: #ef4444; cursor: pointer; transition: transform 0.2s; }
        .action-icon:hover { transform: scale(1.1); }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 1024px) {
            .entry-row-container { grid-template-columns: 1fr; gap: 10px; padding: 15px; }
            .date-group { width: 100%; }
            .filter-row { flex-direction: column; align-items: flex-start; }
            .tab-group { width: 100%; justify-content: space-between; margin-top: 10px; }
            .btn-filter { flex: 1; text-align: center; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
        }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    
    <div class="page-header">
        <div>
            <h2>General Ledger</h2>
            <p>Track all financial transactions and account balances</p>
        </div>
        <div style="text-align: right; font-size: 12px; color: var(--text-muted);">
            Today: <strong><?php echo date('d M, Y'); ?></strong>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card credit">
            <div class="summary-label">Total Credit (In)</div>
            <div class="sum-value" style="color: #059669;">₹<?php echo number_format($total_credit, 2); ?></div>
            <i class="ph ph-arrow-circle-down sum-icon" style="color: #059669;"></i>
        </div>
        <div class="summary-card debit">
            <div class="summary-label">Total Debit (Out)</div>
            <div class="sum-value" style="color: #dc2626;">₹<?php echo number_format($total_debit, 2); ?></div>
            <i class="ph ph-arrow-circle-up sum-icon" style="color: #dc2626;"></i>
        </div>
        <div class="summary-card balance">
            <div class="summary-label">Net Balance</div>
            <div class="sum-value" style="color: <?php echo $net_balance >= 0 ? '#059669' : '#dc2626'; ?>;">
                ₹<?php echo number_format($net_balance, 2); ?>
            </div>
            <i class="ph ph-wallet sum-icon" style="color: #3b82f6;"></i>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Entries</div>
            <div class="sum-value" style="color: var(--theme-color);"><?php echo $total_entries; ?></div>
            <i class="ph ph-list-bullets sum-icon" style="color: var(--theme-color);"></i>
        </div>
    </div>

    <div class="filter-toolbar">
        <div class="filter-row">
            <div class="date-group">
                <label>From:</label>
                <input type="date" id="fromDate" value="<?php echo $filter_from; ?>">
                
                <label>To:</label>
                <input type="date" id="toDate" value="<?php echo $filter_to; ?>">
                
                <label>Bank:</label>
                <select id="filterBank" style="width: 150px;">
                    <option value="All" <?php if($filter_bank == 'All') echo 'selected'; ?>>All Banks</option>
                    <option value="Canara" <?php if($filter_bank == 'Canara') echo 'selected'; ?>>Canara</option>
                    <option value="HDFC" <?php if($filter_bank == 'HDFC') echo 'selected'; ?>>HDFC</option>
                    <option value="ICICI" <?php if($filter_bank == 'ICICI') echo 'selected'; ?>>ICICI</option>
                    <option value="SBI" <?php if($filter_bank == 'SBI') echo 'selected'; ?>>SBI</option>
                </select>

                <button onclick="applyFilter()" class="btn-apply">
                    <i class="ph ph-funnel"></i> Apply Filter
                </button>
            </div>
            
            <div class="tab-group">
                <a href="#" onclick="filterCat('All')" class="btn-filter <?php echo $filter_cat == 'All' ? 'active' : ''; ?>">All</a>
                <a href="#" onclick="filterCat('Expenses')" class="btn-filter <?php echo $filter_cat == 'Expenses' ? 'active' : ''; ?>">Expenses</a>
                <a href="#" onclick="filterCat('Invoice')" class="btn-filter <?php echo $filter_cat == 'Invoice' ? 'active' : ''; ?>">Income</a>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Add Manual Transaction</h3>
        
        <?php if($success_msg): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 13px;">
                <i class="ph ph-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <div class="entry-header-row">
            <label>Transaction Date</label>
            <input type="date" id="commonEntryDate" value="<?php echo date('Y-m-d'); ?>" onchange="syncDates()">
        </div>

        <form method="POST">
            <input type="hidden" name="save_entry" value="1">
            <input type="hidden" name="entry_type" value="Expenses">
            <input type="hidden" name="entry_date" class="hidden-date" value="<?php echo date('Y-m-d'); ?>">
            
            <div class="entry-row-container">
                <div class="row-label" style="color: #dc2626;">Expense (Out)</div>
                <input type="text" name="remarks" placeholder="Description (e.g., Office Rent)" required>
                <input type="text" name="party_name" placeholder="Paid To (e.g., Landlord)" required>
                
                <select name="bank_name" required>
                    <option value="">Select Bank</option>
                    <option value='Canara'>Canara</option>
                    <option value='HDFC'>HDFC</option>
                    <option value='ICICI'>ICICI</option>
                    <option value='SBI'>SBI</option>
                </select>

                <input type="number" step="0.01" name="debit_amount" placeholder="Amount (₹)" class="inp-amount" required>
                <button type="submit" class="btn-save-entry" style="background: #dc2626;">Save Expense</button>
            </div>
        </form>

        <form method="POST">
            <input type="hidden" name="save_entry" value="1">
            <input type="hidden" name="entry_type" value="Invoice">
            <input type="hidden" name="entry_date" class="hidden-date" value="<?php echo date('Y-m-d'); ?>">

            <div class="entry-row-container">
                <div class="row-label" style="color: #059669;">Income (In)</div>
                <input type="text" name="remarks" placeholder="Description (e.g., Project Payment)" required>
                <input type="text" name="party_name" placeholder="Received From (Client Name)" required>
                
                <select name="bank_name" required>
                    <option value="">Select Bank</option>
                    <option value='Canara'>Canara</option>
                    <option value='HDFC'>HDFC</option>
                    <option value='ICICI'>ICICI</option>
                    <option value='SBI'>SBI</option>
                </select>

                <input type="number" step="0.01" name="credit_amount" placeholder="Amount (₹)" class="inp-amount" required>
                <button type="submit" class="btn-save-entry" style="background: #059669;">Save Income</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 class="card-title">Transaction Ledger</h3>
        <div class="table-responsive">
            <table class="history-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 10%;">Type</th>
                        <th style="width: 15%;">Party / Source</th>
                        <th style="width: 10%;">Bank</th>
                        <th style="width: 25%;">Description</th>
                        <th style="width: 10%;">Debit (Out)</th>
                        <th style="width: 10%;">Credit (In)</th>
                        <th style="width: 5%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2026-02-10</td>
                        <td><span class="badge badge-invoice">Income</span></td>
                        <td>Facebook India</td>
                        <td>Canara</td>
                        <td>Project Advance</td>
                        <td class="text-debit">-</td>
                        <td class="text-credit">₹10,000.00</td>
                        <td style="text-align:center;"><i class="ph ph-trash action-icon"></i></td>
                    </tr>
                    <tr>
                        <td>2026-02-09</td>
                        <td><span class="badge badge-expense">Expense</span></td>
                        <td>Office Supplies</td>
                        <td>HDFC</td>
                        <td>Stationery Purchase</td>
                        <td class="text-debit">₹1,500.00</td>
                        <td class="text-credit">-</td>
                        <td style="text-align:center;"><i class="ph ph-trash action-icon"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
    function syncDates() {
        const commonDate = document.getElementById('commonEntryDate').value;
        document.querySelectorAll('.hidden-date').forEach(input => input.value = commonDate);
    }
    
    function applyFilter() {
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;
        const bank = document.getElementById('filterBank').value;
        const urlParams = new URLSearchParams(window.location.search);
        const cat = urlParams.get('cat') || 'All';
        window.location.href = `ledger.php?cat=${cat}&bank=${bank}&from=${from}&to=${to}`;
    }

    function filterCat(category) {
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;
        const bank = document.getElementById('filterBank').value;
        window.location.href = `ledger.php?cat=${category}&bank=${bank}&from=${from}&to=${to}`;
    }

    window.onload = function() { syncDates(); };
</script>

</body>
</html>