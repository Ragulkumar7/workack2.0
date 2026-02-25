<?php 
// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// Simulate updating invoice status to "Sent to Client"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_sent') {
    if(ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    $inv_id = mysqli_real_escape_string($conn, $_POST['invoice_id']);
    
    // In a real scenario, you'd update the status in the DB
    // mysqli_query($conn, "UPDATE invoices SET status = 'Sent to Client' WHERE invoice_no = '$inv_id'");
    
    echo json_encode(['status' => 'success']);
    exit;
}

// Fetch Approved Invoices ready for Dispatch
// We fetch 'Approved' invoices which means Accounts verified it, and it's ready for the Sales Exec.
$pending_query = mysqli_query($conn, "
    SELECT i.*, c.client_name, c.mobile_number, c.email as client_email 
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    WHERE i.status = 'Approved' 
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
        .dispatch-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .dispatch-table tr:hover td { background: #f8fafc; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-ready { background: #fef9c3; color: #d97706; border: 1px solid #fde047; }
        .badge-sent { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        .action-btns { display: flex; gap: 8px; }
        .btn-action { padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; transition: 0.2s; }
        .btn-view { background: #f1f5f9; color: #475569; }
        .btn-view:hover { background: #e2e8f0; }
        .btn-send { background: var(--theme-color); color: white; }
        .btn-send:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }

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

        .send-options { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .btn-whatsapp { background: #25D366; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; font-size: 13px;}
        .btn-whatsapp:hover { background: #1ebc59; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.3); }
        .btn-email { background: #3b82f6; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; font-size: 13px;}
        .btn-email:hover { background: #2563eb; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }

        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; color: var(--theme-color); }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>Invoice Dispatch Hub</h2>
            <p>Send generated invoices securely to your clients.</p>
        </div>
    </div>

    <div class="card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-pending')">
                <i class="ph-bold ph-paper-plane-tilt"></i> Pending Dispatch
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-sent')">
                <i class="ph-bold ph-check-circle"></i> Sent to Clients
            </button>
        </div>

        <div id="tab-pending" class="tab-pane active">
            <table class="dispatch-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Client Details</th>
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
                    ?>
                    <tr id="row-<?= $row['invoice_no'] ?>">
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($row['client_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                                <i class="ph-fill ph-phone"></i> <?= htmlspecialchars($row['mobile_number'] ?: 'No Mobile') ?>
                            </div>
                        </td>
                        <td><?= date('d M Y', strtotime($row['invoice_date'])) ?></td>
                        <td style="font-weight: 700; color: #1b5a5a;">₹<?= number_format($row['grand_total'], 2) ?></td>
                        <td><span class="badge badge-ready">Ready to Send</span></td>
                        <td style="text-align: right;">
                            <div class="action-btns" style="justify-content: flex-end;">
                                <button class="btn-action btn-view" onclick="viewInvoice('<?= $row['id'] ?>')"><i class="ph-bold ph-eye"></i> View</button>
                                <button class="btn-action btn-send" onclick="openDispatchModal('<?= $row['invoice_no'] ?>', '<?= addslashes($row['client_name']) ?>', '<?= $row['mobile_number'] ?>', '<?= $row['client_email'] ?>', '<?= $row['grand_total'] ?>')">
                                    <i class="ph-bold ph-paper-plane-right"></i> Send
                                </button>
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
                                <p>No approved invoices are waiting to be dispatched.</p>
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
                        <th>Client Details</th>
                        <th>Sent Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="sentTableBody">
                    <tr>
                        <td><strong>INV-2026-881</strong></td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-main);">Reliance Industries</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;"><i class="ph-fill ph-envelope"></i> reliance@example.com</div>
                        </td>
                        <td>23 Feb 2026</td>
                        <td style="font-weight: 700; color: #1b5a5a;">₹1,50,000.00</td>
                        <td><span class="badge badge-sent">Sent via Mail</span></td>
                        <td style="text-align: right;">
                            <button class="btn-action btn-view"><i class="ph-bold ph-arrow-counter-clockwise"></i> Resend</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="dispatchModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="ph-bold ph-paper-plane-tilt" style="color: var(--theme-color);"></i> Dispatch Invoice</h3>
            <i class="ph-bold ph-x close-modal" onclick="closeModal()"></i>
        </div>
        <div class="modal-body">
            
            <div class="client-info-box">
                <p>Invoice No: <strong id="m_inv_no"></strong></p>
                <p>Client Name: <strong id="m_client"></strong></p>
                <p>Amount Due: <strong id="m_amount"></strong></p>
            </div>

            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 5px;">Choose how you want to send this invoice to the client:</p>

            <div class="send-options">
                <button class="btn-whatsapp" onclick="sendViaWhatsApp()">
                    <i class="ph-bold ph-whatsapp-logo" style="font-size: 18px;"></i> WhatsApp
                </button>
                <button class="btn-email" onclick="sendViaEmail()">
                    <i class="ph-bold ph-envelope-simple" style="font-size: 18px;"></i> Send Email
                </button>
            </div>
            
            <input type="hidden" id="h_mobile">
            <input type="hidden" id="h_email">
        </div>
    </div>
</div>

<script>
    // Tab Switching Logic
    function switchTab(evt, id) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    // Modal Logic
    function openDispatchModal(invNo, client, mobile, email, amount) {
        document.getElementById('m_inv_no').innerText = invNo;
        document.getElementById('m_client').innerText = client;
        document.getElementById('m_amount').innerText = '₹' + parseFloat(amount).toFixed(2);
        
        document.getElementById('h_mobile').value = mobile || '';
        document.getElementById('h_email').value = email || '';

        document.getElementById('dispatchModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('dispatchModal').classList.remove('active');
    }

    // Close modal when clicking outside
    document.getElementById('dispatchModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // --- SEND ACTIONS ---

    function sendViaWhatsApp() {
        const mobile = document.getElementById('h_mobile').value;
        const invNo = document.getElementById('m_inv_no').innerText;
        const client = document.getElementById('m_client').innerText;
        const amount = document.getElementById('m_amount').innerText;

        if (!mobile || mobile.trim() === '') {
            Swal.fire('No Mobile Number', 'Please update the client profile with a valid mobile number first.', 'warning');
            return;
        }

        // Create a professional WhatsApp message
        const text = encodeURIComponent(`Hello ${client},\n\nPlease find your Invoice *${invNo}* attached for the amount of *${amount}*.\n\nThank you for your business!\n- Regards, Sales Team`);
        
        // Open WhatsApp Web/App
        window.open(`https://wa.me/91${mobile}?text=${text}`, '_blank');
        
        markAsSent(invNo, 'WhatsApp');
    }

    function sendViaEmail() {
        const email = document.getElementById('h_email').value;
        const invNo = document.getElementById('m_inv_no').innerText;

        // For UI Demo: Simulate Email Sending
        const btn = document.querySelector('.btn-email');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Sending...';
        btn.disabled = true;

        setTimeout(() => {
            btn.innerHTML = origText;
            btn.disabled = false;
            
            Swal.fire({
                title: 'Email Sent!',
                text: `Invoice ${invNo} has been emailed securely.`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });

            markAsSent(invNo, 'Email');
        }, 1500);
    }

    // Move from Pending to Sent Tab visually
    function markAsSent(invNo, method) {
        closeModal();
        
        // Find row and remove from pending
        const row = document.getElementById('row-' + invNo);
        if(row) {
            // Get data to move it
            const clientHtml = row.cells[1].innerHTML;
            const amountHtml = row.cells[3].innerHTML;
            const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            // Create new row for Sent Tab
            const newRow = `
                <tr>
                    <td><strong>${invNo}</strong></td>
                    <td>${clientHtml}</td>
                    <td>${today}</td>
                    <td>${amountHtml}</td>
                    <td><span class="badge badge-sent">Sent via ${method}</span></td>
                    <td style="text-align: right;">
                        <button class="btn-action btn-view"><i class="ph-bold ph-arrow-counter-clockwise"></i> Resend</button>
                    </td>
                </tr>
            `;
            
            // Add to sent tab and remove from pending
            document.getElementById('sentTableBody').insertAdjacentHTML('afterbegin', newRow);
            row.remove();

            // Check if pending table is empty
            if (document.querySelectorAll('#tab-pending tbody tr').length === 0) {
                document.querySelector('#tab-pending tbody').innerHTML = `
                    <tr><td colspan="6"><div class="empty-state"><i class="ph-fill ph-check-circle"></i><h3>All Caught Up!</h3><p>No approved invoices are waiting to be dispatched.</p></div></td></tr>
                `;
            }

            // Tell the backend to update status (Ajax simulation)
            const fd = new FormData();
            fd.append('action', 'mark_sent');
            fd.append('invoice_id', invNo);
            fetch('', { method: 'POST', body: fd });
        }
    }

    // Simple view redirect (Assumes you have an invoice print page)
    function viewInvoice(id) {
        // You can link this back to your standard print page, e.g. poprint.php or new_invoice logic
        alert("This will open the invoice PDF view for ID: " + id);
    }
</script>

</body>
</html>