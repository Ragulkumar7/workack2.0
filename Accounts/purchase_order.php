<?php
// 1. SESSION LOGIC MUST BE THE VERY FIRST THING ON THE PAGE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * PURCHASE ORDER MANAGEMENT - UI ONLY
 * Color Theme: #1b5a5a
 */

// Dummy data for the history table
$po_history = [
    ["po_no" => "PO-IT-2026-205", "vendor" => "Catherine", "phone" => "8956231456", "date" => "30-Jan-2026", "total" => "600.00", "paid" => "200.00", "balance" => "400.00", "bank" => "HDFC"],
    ["po_no" => "PO-IT-2026-168", "vendor" => "Varsh", "phone" => "9856231458", "date" => "29-Jan-2026", "total" => "4000.00", "paid" => "1000.00", "balance" => "3000.00", "bank" => "HDFC"],
    ["po_no" => "PO-IT-2026-170", "vendor" => "Dustin", "phone" => "8541236565", "date" => "29-Jan-2026", "total" => "40500.00", "paid" => "40000.00", "balance" => "500.00", "bank" => "ICICI"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Management | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1b5a5a; /* Brand Color */
            --bg-body: #f4f7f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --white: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        body { background-color: var(--bg-body); display: flex; overflow-x: hidden; }

        .main-page-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            margin-left: 260px; /* Sidebar Width */
            display: flex;
            flex-direction: column;
            background: var(--bg-body);
        }

        .main-content {
            padding: 25px;
            margin-top: 60px; /* Header Offset */
        }

        /* --- CARDS --- */
        .card { 
            background: var(--white); 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            border: 1px solid #e2e8f0; 
            margin-bottom: 25px; 
            overflow: hidden;
        }
        
        .card-header { 
            background: var(--primary-color); 
            color: white; 
            padding: 15px 20px; 
            font-size: 16px; 
            font-weight: 600;
        }

        .card-body { padding: 20px; }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 15px 0 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #f1f5f9;
        }

        /* --- FORMS --- */
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 15px; 
        }

        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px; }
        
        input, select, textarea { 
            padding: 8px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            width: 100%; 
            font-size: 13px; 
        }

        /* --- TABLES --- */
        .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .items-table th { background: #f8fafc; padding: 10px; text-align: left; font-size: 10px; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .items-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }

        .btn-add-row { background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .btn-save-po { background: var(--primary-color); color: white; padding: 10px 25px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; }
        .btn-remove { background: #ef4444; color: white; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer; }

        .calculation-summary { display: flex; justify-content: flex-end; margin-top: 15px; }
        .summary-box { width: 300px; background: #f0fdfa; padding: 15px; border-radius: 10px; border: 1px solid #ccfbf1; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
        .summary-row.total { font-weight: 800; color: var(--primary-color); border-top: 1px solid #99f6e4; padding-top: 6px; margin-top: 6px; }

        .action-btns i { cursor: pointer; font-size: 16px; margin: 0 4px; }
        .btn-view { color: var(--primary-color); }
        .btn-delete { color: #ef4444; }

        @media (max-width: 992px) { .content-area { margin-left: 0; } }
    </style>
</head>
<body>

    <div class="main-page-container">
        <?php include('../sidebars.php'); ?>

        <div class="content-area">
            <?php include('../header.php'); ?>

            <main class="main-content">
                <div style="margin-bottom: 20px;">
                    <h2 style="color: var(--primary-color); font-size: 22px;">Purchase Order Management</h2>
                    <p style="color: var(--text-muted); font-size: 12px;">Create and track vendor purchases</p>
                </div>

                <form method="POST">
                    <div class="card">
                        <div class="card-header">New Purchase Order</div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>PO Number</label>
                                    <input type="text" value="PO-IT-2026-<?php echo rand(100, 999); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>PO Date</label>
                                    <input type="date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Accounting Type</label>
                                    <select>
                                        <option>Accounting</option>
                                        <option>Non-Accounting</option>
                                    </select>
                                </div>
                            </div>

                            <h3 class="section-title">Vendor Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Vendor Name</label>
                                    <input type="text" placeholder="Vendor name">
                                </div>
                                <div class="form-group">
                                    <label>GST Number</label>
                                    <input type="text" placeholder="GSTIN">
                                </div>
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" placeholder="Bank Name">
                                </div>
                            </div>

                            <h3 class="section-title">Items</h3>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Description</th>
                                        <th style="width: 80px;">Qty</th>
                                        <th style="width: 100px;">Rate</th>
                                        <th style="width: 120px;">Total</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <tr class="item-row">
                                        <td>1</td>
                                        <td><input type="text" placeholder="Item details"></td>
                                        <td><input type="number" class="item-qty" value="1" onchange="calculateRow(this)"></td>
                                        <td><input type="number" class="item-rate" placeholder="0.00" onchange="calculateRow(this)"></td>
                                        <td><input type="number" class="item-total" readonly placeholder="0.00"></td>
                                        <td><button type="button" class="btn-remove" onclick="removeRow(this)">×</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn-add-row" onclick="addRow()">+ Add Item</button>

                            <div class="calculation-summary">
                                <div class="summary-box">
                                    <div class="summary-row">
                                        <span>Net Total:</span>
                                        <span id="displayNetTotal">₹0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Freight:</span>
                                        <input type="number" id="freight" value="0" style="width:70px; text-align:right;" onchange="calculateGrandTotal()">
                                    </div>
                                    <div class="summary-row total">
                                        <span>Grand Total:</span>
                                        <span id="displayGrandTotal">₹0.00</span>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 20px; text-align: right;">
                                <button type="button" class="btn-save-po">Save PO</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="card">
                    <div class="card-header" style="background:#f8fafc; color: var(--primary-color); border-bottom: 1px solid #e2e8f0;">Recent History</div>
                    <div class="card-body" style="padding:0;">
                        <table class="items-table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th style="padding-left:20px;">PO No</th>
                                    <th>Vendor</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Balance</th>
                                    <th style="text-align:right; padding-right:20px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($po_history as $po): ?>
                                <tr>
                                    <td style="padding-left:20px;"><strong><?php echo $po['po_no']; ?></strong></td>
                                    <td><?php echo $po['vendor']; ?></td>
                                    <td><?php echo $po['date']; ?></td>
                                    <td>₹ <?php echo $po['total']; ?></td>
                                    <td style="color:#ef4444; font-weight:600;">₹ <?php echo $po['balance']; ?></td>
                                    <td style="text-align:right; padding-right:20px;" class="action-btns">
                                        <i class="ph ph-eye btn-view"></i>
                                        <i class="ph ph-trash btn-delete"></i>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        let rowCount = 1;
        function addRow() {
            rowCount++;
            const tbody = document.getElementById('itemsTableBody');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td>${rowCount}</td>
                <td><input type="text" placeholder="Item details"></td>
                <td><input type="number" class="item-qty" value="1" onchange="calculateRow(this)"></td>
                <td><input type="number" class="item-rate" placeholder="0.00" onchange="calculateRow(this)"></td>
                <td><input type="number" class="item-total" readonly placeholder="0.00"></td>
                <td><button type="button" class="btn-remove" onclick="removeRow(this)">×</button></td>
            `;
            tbody.appendChild(row);
        }

        function removeRow(btn) {
            if(document.querySelectorAll('.item-row').length > 1) {
                btn.closest('tr').remove();
                calculateGrandTotal();
            }
        }

        function calculateRow(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const total = qty * rate;
            row.querySelector('.item-total').value = total.toFixed(2);
            calculateGrandTotal();
        }

        function calculateGrandTotal() {
            let netTotal = 0;
            document.querySelectorAll('.item-total').forEach(input => {
                netTotal += parseFloat(input.value) || 0;
            });
            const freight = parseFloat(document.getElementById('freight').value) || 0;
            const grandTotal = netTotal + freight;
            
            document.getElementById('displayNetTotal').textContent = '₹' + netTotal.toFixed(2);
            document.getElementById('displayGrandTotal').textContent = '₹' + grandTotal.toFixed(2);
        }
    </script>
</body>
</html>