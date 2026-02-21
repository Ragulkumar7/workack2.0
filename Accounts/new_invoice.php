<?php 
// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// 2. BACKEND AJAX HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_client') {
        $name = mysqli_real_escape_string($conn, $_POST['client_name']);
        if (mysqli_query($conn, "INSERT INTO clients (client_name) VALUES ('$name')")) {
            echo json_encode(['status' => 'success', 'id' => mysqli_insert_id($conn), 'name' => $name]);
        } else { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($_POST['action'] === 'save_invoice') {
        $invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
        $client_id = intval($_POST['client_id']);
        $bank = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
        $sub_total = floatval($_POST['sub_total']);
        $tax_amt = floatval($_POST['tax_amount']);
        $discount = floatval($_POST['discount']);
        $grand_total = floatval($_POST['grand_total']);

        $sql = "INSERT INTO invoices (invoice_no, client_id, bank_name, invoice_date, sub_total, tax_amount, discount, grand_total, status) 
                VALUES ('$invoice_no', $client_id, '$bank', '$date', $sub_total, $tax_amt, $discount, $grand_total, 'Pending Approval')";
        
        if (mysqli_query($conn, $sql)) {
            $last_id = mysqli_insert_id($conn);
            if(isset($_POST['item_desc']) && is_array($_POST['item_desc'])) {
                for ($i = 0; $i < count($_POST['item_desc']); $i++) {
                    $desc = mysqli_real_escape_string($conn, $_POST['item_desc'][$i]);
                    $qty = intval($_POST['item_qty'][$i]);
                    $rate = floatval($_POST['item_rate'][$i]);
                    $gst = floatval($_POST['item_gst'][$i]);
                    $gst_amt = floatval($_POST['item_gst_amt'][$i]);
                    $total = floatval($_POST['item_total'][$i]);
                    mysqli_query($conn, "INSERT INTO invoice_items (invoice_id, description, qty, rate, gst_rate, gst_amount, total_amount) 
                                         VALUES ($last_id, '$desc', $qty, $rate, $gst, $gst_amt, $total)");
                }
            }
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]); }
        exit;
    }

    // NEW: Fetch full data for printing
    if ($_POST['action'] === 'fetch_invoice_details') {
        $inv_id = intval($_POST['id']);
        $inv_res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = $inv_id");
        $invoice = mysqli_fetch_assoc($inv_res);
        $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
        $items = [];
        while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
        echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items]);
        exit;
    }
}

