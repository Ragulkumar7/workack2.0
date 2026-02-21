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
    
    // A. Add New Client via "+" Button
    if ($_POST['action'] === 'add_client') {
        $name = mysqli_real_escape_string($conn, $_POST['client_name']);
        if (mysqli_query($conn, "INSERT INTO clients (client_name) VALUES ('$name')")) {
            echo json_encode(['status' => 'success', 'id' => mysqli_insert_id($conn), 'name' => $name]);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }

    // B. Save Full Invoice and Line Items
    if ($_POST['action'] === 'save_invoice') {
        $invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
        $client_id = intval($_POST['client_id']);
        $bank = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
        $sub_total = floatval($_POST['sub_total']);
        $tax_amt = floatval($_POST['tax_amount']);
        $discount = floatval($_POST['discount']);
        $grand_total = floatval($_POST['grand_total']);

        // Insert Header
        $sql = "INSERT INTO invoices (invoice_no, client_id, bank_name, invoice_date, sub_total, tax_amount, discount, grand_total, status) 
                VALUES ('$invoice_no', $client_id, '$bank', '$date', $sub_total, $tax_amt, $discount, $grand_total, 'Pending Approval')";
        
        if (mysqli_query($conn, $sql)) {
            $last_id = mysqli_insert_id($conn);
            
            // Insert each Item Row
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
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }
}

// 3. FETCH DATA FOR DROPDOWNS AND HISTORY
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
        
        /* Fixed Table Alignment for History Section */
        .history-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .history-table th { background: #f8fafc; font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; }
        .history-table td { font-size: 13px; }
        
        /* Column Width Definitions */
        .col-inv { width: 18%; }
        .col-client { width: 25%; }
        .col-date { width: 15%; }
        .col-amt { width: 15%; }
        .col-status { width: 15%; }
        .col-action { width: 12%; text-align: center !important; }

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
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div class="header-area">
        <div>
            <h2>Invoice Creation</h2>
            <p>Generate and manage account receivables</p>
        </div>
    </div>

    <div class="invoice-card">
        <form id="invoiceForm">
            <div class="invoice-header">
                <div class="form-group">
                    <label>Invoice Number</label>
                    <input type="text" name="invoice_no" value="INV-<?= date('Y-m') ?>-<?= rand(100, 999) ?>" readonly style="background:#f1f5f9;">
                </div>
                
                <div class="form-group">
                    <label>Client Name</label>
                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <select name="client_id" id="client_id" required style="flex:1">
                            <option value="">Select Client</option>
                            <?php while($row = mysqli_fetch_assoc($clients)) { echo "<option value='".$row['id']."'>".$row['client_name']."</option>"; } ?>
                        </select>
                        <button type="button" class="btn-add-client" onclick="document.getElementById('addClientModal').style.display='flex'">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Receiving Bank</label>
                    <input type="text" name="bank_name" list="bank_list" placeholder="Select Bank" required>
                    <datalist id="bank_list">
                        <option value="Canara"><option value="HDFC"><option value="ICICI"><option value="SBI"><option value="Axis"><option value="HSBC">
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label>Invoice Date</label>
                    <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <table class="items-table w-full" style="border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; font-size: 11px; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 12px; text-align: center; width: 5%;">#</th>
                        <th style="padding: 12px; text-align: left;">Description</th>
                        <th style="padding: 12px; width: 10%;">Qty</th>
                        <th style="padding: 12px; width: 15%;">Rate</th>
                        <th style="padding: 12px; width: 10%;">GST %</th>
                        <th style="padding: 12px; width: 15%;">Total</th>
                        <th style="padding: 12px; width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <tr class="item-row">
                        <td style="text-align: center;">1</td>
                        <td><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
                        <td><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
                        <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
                        <td><input type="number" name="item_gst[]" class="item-gst" value="18" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
                        <td><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc"></td>
                        <input type="hidden" name="item_gst_amt[]" class="item-gst-amt">
                        <td style="text-align: center;"><button type="button" onclick="this.closest('tr').remove(); calculateGrandTotal();" style="color:#ef4444; border:none; background:none; cursor:pointer; font-size:18px;">&times;</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="mt-4 text-teal-700 font-bold text-sm" style="background:none; border:none; cursor:pointer;" onclick="addRow()">+ Add New Row</button>

            <div class="summary-box mt-8">
                <div class="summary-row"><span>Subtotal</span><span id="displaySubtotal">₹0.00</span></div>
                <div class="summary-row"><span>Discount</span><input type="number" name="discount" id="discount" value="0" class="w-20 text-right" onchange="calculateGrandTotal()" style="border:1px solid #ddd; border-radius:4px; padding:4px;"></div>
                <div class="summary-row"><span>GST Amount</span><span id="displayTax">₹0.00</span></div>
                <div class="summary-row total"><span>Grand Total</span><span id="displayGrandTotal">₹0.00</span></div>
            </div>

            <input type="hidden" name="sub_total" id="sub_total_hidden">
            <input type="hidden" name="tax_amount" id="tax_amount_hidden">
            <input type="hidden" name="grand_total" id="grand_total_hidden">
            <input type="hidden" name="action" value="save_invoice">

            <div class="flex justify-end gap-3 mt-8">
                <button type="button" style="padding:10px 20px; border-radius:8px; border:1px solid #ddd; background:#fff; cursor:pointer;" onclick="location.reload()">Reset</button>
                <button type="button" onclick="submitInvoice()" id="saveBtn" class="btn-save">
                    <i class="ph-bold ph-paper-plane-right"></i> Send to CFO for Approval
                </button>
            </div>
        </form>
    </div>

    <div class="history-section">
        <h3 style="margin-top:0; margin-bottom:20px; color:var(--text-main);">Recent Invoice History</h3>
        <table class="history-table">
            <thead>
                <tr>
                    <th class="col-inv">Invoice No</th>
                    <th class="col-client">Client</th>
                    <th class="col-date">Date</th>
                    <th class="col-amt">Amount</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($history) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($history)): 
                        $statusClass = ($row['status'] == 'Approved') ? 'badge-approved' : 'badge-pending';
                    ?>
                    <tr>
                        <td class="col-inv"><strong><?= $row['invoice_no'] ?></strong></td>
                        <td class="col-client"><?= htmlspecialchars($row['client_name']) ?></td>
                        <td class="col-date"><?= date('d-m-Y', strtotime($row['invoice_date'])) ?></td>
                        <td class="col-amt">₹<?= number_format($row['grand_total'], 2) ?></td>
                        <td class="col-status"><span class="status-pill <?= $statusClass ?>"><?= $row['status'] ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No invoice records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="addClientModal">
    <div class="modal-content">
        <h3 style="margin-bottom:15px; color:var(--theme-color)">Add New Client</h3>
        <div class="form-group"><label>Client Name</label><input type="text" id="new_client_name" placeholder="Company Name"></div>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" style="padding:8px 15px; border-radius:6px; border:1px solid #ddd; background:#fff; cursor:pointer;" onclick="document.getElementById('addClientModal').style.display='none'">Cancel</button>
            <button type="button" class="btn-save" style="padding:8px 15px;" onclick="saveNewClient()">Save Client</button>
        </div>
    </div>
</div>

<script>
    function addRow() {
        const tbody = document.getElementById('itemsTableBody');
        const rowCount = tbody.children.length + 1;
        const tr = document.createElement('tr');
        tr.className = "item-row";
        tr.innerHTML = `
            <td style="text-align: center;">${rowCount}</td>
            <td><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
            <td><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
            <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
            <td><input type="number" name="item_gst[]" class="item-gst" value="18" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px"></td>
            <td><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc"></td>
            <input type="hidden" name="item_gst_amt[]" class="item-gst-amt">
            <td style="text-align: center;"><button type="button" onclick="this.closest('tr').remove(); calculateGrandTotal();" style="color:#ef4444; border:none; background:none; cursor:pointer; font-size:18px;">&times;</button></td>`;
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
            const rowQty = parseFloat(r.querySelector('.item-qty').value) || 0;
            const rowRate = parseFloat(r.querySelector('.item-rate').value) || 0;
            sub += rowQty * rowRate;
            tax += parseFloat(r.querySelector('.item-gst-amt').value) || 0;
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

    function saveNewClient() {
        const name = document.getElementById('new_client_name').value;
        if(!name) return;
        const fd = new FormData(); fd.append('action', 'add_client'); fd.append('client_name', name);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.status === 'success') {
                const sel = document.getElementById('client_id');
                const opt = new Option(res.name, res.id);
                sel.add(opt); 
                sel.value = res.id;
                document.getElementById('addClientModal').style.display = 'none';
                document.getElementById('new_client_name').value = '';
            }
        });
    }

    function submitInvoice() {
        const btn = document.getElementById('saveBtn'); 
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Processing...';

        const form = document.getElementById('invoiceForm');
        const formData = new FormData(form);
        
        // No delay - execute immediately
        fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(res.status === 'success') { 
                window.location.reload(); 
            } else {
                Swal.fire('Error', data.message || 'Could not save invoice.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="ph-bold ph-paper-plane-right"></i> Send to CFO for Approval';
            }
        }).catch(err => {
            // Force reload on success if json parse fails but row was added
            window.location.reload();
        });
    }
</script>
</body>
</html>