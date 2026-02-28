<?php 
// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// Neoera Infotech Default Details (Used ONLY for Printing)
$company_details = [
    'name' => 'Neoera infotech',
    'address' => '9/96 h, post, village nagar, Kurumbapalayam SSKulam, coimbatore, Tamil Nadu 641107',
    'phone' => '+91 866 802 5451',
    'email' => 'Contact@neoerainfotech.com',
    'website' => 'www.neoerainfotech.com',
    'logo' => '../assets/neoera.png' // Corrected Logo path
];

// 2. BACKEND AJAX HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if(ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        // --- SAVE FULL INVOICE ---
        if ($_POST['action'] === 'save_invoice') {
            $invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
            $client_id = intval($_POST['client_id']);
            $date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
            
            // Bank Details
            $client_bank_info = substr(mysqli_real_escape_string($conn, $_POST['hidden_client_bank_details'] ?? ''), 0, 250);
            $company_bank_id = intval($_POST['company_bank_id'] ?? 0);
            
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            $terms = "THANK YOU FOR YOUR BUSINESS!"; // Hardcoded for print

            $sub_total = isset($_POST['final_sub_total']) ? floatval($_POST['final_sub_total']) : 0.00;
            $total_discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0.00;
            $cgst = isset($_POST['cgst']) ? floatval($_POST['cgst']) : 0.00;
            $sgst = isset($_POST['sgst']) ? floatval($_POST['sgst']) : 0.00;
            $round_off = isset($_POST['round_off']) ? floatval($_POST['round_off']) : 0.00;
            $grand_total = isset($_POST['final_grand_total']) ? floatval($_POST['final_grand_total']) : 0.00;

            // SAFE SCHEMA CHECK: Automatically adds CGST, SGST if missing so the query won't crash
            mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN IF NOT EXISTS company_bank_id INT DEFAULT 0");
            mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN IF NOT EXISTS cgst DECIMAL(15,2) DEFAULT 0.00");
            mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN IF NOT EXISTS sgst DECIMAL(15,2) DEFAULT 0.00");
            mysqli_query($conn, "ALTER TABLE invoices MODIFY bank_name VARCHAR(255)");
            
            // CORRECTED INSERT QUERY: Removed 'client_name' and 'due_date' as they don't exist in your table structure
            $sql = "INSERT INTO invoices (invoice_no, client_id, bank_name, company_bank_id, invoice_date, sub_total, discount, cgst, sgst, round_off, grand_total, status, notes, terms, created_at) 
                    VALUES ('$invoice_no', $client_id, '$client_bank_info', $company_bank_id, '$date', $sub_total, $total_discount, $cgst, $sgst, $round_off, $grand_total, 'Pending Approval', '$notes', '$terms', NOW())";
            
            if (mysqli_query($conn, $sql)) {
                $last_id = mysqli_insert_id($conn);
                
                if(isset($_POST['item_desc']) && is_array($_POST['item_desc'])) {
                    for ($i = 0; $i < count($_POST['item_desc']); $i++) {
                        $desc = mysqli_real_escape_string($conn, $_POST['item_desc'][$i]);
                        $qty = intval($_POST['item_qty'][$i]);
                        $rate = floatval($_POST['item_rate'][$i]);
                        $item_disc = floatval($_POST['item_disc_val'][$i]);
                        $total = floatval($_POST['item_total'][$i]);

                        if(!empty($desc)) {
                            // CORRECTED ITEMS QUERY: Removed 'tax_percentage' and 'tax_amount' columns to match your exact DB structure
                            $item_sql = "INSERT INTO invoice_items (invoice_id, description, qty, rate, discount_amount, total_amount) 
                                         VALUES ($last_id, '$desc', $qty, $rate, $item_disc, $total)";
                            mysqli_query($conn, $item_sql);
                        }
                    }
                }
                echo json_encode(['status' => 'success']);
            } else { 
                echo json_encode(['status' => 'error', 'message' => "Insert Error: " . mysqli_error($conn)]); 
            }
            exit;
        }

        // --- FETCH DETAILS FOR PRINTING ---
        if ($_POST['action'] === 'fetch_invoice_details') {
            $inv_id = intval($_POST['id']);
            
            $inv_res = mysqli_query($conn, "SELECT i.*, c.client_name, c.company_name, c.mobile_number, c.gst_number as c_gst 
                                            FROM invoices i 
                                            JOIN clients c ON i.client_id = c.id 
                                            WHERE i.id = $inv_id");
            if(!$inv_res) throw new Exception(mysqli_error($conn));
            $invoice = mysqli_fetch_assoc($inv_res);
            
            $company_bank = null;
            if(!empty($invoice['company_bank_id'])) {
                $check_cb = mysqli_query($conn, "SHOW TABLES LIKE 'company_banks'");
                if(mysqli_num_rows($check_cb) > 0) {
                    $cb_res = mysqli_query($conn, "SELECT * FROM company_banks WHERE id = " . $invoice['company_bank_id']);
                    $company_bank = mysqli_fetch_assoc($cb_res);
                }
            }
            
            $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
            $items = [];
            while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
            
            echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items, 'company_bank' => $company_bank]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. FETCH UI DATA (RELAXED CONDITION & SAFE QUERY)
$clients_query = mysqli_query($conn, "SELECT * FROM clients ORDER BY client_name ASC");
$all_clients = [];
if($clients_query) {
    while($row = mysqli_fetch_assoc($clients_query)) {
        $banks = [];
        $b_name = isset($row['bank_name']) ? trim($row['bank_name']) : '';
        $a_num = isset($row['account_number']) ? trim($row['account_number']) : '';
        $ifsc = isset($row['ifsc_code']) ? trim($row['ifsc_code']) : '';
        
        if ($b_name !== '' || $a_num !== '' || $ifsc !== '') {
            $display_text = ($b_name ?: 'Bank') . ($a_num ? " - " . $a_num : '');
            $details_text = "Bank: " . ($b_name ?: 'N/A') . " | A/C: " . ($a_num ?: 'N/A') . " | IFSC: " . ($ifsc ?: 'N/A');
            
            $banks[] = [
                'bank_name' => $b_name,
                'account_number' => $a_num,
                'ifsc_code' => $ifsc,
                'display' => $display_text,
                'details' => $details_text
            ];
        }
        $row['banks_list'] = $banks;
        $all_clients[] = $row;
    }
}

$all_services = [];
$check_srv = mysqli_query($conn, "SHOW TABLES LIKE 'crm_services'");
if(mysqli_num_rows($check_srv) > 0) {
    $services_query = mysqli_query($conn, "SELECT service_id, service_name, rate FROM crm_services");
    if($services_query) { while($row = mysqli_fetch_assoc($services_query)) { $all_services[] = $row; } }
}

$all_company_banks = [];
$check_cb = mysqli_query($conn, "SHOW TABLES LIKE 'company_banks'");
if(mysqli_num_rows($check_cb) > 0) {
    $co_banks_query = mysqli_query($conn, "SELECT id, bank_name, account_number, ifsc_code, phone_number FROM company_banks");
    if($co_banks_query) { while($row = mysqli_fetch_assoc($co_banks_query)) { $all_company_banks[] = $row; } }
}

$inv_query = mysqli_query($conn, "SELECT id FROM invoices ORDER BY id DESC LIMIT 1");
$last_id = mysqli_fetch_assoc($inv_query)['id'] ?? 0;
$next_id = $last_id + 1;
$financial_year = date('y') . '-' . (date('y') + 1);
$invoice_number = "WS/INV/" . str_pad($next_id, 4, '0', STR_PAD_LEFT) . "/" . $financial_year;

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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f3f4f6; --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; box-sizing: border-box;}
        .invoice-card, .history-section { background: white; padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px;}
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 13px; outline: none;}
        .form-group input:focus, .form-group select:focus { border-color: var(--theme-color); box-shadow: 0 0 0 2px rgba(27,90,90,0.1); }
        input[readonly] { background-color: #f8fafc; color: #64748b; cursor: not-allowed; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: #f8fafc; padding: 12px; text-align: left; font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        .items-table td { padding: 10px 5px; vertical-align: top; border-bottom: 1px solid #f1f5f9;}
        
        /* Totals */
        .total-box { width: 330px; margin-left: auto; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); }
        .total-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 14px; color: #475569; font-weight: 600;}
        .total-row.grand { border-top: 2px solid #ddd; padding-top: 10px; font-weight: 800; color: var(--theme-color); font-size: 16px; margin-bottom: 0;}
        
        .btn-save { background: var(--theme-color); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 15px; transition: 0.2s;}
        .btn-save:hover { background: #144444; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(27,90,90,0.2);}
        .btn-add-item { background: #e0f2fe; color: #0369a1; border: 1px dashed #0369a1; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 15px; transition: 0.2s;}
        .btn-add-item:hover { background: #bae6fd; }

        /* History Table */
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .status-pill { background: #f1f5f9; color: #64748b; padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; border: 1px solid #e2e8f0; display: inline-block; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .btn-print { padding: 8px 12px; border-radius: 6px; font-size: 14px; cursor: pointer; background: #e0f2fe; color: #0369a1; border: none; display: inline-flex; align-items: center; transition: 0.2s; font-weight: bold;}
        .btn-print:hover { background: #bae6fd; }
        
        .info-grid { display: grid; gap: 20px; margin-bottom: 25px;}

        /* PRINT TEMPLATE EXACT REPLICA */
        #printableInvoice { display: none; width: 210mm; padding: 20mm; background: white; color: #000; line-height: 1.4; font-family: Arial, sans-serif; box-sizing: border-box; }
        @media print {
            body * { visibility: hidden; } 
            #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { display: block !important; position: absolute; left: 0; top: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    <div style="margin-bottom: 25px;">
        <h2 style="color:var(--theme-color); margin:0; font-size: 24px; font-weight:800;">Create Invoice</h2>
        <p style="margin:0; font-size:14px; color:var(--text-muted);">Generate and send invoices to CFO for approval</p>
    </div>

    <div class="invoice-card">
        <form id="invoiceForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                <div class="form-group">
                    <label>Invoice No</label>
                    <input type="text" name="invoice_no" value="<?= $invoice_number ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Invoice Date</label>
                    <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" required>
                </div>
            </div>

            <h4 style="font-size: 14px; color: var(--theme-color); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Client & Bank Information</h4>
            
            <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="form-group">
                    <label>Select Client *</label>
                    <select name="client_id" id="clientSelect" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach ($all_clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['client_name'] ?> <?= !empty($c['company_name']) ? "({$c['company_name']})" : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="client_name" id="hiddenClientName">
                </div>
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" id="ui_client_gst" readonly placeholder="Auto-fetched">
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="text" id="ui_client_mobile" readonly placeholder="Auto-fetched">
                </div>
            </div>

            <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr 1fr; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Select Client Bank</label>
                    <select id="clientBankSelect">
                        <option value="">-- No Client Bank Found --</option>
                    </select>
                    <input type="hidden" name="hidden_client_bank_details" id="hidden_client_bank_details">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Bank Name</label>
                    <input type="text" id="ui_client_bank" readonly placeholder="Auto-fetched" style="background: white;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Account Number</label>
                    <input type="text" id="ui_client_acc" readonly placeholder="Auto-fetched" style="background: white;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>IFSC Code</label>
                    <input type="text" id="ui_client_ifsc" readonly placeholder="Auto-fetched" style="background: white;">
                </div>
            </div>

            <h4 style="font-size: 14px; color: var(--theme-color); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 10px;">Our Company Bank Details (For Print)</h4>
            <div class="info-grid" style="grid-template-columns: 1fr;">
                <div class="form-group" style="max-width: 400px;">
                    <label>Select Company Bank *</label>
                    <select name="company_bank_id" required>
                        <?php if(empty($all_company_banks)): ?>
                            <option value="" disabled selected>No Company Banks Registered</option>
                        <?php else: ?>
                            <option value="" disabled selected>-- Choose Bank --</option>
                            <?php foreach ($all_company_banks as $cb): ?>
                                <option value="<?= $cb['id'] ?>"><?= $cb['bank_name'] ?> - A/C: <?= $cb['account_number'] ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">#</th>
                        <th style="width: 45%;">Description / Service</th>
                        <th style="width: 10%; text-align: center;">Qty</th>
                        <th style="width: 15%; text-align: right;">Rate (₹)</th>
                        <th style="width: 10%; text-align: center;">Disc (₹)</th>
                        <th style="width: 10%; text-align: right;">Total (₹)</th>
                        <th style="width: 5%; text-align: center;">Act</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <tr class="item-row">
                        <td style="text-align: center; font-weight: 700; color: #777; padding-top: 18px;">1</td>
                        <td><input type="text" name="item_desc[]" placeholder="Description..." list="servicesList" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
                        <td><input type="number" name="item_qty[]" class="qty-input" value="1" min="1" oninput="calculateTotals()" required style="width: 100%; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
                        <td><input type="number" name="item_rate[]" class="rate-input" placeholder="0.00" step="0.01" oninput="calculateTotals()" required style="width: 100%; text-align: right; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
                        <td><input type="number" name="item_disc_val[]" class="disc-input" value="0" min="0" oninput="calculateTotals()" style="width: 100%; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
                        <td>
                            <input type="text" name="item_total[]" class="row-total" value="0.00" readonly style="width: 100%; text-align: right; font-weight: 700; background: #f8fafc; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                            <input type="hidden" name="item_tax_p[]" class="tax-p-hidden" value="0">
                            <input type="hidden" name="item_tax_a[]" class="row-tax-amount" value="0">
                        </td>
                        <td style="text-align: center; padding-top: 15px;"><i class="ph ph-trash remove-item" style="font-size: 20px; color: #dc2626; cursor: pointer;" onclick="this.closest('tr').remove(); calculateTotals(); updateRowNumbers();"></i></td>
                    </tr>
                </tbody>
            </table>
            
            <datalist id="servicesList">
                <?php foreach ($all_services as $s): ?>
                    <option value="<?= htmlspecialchars($s['service_name']) ?>" data-rate="<?= $s['rate'] ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <button type="button" class="btn-add-item" onclick="addRow()"><i class="ph ph-plus-circle"></i> Add Line Item</button>

            <div style="display: flex; justify-content: space-between; gap: 40px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                <div style="flex: 1;">
                    <div class="form-group">
                        <label>Invoice Notes</label>
                        <textarea name="notes" rows="3" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 10px; font-family: inherit; resize: vertical;">If you have any questions concerning this invoice, contact Neoera Infotech.</textarea>
                    </div>
                </div>

                <div class="total-box">
                    <div class="total-row"><span>Sub Total</span><span>₹ <span id="displaySubtotal">0.00</span></span></div>
                    <div class="total-row"><span>Discount</span><span style="color: #dc2626;">- ₹ <span id="displayDiscount">0.00</span></span></div>
                    
                    <div class="total-row" style="background: white; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                        <span style="display: flex; align-items: center; gap: 8px; color: var(--theme-color);">
                            GST % 
                            <input type="number" id="overall_gst_p" value="18" min="0" max="100" oninput="calculateTotals()" style="width: 60px; padding: 4px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-weight: bold; font-family: inherit; font-size: 14px;">
                        </span>
                        <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Splits to CGST/SGST)</span>
                    </div>

                    <div class="total-row" style="margin-top: 10px;"><span style="color: var(--text-muted); font-size: 12px;">CGST (<span id="cgst_label">9</span>%)</span><span>₹ <span id="displayCgst">0.00</span></span></div>
                    <div class="total-row"><span style="color: var(--text-muted); font-size: 12px;">SGST (<span id="sgst_label">9</span>%)</span><span>₹ <span id="displaySgst">0.00</span></span></div>
                    
                    <div class="total-row" style="margin-top: 10px;"><span>Round Off</span><span>₹ <span id="displayRoundOff">0.00</span></span></div>
                    <div class="total-row grand"><span>GRAND TOTAL</span><span>₹ <span id="displayGrandTotal">0.00</span></span></div>
                    
                    <input type="hidden" name="final_sub_total" id="hiddenSubTotal">
                    <input type="hidden" name="discount" id="hiddenDiscountTotal">
                    <input type="hidden" name="final_tax_amount" id="hiddenTaxTotal">
                    <input type="hidden" name="cgst" id="hiddenCgst">
                    <input type="hidden" name="sgst" id="hiddenSgst">
                    <input type="hidden" name="round_off" id="hiddenRoundOff">
                    <input type="hidden" name="final_grand_total" id="hiddenGrandTotal">
                    <input type="hidden" name="action" value="save_invoice">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="submitInvoice()" id="saveBtn" class="btn-save">
                    <i class="ph ph-paper-plane-right"></i> Save & Send to CFO
                </button>
            </div>
        </form>
    </div>

    <div class="history-section">
        <h3 style="margin-top:0; color:var(--text-main); margin-bottom: 20px;">Recent Invoices</h3>
        <table class="history-table">
            <thead>
                <tr style="background:#f8fafc; font-size:11px; text-transform:uppercase; color:var(--text-muted);">
                    <th>Invoice No</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th style="color:#ef4444;">Net Pending Balance</th>
                    <th>Status</th>
                    <th style="text-align:center;">Print</th>
                </tr>
            </thead>
            <tbody>
                <?php if($history): while($row = mysqli_fetch_assoc($history)): $isApp = ($row['status'] == 'Approved' || $row['status'] == 'Paid'); ?>
                <tr>
                    <td><strong><?= $row['invoice_no'] ?></strong></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                    <td>₹<?= number_format($row['grand_total'], 2) ?></td>
                    <td style="color:#ef4444; font-weight:700;">₹<?= number_format($row['account_balance'], 2) ?></td>
                    <td><span class="status-pill <?= $isApp ? 'badge-approved' : '' ?>"><?= $row['status'] ?></span></td>
                    <td style="text-align:center;">
                        <?php if($isApp): ?>
                            <button onclick="prepareAndPrint('<?= $row['id'] ?>')" class="btn-print"><i class="ph-bold ph-printer" style="margin-right: 5px;"></i> Print</button>
                        <?php else: ?>
                            <span style="color:#94a3b8; font-size:12px; font-weight: 600;">Pending CFO</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="printableInvoice">
    <div style="display: flex; justify-content: space-between; padding-bottom: 20px; border-bottom: 3px solid #000; margin-bottom: 30px;">
        <div style="text-align: left;">
            <img src="<?= $company_details['logo'] ?>" alt="Logo" style="max-height: 60px;">
            <div style="font-size: 16px; font-weight: 800; color: #000; text-transform: uppercase; margin-top: 5px;"><?= $company_details['name'] ?></div>
            <div style="font-size: 13px; margin-top: 5px; line-height: 1.4; max-width: 280px; color: #000;">
                <?= $company_details['address'] ?><br>
                Phone <?= $company_details['phone'] ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 36px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 15px; color: #000;">INVOICE</div>
            <table style="margin-left: auto; text-align: left; font-size: 14px; color: #000;">
                <tr><td style="font-weight: bold; text-align: right; padding: 4px 10px;">DATE:</td><td style="padding: 4px 10px;" id="p_date"></td></tr>
                <tr><td style="font-weight: bold; text-align: right; padding: 4px 10px;">INVOICE #:</td><td style="padding: 4px 10px;" id="p_inv_no"></td></tr>
            </table>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; color: #000;">
        <div>
            <h4 style="font-size: 13px; font-weight: bold; margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; text-transform: uppercase;">Bill To:</h4>
            <p style="font-weight: bold; font-size: 13px; margin: 4px 0; text-transform: uppercase; color: #000;" id="p_client"></p>
            <p style="margin: 4px 0; font-size: 13px; color: #000;" id="p_client_gst"></p>
            <p style="margin: 4px 0; font-size: 13px; color: #000;" id="p_client_phone"></p>
            <p style="margin: 4px 0; font-size: 13px; white-space: pre-line; color: #000;" id="p_client_bank"></p>
        </div>
        
        <div>
            <h4 style="font-size: 13px; font-weight: bold; margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; text-transform: uppercase;">Account Details:</h4>
            <p style="margin: 4px 0; font-size: 13px; color: #000;"><strong>Account Name:</strong> <span style="text-transform: uppercase;"><?= $company_details['name'] ?></span></p>
            <p style="margin: 4px 0; font-size: 13px; color: #000;"><strong>Account Number:</strong> <span id="p_co_acc"></span></p>
            <p style="margin: 4px 0; font-size: 13px; color: #000;"><strong>IFSC:</strong> <span id="p_co_ifsc"></span></p>
            <p style="margin: 4px 0; font-size: 13px; color: #000;"><strong>UPI ID:</strong> <span id="p_co_upi"></span></p>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; color: #000;">
        <thead>
            <tr>
                <th style="background: #f0f0f0; border-bottom: 2px solid #000; padding: 12px 10px; text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; width: 5%;">S.No</th>
                <th style="background: #f0f0f0; border-bottom: 2px solid #000; padding: 12px 10px; text-align: left; font-size: 13px; font-weight: bold; text-transform: uppercase; width: 45%;">DESCRIPTION</th>
                <th style="background: #f0f0f0; border-bottom: 2px solid #000; padding: 12px 10px; text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; width: 15%;">QUANTITY</th>
                <th style="background: #f0f0f0; border-bottom: 2px solid #000; padding: 12px 10px; text-align: right; font-size: 13px; font-weight: bold; text-transform: uppercase; width: 15%;">UNIT PRICE</th>
                <th style="background: #f0f0f0; border-bottom: 2px solid #000; padding: 12px 10px; text-align: right; font-size: 13px; font-weight: bold; text-transform: uppercase; width: 20%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody id="p_items"></tbody>
    </table>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 40px; color: #000;">
        <table style="width: 300px; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;">Total</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;" id="p_sub"></td>
            </tr>
            <tr id="tr_p_disc">
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;">Discount</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;" id="p_disc"></td>
            </tr>
            <tr id="tr_p_cgst">
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;">CGST</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;" id="p_cgst"></td>
            </tr>
            <tr id="tr_p_sgst">
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;">SGST</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;" id="p_sgst"></td>
            </tr>
            <tr id="tr_p_roff">
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;">Round Off</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 14px;" id="p_roff"></td>
            </tr>
            <tr style="font-weight: bold; font-size: 18px; border-top: 2px solid #000; border-bottom: 2px solid #000; background: #f9f9f9;">
                <td style="padding: 8px 10px; text-align: right;">TOTAL</td>
                <td style="padding: 8px 10px; text-align: right;" id="p_grand"></td>
            </tr>
        </table>
    </div>

    <div style="font-size: 13px; margin-bottom: 40px; color: #000;">
        <p style="margin: 5px 0;" id="p_notes"></p>
        <p style="font-weight: bold; margin-top: 15px;" id="p_terms"></p>
    </div>

    <div style="text-align: center; font-size: 13px; border-top: 1px solid #000; padding-top: 15px; font-weight: bold; color: #000;">
        <?= $company_details['name'] ?><br>
        Phone <?= $company_details['phone'] ?> | <?= $company_details['email'] ?> | <?= $company_details['website'] ?>
    </div>
</div>

<script>
    const clientData = <?= json_encode($all_clients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    let itemIndex = 1;

    // --- BULLETPROOF AUTO-FILL UI LOGIC ---
    function updateClientBankUI() {
        const bankSelect = document.getElementById('clientBankSelect');
        const selectedOption = bankSelect.options[bankSelect.selectedIndex];

        if (selectedOption && selectedOption.value !== "") {
            document.getElementById('ui_client_bank').value = selectedOption.getAttribute('data-bname') || '';
            document.getElementById('ui_client_acc').value = selectedOption.getAttribute('data-bacc') || '';
            document.getElementById('ui_client_ifsc').value = selectedOption.getAttribute('data-bifsc') || '';
            document.getElementById('hidden_client_bank_details').value = selectedOption.getAttribute('data-bdet') || '';
        } else {
            document.getElementById('ui_client_bank').value = '';
            document.getElementById('ui_client_acc').value = '';
            document.getElementById('ui_client_ifsc').value = '';
            document.getElementById('hidden_client_bank_details').value = '';
        }
    }

    document.getElementById('clientSelect').addEventListener('change', function() {
        const clientId = this.value;
        const bankSelect = document.getElementById('clientBankSelect');
        
        bankSelect.innerHTML = '<option value="">-- No Client Bank Found --</option>';
        document.getElementById('ui_client_gst').value = '';
        document.getElementById('ui_client_mobile').value = '';
        document.getElementById('hiddenClientName').value = '';
        
        updateClientBankUI(); // Clear bank inputs initially

        if (!clientId) return;

        const client = clientData.find(c => c.id == clientId);
        if (client) {
            document.getElementById('hiddenClientName').value = client.company_name ? client.company_name : client.client_name;
            document.getElementById('ui_client_gst').value = client.gst_number || '';
            document.getElementById('ui_client_mobile').value = client.mobile_number || '';

            if (client.banks_list && client.banks_list.length > 0) {
                bankSelect.innerHTML = '<option value="">-- Select Bank --</option>';
                client.banks_list.forEach((bank, idx) => {
                    const option = document.createElement('option');
                    option.value = idx; 
                    option.text = bank.display;
                    option.setAttribute('data-bname', bank.bank_name || '');
                    option.setAttribute('data-bacc', bank.account_number || '');
                    option.setAttribute('data-bifsc', bank.ifsc_code || '');
                    option.setAttribute('data-bdet', bank.details || '');
                    bankSelect.appendChild(option);
                });
                
                // Automatically auto-select if there is exactly 1 bank
                if(client.banks_list.length === 1) {
                    bankSelect.selectedIndex = 1;
                }
            }
        }
        
        // Final update to push data to UI text boxes
        updateClientBankUI();
    });

    document.getElementById('clientBankSelect').addEventListener('change', updateClientBankUI);

    // --- CALCULATIONS & TABLE LOGIC ---
    function addRow() {
        const tbody = document.getElementById('itemsBody');
        const tr = document.createElement('tr');
        tr.className = "item-row";
        tr.innerHTML = `
            <td style="text-align: center; font-weight: 700; color: #777; padding-top: 18px;"></td>
            <td><input type="text" name="item_desc[]" placeholder="Description..." required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
            <td><input type="number" name="item_qty[]" class="qty-input" value="1" min="1" oninput="calculateTotals()" required style="width: 100%; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
            <td><input type="number" name="item_rate[]" class="rate-input" placeholder="0.00" step="0.01" oninput="calculateTotals()" required style="width: 100%; text-align: right; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
            <td><input type="number" name="item_disc_val[]" class="disc-input" value="0" min="0" oninput="calculateTotals()" style="width: 100%; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"></td>
            <td>
                <input type="text" name="item_total[]" class="row-total" value="0.00" readonly style="width: 100%; text-align: right; font-weight: 700; background: #f8fafc; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            </td>
            <td style="text-align: center; padding-top: 15px;"><i class="ph ph-trash remove-item" style="font-size: 20px; color: #dc2626; cursor: pointer;" onclick="this.closest('tr').remove(); calculateTotals(); updateRowNumbers();"></i></td>
        `;
        tbody.appendChild(tr);
        updateRowNumbers();
    }

    function calculateTotals() {
        let subtotal = 0, totalDisc = 0;
        
        // Get the single GST% from the summary box
        const overallGstP = parseFloat(document.getElementById('overall_gst_p').value) || 0;
        const halfGstP = (overallGstP / 2).toFixed(1);
        
        document.getElementById('cgst_label').innerText = halfGstP;
        document.getElementById('sgst_label').innerText = halfGstP;

        document.querySelectorAll('.item-row').forEach(r => {
            const qty = parseFloat(r.querySelector('.qty-input').value) || 0;
            const rate = parseFloat(r.querySelector('.rate-input').value) || 0;
            const disc = parseFloat(r.querySelector('.disc-input').value) || 0;
            
            const lineAmount = (qty * rate) - disc;
            r.querySelector('.row-total').value = lineAmount.toFixed(2);

            subtotal += (qty * rate);
            totalDisc += disc;
        });
        
        const taxable = subtotal - totalDisc;
        const taxTotal = taxable * (overallGstP / 100);
        
        const cgst = taxTotal / 2; 
        const sgst = taxTotal / 2;
        
        const exact = taxable + taxTotal;
        const grand = Math.round(exact);
        const roff = grand - exact;

        // Visual Display updates
        document.getElementById('displaySubtotal').innerText = subtotal.toFixed(2);
        document.getElementById('displayDiscount').innerText = totalDisc.toFixed(2);
        document.getElementById('displayCgst').innerText = cgst.toFixed(2);
        document.getElementById('displaySgst').innerText = sgst.toFixed(2);
        document.getElementById('displayRoundOff').innerText = roff.toFixed(2);
        document.getElementById('displayGrandTotal').innerText = grand.toFixed(2);

        // Hidden input updates for Database POST
        document.getElementById('hiddenSubTotal').value = subtotal.toFixed(2);
        document.getElementById('hiddenDiscountTotal').value = totalDisc.toFixed(2);
        document.getElementById('hiddenCgst').value = cgst.toFixed(2);
        document.getElementById('hiddenSgst').value = sgst.toFixed(2);
        document.getElementById('hiddenRoundOff').value = roff.toFixed(2);
        document.getElementById('hiddenGrandTotal').value = grand;
    }

    function updateRowNumbers() {
        document.querySelectorAll('.item-row').forEach((row, index) => {
            row.cells[0].innerText = index + 1;
        });
    }

    // --- FORM SUBMISSION ---
    function submitInvoice() {
        const form = document.getElementById('invoiceForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const btn = document.getElementById('saveBtn'); 
        btn.disabled = true; 
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Saving...';
        
        fetch('', { method: 'POST', body: new FormData(form) })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({title: 'Success!', text: 'Invoice sent to CFO for approval.', icon: 'success', timer: 1500, showConfirmButton: false})
                .then(() => window.location.reload());
            } else {
                Swal.fire('Database Error', data.message, 'error');
                btn.disabled = false; btn.innerHTML = '<i class="ph ph-paper-plane-right"></i> Save & Send to CFO';
            }
        }).catch(err => {
            console.error(err);
            Swal.fire('Network Error', 'Connection failed.', 'error');
            btn.disabled = false; btn.innerHTML = '<i class="ph ph-paper-plane-right"></i> Save & Send to CFO';
        });
    }

    // --- PRINTING LOGIC ---
    function prepareAndPrint(id) {
        const fd = new FormData(); 
        fd.append('action', 'fetch_invoice_details'); 
        fd.append('id', id);
        
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                const inv = data.invoice;
                
                const dateObj = new Date(inv.invoice_date);
                const formattedDate = dateObj.toLocaleDateString('en-GB'); 

                document.getElementById('p_inv_no').innerText = inv.invoice_no;
                document.getElementById('p_date').innerText = formattedDate;
                
                document.getElementById('p_client').innerText = inv.company_name || inv.client_name;
                document.getElementById('p_client_gst').innerText = inv.c_gst ? `GSTIN: ${inv.c_gst}` : '';
                document.getElementById('p_client_phone').innerText = inv.mobile_number ? `Phone: ${inv.mobile_number}` : '';
                
                // Format bank details to break newlines cleanly in PDF
                document.getElementById('p_client_bank').innerText = inv.bank_name ? "Client Bank Info:\n" + inv.bank_name.split(' | ').join('\n') : '';

                if (data.company_bank) {
                    document.getElementById('p_co_acc').innerText = data.company_bank.account_number;
                    document.getElementById('p_co_ifsc').innerText = data.company_bank.ifsc_code;
                    document.getElementById('p_co_upi').innerText = data.company_bank.phone_number ? data.company_bank.phone_number + "@upi" : 'N/A';
                } else {
                    document.getElementById('p_co_acc').innerText = "N/A";
                    document.getElementById('p_co_ifsc').innerText = "N/A";
                    document.getElementById('p_co_upi').innerText = "N/A";
                }

                document.getElementById('p_sub').innerText = '₹ ' + parseFloat(inv.sub_total).toFixed(2);
                
                if(parseFloat(inv.discount) > 0) {
                    document.getElementById('p_disc').innerText = '₹ ' + parseFloat(inv.discount).toFixed(2);
                    document.getElementById('tr_p_disc').style.display = 'table-row';
                } else {
                    document.getElementById('tr_p_disc').style.display = 'none';
                }

                if (parseFloat(inv.cgst) > 0) {
                    document.getElementById('p_cgst').innerText = '₹ ' + parseFloat(inv.cgst).toFixed(2);
                    document.getElementById('tr_p_cgst').style.display = 'table-row';
                } else {
                    document.getElementById('tr_p_cgst').style.display = 'none';
                }

                if (parseFloat(inv.sgst) > 0) {
                    document.getElementById('p_sgst').innerText = '₹ ' + parseFloat(inv.sgst).toFixed(2);
                    document.getElementById('tr_p_sgst').style.display = 'table-row';
                } else {
                    document.getElementById('tr_p_sgst').style.display = 'none';
                }
                
                if (parseFloat(inv.round_off) !== 0) {
                    document.getElementById('p_roff').innerText = '₹ ' + parseFloat(inv.round_off).toFixed(2);
                    document.getElementById('tr_p_roff').style.display = 'table-row';
                } else {
                    document.getElementById('tr_p_roff').style.display = 'none';
                }

                document.getElementById('p_grand').innerText = '₹ ' + parseFloat(inv.grand_total).toFixed(2);
                
                document.getElementById('p_notes').innerText = inv.notes || '';
                // Uses the hardcoded terms passed to DB
                document.getElementById('p_terms').innerText = inv.terms || '';
                
                const table = document.getElementById('p_items'); 
                table.innerHTML = '';
                let sno = 1;
                data.items.forEach(it => { 
                    table.innerHTML += `<tr style="font-size: 13px;">
                        <td style="border-bottom:1px solid #ddd; padding:12px 10px; text-align:center;">${sno++}</td>
                        <td style="border-bottom:1px solid #ddd; padding:12px 10px;">${it.description}</td>
                        <td style="border-bottom:1px solid #ddd; padding:12px 10px; text-align:center;">${it.qty}</td>
                        <td style="border-bottom:1px solid #ddd; padding:12px 10px; text-align:right;">${parseFloat(it.rate).toFixed(2)}</td>
                        <td style="border-bottom:1px solid #ddd; padding:12px 10px; text-align:right;">${parseFloat(it.total_amount).toFixed(2)}</td>
                    </tr>`; 
                });
                
                setTimeout(() => { 
                    window.print(); 
                }, 500);
            }
        });
    }

    // Call once to set initial GST load states correctly
    calculateTotals();
</script>
</body>
</html>