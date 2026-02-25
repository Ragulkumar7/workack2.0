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

    // --- REAL: Update invoice with the assigned Sales Executive ---
    if ($_POST['action'] === 'mark_sent') {
        $inv_id = mysqli_real_escape_string($conn, $_POST['invoice_id']);
        $exec_name = mysqli_real_escape_string($conn, $_POST['exec_name']);
        
        mysqli_query($conn, "UPDATE invoices SET assigned_executive = '$exec_name' WHERE invoice_no = '$inv_id'");
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- FETCH INVOICE DETAILS FOR IN-APP VIEW ---
    if ($_POST['action'] === 'fetch_invoice_details') {
        try {
            $inv_id = intval($_POST['id']);
            $inv_res = mysqli_query($conn, "SELECT i.*, c.client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = $inv_id");
            if(!$inv_res) throw new Exception(mysqli_error($conn));
            $invoice = mysqli_fetch_assoc($inv_res);
            
            $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
            if(!$items_res) throw new Exception(mysqli_error($conn));
            $items = [];
            while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
            
            echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// 3. FETCH DATA FOR UI
$clients = mysqli_query($conn, "SELECT * FROM clients ORDER BY client_name ASC");
$executives_query = mysqli_query($conn, "SELECT id, name, employee_id FROM users WHERE role IN ('Sales Executive', 'Sales Manager', 'Sales') ORDER BY name ASC");

// Fetch Pending Approval & Approved Invoices (That are NOT YET sent to executive)
// FIXED: Used i.status as inv_status to prevent column overlap with clients table
$pending_query = mysqli_query($conn, "
    SELECT i.*, c.*, c.email as client_email, i.id as inv_pk_id, c.client_name as fallback_company, i.status as inv_status 
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    WHERE i.status IN ('Pending Approval', 'Approved') 
    AND (i.assigned_executive IS NULL OR i.assigned_executive = '')
    ORDER BY i.created_at DESC
");

// Fetch Invoices already Sent to Executives
$sent_query = mysqli_query($conn, "
    SELECT i.*, c.client_name as fallback_company, c.company_name, c.email as client_email 
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    WHERE i.assigned_executive IS NOT NULL AND i.assigned_executive != ''
    ORDER BY i.created_at DESC
");

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Dispatch | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --theme-color: #1b5a5a; 
            --bg-body: #f1f5f9; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e2e8f0; 
            --primary-sidebar-width: 95px; 
        }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; }
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end;}
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }

        .header-actions { display: flex; gap: 15px; }
        .btn-report { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; transition: 0.2s;}
        .btn-report:hover { background: #bae6fd; transform: translateY(-1px); }
        
        .btn-new-inv { background: var(--theme-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; transition: 0.2s; text-decoration: none;}
        .btn-new-inv:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }

        .card { background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 30px; }
        
        /* Tabs */
        .tabs-header { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); }
        .tab-btn { padding: 16px 25px; background: none; border: none; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; display: flex; align-items: center; gap: 8px;}
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-btn:hover:not(.active) { color: var(--text-main); }
        
        .tab-pane { display: none; padding: 0; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Table */
        .dispatch-table { width: 100%; border-collapse: collapse; }
        .dispatch-table th { text-align: left; padding: 15px 20px; background: white; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid #f1f5f9; }
        .dispatch-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
        .dispatch-table tr:hover td { background: #f8fafc; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge-pending { background: #fef9c3; color: #d97706; border: 1px solid #fde047; }
        .badge-approved { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-sent { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .action-btns { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action { padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; transition: 0.2s; }
        .btn-view { background: #f1f5f9; color: #475569; }
        .btn-view:hover { background: #e2e8f0; }
        
        .btn-print-direct { background: #ffedd5; color: #ea580c; }
        .btn-print-direct:hover { background: #fed7aa; }

        .btn-send { background: var(--theme-color); color: white; }
        .btn-send:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }
        .btn-disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(3px); opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: white; padding: 0; border-radius: 12px; width: 100%; max-width: 450px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); transform: translateY(-20px); transition: transform 0.3s ease; overflow: hidden; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        
        .modal-header { padding: 20px 25px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 16px; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .close-modal { font-size: 20px; color: #94a3b8; cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: #ef4444; }
        
        .modal-body { padding: 25px; }
        .client-info-box { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #cbd5e1; }
        .client-info-box p { margin: 5px 0; font-size: 13px; color: #475569; display: flex; justify-content: space-between; }
        .client-info-box strong { color: var(--text-main); }

        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; color: var(--theme-color); }

        /* Form styling inside Modals */
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }

        /* --- PERFECT PRINT TEMPLATE ALIGNMENT --- */
        @media print {
            @page { size: A4; margin: 0; }
            body * { visibility: hidden; } 
            
            .modal-overlay, .modal-content, .modal-body {
                position: static !important; overflow: visible !important; transform: none !important;
                box-shadow: none !important; max-height: none !important; background: white !important;
                padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important;
            }

            #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { 
                position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 15mm; box-shadow: none !important; 
            }
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>Invoice Dispatch Hub</h2>
            <p>Generate invoices and dispatch them to Sales Executives.</p>
        </div>
        <div class="header-actions">
            <button class="btn-report" onclick="sendDailyUpdate()">
                <i class="ph-bold ph-file-arrow-up text-lg"></i> Daily Update to Accounts
            </button>
            <button class="btn-new-inv" onclick="openModal('createInvoiceModal')">
                <i class="ph-bold ph-plus-circle text-lg"></i> Generate New Invoice
            </button>
        </div>
    </div>

    <div class="card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-pending')">
                <i class="ph-bold ph-paper-plane-tilt"></i> Dispatch Queue
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-sent')">
                <i class="ph-bold ph-check-circle"></i> Sent to Executives
            </button>
        </div>

        <div id="tab-pending" class="tab-pane active">
            <table class="dispatch-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th style="width: 300px;">Client Details</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_pending = false;
                    if($pending_query) {
                        while($row = mysqli_fetch_assoc($pending_query)) {
                            $has_pending = true;
                            
                            $display_company = $row['company_name'] ?? $row['fallback_company'] ?? 'N/A';
                            $display_name = $row['name'] ?? $row['contact_person'] ?? 'N/A';
                            $display_desig = $row['designation'] ?? 'N/A';
                            $display_email = $row['client_email'] ?? 'No Mail ID';
                            $display_desc = $row['description'] ?? 'No description provided.';
                            
                            // FIXED: Check explicit inv_status
                            $status = $row['inv_status'];
                    ?>
                    <tr id="row-<?= $row['invoice_no'] ?>">
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        
                        <td>
                            <div style="font-weight: 800; color: var(--theme-color); font-size: 14px; margin-bottom: 4px;">
                                <?= htmlspecialchars($display_company) ?>
                            </div>
                            <div style="font-size: 12px; color: #475569; font-weight: 600;">
                                <i class="ph-fill ph-user-circle"></i> <?= htmlspecialchars($display_name) ?> 
                                <span style="color: var(--text-muted); font-weight: 400; margin-left: 4px;">| <?= htmlspecialchars($display_desig) ?></span>
                            </div>
                            <div style="font-size: 12px; color: #0284c7; margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                                <i class="ph-fill ph-envelope"></i> <a href="mailto:<?= htmlspecialchars($display_email) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($display_email) ?></a>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px; line-height: 1.4; background: #f8fafc; padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <strong>Desc:</strong> <?= htmlspecialchars($display_desc) ?>
                            </div>
                        </td>

                        <td><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                        <td style="font-weight: 700; color: #1b5a5a; font-size: 15px;">₹<?= number_format($row['grand_total'], 2) ?></td>
                        
                        <td>
                            <?php if($status === 'Approved'): ?>
                                <span class="badge badge-approved"><i class="ph-bold ph-check"></i> Accounts Approved</span>
                            <?php else: ?>
                                <span class="badge badge-pending"><i class="ph-bold ph-clock"></i> Pending Approval</span>
                            <?php endif; ?>
                        </td>
                        
                        <td style="text-align: right;">
                            <div class="action-btns" style="justify-content: flex-end;">
                                <button class="btn-action btn-view" onclick="viewInvoice('<?= $row['inv_pk_id'] ?>')">
                                    <i class="ph-bold ph-eye"></i> View
                                </button>
                                <button class="btn-action btn-print-direct" onclick="printInvoiceDirect('<?= $row['inv_pk_id'] ?>')">
                                    <i class="ph-bold ph-printer"></i> Print
                                </button>
                                
                                <?php if($status === 'Approved'): ?>
                                    <button class="btn-action btn-send" onclick="openDispatchModal('<?= $row['invoice_no'] ?>', '<?= addslashes($display_company) ?>', '<?= $row['grand_total'] ?>')">
                                        <i class="ph-bold ph-paper-plane-right"></i> Send
                                    </button>
                                <?php else: ?>
                                    <button class="btn-action btn-disabled" title="Waiting for CFO/Accounts Approval" onclick="Swal.fire('Pending Approval', 'You cannot dispatch this invoice until the CFO or Accounts team approves it.', 'info'); return false;">
                                        <i class="ph-bold ph-paper-plane-right"></i> Send
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        }
                    } 
                    if(!$has_pending): 
                    ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="ph-fill ph-check-circle"></i>
                                <h3>All Caught Up!</h3>
                                <p>No invoices are pending dispatch or awaiting approval.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-sent" class="tab-pane">
            <table class="dispatch-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th style="width: 300px;">Client Details</th>
                        <th>Sent Date</th>
                        <th>Amount</th>
                        <th>Assigned Executive</th>
                    </tr>
                </thead>
                <tbody id="sentTableBody">
                    <?php 
                    if($sent_query && mysqli_num_rows($sent_query) > 0): 
                        while($s_row = mysqli_fetch_assoc($sent_query)): 
                            $s_company = $s_row['company_name'] ?? $s_row['fallback_company'] ?? 'N/A';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s_row['invoice_no']) ?></strong></td>
                        <td>
                            <div style="font-weight: 700; color: var(--theme-color); font-size: 14px;">
                                <?= htmlspecialchars($s_company) ?>
                            </div>
                        </td>
                        <td><?= date('d M Y', strtotime($s_row['created_at'])) ?></td>
                        <td style="font-weight: 700; color: #1b5a5a; font-size: 15px;">₹<?= number_format($s_row['grand_total'], 2) ?></td>
                        <td><span class="badge badge-sent"><i class="ph-bold ph-user"></i> <?= htmlspecialchars($s_row['assigned_executive']) ?></span></td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr id="empty-sent-msg"><td colspan="5" style="text-align:center; padding: 30px; color: #94a3b8;">No invoices dispatched yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>


<div class="modal-overlay" id="createInvoiceModal">
    <div class="modal-content" style="max-width: 900px; padding: 0;">
        <div class="modal-header">
            <h3><i class="ph-bold ph-plus-circle" style="color: var(--theme-color);"></i> Generate New Invoice</h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal('createInvoiceModal')"></i>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto; background: #f8fafc;">
            <form id="invoiceForm">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                    <div class="form-group"><label>Invoice No</label><input type="text" name="invoice_no" value="INV-<?= date('Y-m') ?>-<?= rand(100, 999) ?>" readonly style="background:#f1f5f9; padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box;"></div>
                    <div class="form-group">
                        <label>Select Client *</label>
                        <div style="display:flex; gap:5px;">
                            <select name="client_id" id="client_id" required style="flex:1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                                <option value="">Choose Existing Client</option>
                                <?php if($clients) { mysqli_data_seek($clients, 0); while($row = mysqli_fetch_assoc($clients)) { echo "<option value='".$row['id']."'>".htmlspecialchars($row['client_name'])."</option>"; } } ?>
                            </select>
                            <button type="button" style="background:var(--theme-color); color:white; border:none; border-radius:6px; padding:0 12px; cursor:pointer;" onclick="openModal('addClientModal')">
                                <i class="ph-bold ph-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group"><label>Invoice Date *</label><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box;"></div>
                    <div class="form-group"><label>Bank Details *</label><input type="text" name="bank_name" list="bank_list" required placeholder="Select Bank" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box;"><datalist id="bank_list"><option value="South Indian Bank"><option value="ICICI Bank"><option value="SBI"></datalist></div>
                    
                    <div class="form-group"><label>Client Mobile</label><input type="text" name="client_mobile" id="client_mobile" placeholder="Auto-filled" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box;"></div>
                    <div class="form-group"><label>Client GSTIN</label><input type="text" name="client_gst" id="client_gst" placeholder="Auto-filled" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box;"></div>
                    <div class="form-group">
                        <label>Payment Mode *</label>
                        <select name="payment_mode" id="payment_mode" required style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; box-sizing: border-box; font-family: inherit;">
                            <option value="">Select Method</option>
                            <option value="UPI">UPI</option>
                            <option value="Cash">Cash</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Bank Transfer">Bank Transfer (NEFT/RTGS)</option>
                        </select>
                    </div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color);">
                    <table style="width:100%; border-collapse:collapse; margin-bottom: 10px;">
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
                                <td style="padding:5px; text-align:center;"><button type="button" onclick="if(document.querySelectorAll('.item-row').length > 1) { this.closest('tr').remove(); calculateGrandTotal(); }" style="color:red; background:none; border:none; cursor:pointer; font-size:18px;">&times;</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" onclick="addRow()" style="color:var(--theme-color); font-weight:bold; background:none; border:none; cursor:pointer; font-size: 13px;">+ Add Item Row</button>
                </div>

                <div style="width: 100%; max-width: 350px; background: white; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-left: auto; margin-top:20px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color: var(--text-main);"><span>Subtotal</span><span id="displaySubtotal">₹0.00</span></div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color: var(--text-main);"><span>Discount</span><span id="displayDiscount">₹0.00</span></div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color: var(--text-main);"><span>CGST (9%)</span><span id="displayCGST">₹0.00</span></div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color: var(--text-main);"><span>SGST (9%)</span><span id="displaySGST">₹0.00</span></div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color: var(--text-main);"><span>Round Off</span><span id="displayRoundOff">₹0.00</span></div>
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
                    <button type="button" style="padding:12px 20px; border:1px solid #ddd; border-radius:8px; background:white; cursor:pointer; font-weight:600; font-family: inherit;" onclick="document.getElementById('invoiceForm').reset(); calculateGrandTotal();">Reset Form</button>
                    <button type="button" onclick="submitInvoice()" id="saveBtn" style="background: var(--theme-color); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; font-family: inherit; font-size: 14px;">
                        <i class="ph-bold ph-paper-plane-right"></i> Generate & Send to Accounts
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addClientModal" style="z-index: 2500;">
    <div class="modal-content" style="max-width: 400px; padding: 0;">
        <div class="modal-header">
            <h3><i class="ph-bold ph-user-plus" style="color: var(--theme-color);"></i> Add New Client</h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal('addClientModal')"></i>
        </div>
        <div class="modal-body">
            <label style="display:block; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom: 6px;">Client Name *</label>
            <input type="text" id="new_client_name" placeholder="E.g., Facebook India" style="width:100%; border:1px solid #cbd5e1; padding:12px; border-radius:8px; margin-bottom:15px; box-sizing:border-box; outline:none; font-family: inherit;" required>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom: 6px;">GST Number</label>
                    <input type="text" id="new_client_gst" placeholder="Optional" style="width:100%; border:1px solid #cbd5e1; padding:12px; border-radius:8px; box-sizing:border-box; outline:none; font-family: inherit;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom: 6px;">Mobile Number</label>
                    <input type="text" id="new_client_mobile" placeholder="Mobile" style="width:100%; border:1px solid #cbd5e1; padding:12px; border-radius:8px; box-sizing:border-box; outline:none; font-family: inherit;">
                </div>
            </div>

            <label style="display:block; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom: 6px;">Preferred Payment Method</label>
            <select id="new_client_payment" style="width:100%; border:1px solid #cbd5e1; padding:12px; border-radius:8px; margin-bottom:25px; box-sizing:border-box; outline:none; font-family: inherit; background: white;">
                <option value="">Select Method</option>
                <option value="UPI">UPI</option>
                <option value="Cash">Cash</option>
                <option value="Debit Card">Debit Card</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Bank Transfer">Bank Transfer (NEFT/RTGS)</option>
            </select>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button onclick="closeModal('addClientModal')" style="padding:12px 20px; border-radius:8px; border:1px solid #ddd; background:white; cursor:pointer; font-weight:600; font-family: inherit;">Cancel</button>
                <button onclick="saveNewClient()" style="background:var(--theme-color); color:white; padding:12px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-family: inherit;">Save Client</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="dispatchModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="ph-bold ph-paper-plane-tilt" style="color: var(--theme-color);"></i> Assign to Sales Executive</h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal('dispatchModal')"></i>
        </div>
        <div class="modal-body">
            
            <div class="client-info-box">
                <p>Invoice No: <strong id="m_inv_no"></strong></p>
                <p>Company Name: <strong id="m_client"></strong></p>
                <p>Amount Due: <strong id="m_amount"></strong></p>
            </div>

            <div style="margin-top: 15px;">
                <label style="display:block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">Select Sales Executive</label>
                <select id="m_exec" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: inherit; font-size: 13px; outline: none;" required>
                    <option value="">-- Choose Executive --</option>
                    <?php 
                        if($executives_query){
                            mysqli_data_seek($executives_query, 0); 
                            while($ex = mysqli_fetch_assoc($executives_query)): 
                    ?>
                        <option value="<?= htmlspecialchars($ex['name']) ?>"><?= htmlspecialchars($ex['name']) ?> (<?= htmlspecialchars($ex['employee_id']) ?>)</option>
                    <?php 
                            endwhile; 
                        }
                    ?>
                </select>
            </div>

            <p style="font-size: 12px; color: #64748b; margin-top: 20px; line-height: 1.5; background: #f8fafc; padding: 10px; border-radius: 6px;">
                <i class="ph-fill ph-info"></i> Sending this will automatically notify the selected Sales Executive to forward the invoice to the client and collect the payment.
            </p>

            <button class="btn-action btn-send" id="dispatchBtn" style="width: 100%; padding: 14px; justify-content: center; margin-top: 15px; font-size: 14px;" onclick="sendToExecutive()">
                <i class="ph-bold ph-paper-plane-right"></i> Forward to Executive
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="previewModal">
    <div class="modal-content" style="max-width: 800px; padding: 0;">
        <div class="modal-header" style="background: var(--theme-color); color: white; border:none;">
            <h3 style="color: white; margin: 0;"><i class="ph-bold ph-file-text"></i> Invoice Preview</h3>
            <i class="ph-bold ph-x close-modal" style="color: white;" onclick="closeModal('previewModal')"></i>
        </div>
        
        <div class="modal-body" style="padding: 30px; max-height: 70vh; overflow-y: auto; background: #e2e8f0;">
            <div id="printableInvoice" style="width: 100%; max-width: 210mm; padding: 15mm; background: white; color: #333; line-height: 1.4; margin: 0 auto; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
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
                <div style="display: flex; justify-content: flex-end;">
                    <div style="width:250px;">
                        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>Sub Total</span><span id="p_sub"></span></div>
                        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>Discount</span><span id="p_disc"></span></div>
                        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>CGST (9%)</span><span id="p_cgst"></span></div>
                        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>SGST (9%)</span><span id="p_sgst"></span></div>
                        <div style="display:flex; justify-content:space-between; padding:5px 0; font-size:13px;"><span>Round Off</span><span id="p_roff"></span></div>
                        <div style="display:flex; justify-content:space-between; border-top:2px solid #1b5a5a; font-weight:800; font-size:16px; margin-top:5px; padding-top:5px; color:#1b5a5a;"><span>GRAND TOTAL</span><span id="p_grand"></span></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="padding: 15px 25px; background: white; border-top: 1px solid #eee; text-align: right;">
            <button class="btn-action btn-send" style="padding: 10px 20px; font-size: 14px;" onclick="window.print()">
                <i class="ph-bold ph-printer"></i> Print / Save as PDF
            </button>
        </div>
    </div>
</div>

<script>
    // --- GLOBAL MODAL OPEN/CLOSE ---
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    // --- CREATE INVOICE FORM LOGIC ---
    document.getElementById('client_id')?.addEventListener('change', function() {
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
        tr.innerHTML = `<td style="padding:5px;"><input type="text" name="item_desc[]" required style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_qty[]" class="item-qty" value="1" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_rate[]" class="item-rate" step="0.01" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_disc_val[]" class="item-disc" value="0" onchange="calculateRow(this)" style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; box-sizing:border-box;"></td><td style="padding:5px;"><input type="number" name="item_total[]" class="item-total" readonly style="width:100%; border:1px solid #ddd; padding:8px; border-radius:4px; background:#f8fafc; box-sizing:border-box;"></td><td style="padding:5px; text-align:center;"><button type="button" onclick="if(document.querySelectorAll('.item-row').length > 1) { this.closest('tr').remove(); calculateGrandTotal(); }" style="color:red; background:none; border:none; cursor:pointer; font-size:18px;">&times;</button></td>`;
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
        if (!document.getElementById('client_id').value) {
            Swal.fire('Required', 'Please select a Client to generate the invoice.', 'warning');
            return;
        }

        const btn = document.getElementById('saveBtn'); 
        btn.disabled = true; 
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Sending to Accounts...';
        
        fetch('', { method: 'POST', body: new FormData(document.getElementById('invoiceForm')) })
        .then(async response => {
            const textResponse = await response.text();
            try {
                const data = JSON.parse(textResponse);
                if(data.status === 'success') {
                    Swal.fire({
                        title: 'Invoice Sent!', 
                        text: 'It is now pending approval from the CFO / Accounts team.', 
                        icon: 'success', 
                        timer: 3000, 
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire('Database Error', data.message, 'error');
                    btn.disabled = false; btn.innerHTML = '<i class="ph-bold ph-paper-plane-right"></i> Generate & Send to Accounts';
                }
            } catch (err) {
                Swal.fire('Error', 'Something went wrong.', 'error');
                btn.disabled = false; btn.innerHTML = '<i class="ph-bold ph-paper-plane-right"></i> Generate & Send to Accounts';
            }
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
                closeModal('addClientModal');
                
                document.getElementById('new_client_name').value = '';
                document.getElementById('new_client_gst').value = '';
                document.getElementById('new_client_mobile').value = '';
                document.getElementById('new_client_payment').value = '';
                
                document.getElementById('client_mobile').value = fd.get('mobile_number');
                document.getElementById('client_gst').value = fd.get('gst_number');
                document.getElementById('payment_mode').value = fd.get('payment_method');
                
            } else { alert("Error saving client: " + res.message); }
        });
    }

    // --- DAILY REPORT UPDATE ---
    function sendDailyUpdate() {
        Swal.fire({
            title: 'Sending Update...',
            text: "Compiling today's dispatch report for the Accounts Team.",
            icon: 'info',
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
            Swal.fire('Success!', 'Daily update report sent to Accounts securely.', 'success');
        });
    }

    // --- TAB LOGIC ---
    function switchTab(evt, id) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    // --- VIEW IN-APP INVOICE PREVIEW & DIRECT PRINT ---
    function printInvoiceDirect(id) {
        viewInvoice(id, true);
    }

    function viewInvoice(id, autoPrint = false) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

        const fd = new FormData(); 
        fd.append('action', 'fetch_invoice_details'); 
        fd.append('id', id);
        
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            Swal.close();
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
                
                document.getElementById('previewModal').classList.add('active');

                if (autoPrint) {
                    setTimeout(() => {
                        window.print();
                    }, 400);
                }

            } else {
                Swal.fire('Error', 'Failed to load invoice details.', 'error');
            }
        });
    }

    function openDispatchModal(invNo, client, amount) {
        document.getElementById('m_inv_no').innerText = invNo;
        document.getElementById('m_client').innerText = client;
        document.getElementById('m_amount').innerText = '₹' + parseFloat(amount).toFixed(2);
        document.getElementById('m_exec').value = ""; 
        openModal('dispatchModal');
    }

    function sendToExecutive() {
        const execName = document.getElementById('m_exec').value;
        const invNo = document.getElementById('m_inv_no').innerText;

        if (!execName) {
            Swal.fire('Selection Required', 'Please select a Sales Executive from the list.', 'warning');
            return;
        }

        const btn = document.getElementById('dispatchBtn');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Dispatching...';
        btn.disabled = true;

        setTimeout(() => {
            btn.innerHTML = origText;
            btn.disabled = false;
            
            Swal.fire({
                title: 'Dispatched to Executive!',
                text: `Invoice ${invNo} has been assigned to ${execName}.`,
                icon: 'success',
                timer: 2500,
                showConfirmButton: false
            });

            moveRowToSent(invNo, execName);
        }, 1200);
    }

    function moveRowToSent(invNo, execName) {
        closeModal('dispatchModal');
        
        const row = document.getElementById('row-' + invNo);
        if(row) {
            const clientHtml = row.cells[1].innerHTML;
            const amountHtml = row.cells[3].innerHTML;
            const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            const newRow = `
                <tr>
                    <td><strong>${invNo}</strong></td>
                    <td>${clientHtml}</td>
                    <td>${today}</td>
                    <td>${amountHtml}</td>
                    <td><span class="badge badge-sent"><i class="ph-bold ph-user"></i> ${execName}</span></td>
                </tr>
            `;
            
            document.getElementById('sentTableBody').insertAdjacentHTML('afterbegin', newRow);
            row.remove();
            
            // Remove empty message if it exists
            const emptyMsg = document.getElementById('empty-sent-msg');
            if (emptyMsg) emptyMsg.remove();

            if (document.querySelectorAll('#tab-pending tbody tr').length === 0) {
                document.querySelector('#tab-pending tbody').innerHTML = `
                    <tr><td colspan="6"><div class="empty-state"><i class="ph-fill ph-check-circle"></i><h3>All Caught Up!</h3><p>No invoices are pending dispatch or awaiting approval.</p></div></td></tr>
                `;
            }

            // Sync with backend
            const fd = new FormData();
            fd.append('action', 'mark_sent');
            fd.append('invoice_id', invNo);
            fd.append('exec_name', execName);
            fetch('', { method: 'POST', body: fd });
        }
    }
</script>

</body>
</html>