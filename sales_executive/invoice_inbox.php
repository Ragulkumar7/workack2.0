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
 $current_user_name = $_SESSION['name'] ?? 'Unknown';

// 2. BACKEND AJAX HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if(ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    // --- FETCH INVOICE DETAILS FOR MODAL ---
    if ($_POST['action'] === 'fetch_invoice_details') {
        $inv_id = intval($_POST['id']);
        // Ensure the executive actually owns this invoice
        $stmt = $conn->prepare("SELECT i.*, c.client_name, c.company_name, c.mobile_number, c.gst_number as c_gst 
                               FROM invoices i 
                               JOIN clients c ON i.client_id = c.id 
                               WHERE i.id = ? AND i.assigned_executive = ?");
        $stmt->bind_param("is", $inv_id, $current_user_name);
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

        $stmt = $conn->prepare("UPDATE invoices SET payment_status = ? WHERE id = ? AND assigned_executive = ?");
        $stmt->bind_param("sis", $new_status, $inv_id, $current_user_name);
        
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
// Pending Action: Assigned to me, Approved by Admin, Not Paid yet
 $pending_query = mysqli_query($conn, "
    SELECT i.*, c.client_name, c.company_name, c.email as client_email, c.mobile_number 
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    WHERE i.assigned_executive = '$current_user_name' 
    AND i.status = 'Approved' 
    AND (i.payment_status = 'Unpaid' OR i.payment_status IS NULL)
    ORDER BY i.created_at DESC
");

// History: Paid or Rejected
 $history_query = mysqli_query($conn, "
    SELECT i.*, c.client_name, c.company_name 
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    WHERE i.assigned_executive = '$current_user_name' 
    AND (i.payment_status = 'Paid' OR i.status = 'Rejected')
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
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); min-height: 100vh; }
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end;}
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }

        .card { background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 30px; }
        
        /* Tabs */
        .tabs-header { display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color); }
        .tab-btn { padding: 16px 25px; background: none; border: none; font-size: 13px; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; display: flex; align-items: center; gap: 8px;}
        .tab-btn.active { color: var(--theme-color); border-bottom-color: var(--theme-color); background: white; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Table */
        .dispatch-table { width: 100%; border-collapse: collapse; }
        .dispatch-table th { text-align: left; padding: 15px 20px; background: white; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid #f1f5f9; }
        .dispatch-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
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
        
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(3px); }
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 0; border-radius: 12px; width: 100%; max-width: 700px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); max-height: 90vh; display: flex; flex-direction: column; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 16px; color: var(--text-main); }
        .close-modal { font-size: 20px; color: #94a3b8; cursor: pointer; }
        .modal-body { padding: 20px; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid var(--border-color); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .detail-box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .detail-box p { margin: 4px 0; font-size: 13px; }
        
        /* Print Styles */
        @media print {
            body * { visibility: hidden; }
            #printableInvoice, #printableInvoice * { visibility: visible; }
            #printableInvoice { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>My Invoice Inbox</h2>
            <p>Assigned tasks from management for collection.</p>
        </div>
    </div>

    <div class="card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-pending')">
                <i class="ph-bold ph-envelope-simple"></i> Pending Action
                <?php if($pending_query && mysqli_num_rows($pending_query) > 0): ?>
                    <span style="background:#ef4444; color:white; padding:2px 8px; border-radius:10px; font-size:10px;"><?= mysqli_num_rows($pending_query) ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-history')">
                <i class="ph-bold ph-check-circle"></i> Completed / History
            </button>
        </div>

        <div id="tab-pending" class="tab-pane active">
            <table class="dispatch-table">
                <thead><tr><th>Invoice No</th><th>Client Details</th><th>Issued Date</th><th>Amount</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    <?php 
                    if($pending_query && mysqli_num_rows($pending_query) > 0):
                        while($row = mysqli_fetch_assoc($pending_query)):
                            $company = $row['company_name'] ?? $row['client_name'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        <td>
                            <div style="font-weight:700; color:var(--text-main);"><?= htmlspecialchars($company) ?></div>
                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($row['mobile_number']) ?></div>
                        </td>
                        <td><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                        <td style="font-weight:700; color:var(--theme-color);">₹<?= number_format($row['grand_total'], 2) ?></td>
                        <td style="text-align:right;">
                            <div class="action-btns" style="justify-content:flex-end;">
                                <button class="btn-action btn-view" onclick="viewInvoice(<?= $row['id'] ?>)"><i class="ph-bold ph-eye"></i> View</button>
                                <button class="btn-action btn-print" onclick="printInvoice(<?= $row['id'] ?>)"><i class="ph-bold ph-printer"></i> Print</button>
                                <button class="btn-action btn-success" onclick="markAsPaid(<?= $row['id'] ?>)"><i class="ph-bold ph-check"></i> Paid</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No pending invoices assigned to you.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-history" class="tab-pane">
            <table class="dispatch-table">
                <thead><tr><th>Invoice No</th><th>Client</th><th>Status</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php 
                    if($history_query && mysqli_num_rows($history_query) > 0):
                        while($row = mysqli_fetch_assoc($history_query)):
                            $isPaid = ($row['payment_status'] == 'Paid');
                            $badge = $isPaid ? 'badge-paid' : 'badge-rejected';
                            $statusText = $isPaid ? 'Paid' : $row['status'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        <td><?= htmlspecialchars($row['company_name'] ?? $row['client_name']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= $statusText ?></span></td>
                        <td style="font-weight:700;">₹<?= number_format($row['grand_total'], 2) ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);">No history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Invoice Details: <span id="v_inv_no" style="color:#64748b;"></span></h3>
            <i class="ph-bold ph-x close-modal" onclick="document.getElementById('viewModal').classList.remove('active')"></i>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-box">
                    <p style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Client Info</p>
                    <h4 style="margin:5px 0; color:var(--theme-color);" id="v_client"></h4>
                    <p id="v_phone"></p>
                </div>
                <div class="detail-box">
                    <p style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase;">Financials</p>
                    <p><strong>Date:</strong> <span id="v_date"></span></p>
                    <p><strong>Grand Total:</strong> <span id="v_total" style="color:var(--theme-color); font-weight:700;"></span></p>
                </div>
            </div>
            <table class="dispatch-table" style="margin:0;">
                <thead><tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody id="v_items"></tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-print" onclick="triggerPrintFromModal()"><i class="ph-bold ph-printer"></i> Print</button>
        </div>
    </div>
</div>

<!-- Hidden Printable Area -->
<div id="printableInvoice" style="display:none; padding:20mm; background:white;">
    <div style="text-align:center; margin-bottom:20px;">
        <h1 style="color:var(--theme-color); margin:0;">NEOERA INFOTECH</h1>
        <p style="margin:0; font-size:12px;">9/96 h, post, village nagar, Coimbatore 641107</p>
    </div>
    <hr>
    <h2 style="text-align:center;">TAX INVOICE</h2>
    <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
        <div>
            <strong>Bill To:</strong><br>
            <span id="p_client"></span><br>
            <span id="p_phone"></span>
        </div>
        <div style="text-align:right;">
            <strong>Invoice #:</strong> <span id="p_inv"></span><br>
            <strong>Date:</strong> <span id="p_date"></span>
        </div>
    </div>
    <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
        <thead><tr style="background:#f1f5f9;"><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
        <tbody id="p_items"></tbody>
    </table>
    <div style="text-align:right;">
        <h3>Grand Total: ₹<span id="p_total"></span></h3>
    </div>
</div>

<script>
    let currentViewingId = null;

    function switchTab(evt, id) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

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
                document.getElementById('v_inv_no').innerText = inv.invoice_no;
                document.getElementById('v_client').innerText = inv.company_name || inv.client_name;
                document.getElementById('v_phone').innerText = inv.mobile_number || 'N/A';
                document.getElementById('v_date').innerText = inv.invoice_date;
                document.getElementById('v_total').innerText = '₹' + parseFloat(inv.grand_total).toFixed(2);
                
                const tbody = document.getElementById('v_items');
                tbody.innerHTML = '';
                data.items.forEach(it => {
                    tbody.innerHTML += `<tr>
                        <td style="padding:10px;">${it.description}</td>
                        <td style="text-align:center; padding:10px;">${it.qty}</td>
                        <td style="text-align:right; padding:10px;">₹${parseFloat(it.total_amount).toFixed(2)}</td>
                    </tr>`;
                });
                
                document.getElementById('viewModal').classList.add('active');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function triggerPrintFromModal() {
        printInvoice(currentViewingId);
    }

    function printInvoice(id) {
        const fd = new FormData();
        fd.append('action', 'fetch_invoice_details');
        fd.append('id', id);
        
        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success'){
                const inv = data.invoice;
                document.getElementById('p_client').innerText = inv.company_name || inv.client_name;
                document.getElementById('p_phone').innerText = inv.mobile_number;
                document.getElementById('p_inv').innerText = inv.invoice_no;
                document.getElementById('p_date').innerText = inv.invoice_date;
                document.getElementById('p_total').innerText = parseFloat(inv.grand_total).toFixed(2);
                
                const tbody = document.getElementById('p_items');
                tbody.innerHTML = '';
                data.items.forEach(it => {
                    tbody.innerHTML += `<tr>
                        <td>${it.description}</td>
                        <td>${it.qty}</td>
                        <td>₹${parseFloat(it.rate).toFixed(2)}</td>
                        <td>₹${parseFloat(it.total_amount).toFixed(2)}</td>
                    </tr>`;
                });

                // Hide main UI, show printable area
                document.querySelector('.main-content').style.display = 'none';
                const printDiv = document.getElementById('printableInvoice');
                printDiv.style.display = 'block';
                
                window.print();
                
                // Restore UI
                setTimeout(() => {
                    printDiv.style.display = 'none';
                    document.querySelector('.main-content').style.display = 'block';
                    document.getElementById('viewModal').classList.remove('active');
                }, 100);
            }
        });
    }

    function markAsPaid(id) {
        Swal.fire({
            title: 'Mark as Paid?',
            text: "This will move the invoice to your history tab.",
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
                        Swal.fire('Updated!', 'Invoice marked as Paid.', 'success').then(() => location.reload());
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