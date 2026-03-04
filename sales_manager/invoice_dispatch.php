<?php 
// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// 2. BACKEND AJAX HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if(ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    
    // --- ADD CLIENT (From inside Generate Invoice Modal) ---
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

    // --- SAVE FULL NEW INVOICE ---
    if ($_POST['action'] === 'save_invoice') {
        $invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
        $client_id = intval($_POST['client_id']);
        $bank = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
        
        $client_mobile = mysqli_real_escape_string($conn, $_POST['client_mobile'] ?? '');
        $client_gst = mysqli_real_escape_string($conn, $_POST['client_gst'] ?? '');
        $payment_mode = mysqli_real_escape_string($conn, $_POST['payment_mode'] ?? '');

        mysqli_query($conn, "UPDATE clients SET mobile_number='$client_mobile', gst_number='$client_gst', payment_method='$payment_mode' WHERE id=$client_id");
        
        $sub_total = isset($_POST['sub_total']) ? floatval($_POST['sub_total']) : 0.00;
        $total_discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0.00;
        $cgst = isset($_POST['cgst']) ? floatval($_POST['cgst']) : 0.00;
        $sgst = isset($_POST['sgst']) ? floatval($_POST['sgst']) : 0.00;
        $round_off = isset($_POST['round_off']) ? floatval($_POST['round_off']) : 0.00;
        $grand_total = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : 0.00;

        // Automatically sends to cfo_approvals.php by setting status to 'Pending Approval'
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

    // --- REAL: Update invoice with the approval status ---
    if ($_POST['action'] === 'update_status') {
        $invoice_id = intval($_POST['invoice_id']);
        $new_status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $update_sql = "UPDATE invoices SET status = '$new_status' WHERE id = $invoice_id";
        
        if (mysqli_query($conn, $update_sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // --- NEW: Send Individual Invoice to CFO/Accounts ---
    if ($_POST['action'] === 'send_to_cfo') {
        $invoice_id = intval($_POST['invoice_id']);
        $update_sql = "UPDATE invoices SET status = 'Pending CFO Approval' WHERE id = $invoice_id";
        
        if (mysqli_query($conn, $update_sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // --- NEW: Batch Daily Update to CFO/Accounts ---
    if ($_POST['action'] === 'daily_update_to_cfo') {
        $update_sql = "UPDATE invoices SET status = 'Pending CFO Approval' WHERE status = 'Pending Approval'";
        
        if (mysqli_query($conn, $update_sql)) {
            $affected = mysqli_affected_rows($conn);
            echo json_encode(['status' => 'success', 'affected_rows' => $affected]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // --- FETCH PENDING INVOICES ---
    if ($_POST['action'] === 'fetch_pending') {
        $res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i 
                                    LEFT JOIN clients c ON i.client_id = c.id 
                                    WHERE i.status = 'Pending Approval' ORDER BY i.invoice_date DESC");
        $invoices = [];
        while($row = mysqli_fetch_assoc($res)) {
            $invoices[] = $row;
        }
        echo json_encode($invoices);
        exit;
    }

    // --- FETCH SENT TO EXECUTIVES ---
    if ($_POST['action'] === 'fetch_sent') {
        $res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i 
                                    LEFT JOIN clients c ON i.client_id = c.id 
                                    WHERE i.status = 'Sent to Executive' ORDER BY i.invoice_date DESC");
        $invoices = [];
        while($row = mysqli_fetch_assoc($res)) {
            $invoices[] = $row;
        }
        echo json_encode($invoices);
        exit;
    }

    // --- FETCH SENT TO CFO ---
    if ($_POST['action'] === 'fetch_sent_to_cfo') {
        $res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i 
                                    LEFT JOIN clients c ON i.client_id = c.id 
                                    WHERE i.status = 'Pending CFO Approval' ORDER BY i.invoice_date DESC");
        $invoices = [];
        while($row = mysqli_fetch_assoc($res)) {
            $invoices[] = $row;
        }
        echo json_encode($invoices);
        exit;
    }

    // --- MARK AS SENT TO EXECUTIVE ---
    if ($_POST['action'] === 'mark_sent') {
        $invoice_id = intval($_POST['invoice_id']);
        $exec_name = mysqli_real_escape_string($conn, $_POST['exec_name']);
        
        $update_sql = "UPDATE invoices SET status = 'Sent to Executive' WHERE id = $invoice_id";
        
        if (mysqli_query($conn, $update_sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // --- GENERATE PDF PREVIEW ---
    if ($_POST['action'] === 'generate_pdf') {
        $invoice_id = intval($_POST['invoice_id']);
        $auto_print = isset($_POST['auto_print']) ? true : false;
        
        // Fetch invoice details
        $res = mysqli_query($conn, "SELECT i.*, c.client_name, c.gst_number, c.mobile_number FROM invoices i 
                                    LEFT JOIN clients c ON i.client_id = c.id WHERE i.id = $invoice_id");
        
        if ($inv = mysqli_fetch_assoc($res)) {
            // Fetch items
            $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
            $data = ['invoice' => $inv, 'items' => []];
            while($item = mysqli_fetch_assoc($items_res)) {
                $data['items'][] = $item;
            }
            
            // Generate HTML for PDF (simplified)
            $html = generateInvoiceHTML($data);
            
            // Use TCPDF or similar for PDF generation (assuming you have it)
            // For now, we'll just return success and handle client-side
            echo json_encode(['status' => 'success', 'html' => $html]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        }
        exit;
    }
}

// --- HELPER FUNCTION: Generate Invoice HTML ---
function generateInvoiceHTML($data) {
    $inv = $data['invoice'];
    $items = $data['items'];
    
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            <h1 style="margin: 0; color: #333;">INVOICE</h1>
            <p style="margin: 5px 0; color: #666;">Invoice No: ' . $inv['invoice_no'] . '</p>
        </div>
        
        <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
            <div style="width: 48%;">
                <h3 style="margin: 0 0 10px 0; color: #333;">Bill To:</h3>
                <p style="margin: 5px 0; font-weight: bold;">' . htmlspecialchars($inv['client_name']) . '</p>
                <p style="margin: 5px 0;">GST: ' . htmlspecialchars($inv['gst_number'] ?? 'N/A') . '</p>
                <p style="margin: 5px 0;">Mobile: ' . htmlspecialchars($inv['mobile_number'] ?? 'N/A') . '</p>
            </div>
            <div style="width: 48%; text-align: right;">
                <p style="margin: 5px 0;"><strong>Date:</strong> ' . date('d M Y', strtotime($inv['invoice_date'])) . '</p>
                <p style="margin: 5px 0;"><strong>Bank:</strong> ' . htmlspecialchars($inv['bank_name']) . '</p>
            </div>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="border:1px solid #ddd; padding:10px; text-align: left;">Description</th>
                    <th style="border:1px solid #ddd; padding:10px; text-align: center;">Qty</th>
                    <th style="border:1px solid #ddd; padding:10px; text-align: right;">Rate</th>
                    <th style="border:1px solid #ddd; padding:10px; text-align: right;">Discount</th>
                    <th style="border:1px solid #ddd; padding:10px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody id="p_items">
                <!-- Items populated via JS -->
            </tbody>
        </table>
        
        <div style="text-align: right; margin-bottom: 20px;">
            <p><strong>Sub Total:</strong> ₹' . number_format($inv['sub_total'], 2) . '</p>
            <p><strong>Discount:</strong> ₹' . number_format($inv['discount'], 2) . '</p>
            <p><strong>CGST (9%):</strong> ₹' . number_format($inv['cgst'], 2) . '</p>
            <p><strong>SGST (9%):</strong> ₹' . number_format($inv['sgst'], 2) . '</p>
            <p><strong>Round Off:</strong> ₹' . number_format($inv['round_off'], 2) . '</p>
            <p style="font-size: 18px; font-weight: bold; color: #333; border-top: 2px solid #333; padding-top: 10px;">
                <strong>Grand Total: ₹' . number_format($inv['grand_total'], 0) . '</strong>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 40px; font-size: 12px; color: #666;">
            <p>Thank you for your business!</p>
            <p>Generated on ' . date('d M Y H:i') . '</p>
        </div>
    </div>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Dispatch Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.0/dist/css/phosphor.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { color: #1e293b; font-size: 28px; font-weight: 700; }
        .header p { color: #64748b; margin-top: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: #10b981; color: white; }
        .btn-primary:hover { background: #059669; transform: translateY(-1px); }
        .btn-secondary { background: #e2e8f0; color: #1e293b; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-small { padding: 6px 12px; font-size: 12px; }

        .tabs { display: flex; background: white; border-radius: 12px 12px 0 0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 0; }
        .tab { flex: 1; padding: 15px; text-align: center; background: #f1f5f9; cursor: pointer; border: none; font-weight: 600; transition: all 0.3s; }
        .tab.active { background: #1e293b; color: white; }
        .tab-content { display: none; background: white; border-radius: 0 0 12px 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .tab-content.active { display: block; }

        .table-wrapper { overflow-x: auto; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #64748b; text-transform: uppercase; font-size: 12px; }
        tr:hover { background: #f8fafc; }
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge.approved { background: #dcfce7; color: #16a34a; }
        .badge.pending { background: #fef3c7; color: #d97706; }
        .badge.sent { background: #dbeafe; color: #2563eb; }

        .action-btns { display: flex; gap: 5px; }
        .action-btn { padding: 6px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.3s; }
        .action-btn.view { background: #eff6ff; color: #2563eb; }
        .action-btn.view:hover { background: #dbeafe; }
        .action-btn.print { background: #fffbeb; color: #f59e0b; }
        .action-btn.print:hover { background: #fef3c7; }
        .action-btn.send { background: #ecfdf5; color: #059669; }
        .action-btn.send:hover { background: #d1fae5; }
        .action-btn.cfo { background: #f3e8ff; color: #7c3aed; }
        .action-btn.cfo:hover { background: #e9d5ff; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        .modal-header h3 { color: #1e293b; }
        .close { cursor: pointer; font-size: 24px; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #64748b; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; }

        .empty-state { text-align: center; padding: 40px; color: #64748b; }
        .empty-state i { font-size: 48px; margin-bottom: 10px; opacity: 0.5; }
        .empty-state h3 { margin-bottom: 5px; }

        @media (max-width: 768px) { .container { padding: 10px; } .header { flex-direction: column; gap: 15px; text-align: center; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>📋 Invoice Dispatch Hub</h1>
            <p>Generate invoices and dispatch them to Sales Executives.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-secondary" onclick="dailyUpdateToAccounts()">
                📊 Daily Update to Accounts
            </button>
            <button class="btn btn-primary" onclick="openGenerateModal()">
                ➕ Generate New Invoice
            </button>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('pending')">Dispatch Queue</button>
        <button class="tab" onclick="switchTab('sent')">Sent to Executives</button>
        <button class="tab" onclick="switchTab('cfo')">Sent to CFO</button>
    </div>

    <!-- PENDING TAB -->
    <div id="tab-pending" class="tab-content active">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Client Details</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pendingTableBody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
        <div id="empty-pending-msg" class="empty-state" style="display: none;">
            <i class="ph-fill ph-check-circle"></i>
            <h3>All Caught Up!</h3>
            <p>No invoices are pending dispatch or awaiting approval.</p>
        </div>
    </div>

    <!-- SENT TO EXECUTIVES TAB -->
    <div id="tab-sent" class="tab-content">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Client Details</th>
                        <th>Date Sent</th>
                        <th>Amount</th>
                        <th>Executive</th>
                    </tr>
                </thead>
                <tbody id="sentTableBody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
        <div id="empty-sent-msg" class="empty-state" style="display: none;">
            <i class="ph-fill ph-user-circle"></i>
            <h3>No Invoices Sent Yet</h3>
            <p>Dispatch some invoices to sales executives to see them here.</p>
        </div>
    </div>

    <!-- SENT TO CFO TAB -->
    <div id="tab-cfo" class="tab-content">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Client Details</th>
                        <th>Date Sent</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="cfoTableBody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
        <div id="empty-cfo-msg" class="empty-state" style="display: none;">
            <i class="ph-fill ph-building"></i>
            <h3>No Invoices Sent to CFO</h3>
            <p>Send some invoices for CFO approval to see them here.</p>
        </div>
    </div>
</div>

<!-- GENERATE INVOICE MODAL -->
<div id="generateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Generate New Invoice</h3>
            <span class="close" onclick="closeGenerateModal()">&times;</span>
        </div>
        <form id="invoiceForm">
            <div class="form-group">
                <label>Client</label>
                <select id="clientSelect" required>
                    <option value="">Select Client</option>
                </select>
                <button type="button" class="btn btn-small btn-secondary" onclick="addNewClient()" style="margin-top: 5px;">+ Add New Client</button>
            </div>
            <div class="form-group">
                <label>Invoice No</label>
                <input type="text" id="invoiceNo" placeholder="e.g. INV/001/26-27" required>
            </div>
            <div class="form-group">
                <label>Invoice Date</label>
                <input type="date" id="invoiceDate" required>
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" id="bankName" placeholder="e.g. HDFC Bank" required>
            </div>
            <!-- Items Table -->
            <div class="form-group">
                <label>Items</label>
                <table id="itemsTable" style="width:100%; border-collapse:collapse;">
                    <thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Disc %</th><th>Total</th><th>Action</th></tr></thead>
                    <tbody>
                        <tr><td><input type="text" placeholder="Item description"></td>
                            <td><input type="number" value="1" min="1"></td>
                            <td><input type="number" step="0.01" placeholder="0.00"></td>
                            <td><input type="number" value="0" min="0" max="100" step="0.01"></td>
                            <td id="rowTotal">0.00</td>
                            <td><button type="button" onclick="removeRow(this)">Remove</button></td></tr>
                    </tbody>
                </table>
                <button type="button" onclick="addItemRow()" class="btn btn-small btn-secondary" style="margin-top: 10px;">+ Add Item</button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                <div><label>Sub Total</label><input type="number" id="subTotal" readonly></div>
                <div><label>Discount (%)</label><input type="number" id="discountPct" value="0" min="0" max="100" step="0.01" onchange="calculateTotals()"></div>
                <div><label>CGST (9%)</label><input type="number" id="cgstAmt" readonly></div>
                <div><label>SGST (9%)</label><input type="number" id="sgstAmt" readonly></div>
                <div><label>Round Off</label><input type="number" id="roundOff" value="0" step="0.01" onchange="calculateTotals()"></div>
                <div><label>Grand Total</label><input type="number" id="grandTotal" readonly style="font-weight: bold; font-size: 16px;"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Generate & Save Invoice</button>
        </form>
    </div>
</div>

<!-- DISPATCH MODAL -->
<div id="dispatchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="m_inv_no"></h3>
            <span class="close" onclick="closeModal('dispatchModal')">&times;</span>
        </div>
        <p><strong>Client:</strong> <span id="m_client"></span></p>
        <p><strong>Amount:</strong> <span id="m_amount"></span></p>
        <div class="form-group">
            <label>Select Sales Executive</label>
            <select id="m_exec" required>
                <option value="">Choose Executive</option>
                <option value="John Doe">John Doe</option>
                <option value="Jane Smith">Jane Smith</option>
                <option value="Mike Johnson">Mike Johnson</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="sendToExecutive()" id="dispatchBtn">Send to Executive</button>
        <button class="btn btn-secondary action-btn cfo" onclick="sendToCFO()" style="width: 100%; margin-top: 10px;">Send to CFO for Approval</button>
    </div>
</div>

<!-- PDF PREVIEW MODAL -->
<div id="previewModal" class="modal">
    <div class="modal-content" style="width: 90%; max-width: 800px; height: 80vh;">
        <div class="modal-header">
            <h3>Invoice Preview</h3>
            <span class="close" onclick="closeModal('previewModal')">&times;</span>
        </div>
        <div class="modal-body" style="padding: 0; overflow-y: auto;">
            <iframe id="pdfFrame" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="window.print()">Print Invoice</button>
            <button class="btn btn-secondary" onclick="closeModal('previewModal')">Close</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let currentInvoices = { pending: [], sent: [], cfo: [] };

    // Load data on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadPendingInvoices();
        loadSentInvoices();
        loadCFOInvoices();
        document.getElementById('invoiceDate').valueAsDate = new Date();
    });

    function loadPendingInvoices() {
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=fetch_pending'
        })
        .then(res => res.json())
        .then(data => {
            currentInvoices.pending = data;
            renderTable('pendingTableBody', data, 'pending');
            toggleEmptyState('pending', data.length === 0);
        });
    }

    function loadSentInvoices() {
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=fetch_sent'
        })
        .then(res => res.json())
        .then(data => {
            currentInvoices.sent = data;
            renderTable('sentTableBody', data, 'sent');
            toggleEmptyState('sent', data.length === 0);
        });
    }

    function loadCFOInvoices() {
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=fetch_sent_to_cfo'
        })
        .then(res => res.json())
        .then(data => {
            currentInvoices.cfo = data;
            renderTable('cfoTableBody', data, 'cfo');
            toggleEmptyState('cfo', data.length === 0);
        });
    }

    function renderTable(tableId, data, type) {
        const tbody = document.getElementById(tableId);
        tbody.innerHTML = '';
        data.forEach(inv => {
            const rowId = `row-${inv.invoice_no}`;
            const clientHtml = `
                <div>
                    <strong>${inv.client_name}</strong><br>
                    <small style="color: #64748b;">No Mail ID | Desc: No description provided</small>
                </div>
            `;
            const amountHtml = '₹' + parseFloat(inv.grand_total).toLocaleString('en-IN');
            const dateSent = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            let rowHtml = `
                <tr id="${rowId}">
                    <td><strong>${inv.invoice_no}</strong></td>
                    <td>${clientHtml}</td>
                    <td>${inv.invoice_date ? new Date(inv.invoice_date).toLocaleDateString('en-GB') : dateSent}</td>
                    <td>${amountHtml}</td>
            `;

            if (type === 'pending') {
                rowHtml += `
                    <td><span class="badge pending">Pending</span></td>
                    <td class="action-btns">
                        <button class="action-btn view" onclick="previewInvoice(${inv.id}, false)">👁️ View</button>
                        <button class="action-btn print" onclick="previewInvoice(${inv.id}, true)">🖨️ Print</button>
                        <button class="action-btn send" onclick="openDispatchModal('${inv.invoice_no}', '${inv.client_name}', ${inv.grand_total})">📤 Send</button>
                        <button class="action-btn cfo" onclick="sendIndividualToCFO(${inv.id}, '${inv.invoice_no}')">👑 Send to CFO</button>
                    </td>
                `;
            } else if (type === 'sent') {
                rowHtml += `<td><span class="badge sent">Sent</span></td><td></td></tr>`;
            } else if (type === 'cfo') {
                rowHtml += `<td><span class="badge approved">Pending CFO</span></td><td></td></tr>`;
            }

            rowHtml += '</tr>';
            tbody.innerHTML += rowHtml;
        });
    }

    function toggleEmptyState(tab, isEmpty) {
        const msgId = `empty-${tab}-msg`;
        const msg = document.getElementById(msgId);
        if (msg) msg.style.display = isEmpty ? 'block' : 'none';
    }

    function switchTab(tabName) {
        // Update tabs
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');
        
        // Update content
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(`tab-${tabName}`).classList.add('active');
    }

    // --- DAILY UPDATE TO ACCOUNTS / CFO ---
    function dailyUpdateToAccounts() {
        Swal.fire({
            title: 'Daily Update to CFO?',
            text: 'This will send all pending invoices to CFO for approval.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send All',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const btn = event.target;
                const origText = btn.innerHTML;
                btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Updating...';
                btn.disabled = true;

                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=daily_update_to_cfo'
                })
                .then(res => res.json())
                .then(data => {
                    btn.innerHTML = origText;
                    btn.disabled = false;
                    
                    if (data.status === 'success') {
                        Swal.fire('Success!', `Sent ${data.affected_rows} invoices to CFO.`, 'success');
                        loadPendingInvoices(); // Reload pending
                        loadCFOInvoices(); // Reload CFO tab
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }

    // --- SEND INDIVIDUAL TO CFO ---
    function sendIndividualToCFO(invoiceId, invoiceNo) {
        Swal.fire({
            title: `Send ${invoiceNo} to CFO?`,
            text: 'This invoice will be moved to CFO approval queue.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send to CFO'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=send_to_cfo&invoice_id=${invoiceId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Sent!', 'Invoice sent to CFO for approval.', 'success');
                        loadPendingInvoices();
                        loadCFOInvoices();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }

    // --- OTHER FUNCTIONS (unchanged for brevity, but include them as in original) ---
    function openGenerateModal() { /* ... */ }
    function closeGenerateModal() { /* ... */ }
    function addNewClient() { /* ... */ }
    function addItemRow() { /* ... */ }
    function removeRow(btn) { /* ... */ }
    function calculateTotals() { /* ... */ }
    function previewInvoice(id, autoPrint) { /* ... */ }
    function openDispatchModal(invNo, client, amount) { /* ... */ }
    function sendToExecutive() { /* ... */ }
    function moveRowToSent(invNo, execName) { /* ... */ }
    function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
</script>

</body>
</html>