<?php 
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

// Get logged in executive's name
$my_name = !empty($_SESSION['name']) ? $_SESSION['name'] : 'Prem Karthick'; 

// --- HANDLE AJAX SAVING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_expense') {
    ob_clean();
    header('Content-Type: application/json');
    
    $name = mysqli_real_escape_string($conn, $_POST['expense_name']);
    $date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $amount = floatval($_POST['amount']);

    $sql = "INSERT INTO sales_expenses (executive_name, expense_name, expense_date, payment_method, amount, status) 
            VALUES ('$my_name', '$name', '$date', '$method', $amount, 'Pending')";
    
    if(mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// --- FETCH MY EXPENSES ---
$expenses_query = mysqli_query($conn, "SELECT * FROM sales_expenses WHERE executive_name = '$my_name' ORDER BY created_at DESC");
$my_expenses = [];
$metrics = ['total_amt' => 0, 'pending_amt' => 0, 'approved_amt' => 0, 'rejected_amt' => 0];

if ($expenses_query) {
    while($row = mysqli_fetch_assoc($expenses_query)) {
        $my_expenses[] = $row;
        $amt = (float)$row['amount'];
        $metrics['total_amt'] += $amt;
        
        if($row['status'] == 'Pending') $metrics['pending_amt'] += $amt;
        if($row['status'] == 'Approved' || $row['status'] == 'Forwarded') $metrics['approved_amt'] += $amt;
        if($row['status'] == 'Rejected') $metrics['rejected_amt'] += $amt;
    }
}

if(ob_get_length()) ob_clean();
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Expenses | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --theme-color: #1b5a5a; --bg-body: #f8fafc; --text-main: #0f172a; --text-muted: #64748b; --border-color: #e2e8f0; --primary-sidebar-width: 95px; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 40px; width: calc(100% - var(--primary-sidebar-width)); min-height: 100vh; box-sizing: border-box;}
        
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h2 { color: var(--theme-color); margin: 0 0 5px 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;}
        .page-header p { margin: 0; font-size: 14px; color: var(--text-muted); }

        .btn-primary { background: var(--theme-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(27, 90, 90, 0.2);}
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(27, 90, 90, 0.3); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .stat-card { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: transform 0.2s;}
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-info h4 { margin: 0; font-size: 22px; color: var(--text-main); font-weight: 800;}
        .stat-info p { margin: 0; font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}

        .table-container { background: white; border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 20px; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 20px; font-size: 14px; font-weight: 600; color: var(--text-main); border-bottom: 1px solid var(--border-color); vertical-align: middle;}
        tbody tr:hover { background: #fcfcfd; }
        tbody tr:last-child td { border-bottom: none; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;}
        .stat-Pending { background: #fef9c3; color: #d97706; }
        .stat-Approved { background: #dcfce7; color: #16a34a; }
        .stat-Forwarded { background: #e0f2fe; color: #0284c7; }
        .stat-Rejected { background: #fee2e2; color: #dc2626; }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; backdrop-filter: blur(2px);}
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 450px; position: relative; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.2s;}
        .close-modal:hover { color: var(--theme-color); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .form-group input, .form-group select { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; font-weight: 600; color: var(--text-main); box-sizing: border-box; outline: none; transition: 0.2s;}
        .form-group input:focus, .form-group select:focus { border-color: var(--theme-color); box-shadow: 0 0 0 3px rgba(27,90,90,0.1); }
    </style>
</head>
<body>

<main class="main-content">
    <div class="page-header">
        <div>
            <h2>My Expense Claims</h2>
            <p>Track and submit your work-related expenses.</p>
        </div>
        <button class="btn-primary" onclick="document.getElementById('expenseModal').classList.add('active')">
            <i class="ph-bold ph-plus-circle" style="font-size: 18px;"></i> Add Expense
        </button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #f1f5f9; color: #475569;"><i class="ph-fill ph-wallet"></i></div>
            <div class="stat-info"><h4>₹<?= number_format($metrics['total_amt']) ?></h4><p>Total Claimed</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;"><i class="ph-fill ph-check-circle"></i></div>
            <div class="stat-info"><h4>₹<?= number_format($metrics['approved_amt']) ?></h4><p>Approved Amt</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef9c3; color: #d97706;"><i class="ph-fill ph-clock-countdown"></i></div>
            <div class="stat-info"><h4>₹<?= number_format($metrics['pending_amt']) ?></h4><p>Pending Amt</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;"><i class="ph-fill ph-x-circle"></i></div>
            <div class="stat-info"><h4>₹<?= number_format($metrics['rejected_amt']) ?></h4><p>Rejected Amt</p></div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Expense Name</th>
                    <th>Payment Method</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($my_expenses)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">No expenses submitted yet.</td></tr>
                <?php else: foreach($my_expenses as $exp): ?>
                    <tr>
                        <td style="color: var(--text-muted);"><i class="ph-bold ph-calendar-blank" style="margin-right: 4px;"></i> <?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                        <td>
                            <?= htmlspecialchars($exp['expense_name']) ?>
                            <?php if($exp['status'] == 'Rejected' && !empty($exp['rejection_reason'])): ?>
                                <div style="font-size: 11px; color: #dc2626; margin-top: 4px; font-weight: 500;">
                                    <i class="ph-fill ph-info"></i> Reason: <?= htmlspecialchars($exp['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><span style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 12px;"><?= htmlspecialchars($exp['payment_method']) ?></span></td>
                        <td style="font-size: 16px; font-weight: 800;">₹<?= number_format($exp['amount']) ?></td>
                        <td>
                            <span class="status-badge stat-<?= $exp['status'] ?>">
                                <?php 
                                    if($exp['status'] == 'Pending') echo '<i class="ph-bold ph-clock"></i> Pending';
                                    elseif($exp['status'] == 'Approved') echo '<i class="ph-bold ph-check"></i> Approved';
                                    elseif($exp['status'] == 'Forwarded') echo '<i class="ph-bold ph-paper-plane-tilt"></i> Forwarded';
                                    else echo '<i class="ph-bold ph-x"></i> Rejected';
                                ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="expenseModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="document.getElementById('expenseModal').classList.remove('active')"></i>
        <h3 style="margin-top: 0; color: var(--theme-color); font-size: 20px; font-weight: 800; margin-bottom: 25px;">Add New Expense</h3>
        
        <form id="addExpenseForm" onsubmit="event.preventDefault(); submitExpense();">
            <input type="hidden" name="action" value="save_expense">
            <div class="form-group">
                <label>Expense Name / Purpose *</label>
                <input type="text" name="expense_name" required placeholder="e.g. Travel to Client Meet">
            </div>
            <div class="form-group">
                <label>Date incurred *</label>
                <input type="date" name="expense_date" required max="<?= date('Y-m-d') ?>">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (₹) *</label>
                    <input type="number" name="amount" required min="1" step="0.01" placeholder="1500.00">
                </div>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px; margin-top: 10px;" id="btnSubmitForm">Submit Claim</button>
        </form>
    </div>
</div>

<script>
    function submitExpense() {
        const btn = document.getElementById('btnSubmitForm');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Submitting...'; 
        btn.disabled = true;

        fetch(window.location.href, { method: 'POST', body: new FormData(document.getElementById('addExpenseForm')) })
        .then(r => r.json())
        .then(data => { 
            if(data.status === 'success') {
                Swal.fire({icon: 'success', title: 'Submitted!', text: 'Your expense claim has been sent for approval.', confirmButtonColor: '#1b5a5a'})
                .then(() => location.reload());
            } else { 
                Swal.fire('Error', data.message, 'error'); 
                btn.innerHTML = origText; btn.disabled = false; 
            }
        })
        .catch(err => {
            Swal.fire('System Error', 'Could not connect to server.', 'error');
            btn.innerHTML = origText; btn.disabled = false;
        });
    }
</script>

</body>
</html>