// 3. FETCH DATA FOR UI
$clients = mysqli_query($conn, "SELECT * FROM clients ORDER BY client_name ASC");
$history = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id ORDER BY i.created_at DESC");

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --theme-light: #e0f2f1; --bg-body: #f3f4f6; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; --card-shadow: 0 4px 20px rgba(0,0,0,0.03); --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; }
        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header-area h2 { margin: 0; color: var(--theme-color); font-weight: 700; }
        .invoice-card, .history-section { background: white; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); border: 1px solid var(--border-color); margin-bottom: 30px; }
        .invoice-header { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none; font-size: 14px; background: #fff; }
        
        .history-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .history-table th { background: #f8fafc; font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; }
        .col-inv { width: 15%; } .col-client { width: 22%; } .col-date { width: 13%; } .col-amt { width: 15%; } .col-status { width: 15%; } .col-action { width: 20%; text-align: center !important; }

        .btn-save { background: var(--theme-color); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .status-pill { background: #f1f5f9; color: #64748b; padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; border: 1px solid #e2e8f0; display: inline-block; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 400px; border-radius: 12px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .summary-box { width: 100%; max-width: 350px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-left: auto; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; align-items: center; }
        .summary-row.total { font-weight: 700; font-size: 16px; color: var(--theme-color); padding-top: 10px; border-top: 2px solid #cbd5e1; }
        .btn-add-client { background: var(--theme-color); color: white; border: none; padding: 0 12px; border-radius: 6px; cursor: pointer; font-weight: bold; height: 40px; margin-top: auto; }
        .badge-approved { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .badge-pending { background: #fef3c7; color: #d97706; border-color: #fde68a; }

        .btn-action-icon { padding: 6px 10px; border-radius: 6px; font-size: 14px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; text-decoration: none; }
        .btn-print { background: #e0f2fe; color: #0369a1; }
        .btn-download { background: #f0fdf4; color: #15803d; }

        /* --- PRINT TEMPLATE STYLES (MATCHES YOUR PDF) --- */
        #printableInvoice { display: none; width: 210mm; padding: 20mm; background: white; font-family: 'Inter', sans-serif; color: #333; line-height: 1.4; }
        @media print {
            body * { visibility: hidden; }
            #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { display: block !important; position: absolute; left: 0; top: 0; }
        }
        .print-header { border-bottom: 2px solid #1b5a5a; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .company-name { font-size: 26px; font-weight: 800; color: #1b5a5a; letter-spacing: 1px; }
        .print-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .print-table th { background: #f1f5f9; text-align: left; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0; }
        .print-table td { padding: 12px; border: 1px solid #e2e8f0; font-size: 13px; }
        .bank-details-box { border: 1px solid #e2e8f0; padding: 15px; border-radius: 4px; background: #fafafa; font-size: 12px; }
        .total-section { float: right; width: 250px; }
        .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .grand-total-row { border-top: 2px solid #1b5a5a; font-weight: 800; font-size: 16px; color: #1b5a5a; margin-top: 5px; }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div class="header-area">
        <div><h2>Invoice Management</h2><p>Generate account receivables matching your official template</p></div>
    </div>

    <div class="invoice-card">
        <form id="invoiceForm">
            <div class="invoice-header">
                <div class="form-group"><label>Invoice Number</label><input type="text" name="invoice_no" value="INV-<?= date('Y-m') ?>-<?= rand(100, 999) ?>" readonly style="background:#f1f5f9;"></div>
                <div class="form-group"><label>Client Name</label><div style="display: flex; gap: 8px; align-items: flex-end;"><select name="client_id" id="client_id" required style="flex:1"><option value="">Select Client</option><?php mysqli_data_seek($clients, 0); while($row = mysqli_fetch_assoc($clients)) { echo "<option value='".$row['id']."'>".$row['client_name']."</option>"; } ?></select><button type="button" class="btn-add-client" onclick="document.getElementById('addClientModal').style.display='flex'">+</button></div></div>
                <div class="form-group"><label>Receiving Bank</label><input type="text" name="bank_name" list="bank_list" placeholder="Select Bank" required><datalist id="bank_list"><option value="South Indian Bank"><option value="ICICI"><option value="SBI"><option value="Axis"></datalist></div>
                <div class="form-group"><label>Invoice Date</label><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <table class="items-table w-full" style="border-collapse: collapse;">
                <thead><tr style="background: #f8fafc; font-size: 11px; text-transform: uppercase; color: var(--text-muted);"><th style="padding:12px; width:5%;">#</th><th style="padding:12px; text-align:left;">Description</th><th style="padding:12px; width:10%;">Qty</th><th style="padding:12px; width:15%;">Rate</th><th style="padding:12px; width:10%;">GST %</th><th style="padding:12px; width:15%;">Total</th><th style="padding:12px; width:5%;"></th></tr></thead>
                <tbody id="itemsTableBody"><tr class="item-row"><td style="text-align: center;">1</td><td><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_gst[]" class="item-gst" value="18" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc"></td><input type="hidden" name="item_gst_amt[]" class="item-gst-amt"><td style="text-align: center;"><button type="button" onclick="this.closest('tr').remove(); calculateGrandTotal();" style="color:#ef4444; border:none; background:none; cursor:pointer;">&times;</button></td></tr></tbody>
            </table>
            <button type="button" class="mt-4 text-teal-700 font-bold text-sm" onclick="addRow()">+ Add New Row</button>
            <div class="summary-box mt-8"><div class="summary-row"><span>Subtotal</span><span id="displaySubtotal">₹0.00</span></div><div class="summary-row"><span>Discount</span><input type="number" name="discount" id="discount" value="0" class="w-20 text-right" onchange="calculateGrandTotal()"></div><div class="summary-row"><span>GST Amount</span><span id="displayTax">₹0.00</span></div><div class="summary-row total"><span>Grand Total</span><span id="displayGrandTotal">₹0.00</span></div></div>
            <input type="hidden" name="sub_total" id="sub_total_hidden"><input type="hidden" name="tax_amount" id="tax_amount_hidden"><input type="hidden" name="grand_total" id="grand_total_hidden"><input type="hidden" name="action" value="save_invoice">
            <div class="flex justify-end gap-3 mt-8"><button type="button" onclick="location.reload()">Reset</button><button type="button" onclick="submitInvoice()" id="saveBtn" class="btn-save"><i class="ph-bold ph-paper-plane-right"></i> Send to CFO for Approval</button></div>
        </form>
    </div>

    <div class="history-section">
        <h3>Recent Invoice History</h3>
        <table class="history-table">
            <thead><tr><th class="col-inv">Invoice No</th><th class="col-client">Client</th><th class="col-date">Date</th><th class="col-amt">Amount</th><th class="col-status">Status</th><th class="col-action">Actions</th></tr></thead>
            <tbody>
                <?php if(mysqli_num_rows($history) > 0): while($row = mysqli_fetch_assoc($history)): 
                    $isApproved = ($row['status'] == 'Approved');
                ?>
                <tr>
                    <td class="col-inv"><strong><?= $row['invoice_no'] ?></strong></td>
                    <td class="col-client"><?= htmlspecialchars($row['client_name']) ?></td>
                    <td class="col-date"><?= $row['invoice_date'] ?></td>
                    <td class="col-amt">₹<?= number_format($row['grand_total'], 2) ?></td>
                    <td class="col-status"><span class="status-pill <?= $isApproved ? 'badge-approved' : 'badge-pending' ?>"><?= $row['status'] ?></span></td>
                    <td class="col-action">
                        <?php if($isApproved): ?>
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <a href="javascript:void(0)" class="btn-action-icon btn-print" onclick="prepareAndPrint('<?= $row['id'] ?>')"><i class="ph ph-printer"></i></a>
                                <a href="javascript:void(0)" class="btn-action-icon btn-download" onclick="prepareAndPrint('<?= $row['id'] ?>')"><i class="ph ph-download-simple"></i></a>
                            </div>
                        <?php else: ?><span style="font-size:11px; color:#94a3b8;">Pending Approval</span><?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="addClientModal">
    <div class="modal-content"><h3 style="margin-bottom:15px; color:var(--theme-color)">Add New Client</h3><div class="form-group"><label>Client Name</label><input type="text" id="new_client_name" placeholder="Company Name"></div><div class="flex justify-end gap-2 mt-4"><button type="button" onclick="document.getElementById('addClientModal').style.display='none'">Cancel</button><button type="button" class="btn-save" onclick="saveNewClient()">Save Client</button></div></div>
</div>

<div id="printableInvoice">
    <div class="print-header">
        <div>
            <div class="company-name">NEOERA INFOTECH</div>
            <div style="font-size: 11px;">9/96 h, Post, Village Nagar, Kurumbapalayam SSKulam<br>Coimbatore, Tamil Nadu 641107 | info@neoerait.com</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 20px; font-weight: 800; color: #1b5a5a;">TAX INVOICE</div>
            <div style="font-size: 12px; margin-top: 5px;">Invoice No: <strong id="p_inv_no"></strong></div>
            <div style="font-size: 12px;">Date: <strong id="p_date"></strong></div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div style="width: 45%;">
            <div style="font-size: 11px; font-weight: 800; color: #1b5a5a; border-bottom: 1px solid #eee; margin-bottom: 8px;">BILLED TO</div>
            <div style="font-size: 14px; font-weight: 800;" id="p_client"></div>
            <div style="font-size: 12px; color: #666;">Client Registration Details</div>
        </div>
        <div style="width: 45%; text-align: right;">
            <div style="font-size: 11px; font-weight: 800; color: #1b5a5a; border-bottom: 1px solid #eee; margin-bottom: 8px;">PAY TO (Bank Details)</div>
            <div style="font-size: 12px;"><strong id="p_bank"></strong><br>A/C: 0663073000000958<br>IFSC: SIBL0000663</div>
        </div>
    </div>

    <table class="print-table">
        <thead><tr><th>S.NO</th><th>PARTICULARS</th><th>QTY</th><th>RATE</th><th>GST %</th><th>TOTAL</th></tr></thead>
        <tbody id="p_items"></tbody>
    </table>

    <div style="overflow: hidden;">
        <div class="total-section">
            <div class="total-row"><span>Sub Total</span><span id="p_sub"></span></div>
            <div class="total-row"><span>GST Amount</span><span id="p_tax"></span></div>
            <div class="total-row grand-total-row"><span>GRAND TOTAL</span><span id="p_grand"></span></div>
        </div>
    </div>

    <div style="margin-top: 100px; display: flex; justify-content: space-between;">
        <div style="text-align: center; width: 200px; border-top: 1px solid #333; font-size: 11px; padding-top: 5px;">Receiver's Signature</div>
        <div style="text-align: center; width: 200px; border-top: 1px solid #333; font-size: 11px; padding-top: 5px;">Authorized Signatory<br><strong>For NEOERA INFOTECH</strong></div>
    </div>
</div>

<script>
    // CORE CALCULATION LOGIC
    function addRow() {
        const tbody = document.getElementById('itemsTableBody');
        const rowCount = tbody.children.length + 1;
        const tr = document.createElement('tr');
        tr.className = "item-row";
        tr.innerHTML = `<td style="text-align:center;">${rowCount}</td><td><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_gst[]" class="item-gst" value="18" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td><td><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc"></td><input type="hidden" name="item_gst_amt[]" class="item-gst-amt"><td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove(); calculateGrandTotal();" style="color:red; background:none; border:none;">&times;</button></td>`;
        tbody.appendChild(tr);
    }

    function calculateRow(input) {
        const row = input.closest('tr');
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        const gst = parseFloat(row.querySelector('.item-gst').value) || 0;
        const sub = qty * rate;
        const gstAmt = (sub * gst) / 100;
        row.querySelector('.item-gst-amt').value = gstAmt.toFixed(2);
        row.querySelector('.item-total').value = (sub + gstAmt).toFixed(2);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let sub = 0, tax = 0;
        document.querySelectorAll('.item-row').forEach(r => {
            sub += (parseFloat(r.querySelector('.item-qty').value)||0) * (parseFloat(r.querySelector('.item-rate').value)||0);
            tax += parseFloat(r.querySelector('.item-gst-amt').value)||0;
        });
        const disc = parseFloat(document.getElementById('discount').value) || 0;
        const total = sub + tax - disc;
        document.getElementById('displaySubtotal').innerText = '₹' + sub.toFixed(2);
        document.getElementById('displayTax').innerText = '₹' + tax.toFixed(2);
        document.getElementById('displayGrandTotal').innerText = '₹' + total.toFixed(2);
        document.getElementById('sub_total_hidden').value = sub.toFixed(2);
        document.getElementById('tax_amount_hidden').value = tax.toFixed(2);
        document.getElementById('grand_total_hidden').value = total.toFixed(2);
    }

    function submitInvoice() {
        const btn = document.getElementById('saveBtn'); btn.disabled = true; btn.innerHTML = 'Sending...';
        fetch('', { method: 'POST', body: new FormData(document.getElementById('invoiceForm')) })
        .then(r => r.json()).then(data => { if(data.status === 'success') location.reload(); });
    }

    // --- PRINTING LOGIC: FETCHES REAL DATA & POPULATES TEMPLATE ---
    function prepareAndPrint(invId) {
        const fd = new FormData(); fd.append('action', 'fetch_invoice_details'); fd.append('id', invId);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const inv = data.invoice;
                document.getElementById('p_inv_no').innerText = inv.invoice_no;
                document.getElementById('p_date').innerText = inv.invoice_date;
                document.getElementById('p_client').innerText = inv.client_name;
                document.getElementById('p_bank').innerText = inv.bank_name;
                document.getElementById('p_sub').innerText = '₹' + parseFloat(inv.sub_total).toFixed(2);
                document.getElementById('p_tax').innerText = '₹' + parseFloat(inv.tax_amount).toFixed(2);
                document.getElementById('p_grand').innerText = '₹' + parseFloat(inv.grand_total).toFixed(2);

                const itemsTable = document.getElementById('p_items');
                itemsTable.innerHTML = '';
                data.items.forEach((it, idx) => {
                    itemsTable.innerHTML += `<tr><td>${idx+1}</td><td>${it.description}</td><td>${it.qty}</td><td>${it.rate}</td><td>${it.gst_rate}%</td><td>${it.total_amount}</td></tr>`;
                });
                
                window.print();
            }
        });
    }

    function saveNewClient() {
        const fd = new FormData(); fd.append('action', 'add_client'); fd.append('client_name', document.getElementById('new_client_name').value);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.status === 'success') {
                const sel = document.getElementById('client_id');
                sel.add(new Option(res.name, res.id)); sel.value = res.id;
                document.getElementById('addClientModal').style.display = 'none';
            }
        });
    }
</script>
</body>
</html>