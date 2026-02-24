<?php 
// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// 2. BACKEND AJAX HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if(ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        // --- ADD CLIENT WITH NEW FIELDS ---
        if ($_POST['action'] === 'add_client') {
            $name = mysqli_real_escape_string($conn, $_POST['client_name']);
            $gst = mysqli_real_escape_string($conn, $_POST['gst_number'] ?? '');
            $mobile = mysqli_real_escape_string($conn, $_POST['mobile_number'] ?? '');
            $payment = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');

            $insert_sql = "INSERT INTO clients (client_name, gst_number, mobile_number, payment_method) 
                           VALUES ('$name', '$gst', '$mobile', '$payment')";

            if (mysqli_query($conn, $insert_sql)) {
                echo json_encode(['status' => 'success', 'id' => mysqli_insert_id($conn), 'name' => $name]);
            } else { 
                echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]); 
            }
            exit;
        }

        // --- FETCH EXISTING CLIENT DETAILS (AUTO-FILL) ---
        if ($_POST['action'] === 'get_client_details') {
            $cid = intval($_POST['client_id']);
            $res = mysqli_query($conn, "SELECT * FROM clients WHERE id=$cid");
            echo json_encode(mysqli_fetch_assoc($res));
            exit;
        }

        // --- SAVE FULL INVOICE ---
        if ($_POST['action'] === 'save_invoice') {
            $invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
            $client_id = intval($_POST['client_id']);
            $bank = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
            
            // New details from the main form
            $client_mobile = mysqli_real_escape_string($conn, $_POST['client_mobile'] ?? '');
            $client_gst = mysqli_real_escape_string($conn, $_POST['client_gst'] ?? '');
            $payment_mode = mysqli_real_escape_string($conn, $_POST['payment_mode'] ?? '');

            // Update the client's profile
            mysqli_query($conn, "UPDATE clients SET mobile_number='$client_mobile', gst_number='$client_gst', payment_method='$payment_mode' WHERE id=$client_id");
            
            $sub_total = isset($_POST['sub_total']) ? floatval($_POST['sub_total']) : 0.00;
            $total_discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0.00;
            $cgst = isset($_POST['cgst']) ? floatval($_POST['cgst']) : 0.00;
            $sgst = isset($_POST['sgst']) ? floatval($_POST['sgst']) : 0.00;
            $round_off = isset($_POST['round_off']) ? floatval($_POST['round_off']) : 0.00;
            $grand_total = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : 0.00;

            $sql = "INSERT INTO invoices (invoice_no, client_id, bank_name, invoice_date, sub_total, discount, cgst, sgst, round_off, grand_total, status) 
                    VALUES ('$invoice_no', $client_id, '$bank', '$date', $sub_total, $total_discount, $cgst, $sgst, $round_off, $grand_total, 'Pending Approval')";
            
            if (mysqli_query($conn, $sql)) {
                $last_id = mysqli_insert_id($conn);
                
                if(isset($_POST['item_desc']) && is_array($_POST['item_desc'])) {
                    for ($i = 0; $i < count($_POST['item_desc']); $i++) {
                        $desc = mysqli_real_escape_string($conn, $_POST['item_desc'][$i]);
                        $qty = intval($_POST['item_qty'][$i]);
                        $rate = floatval($_POST['item_rate'][$i]);
                        $item_disc = floatval($_POST['item_disc_val'][$i]);
                        $total = floatval($_POST['item_total'][$i]);

                        $item_sql = "INSERT INTO invoice_items (invoice_id, description, qty, rate, discount_amount, total_amount) 
                                     VALUES ($last_id, '$desc', $qty, $rate, $item_disc, $total)";
                        
                        if(!mysqli_query($conn, $item_sql)) {
                             echo json_encode(['status' => 'error', 'message' => "Item Error: " . mysqli_error($conn)]);
                             exit;
                        }
                    }
                }
                echo json_encode(['status' => 'success']);
            } else { 
                echo json_encode(['status' => 'error', 'message' => "Main Insert Error: " . mysqli_error($conn)]); 
            }
            exit;
        }

        // --- FETCH DETAILS FOR PRINTING ---
        if ($_POST['action'] === 'fetch_invoice_details') {
            $inv_id = intval($_POST['id']);
            $inv_res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = $inv_id");
            if(!$inv_res) throw new Exception(mysqli_error($conn));
            $invoice = mysqli_fetch_assoc($inv_res);
            
            $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
            if(!$items_res) throw new Exception(mysqli_error($conn));
            $items = [];
            while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
            
            echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. FETCH UI DATA
$clients = mysqli_query($conn, "SELECT * FROM clients ORDER BY client_name ASC");

// DIRECT LEDGER BALANCE FETCH LOGIC (FIXED)
$history_query = "
    SELECT 
        i.*, 
        c.client_name,
        (COALESCE(inv_totals.total_invoiced, 0) - COALESCE(ldg_totals.total_paid, 0)) AS account_balance
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    LEFT JOIN (
        SELECT client_id, SUM(grand_total) as total_invoiced 
        FROM invoices 
        WHERE status NOT IN ('Draft', 'Rejected')
        GROUP BY client_id
    ) inv_totals ON c.id = inv_totals.client_id
    LEFT JOIN (
        SELECT TRIM(LOWER(party_name)) as p_name, SUM(credit_amount) as total_paid 
        FROM general_ledger 
        WHERE credit_amount > 0 
        GROUP BY TRIM(LOWER(party_name))
    ) ldg_totals ON TRIM(LOWER(c.client_name)) = ldg_totals.p_name
    ORDER BY i.created_at DESC
";
$history = mysqli_query($conn, $history_query);

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Management | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f3f4f6; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; }
        .invoice-card, .history-section { background: white; padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .btn-save { background: var(--theme-color); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .status-pill { background: #f1f5f9; color: #64748b; padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; border: 1px solid #e2e8f0; display: inline-block; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .btn-action-icon { padding: 6px 10px; border-radius: 6px; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; text-decoration: none; }
        .btn-print { background: #e0f2fe; color: #0369a1; } .btn-download { background: #f0fdf4; color: #15803d; }

        /* PRINT TEMPLATE */
        #printableInvoice { display: none; width: 210mm; padding: 20mm; background: white; color: #333; line-height: 1.4; }
        @media print {
            body * { visibility: hidden; } #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { display: block !important; position: absolute; left: 0; top: 0; }
        }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div style="margin-bottom: 25px;">
        <h2 style="color:var(--theme-color); margin:0; font-size: 24px;">Invoice Creation</h2>
        <p style="margin:0; font-size:14px; color:var(--text-muted);">Manage accounts receivable</p>
    </div>

    <div class="invoice-card">
        <form id="invoiceForm">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px;">
                <div class="form-group"><label>Invoice No</label><input type="text" name="invoice_no" value="INV-<?= date('Y-m') ?>-<?= rand(100, 999) ?>" readonly style="background:#f1f5f9;"></div>
                <div class="form-group">
                    <label>Client</label>
                    <div style="display:flex; gap:5px;">
                        <select name="client_id" id="client_id" required style="flex:1">
                            <option value="">Select Client</option>
                            <?php if($clients) { mysqli_data_seek($clients, 0); while($row = mysqli_fetch_assoc($clients)) { echo "<option value='".$row['id']."'>".$row['client_name']."</option>"; } } ?>
                        </select>
                        <button type="button" style="background:var(--theme-color); color:white; border:none; border-radius:6px; padding:0 10px; cursor:pointer;" onclick="document.getElementById('addClientModal').style.display='flex'">
                            <i class="ph-bold ph-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group"><label>Date</label><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Bank</label><input type="text" name="bank_name" list="bank_list" required placeholder="Select Bank"><datalist id="bank_list"><option value="South Indian Bank"><option value="ICICI Bank"><option value="SBI"></datalist></div>
                
                <div class="form-group"><label>Client Mobile</label><input type="text" name="client_mobile" id="client_mobile" placeholder="Mobile Number"></div>
                <div class="form-group"><label>Client GSTIN</label><input type="text" name="client_gst" id="client_gst" placeholder="GST Number"></div>
                <div class="form-group">
                    <label>Payment Mode *</label>
                    <select name="payment_mode" id="payment_mode" required>
                        <option value="">Select Method</option>
                        <option value="UPI">UPI</option>
                        <option value="Cash">Cash</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Bank Transfer">Bank Transfer (NEFT/RTGS)</option>
                    </select>
                </div>
            </div>

            <table style="width:100%; border-collapse:collapse; margin-top:20px;">
                <thead>
                    <tr style="background:#f8fafc; font-size:11px; text-transform:uppercase; color:var(--text-muted); text-align:left;">
                        <th style="padding:12px;">Description</th>
                        <th style="padding:12px; width:10%;">Qty</th>
                        <th style="padding:12px; width:15%;">Rate</th>
                        <th style="padding:12px; width:15%;">Discount (₹)</th>
                        <th style="padding:12px; width:15%;">Total</th>
                        <th style="padding:12px; width:5%;"></th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <tr class="item-row">
                        <td style="padding:5px;"><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td>
                        <td style="padding:5px;"><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td>
                        <td style="padding:5px;"><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td>
                        <td style="padding:5px;"><input type="number" name="item_disc_val[]" class="item-disc" value="0" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td>
                        <td style="padding:5px;"><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc; box-sizing:border-box;"></td>
                        <td style="padding:5px; text-align:center;"><button type="button" onclick="this.closest('tr').remove(); calculateGrandTotal();" style="color:red; background:none; border:none; cursor:pointer; font-size:18px;">&times;</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" onclick="addRow()" style="margin-top:10px; color:var(--theme-color); font-weight:bold; background:none; border:none; cursor:pointer;">+ Add Row</button>

            <div style="width: 100%; max-width: 350px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-left: auto; margin-top:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px;"><span>Subtotal</span><span id="displaySubtotal">₹0.00</span></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px;"><span>Discount</span><span id="displayDiscount">₹0.00</span></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px;"><span>CGST (9%)</span><span id="displayCGST">₹0.00</span></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px;"><span>SGST (9%)</span><span id="displaySGST">₹0.00</span></div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px;"><span>Round Off</span><span id="displayRoundOff">₹0.00</span></div>
                <div style="display:flex; justify-content:space-between; border-top:2px solid #ddd; font-weight:800; padding-top:10px; color:#1b5a5a; font-size:16px;"><span>Grand Total</span><span id="displayGrandTotal">₹0.00</span></div>
            </div>

            <input type="hidden" name="sub_total" id="sub_total_hidden">
            <input type="hidden" name="discount" id="discount_hidden">
            <input type="hidden" name="cgst" id="cgst_hidden">
            <input type="hidden" name="sgst" id="sgst_hidden">
            <input type="hidden" name="round_off" id="round_off_hidden">
            <input type="hidden" name="grand_total" id="grand_total_hidden">
            <input type="hidden" name="action" value="save_invoice">

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:25px;">
                <button type="button" style="padding:10px 20px; border:1px solid #ddd; border-radius:8px; background:white; cursor:pointer; font-weight:600;" onclick="location.reload()">Reset</button>
                <button type="button" onclick="submitInvoice()" id="saveBtn" class="btn-save"><i class="ph-bold ph-paper-plane-right"></i> Send to CFO</button>
            </div>
        </form>
    </div>

    <div class="history-section">
        <h3 style="margin-top:0; color:var(--text-main);">Recent History</h3>
        <table class="history-table">
            <thead>
                <tr style="background:#f8fafc; font-size:11px; text-transform:uppercase; color:var(--text-muted);">
                    <th style="padding:15px;">Invoice No</th>
                    <th style="padding:15px;">Client</th>
                    <th style="padding:15px;">Date</th>
                    <th style="padding:15px;">Amount</th>
                    <th style="padding:15px; color:#ef4444;">Net Pending Balance</th>
                    <th style="padding:15px;">Status</th>
                    <th style="padding:15px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($history): while($row = mysqli_fetch_assoc($history)): $isApp = ($row['status'] == 'Approved' || $row['status'] == 'Paid'); ?>
                <tr>
                    <td style="padding:15px;"><strong><?= $row['invoice_no'] ?></strong></td>
                    <td style="padding:15px;"><?= htmlspecialchars($row['client_name']) ?></td>
                    <td style="padding:15px;"><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                    <td style="padding:15px;">₹<?= number_format($row['grand_total'], 2) ?></td>
                    <td style="padding:15px; color:#ef4444; font-weight:700;">₹<?= number_format($row['account_balance'], 2) ?></td>
                    <td style="padding:15px;"><span class="status-pill <?= $isApp ? 'badge-approved' : '' ?>"><?= $row['status'] ?></span></td>
                    <td style="padding:15px; text-align:center;">
                        <?php if($isApp): ?>
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <button onclick="prepareAndPrint('<?= $row['id'] ?>')" class="btn-action-icon btn-print"><i class="ph-bold ph-printer"></i></button>
                            </div>
                        <?php else: ?>
                            <span style="color:#94a3b8; font-size:12px;">Waiting Approval</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="addClientModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:white; padding:25px; border-radius:12px; width:400px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color:var(--theme-color); font-size:18px; margin-bottom: 20px;">Add New Client</h3>
        
        <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Client Name *</label>
        <input type="text" id="new_client_name" placeholder="E.g., Facebook India" style="width:100%; border:1px solid #ddd; padding:10px; border-radius:6px; margin-bottom:15px; box-sizing:border-box;" required>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
            <div>
                <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">GST Number</label>
                <input type="text" id="new_client_gst" placeholder="Optional" style="width:100%; border:1px solid #ddd; padding:10px; border-radius:6px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Mobile Number</label>
                <input type="text" id="new_client_mobile" placeholder="Mobile" style="width:100%; border:1px solid #ddd; padding:10px; border-radius:6px; box-sizing:border-box;">
            </div>
        </div>

        <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Preferred Payment Method</label>
        <select id="new_client_payment" style="width:100%; border:1px solid #ddd; padding:10px; border-radius:6px; margin-bottom:25px; box-sizing:border-box;">
            <option value="">Select Method</option>
            <option value="UPI">UPI</option>
            <option value="Cash">Cash</option>
            <option value="Debit Card">Debit Card</option>
            <option value="Credit Card">Credit Card</option>
            <option value="Bank Transfer">Bank Transfer (NEFT/RTGS)</option>
        </select>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button onclick="document.getElementById('addClientModal').style.display='none'" style="padding:10px 15px; border-radius:8px; border:1px solid #ddd; background:white; cursor:pointer; font-weight:600;">Cancel</button>
            <button onclick="saveNewClient()" style="background:var(--theme-color); color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:600;">Save Client</button>
        </div>
    </div>
</div>

<div id="printableInvoice">
    <div style="border-bottom:2px solid #1b5a5a; display:flex; justify-content:space-between; padding-bottom:15px; margin-bottom:20px;">
        <div><div style="font-size:26px; font-weight:800; color:#1b5a5a;">NEOERA INFOTECH</div><div style="font-size:11px;">9/96 h, Post, Village Nagar, Coimbatore 641107</div></div>
        <div style="text-align:right;"><div style="font-size:20px; font-weight:800; color:#1b5a5a;">TAX INVOICE</div><div style="font-size:12px;">No: <strong id="p_inv_no"></strong></div><div style="font-size:12px;">Date: <strong id="p_date"></strong></div></div>
    </div>
    <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
        <div style="width:45%; border-bottom:1px solid #eee; padding-bottom:10px;"><strong style="color:#1b5a5a; font-size:11px;">BILLED TO</strong><div id="p_client" style="font-size:16px; font-weight:800; padding-top:5px;"></div></div>
        <div style="width:45%; text-align:right; border-bottom:1px solid #eee; padding-bottom:10px;"><strong style="color:#1b5a5a; font-size:11px;">BANK DETAILS</strong><div style="font-size:12px; padding-top:5px;">South Indian Bank<br>A/C: 0663073000000958<br>IFSC: SIBL0000663</div></div>
    </div>
    <table style="width:100%; border-collapse:collapse; margin:20px 0;">
        <thead><tr style="background:#f1f5f9; text-align:left; font-size:12px;"><th style="border:1px solid #ddd; padding:10px;">PARTICULARS</th><th style="border:1px solid #ddd; padding:10px; text-align:center;">QTY</th><th style="border:1px solid #ddd; padding:10px; text-align:right;">RATE</th><th style="border:1px solid #ddd; padding:10px; text-align:right;">DISC (₹)</th><th style="border:1px solid #ddd; padding:10px; text-align:right;">TOTAL</th></tr></thead>
        <tbody id="p_items"></tbody>
    </table>
    <div style="float:right; width:220px;">
        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>Sub Total</span><span id="p_sub"></span></div>
        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>Discount</span><span id="p_disc"></span></div>
        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>CGST (9%)</span><span id="p_cgst"></span></div>
        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>SGST (9%)</span><span id="p_sgst"></span></div>
        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>Round Off</span><span id="p_roff"></span></div>
        <div style="display:flex; justify-content:space-between; border-top:2px solid #1b5a5a; font-weight:800; font-size:16px; margin-top:5px; padding-top:5px; color:#1b5a5a;"><span>GRAND TOTAL</span><span id="p_grand"></span></div>
    </div>
</div>

<script>
    // AUTO-FILL LOGIC: Fetch client details when selected
    document.getElementById('client_id').addEventListener('change', function() {
        if(this.value) {
            const fd = new FormData();
            fd.append('action', 'get_client_details');
            fd.append('client_id', this.value);
            
            fetch('', {method: 'POST', body: fd})
            .then(r => r.json())
            .then(data => {
                if(data) {
                    document.getElementById('client_mobile').value = data.mobile_number || '';
                    document.getElementById('client_gst').value = data.gst_number || '';
                    document.getElementById('payment_mode').value = data.payment_method || '';
                }
            });
        } else {
            document.getElementById('client_mobile').value = '';
            document.getElementById('client_gst').value = '';
            document.getElementById('payment_mode').value = '';
        }
    });

    function addRow() {
        const tbody = document.getElementById('itemsTableBody');
        const tr = document.createElement('tr');
        tr.className = "item-row";
        tr.innerHTML = `<td style="padding:5px;"><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_disc_val[]" class="item-disc" value="0" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc; box-sizing:border-box;"></td><td style="padding:5px; text-align:center;"><button type="button" onclick="this.closest('tr').remove(); calculateGrandTotal();" style="color:red; background:none; border:none; cursor:pointer; font-size:18px;">&times;</button></td>`;
        tbody.appendChild(tr);
    }

    function calculateRow(input) {
        const row = input.closest('tr');
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        const disc = parseFloat(row.querySelector('.item-disc').value) || 0;
        row.querySelector('.item-total').value = ((qty * rate) - disc).toFixed(2);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let subtotal = 0, totalDisc = 0;
        document.querySelectorAll('.item-row').forEach(r => {
            subtotal += (parseFloat(r.querySelector('.item-qty').value)||0) * (parseFloat(r.querySelector('.item-rate').value)||0);
            totalDisc += parseFloat(r.querySelector('.item-disc').value)||0;
        });
        
        const taxable = subtotal - totalDisc;
        const cgst = taxable * 0.09; const sgst = taxable * 0.09;
        const exact = taxable + cgst + sgst;
        const grand = Math.round(exact);
        const roff = grand - exact;

        document.getElementById('displaySubtotal').innerText = '₹' + subtotal.toFixed(2);
        document.getElementById('displayDiscount').innerText = '₹' + totalDisc.toFixed(2);
        document.getElementById('displayCGST').innerText = '₹' + cgst.toFixed(2);
        document.getElementById('displaySGST').innerText = '₹' + sgst.toFixed(2);
        document.getElementById('displayRoundOff').innerText = '₹' + roff.toFixed(2);
        document.getElementById('displayGrandTotal').innerText = '₹' + grand.toFixed(2);

        document.getElementById('sub_total_hidden').value = subtotal.toFixed(2);
        document.getElementById('discount_hidden').value = totalDisc.toFixed(2);
        document.getElementById('cgst_hidden').value = cgst.toFixed(2);
        document.getElementById('sgst_hidden').value = sgst.toFixed(2);
        document.getElementById('round_off_hidden').value = roff.toFixed(2);
        document.getElementById('grand_total_hidden').value = grand;
    }

    function submitInvoice() {
        const btn = document.getElementById('saveBtn'); 
        btn.disabled = true; 
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Saving...';
        
        fetch('', { method: 'POST', body: new FormData(document.getElementById('invoiceForm')) })
        .then(async response => {
            const textResponse = await response.text();
            try {
                const data = JSON.parse(textResponse);
                if(data.status === 'success') {
                    Swal.fire({title: 'Success!', text: 'Invoice created & sent for approval', icon: 'success', timer: 1500, showConfirmButton: false})
                    .then(() => window.location.reload());
                } else {
                    Swal.fire('Database Error', data.message, 'error');
                    btn.disabled = false; btn.innerHTML = '<i class="ph-bold ph-paper-plane-right"></i> Send to CFO';
                }
            } catch (err) {
                Swal.fire('Schema Error', 'Please check DB setup. System says: ' + textResponse.substring(0, 100), 'error');
                btn.disabled = false; btn.innerHTML = '<i class="ph-bold ph-paper-plane-right"></i> Send to CFO';
            }
        }).catch(err => {
            Swal.fire('Network Error', 'Connection failed.', 'error');
            btn.disabled = false; btn.innerHTML = '<i class="ph-bold ph-paper-plane-right"></i> Send to CFO';
        });
    }

    function saveNewClient() {
        const name = document.getElementById('new_client_name').value;
        if (!name) { alert("Client Name is required!"); return; }

        const fd = new FormData(); 
        fd.append('action', 'add_client'); 
        fd.append('client_name', name);
        fd.append('gst_number', document.getElementById('new_client_gst').value);
        fd.append('mobile_number', document.getElementById('new_client_mobile').value);
        fd.append('payment_method', document.getElementById('new_client_payment').value);

        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => { 
            if(res.status === 'success') {
                const sel = document.getElementById('client_id');
                sel.add(new Option(res.name, res.id)); 
                sel.value = res.id;
                document.getElementById('addClientModal').style.display = 'none';
                
                document.getElementById('new_client_name').value = '';
                document.getElementById('new_client_gst').value = '';
                document.getElementById('new_client_mobile').value = '';
                document.getElementById('new_client_payment').value = '';
                
                // Auto-fill the main form with what we just typed
                document.getElementById('client_mobile').value = fd.get('mobile_number');
                document.getElementById('client_gst').value = fd.get('gst_number');
                document.getElementById('payment_mode').value = fd.get('payment_method');
                
            } else {
                alert("Error saving client: " + res.message);
            }
        });
    }

    function prepareAndPrint(id) {
        const fd = new FormData(); fd.append('action', 'fetch_invoice_details'); fd.append('id', id);
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const inv = data.invoice;
                document.getElementById('p_inv_no').innerText = inv.invoice_no;
                document.getElementById('p_date').innerText = inv.invoice_date;
                document.getElementById('p_client').innerText = inv.client_name;
                document.getElementById('p_sub').innerText = '₹' + parseFloat(inv.sub_total).toFixed(2);
                document.getElementById('p_disc').innerText = '₹' + parseFloat(inv.discount).toFixed(2);
                document.getElementById('p_cgst').innerText = '₹' + parseFloat(inv.cgst).toFixed(2);
                document.getElementById('p_sgst').innerText = '₹' + parseFloat(inv.sgst).toFixed(2);
                document.getElementById('p_roff').innerText = '₹' + parseFloat(inv.round_off).toFixed(2);
                document.getElementById('p_grand').innerText = '₹' + parseFloat(inv.grand_total).toFixed(0);
                
                const table = document.getElementById('p_items'); table.innerHTML = '';
                data.items.forEach(it => { 
                    table.innerHTML += `<tr>
                        <td style="border:1px solid #ddd; padding:10px; font-size:13px;">${it.description}</td>
                        <td style="border:1px solid #ddd; padding:10px; font-size:13px; text-align:center;">${it.qty}</td>
                        <td style="border:1px solid #ddd; padding:10px; font-size:13px; text-align:right;">${it.rate}</td>
                        <td style="border:1px solid #ddd; padding:10px; font-size:13px; text-align:right;">${it.discount_amount}</td>
                        <td style="border:1px solid #ddd; padding:10px; font-size:13px; text-align:right;">${it.total_amount}</td>
                    </tr>`; 
                });
                window.print();
            }
        });
    }
</script>
</body>
</html>