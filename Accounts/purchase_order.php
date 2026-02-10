<?php
// 1. SESSION LOGIC (Must be at the very top)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * PURCHASE ORDER MANAGEMENT - VIEW & DELETE ENABLED
 * Color Theme: #1b5a5a
 */

// 2. INITIALIZE HISTORY DATA
if (!isset($_SESSION['po_history'])) {
    $_SESSION['po_history'] = [
        ["po_no" => "PO-IT-2026-205", "vendor" => "Catherine", "contact" => "8956231456", "date" => "30-Jan-2026", "total" => "600.00", "balance" => "400.00"],
        ["po_no" => "PO-IT-2026-168", "vendor" => "Varsh", "contact" => "9856231458", "date" => "29-Jan-2026", "total" => "4000.00", "balance" => "3000.00"],
        ["po_no" => "PO-IT-2026-170", "vendor" => "Dustin", "contact" => "8541236565", "date" => "29-Jan-2026", "total" => "40500.00", "balance" => "500.00"]
    ];
}

// 3. HANDLE DELETE ACTION
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    foreach ($_SESSION['po_history'] as $key => $po) {
        if ($po['po_no'] === $delete_id) {
            unset($_SESSION['po_history'][$key]);
            // Re-index array
            $_SESSION['po_history'] = array_values($_SESSION['po_history']);
            break;
        }
    }
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); // Redirect to clean URL
    exit();
}

// 4. SAVE PO ACTION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_po'])) {
    $new_po = [
        "po_no" => $_POST['po_no_hidden'],
        "vendor" => $_POST['vendor_name'],
        "contact" => $_POST['vendor_contact'],
        "date" => date("d-M-Y", strtotime($_POST['po_date'])),
        "total" => $_POST['grand_total_hidden'],
        "balance" => $_POST['grand_total_hidden'] 
    ];
    array_unshift($_SESSION['po_history'], $new_po); 
    header("Location: " . $_SERVER['PHP_SELF']); 
    exit();
}

