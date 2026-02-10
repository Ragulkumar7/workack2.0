<?php
// Changed to sidebars.php to match your existing filename and path
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masters - Bank & Expenses</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1b5a5a;
            --accent-gold: #D4AF37;
            --bg-light: #f8fafc;
            --border: #e4e4e7;
        }

        /* Shift content when sidebar is open */
        .main-content {
            margin-left: 95px; /* Width of Primary Sidebar */
            padding: 30px;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: var(--bg-light);
        }

        /* When handleNavClick adds .main-shifted in JS */
        .main-shifted {
            margin-left: 315px; /* 95px + 220px */
        }

        .header-section {
            margin-bottom: 25px;
        }

        .header-section h2 {
            color: var(--primary-color);
            font-weight: 700;
        }

        /* Responsive Grid */
        .masters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .card h3 {
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        /* Form Styling */
        label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #71717a;
            margin-bottom: 6px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            outline: none;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit:hover {
            background: #144444;
        }

        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f4f4f5;
            text-align: left;
            padding: 12px;
            color: #52525b;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .del-btn {
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .main-content, .main-shifted {
                margin-left: 0;
                padding: 15px;
                padding-top: 20px;
            }
            .masters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<main class="main-content" id="mainContent">
    <div class="header-section">
        <h2>Company Masters</h2>
        <p style="color: #71717a; font-size: 14px;">Setup bank accounts and office expenditure categories.</p>
    </div>

    <div class="masters-grid">
        <div class="card">
            <h3><i class="ph ph-bank"></i> Register Bank</h3>
            <form id="bankForm" onsubmit="event.preventDefault(); addBankUI();">
                <label>Bank Name</label>
                <input type="text" id="bankName" placeholder="e.g. HDFC, SBI..." required>
                <label>Account Number</label>
                <input type="text" id="accNo" placeholder="Enter Account No">
                <button type="submit" class="btn-submit">Register Bank</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="ph ph-list-numbers"></i> Active Accounts</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Bank</th>
                            <th>Acc No</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bankTableBody">
                        <tr>
                            <td><strong>HDFC Bank</strong></td>
                            <td>451236528954</td>
                            <td><i class="ph ph-trash del-btn" onclick="this.closest('tr').remove()"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <h3><i class="ph ph-receipt"></i> Quick Expense Entry</h3>
        <div class="masters-grid" style="grid-template-columns: 1fr 2fr;">
            <form id="expenseForm" onsubmit="event.preventDefault(); addExpenseUI();">
                <label>Date</label>
                <input type="date" id="expDate" value="<?= date('Y-m-d') ?>" required>
                
                <label>Item Description</label>
                <input type="text" id="expItem" placeholder="Printer Paper..." required>
                
                <label>Category</label>
                <select id="catSelect" onchange="checkOther(this)">
                    <option value="Office Supplies">Office Supplies</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Others">Others</option>
                </select>
                
                <div id="otherInput" style="display:none;">
                    <label>Specify Category</label>
                    <input type="text" id="customCat" placeholder="Type category name...">
                </div>

                <label>Amount (₹)</label>
                <input type="number" id="expAmount" placeholder="0.00" required>
                
                <button type="submit" class="btn-submit">Save Expense</button>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="expenseTableBody">
                        <tr>
                            <td>30-Jan-2026</td>
                            <td>Ink Cartridge</td>
                            <td><span style="background:#eefcfd; color:#1b5a5a; padding:3px 8px; border-radius:4px;">Supplies</span></td>
                            <td><strong>₹ 550.00</strong></td>
                            <td><i class="ph ph-trash del-btn" onclick="this.closest('tr').remove()"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    // Handles the "Others" category input field
    function checkOther(select) {
        const otherDiv = document.getElementById('otherInput');
        otherDiv.style.display = (select.value === 'Others') ? 'block' : 'none';
    }

    // Logic to add Bank data to the table visually
    function addBankUI() {
        const name = document.getElementById('bankName').value;
        const acc = document.getElementById('accNo').value;
        const table = document.getElementById('bankTableBody');
        
        const row = `<tr>
            <td><strong>${name}</strong></td>
            <td>${acc || 'N/A'}</td>
            <td><i class="ph ph-trash del-btn" onclick="this.closest('tr').remove()"></i></td>
        </tr>`;
        table.insertAdjacentHTML('afterbegin', row);
        document.getElementById('bankForm').reset();
    }

    // Logic to add Expense data to the table visually
    function addExpenseUI() {
        const date = document.getElementById('expDate').value;
        const item = document.getElementById('expItem').value;
        const amount = document.getElementById('expAmount').value;
        let cat = document.getElementById('catSelect').value;

        if(cat === 'Others') cat = document.getElementById('customCat').value;

        const table = document.getElementById('expenseTableBody');
        const row = `<tr>
            <td>${date}</td>
            <td>${item}</td>
            <td><span style="background:#eefcfd; color:#1b5a5a; padding:3px 8px; border-radius:4px;">${cat}</span></td>
            <td><strong>₹ ${parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong></td>
            <td><i class="ph ph-trash del-btn" onclick="this.closest('tr').remove()"></i></td>
        </tr>`;
        table.insertAdjacentHTML('afterbegin', row);
        document.getElementById('expenseForm').reset();
        document.getElementById('otherInput').style.display = 'none';
    }
</script>

</body>
</html>