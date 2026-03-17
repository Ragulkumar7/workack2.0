<?php
// invoice_inbox.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. SECURITY & DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get Current User Info
$current_user_id = $_SESSION['user_id'];
// Fetch the exact name from the DB to prevent session space/casing mismatches
$user_fetch = mysqli_query($conn, "SELECT name FROM users WHERE id = '$current_user_id'");
if ($user_fetch && mysqli_num_rows($user_fetch) > 0) {
    $u_row = mysqli_fetch_assoc($user_fetch);
    $current_user_name = mysqli_real_escape_string($conn, $u_row['name']);
} else {
    $current_user_name = mysqli_real_escape_string($conn, $_SESSION['name'] ?? 'Unknown');
}

// --- ENTERPRISE DATABASE PATCHER ---
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `invoice_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, 
        `executive_name` VARCHAR(100), 
        `client_id` INT, 
        `expected_amount` DECIMAL(12,2), 
        `notes` TEXT, 
        `status` VARCHAR(50) DEFAULT 'Pending', 
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) {}

// 2. BACKEND AJAX HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if(ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    // --- REQUEST NEW INVOICE FROM MANAGER ---
    if ($_POST['action'] === 'request_invoice') {
        $client_id = (int)$_POST['client_id'];
        $amount = floatval($_POST['expected_amount']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);

        $stmt = $conn->prepare("INSERT INTO invoice_requests (executive_name, client_id, expected_amount, notes) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sids", $current_user_name, $client_id, $amount, $notes);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Invoice request sent to management.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send request: ' . $conn->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database preparation error.']);
        }
        exit;
    }

    // --- FETCH INVOICE DETAILS FOR MODAL ---
    if ($_POST['action'] === 'fetch_invoice_details') {
        $inv_id = intval($_POST['id']);
        
        // 🚨 FIXED: Allow Executive to view it if it's dispatched to the pool (assigned_executive is NULL/Empty)
        $stmt = $conn->prepare("SELECT i.*, c.client_name, c.company_name, c.mobile_number, c.gst_number as c_gst 
                               FROM invoices i 
                               LEFT JOIN clients c ON i.client_id = c.id 
                               WHERE i.id = ? AND (i.assigned_executive = ? OR i.assigned_to = ? OR i.assigned_executive IS NULL OR i.assigned_executive = '')");
        $stmt->bind_param("isi", $inv_id, $current_user_name, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $stmt->close();

        if ($invoice) {
            $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id = $inv_id");
            $items = [];
            while($it = mysqli_fetch_assoc($items_res)) { $items[] = $it; }
            echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invoice not found or access denied.']);
        }
        exit;
    }

    // --- UPDATE PAYMENT STATUS ---
    if ($_POST['action'] === 'update_status') {
        $inv_id = intval($_POST['id']);
        $new_status = mysqli_real_escape_string($conn, $_POST['status']);

        $stmt = $conn->prepare("UPDATE invoices SET payment_status = ? WHERE id = ? AND (assigned_executive = ? OR assigned_to = ? OR assigned_executive IS NULL OR assigned_executive = '')");
        $stmt->bind_param("sisi", $new_status, $inv_id, $current_user_name, $current_user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update status.']);
        }
        $stmt->close();
        exit;
    }
}

// 3. FETCH DATA FOR UI

// A. Clients List for Request Dropdown
$clients_query = mysqli_query($conn, "SELECT id, client_name, company_name FROM clients ORDER BY client_name ASC");

// B. Pending Action: 🚨 FIXED: Pulls unassigned dispatched invoices along with the explicitly assigned ones
$pending_query = mysqli_query($conn, "
    SELECT i.*, c.client_name, c.company_name, c.email as client_email, c.mobile_number 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE (i.assigned_executive = '$current_user_name' OR i.assigned_to = '$current_user_id' OR i.assigned_executive IS NULL OR i.assigned_executive = '') 
    AND (i.payment_status != 'Paid' OR i.payment_status IS NULL)
    AND i.status != 'Rejected'
    ORDER BY i.created_at DESC
");

// C. History: Paid or Rejected
$history_query = mysqli_query($conn, "
    SELECT i.*, c.client_name, c.company_name 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE (i.assigned_executive = '$current_user_name' OR i.assigned_to = '$current_user_id' OR i.assigned_executive IS NULL OR i.assigned_executive = '') 
    AND (i.payment_status = 'Paid' OR i.status = 'Rejected')
    ORDER BY i.created_at DESC
");

// D. My Requests: Invoices requested by this executive
$requests_query = mysqli_query($conn, "
    SELECT r.*, c.client_name, c.company_name 
    FROM invoice_requests r 
    LEFT JOIN clients c ON r.client_id = c.id 
    WHERE r.executive_name = '$current_user_name' 
    ORDER BY r.created_at DESC
");

if (file_exists('../sidebars.php')) include '../sidebars.php'; 
if (file_exists('../header.php')) include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoice Inbox | Workack</title>
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
            --success: #10b981;
            --danger: #ef4444;
        }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); min-height: 100vh; }
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end;}
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }

        .btn-new-inv { background: var(--theme-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; transition: 0.2s;}
        .btn-new-inv:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }

        .card { background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 30px; }
        
        .tabs-header { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); }
        .tab-btn { padding: 16px 25px; background: none; border: none; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; display: flex; align-items: center; gap: 8px;}
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease;}
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .dispatch-table { width: 100%; border-collapse: collapse; }
        .dispatch-table th { text-align: left; padding: 15px 20px; background: white; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid #f1f5f9; }
        .dispatch-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .dispatch-table tr:hover td { background: #f8fafc; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge-unpaid { background: #fef9c3; color: #b45309; border: 1px solid #fde047; }
        .badge-paid { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        .action-btns { display: flex; gap: 8px; }
        .btn-action { padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; transition: 0.2s; }
        .btn-view { background: #f1f5f9; color: #475569; }
        .btn-view:hover { background: #e2e8f0; }
        .btn-print { background: #ffedd5; color: #ea580c; }
        .btn-print:hover { background: #fed7aa; }
        .btn-success { background: var(--success); color: white; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(3px); opacity: 0; transition: opacity 0.3s ease;}
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: white; padding: 0; border-radius: 12px; width: 100%; max-width: 600px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); max-height: 90vh; display: flex; flex-direction: column; transform: translateY(-20px); transition: transform 0.3s ease;}
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-header { padding: 20px 25px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;}
        .modal-header h3 { margin: 0; font-size: 16px; color: var(--text-main); }
        .close-modal { font-size: 20px; color: #94a3b8; cursor: pointer; }
        .modal-body { padding: 25px; overflow-y: auto; }
        .modal-footer { padding: 15px 25px; border-top: 1px solid var(--border-color); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }

        .form-group { display: flex; flex-direction: column; margin-bottom: 18px; }
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .form-input { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; outline: none; transition: 0.2s; box-sizing: border-box;}
        .form-input:focus { border-color: var(--theme-color); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }
        
        .swal2-container { z-index: 9999 !important; }

        /* 🚨 NEW INVOICE PREVIEW STYLES */
        .preview-modal { max-width: 800px; background: #f1f5f9; }
        .preview-header { background: var(--theme-color); color: white; border-top-left-radius: 12px; border-top-right-radius: 12px; padding: 15px 25px; border: none; }
        .preview-header .close-modal { color: white; opacity: 0.8; transition: 0.2s; }
        .preview-header .close-modal:hover { opacity: 1; }
        
        .invoice-preview-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); color: #334155; font-family: 'Plus Jakarta Sans', sans-serif; }
        .inv-header-row { display: flex; justify-content: space-between; align-items: flex-start; }
        .inv-brand h2 { color: var(--theme-color); font-size: 24px; margin: 0 0 5px 0; font-weight: 800; }
        .inv-brand p { margin: 0; font-size: 12px; color: #64748b; }
        .inv-meta { text-align: right; }
        .inv-meta h2 { color: var(--theme-color); font-size: 18px; margin: 0 0 5px 0; font-weight: 700; }
        .inv-meta p { margin: 2px 0; font-size: 12px; color: #475569; }
        
        .inv-divider { height: 2px; background: var(--theme-color); margin: 20px 0; }
        .inv-divider-light { height: 1px; background: #e2e8f0; margin: 20px 0; }
        
        .inv-bill-row { display: flex; justify-content: space-between; align-items: flex-start; }
        .inv-billed-to h4, .inv-bank-details h4 { color: var(--theme-color); font-size: 11px; margin: 0 0 8px 0; font-weight: 800; }
        .inv-billed-to h3 { margin: 0; font-size: 16px; color: #1e293b; font-weight: 700; }
        .inv-bank-details { text-align: right; }
        .inv-bank-details div { font-size: 12px; color: #475569; line-height: 1.5; }
        
        .inv-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .inv-table th { background: #f8fafc; padding: 12px 15px; font-size: 11px; font-weight: 800; color: #0f172a; text-align: left; border: 1px solid #e2e8f0; }
        .inv-table td { padding: 12px 15px; font-size: 13px; color: #334155; border: 1px solid #e2e8f0; }
        
        .inv-totals-box { display: flex; justify-content: flex-end; margin-top: 20px; }
        .inv-totals-table { width: 300px; border-collapse: collapse; }
        .inv-totals-table td { padding: 8px 0; text-align: right; font-size: 13px; color: #475569; }
        .inv-totals-table td:first-child { text-align: left; }
        .inv-totals-table tr.grand-total td { font-weight: 800; color: var(--theme-color); font-size: 16px; padding-top: 15px; border-top: 2px solid var(--theme-color); }

       /* 🚨 PROFESSIONAL PRINT STYLES */
        @media print {
            @page { size: A4; margin: 0; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            
            /* 1. Hide the entire website layout (Sidebar, Header, Main Content) completely */
            body * { visibility: hidden !important; }
            aside, header, nav, .sidebar, .header, .topbar, .navbar, .page-container, .main-content, .modal-overlay, .swal2-container, .page-header, .card { display: none !important; }
            
            /* 2. Show ONLY the invoice, isolate it completely and force it to top-left */
            #printableInvoice, #printableInvoice * { 
                visibility: visible !important; 
            }
            #printableInvoice { 
                display: block !important; 
                position: absolute !important; 
                left: 0 !important; 
                top: 0 !important; 
                width: 100% !important; 
                padding: 20mm !important; 
                margin: 0 !important;
                background: white !important;
                border: none !important; 
                box-shadow: none !important;
                box-sizing: border-box !important;
            }
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>My Invoice Inbox</h2>
            <p>Invoices assigned to you by management and your own requests.</p>
        </div>
        <div>
            <button class="btn-new-inv" onclick="openModal('requestInvoiceModal')">
                <i class="ph-bold ph-plus-circle text-lg"></i> Request Invoice
            </button>
        </div>
    </div>

    <div class="card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-pending')">
                <i class="ph-bold ph-envelope-simple"></i> Inbox Queue
                <?php if($pending_query && mysqli_num_rows($pending_query) > 0): ?>
                    <span style="background:#ef4444; color:white; padding:2px 8px; border-radius:10px; font-size:10px;"><?= mysqli_num_rows($pending_query) ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-requests')">
                <i class="ph-bold ph-paper-plane-tilt"></i> My Requests
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-history')">
                <i class="ph-bold ph-check-circle"></i> Completed / Paid
            </button>
        </div>

        <div id="tab-pending" class="tab-pane active">
            <table class="dispatch-table">
                <thead><tr><th>Invoice No</th><th>Client Details</th><th>Issued Date</th><th>Amount</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    <?php 
                    if($pending_query && mysqli_num_rows($pending_query) > 0):
                        while($row = mysqli_fetch_assoc($pending_query)):
                            $company = !empty($row['company_name']) ? $row['company_name'] : $row['client_name'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        <td>
                            <div style="font-weight:700; color:var(--text-main);"><?= htmlspecialchars($company) ?></div>
                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($row['mobile_number'] ?? 'N/A') ?></div>
                        </td>
                        <td><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                        <td style="font-weight:700; color:var(--theme-color);">₹<?= number_format($row['grand_total'], 2) ?></td>
                        <td style="text-align:right;">
                            <div class="action-btns" style="justify-content:flex-end;">
                                <button class="btn-action btn-view" onclick="viewInvoice(<?= $row['id'] ?>)"><i class="ph-bold ph-eye"></i> View</button>
                                <button class="btn-action btn-print" onclick="printInvoiceDirect(<?= $row['id'] ?>)"><i class="ph-bold ph-download-simple"></i> Download PDF</button>
                                <button class="btn-action btn-success" onclick="markAsPaid(<?= $row['id'] ?>)"><i class="ph-bold ph-check"></i> Mark Paid</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-muted);">Your inbox is clear! No pending invoices assigned to you.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-requests" class="tab-pane">
            <table class="dispatch-table">
                <thead><tr><th>Date</th><th>Client</th><th>Expected Amount</th><th>Notes</th><th>Status</th></tr></thead>
                <tbody>
                    <?php 
                    if($requests_query && mysqli_num_rows($requests_query) > 0):
                        while($row = mysqli_fetch_assoc($requests_query)):
                            $company = !empty($row['company_name']) ? $row['company_name'] : $row['client_name'];
                            $statusBadge = 'badge-unpaid';
                            if ($row['status'] === 'Generated' || $row['status'] === 'Approved') $statusBadge = 'badge-paid';
                            if ($row['status'] === 'Rejected') $statusBadge = 'badge-rejected';
                    ?>
                    <tr>
                        <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                        <td><strong style="color:var(--theme-color);"><?= htmlspecialchars($company) ?></strong></td>
                        <td style="font-weight:700;">₹<?= number_format($row['expected_amount'], 2) ?></td>
                        <td style="color:var(--text-muted); max-width: 250px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= htmlspecialchars($row['notes']) ?>">
                            <?= htmlspecialchars($row['notes']) ?>
                        </td>
                        <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-muted);">You have not requested any invoices yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-history" class="tab-pane">
            <table class="dispatch-table">
                <thead><tr><th>Invoice No</th><th>Client</th><th>Status</th><th>Amount</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    <?php 
                    if($history_query && mysqli_num_rows($history_query) > 0):
                        while($row = mysqli_fetch_assoc($history_query)):
                            $isPaid = ($row['payment_status'] == 'Paid');
                            $badge = $isPaid ? 'badge-paid' : 'badge-rejected';
                            $statusText = $isPaid ? 'Paid' : $row['status'];
                            $company = !empty($row['company_name']) ? $row['company_name'] : $row['client_name'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        <td><?= htmlspecialchars($company) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= $statusText ?></span></td>
                        <td style="font-weight:700;">₹<?= number_format($row['grand_total'], 2) ?></td>
                        <td style="text-align:right;">
                            <button class="btn-action btn-view" onclick="viewInvoice(<?= $row['id'] ?>)"><i class="ph-bold ph-file-pdf"></i> View File</button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-muted);">No history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="requestInvoiceModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="ph-bold ph-plus-circle" style="color: var(--theme-color);"></i> Request Invoice from Manager</h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal('requestInvoiceModal')"></i>
        </div>
        <div class="modal-body">
            <form id="requestForm" onsubmit="event.preventDefault(); submitInvoiceRequest();">
                <input type="hidden" name="action" value="request_invoice">
                <div class="form-group">
                    <label>Select Client *</label>
                    <select name="client_id" required class="form-input">
                        <option value="" disabled selected>-- Choose Client --</option>
                        <?php if($clients_query) { while($row = mysqli_fetch_assoc($clients_query)) { 
                            $cName = !empty($row['company_name']) ? $row['company_name'] : $row['client_name'];
                            echo "<option value='".$row['id']."'>".htmlspecialchars($cName)."</option>"; 
                        } } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expected Deal Amount (₹) *</label>
                    <input type="number" name="expected_amount" required min="1" step="0.01" class="form-input" placeholder="e.g. 50000">
                </div>
                <div class="form-group">
                    <label>Notes / Service Description *</label>
                    <textarea name="notes" rows="4" required class="form-input" placeholder="Describe the services sold so the manager can format the invoice items..."></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-action btn-view" onclick="closeModal('requestInvoiceModal')" style="padding: 10px 20px;">Cancel</button>
                    <button type="submit" id="btnSubmitRequest" class="btn-new-inv"><i class="ph-bold ph-paper-plane-tilt"></i> Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="viewModal">
    <div class="modal-content preview-modal">
        <div class="modal-header preview-header">
            <h3 style="color: white; margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="ph-bold ph-file-text"></i> Invoice Preview
            </h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal('viewModal')"></i>
        </div>
        <div class="modal-body" style="background: #f1f5f9; padding: 25px;">
            <div class="invoice-preview-card">
                <div class="inv-header-row">
                    <div class="inv-brand">
                        <h2>NEOERA INFOTECH</h2>
                        <p>9/96 h, Post, Village Nagar, Coimbatore 641107</p>
                    </div>
                    <div class="inv-meta">
                        <h2>TAX INVOICE</h2>
                        <p>No: <strong id="v_inv_no"></strong></p>
                        <p>Date: <strong id="v_date"></strong></p>
                    </div>
                </div>
                
                <div class="inv-divider"></div>
                
                <div class="inv-bill-row">
                    <div class="inv-billed-to">
                        <h4>BILLED TO</h4>
                        <h3 id="v_client"></h3>
                    </div>
                    <div class="inv-bank-details">
                        <h4>BANK DETAILS</h4>
                        <div id="v_bank"></div>
                    </div>
                </div>
                
                <div class="inv-divider-light"></div>
                
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>PARTICULARS</th>
                            <th style="text-align:center;">QTY</th>
                            <th style="text-align:right;">RATE</th>
                            <th style="text-align:right;">DISC (₹)</th>
                            <th style="text-align:right;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody id="v_items"></tbody>
                </table>
                
                <div class="inv-totals-box">
                    <table class="inv-totals-table">
                        <tr><td>Sub Total</td><td id="v_sub"></td></tr>
                        <tr><td>Discount</td><td id="v_disc"></td></tr>
                        <tr><td>CGST (9%)</td><td id="v_cgst"></td></tr>
                        <tr><td>SGST (9%)</td><td id="v_sgst"></td></tr>
                        <tr><td>Round Off</td><td id="v_round"></td></tr>
                        <tr class="grand-total"><td>GRAND TOTAL</td><td id="v_grand"></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="printableInvoice" style="display:none; font-family: Arial, sans-serif; padding: 20px; color: #000; background: #fff;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <img src="../assets/neoera.png" alt="Neoera Logo" style="height: 45px; margin-bottom: 5px;">
            <h3 style="margin: 5px 0 2px 0; font-size: 14px; font-weight: 900; letter-spacing: 1px;">NEOERA INFOTECH</h3>
            <p style="margin: 0; font-size: 11px; line-height: 1.4;">
                9/96 h, post, village nagar, Kurumbapalayam<br>
                SSKulam, coimbatore, Tamil Nadu 641107<br>
                Phone +91 866 802 5451
            </p>
        </div>
        <div style="text-align: right; margin-top: 20px;">
            <h1 style="margin: 0 0 15px 0; font-size: 28px; font-weight: 900; letter-spacing: 2px;">INVOICE</h1>
            <table style="float: right; text-align: right; font-size: 11px; font-weight: bold;">
                <tr>
                    <td style="padding: 3px 15px 3px 0;">DATE:</td>
                    <td id="p_date" style="font-weight: normal;"></td>
                </tr>
                <tr>
                    <td style="padding: 3px 15px 3px 0;">INVOICE #:</td>
                    <td id="p_inv" style="font-weight: normal;"></td>
                </tr>
            </table>
        </div>
    </div>

    <div style="border-top: 2px solid #000; margin: 25px 0 20px 0;"></div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 11px;">
        <div style="width: 45%;">
            <h4 style="margin: 0 0 5px 0; font-size: 11px; font-weight: bold;">BILL TO:</h4>
            <div style="border-top: 1px solid #ccc; width: 40%; margin-bottom: 8px;"></div>
            <strong id="p_client" style="display: block; margin-bottom: 5px; text-transform: uppercase;"></strong>
            <div>GSTIN: <span id="p_gst"></span></div>
            <div>Phone: <span id="p_phone"></span></div>
        </div>
        <div style="width: 45%;">
            <h4 style="margin: 0 0 5px 0; font-size: 11px; font-weight: bold;">ACCOUNT DETAILS:</h4>
            <div style="border-top: 1px solid #ccc; width: 80%; margin-bottom: 8px;"></div>
            <div style="margin-bottom: 3px;"><strong>Account Name:</strong> <span id="p_acc_name">NEOERA INFOTECH</span></div>
            <div style="margin-bottom: 3px;"><strong>Account Number:</strong> <span id="p_acc_num">N/A</span></div>
            <div style="margin-bottom: 3px;"><strong>IFSC:</strong> <span id="p_ifsc">N/A</span></div>
            <div style="margin-bottom: 3px;"><strong>UPI ID:</strong> <span id="p_upi">N/A</span></div>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; text-align: center;">
        <thead>
            <tr>
                <th style="padding: 8px; border-top: 2px solid #000; border-bottom: 1px solid #000;">S.NO</th>
                <th style="padding: 8px; border-top: 2px solid #000; border-bottom: 1px solid #000; text-align: left;">DESCRIPTION</th>
                <th style="padding: 8px; border-top: 2px solid #000; border-bottom: 1px solid #000;">QUANTITY</th>
                <th style="padding: 8px; border-top: 2px solid #000; border-bottom: 1px solid #000;">UNIT PRICE</th>
                <th style="padding: 8px; border-top: 2px solid #000; border-bottom: 1px solid #000; text-align: right;">AMOUNT</th>
            </tr>
        </thead>
        <tbody id="p_items">
        </tbody>
    </table>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 50px;">
        <table style="width: 250px; text-align: right; font-size: 11px;">
            <tr>
                <td style="padding: 4px 0;">Total</td>
                <td style="padding: 4px 0;" id="p_sub"></td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">CGST</td>
                <td style="padding: 4px 0;" id="p_cgst"></td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">SGST</td>
                <td style="padding: 4px 0;" id="p_sgst"></td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 0;">
                    <div style="border-top: 2px solid #000; margin: 4px 0;"></div>
                </td>
            </tr>
            <tr>
                <td style="padding: 6px 0; font-weight: bold; font-size: 14px;">TOTAL</td>
                <td style="padding: 6px 0; font-weight: bold; font-size: 14px;" id="p_total"></td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 0;">
                    <div style="border-top: 2px solid #000; margin: 4px 0;"></div>
                </td>
            </tr>
        </table>
    </div>

    <div style="border-top: 1px solid #000; margin-top: 100px; padding-top: 10px; text-align: center; font-size: 10px; font-weight: bold;">
        Neoera infotech<br>
        Phone +91 866 802 5451 | Contact@neoerainfotech.com | www.neoerainfotech.com
    </div>
</div>

<script>
    let currentViewingId = null;

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // Close Modals on outside click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    function switchTab(evt, id) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    // --- REQUEST INVOICE LOGIC ---
    function submitInvoiceRequest() {
        const btn = document.getElementById('btnSubmitRequest');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Sending...';
        btn.disabled = true;

        const fd = new FormData(document.getElementById('requestForm'));
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({icon: 'success', title: 'Requested!', text: data.message, confirmButtonColor: '#1b5a5a'})
                .then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }).catch(err => {
            Swal.fire('Error', 'Failed to connect to server.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    // --- VIEW / DOWNLOAD INVOICE LOGIC ---
    function viewInvoice(id) {
        currentViewingId = id;
        const fd = new FormData();
        fd.append('action', 'fetch_invoice_details');
        fd.append('id', id);
        
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success'){
                const inv = data.invoice;
                
                // Header & Client Info
                document.getElementById('v_inv_no').innerText = inv.invoice_no;
                document.getElementById('v_date').innerText = inv.invoice_date;
                document.getElementById('v_client').innerText = inv.company_name || inv.client_name || 'Unknown Client';
                
                // Format Bank Details cleanly (replaces | with <br>)
                let bankHtml = 'N/A';
                if(inv.bank_name) { bankHtml = inv.bank_name.split('|').join('<br>'); }
                document.getElementById('v_bank').innerHTML = bankHtml;

                // Items Table
                const tbody = document.getElementById('v_items');
                tbody.innerHTML = '';
                data.items.forEach(it => {
                    let discAmount = parseFloat(it.discount_amount || 0).toFixed(2);
                    tbody.innerHTML += `<tr>
                        <td>${it.description}</td>
                        <td style="text-align:center;">${it.qty}</td>
                        <td style="text-align:right;">${parseFloat(it.rate).toFixed(2)}</td>
                        <td style="text-align:right;">${discAmount}</td>
                        <td style="text-align:right;">${parseFloat(it.total_amount).toFixed(2)}</td>
                    </tr>`;
                });
                
                // Totals
                document.getElementById('v_sub').innerText = '₹' + parseFloat(inv.sub_total || 0).toFixed(2);
                document.getElementById('v_disc').innerText = '₹' + parseFloat(inv.discount || 0).toFixed(2);
                document.getElementById('v_cgst').innerText = '₹' + parseFloat(inv.cgst || 0).toFixed(2);
                document.getElementById('v_sgst').innerText = '₹' + parseFloat(inv.sgst || 0).toFixed(2);
                document.getElementById('v_round').innerText = '₹' + parseFloat(inv.round_off || 0).toFixed(2);
                document.getElementById('v_grand').innerText = '₹' + parseFloat(inv.grand_total || 0).toFixed(2);
                
                openModal('viewModal');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function printInvoiceDirect(id) {
        currentViewingId = id;
        triggerPrintFromModal();
    }

    function triggerPrintFromModal() {
        const fd = new FormData();
        fd.append('action', 'fetch_invoice_details');
        fd.append('id', currentViewingId);
        
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success'){
                const inv = data.invoice;
                // 🚨 Populate precisely mapped Print UI details
                document.getElementById('p_client').innerText = inv.company_name || inv.client_name || 'Unknown Client';
                document.getElementById('p_gst').innerText = inv.c_gst || 'N/A';
                document.getElementById('p_phone').innerText = inv.mobile_number || 'N/A';
                document.getElementById('p_inv').innerText = inv.invoice_no;
                document.getElementById('p_date').innerText = inv.invoice_date;
                
                const tbody = document.getElementById('p_items');
                tbody.innerHTML = '';
                let counter = 1;
                data.items.forEach(it => {
                    tbody.innerHTML += `<tr>
                        <td style="padding: 8px; border-bottom: 1px solid #eee;">${counter++}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: left;">${it.description}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #eee;">${it.qty}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #eee;">${parseFloat(it.rate).toFixed(2)}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: right;">${parseFloat(it.total_amount).toFixed(2)}</td>
                    </tr>`;
                });

                document.getElementById('p_sub').innerText = '₹ ' + parseFloat(inv.sub_total || 0).toFixed(2);
                document.getElementById('p_cgst').innerText = '₹ ' + parseFloat(inv.cgst || 0).toFixed(2);
                document.getElementById('p_sgst').innerText = '₹ ' + parseFloat(inv.sgst || 0).toFixed(2);
                document.getElementById('p_total').innerText = '₹ ' + parseFloat(inv.grand_total || 0).toFixed(2);

                closeModal('viewModal');
                
                // Allow CSS @media print to do its job, trigger native print dialog
                setTimeout(() => { window.print(); }, 200);
            }
        });
    }

    // --- MARK INVOICE AS PAID ---
    function markAsPaid(id) {
        Swal.fire({
            title: 'Payment Received?',
            text: "This will mark the invoice as Paid and move it to your History tab.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, Mark Paid'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('id', id);
                fd.append('status', 'Paid');
                
                fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success'){
                        Swal.fire('Updated!', 'Invoice successfully marked as Paid.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }
</script>

</body>
</html>