$po_history = $_SESSION['po_history'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Management | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1b5a5a; 
            --bg-body: #f4f7f6;
            --white: #ffffff;
        }

        /* 1. GLOBAL RESET */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        body { 
            background-color: var(--bg-body); 
            display: flex; 
            height: 100vh; 
            width: 100%; 
            overflow: hidden; 
        }

        /* 2. LAYOUT CONTROL */
        .sidebar-wrapper {
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0; 
            background: #1e1b4b; 
            z-index: 10;
        }

        .sidebar-wrapper .sidebar, 
        .sidebar-wrapper nav {
            position: relative !important; 
            height: 100% !important;
            margin: 0 !important;
            left: auto !important;
            top: auto !important;
            border-radius: 0 !important; 
        }

        .content-container {
            flex: 1; 
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto; 
            background-color: var(--bg-body);
        }

        /* 3. HEADER & CONTENT */
        .header-wrapper {
            width: 100%;
            background: var(--white);
            z-index: 5;
        }

        .main-content {
            padding: 25px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* 4. CARDS & FORMS */
        .card { 
            background: var(--white); 
            border-radius: 8px; 
            box-shadow: 0 2px 15px rgba(0,0,0,0.05); 
            border: 1px solid #e2e8f0; 
            margin-bottom: 25px; 
            overflow: hidden;
            width: 100%;
        }
        
        .card-header { background: var(--primary-color); color: white; padding: 15px 20px; font-weight: 600; font-size: 16px; margin-bottom: 0; }
        .card-body { padding: 25px; }

        .section-title {
            font-size: 14px; font-weight: 700; color: var(--primary-color);
            margin: 25px 0 15px; padding-bottom: 5px; border-bottom: 2px solid #f1f5f9;
        }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; }
        
        input, select, textarea { 
            padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; 
            width: 100%; font-size: 14px; outline: none; transition: 0.2s;
        }
        input:focus, textarea:focus { border-color: var(--primary-color); }

        .item-desc-box { 
            height: 80px; 
            resize: vertical; 
            padding: 10px;
            font-size: 14px;
        }

        .items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .items-table th { background: #f8fafc; padding: 15px; text-align: left; font-size: 11px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .items-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }

        .btn-add-row { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 13px; margin-bottom: 15px;}
        .btn-save-po { background: var(--primary-color); color: white; padding: 14px 40px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; transition: 0.3s;}
        .btn-save-po:hover { background: #144646; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-remove { background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; }

        .calculation-summary { display: flex; justify-content: flex-end; margin-top: 20px; }
        .summary-box { width: 350px; background: #f0fdfa; padding: 25px; border-radius: 12px; border: 1px solid #ccfbf1; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .summary-row.total { font-weight: 800; color: var(--primary-color); border-top: 1px solid #99f6e4; padding-top: 12px; margin-top: 10px; font-size: 17px; }

        .action-btns i { cursor: pointer; font-size: 20px; margin: 0 8px; color: var(--primary-color); }
        .action-btns a { text-decoration: none; }
        
        /* Modal Styling Fixes */
        .modal-header { background-color: var(--primary-color); color: white; }
        .modal-title { font-weight: 600; font-size: 18px; }
        .btn-close { filter: invert(1); }
    </style>
</head>
<body>

    <div class="sidebar-wrapper">
        <?php include('../sidebars.php'); ?>
    </div>

    <div class="content-container">
        
        <div class="header-wrapper">
            <?php include('../header.php'); ?>
        </div>

        <main class="main-content">
            <div style="margin-bottom: 25px;">
                <h2 style="color: var(--primary-color); font-size: 26px;">Purchase Order Management</h2>
                <p style="color: #64748b; font-size: 14px;">Fill details and save to update history log instantly.</p>
            </div>

            <form method="POST" id="poForm">
                <div class="card">
                    <div class="card-header">Create Purchase Order</div>
                    <div class="card-body">
                        <div class="form-grid">
                            <?php $generated_po = "PO-IT-2026-" . rand(100, 999); ?>
                            <div class="form-group">
                                <label>PO Number</label>
                                <input type="text" value="<?php echo $generated_po; ?>" disabled style="background:#f8fafc;">
                                <input type="hidden" name="po_no_hidden" value="<?php echo $generated_po; ?>">
                            </div>
                            <div class="form-group">
                                <label>PO Date</label>
                                <input type="date" name="po_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Accounting Type</label>
                                <select name="acc_type">
                                    <option>Accounting</option>
                                    <option>Non-Accounting</option>
                                </select>
                            </div>
                        </div>

                        <h3 class="section-title">Vendor & Shipping Details</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Vendor Name</label>
                                <input type="text" name="vendor_name" placeholder="Enter vendor/supplier name" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="vendor_contact" placeholder="+91 9876543210">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="vendor_email" placeholder="vendor@example.com">
                            </div>
                            <div class="form-group">
                                <label>GST Number</label>
                                <input type="text" placeholder="29ABCDE1234F1Z5">
                            </div>
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" placeholder="e.g. HDFC Bank, SBI, ICICI etc.">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="vendor_address" rows="1" style="height: 46px; resize: none;" placeholder="Complete vendor address"></textarea>
                            </div>
                        </div>

                        <h3 class="section-title">Purchase Items</h3>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Description (Detailed)</th>
                                    <th style="width: 100px;">Qty</th>
                                    <th style="width: 130px;">Rate</th>
                                    <th style="width: 160px;">Total</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <tr class="item-row">
                                    <td>1</td>
                                    <td><textarea name="item_desc[]" class="item-desc-box" placeholder="Enter detailed particulars here..."></textarea></td>
                                    <td><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)"></td>
                                    <td><input type="number" name="item_rate[]" class="item-rate" placeholder="0.00" onchange="calculateRow(this)"></td>
                                    <td><input type="number" name="item_total[]" class="item-total" readonly value="0.00" style="background:#fcfcfc;"></td>
                                    <td><button type="button" class="btn-remove" onclick="removeRow(this)">×</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="btn-add-row" onclick="addRow()">+ Add New Item Row</button>

                        <div class="calculation-summary">
                            <div class="summary-box">
                                <div class="summary-row">
                                    <span>Net Total:</span>
                                    <span id="displayNetTotal">₹0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Freight Charges:</span>
                                    <input type="number" id="freight" value="0" style="width:100px; text-align:right;" onchange="calculateGrandTotal()">
                                </div>
                                <div class="summary-row total">
                                    <span>Grand Total:</span>
                                    <span id="displayGrandTotal">₹0.00</span>
                                    <input type="hidden" name="grand_total_hidden" id="grand_total_hidden" value="0.00">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 35px; text-align: right;">
                            <button type="submit" name="save_po" class="btn-save-po">SAVE PURCHASE ORDER</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card">
                <div class="card-header">History Log</div>
                <div class="card-body" style="padding:0;">
                    <table class="items-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th style="padding-left:25px;">PO Number</th>
                                <th>Vendor</th>
                                <th>Contact</th> 
                                <th>Date</th>
                                <th>Grand Total</th>
                                <th>Balance</th>
                                <th style="text-align:right; padding-right:25px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($po_history as $po): ?>
                            <tr>
                                <td style="padding-left:25px;"><strong><?php echo $po['po_no']; ?></strong></td>
                                <td><?php echo $po['vendor']; ?></td>
                                <td style="color:#64748b;"><?php echo isset($po['contact']) ? $po['contact'] : '-'; ?></td>
                                <td><?php echo $po['date']; ?></td>
                                <td>₹ <?php echo number_format((float)$po['total'], 2); ?></td>
                                <td style="color:#ef4444; font-weight:700;">₹ <?php echo number_format((float)$po['balance'], 2); ?></td>
                                <td style="text-align:right; padding-right:25px;" class="action-btns">
                                    <i class="ph ph-eye" onclick="viewDetails('<?php echo $po['po_no']; ?>', '<?php echo $po['vendor']; ?>', '<?php echo $po['total']; ?>')"></i>
                                    <a href="?delete_id=<?php echo $po['po_no']; ?>" onclick="return confirm('Are you sure you want to delete this Purchase Order?');">
                                        <i class="ph ph-trash" style="color: #ef4444;"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Purchase Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">PO Number:</label>
                        <span id="modal-po-no"></span>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Vendor Name:</label>
                        <span id="modal-vendor"></span>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Total Amount:</label>
                        <span id="modal-total" class="fw-bold text-success"></span>
                    </div>
                    <div class="alert alert-info mt-3" style="font-size: 13px;">
                        Additional item details would be fetched here from a database in a live environment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" style="background-color: var(--primary-color); border:none;">Print</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let rowCount = 1;
        function addRow() {
            rowCount++;
            const tbody = document.getElementById('itemsTableBody');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td>${rowCount}</td>
                <td><textarea name="item_desc[]" class="item-desc-box" placeholder="Enter detailed particulars here..."></textarea></td>
                <td><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)"></td>
                <td><input type="number" name="item_rate[]" class="item-rate" placeholder="0.00" onchange="calculateRow(this)"></td>
                <td><input type="number" name="item_total[]" class="item-total" readonly value="0.00" style="background:#fcfcfc;"></td>
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
            document.getElementById('grand_total_hidden').value = grandTotal.toFixed(2);
        }

        // View Details Function
        function viewDetails(poNo, vendor, total) {
            document.getElementById('modal-po-no').innerText = poNo;
            document.getElementById('modal-vendor').innerText = vendor;
            document.getElementById('modal-total').innerText = '₹ ' + total;
            
            var viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
            viewModal.show();
        }
    </script>
</body>
</html>