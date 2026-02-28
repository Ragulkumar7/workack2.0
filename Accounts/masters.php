<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

// --- AJAX HANDLERS FOR MASTERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. CLIENT MASTER LOGIC
    if ($action === 'add_client') {
        $client_name = mysqli_real_escape_string($conn, $_POST['client_name']);
        $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
        $mobile_number = mysqli_real_escape_string($conn, $_POST['mobile_number']);
        $gst_number = mysqli_real_escape_string($conn, $_POST['gst_number']);
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);

        $sql = "INSERT INTO clients (client_name, company_name, mobile_number, gst_number, bank_name, branch_name, account_number, ifsc_code, status) 
                VALUES ('$client_name', '$company_name', '$mobile_number', '$gst_number', '$bank_name', '$branch_name', '$account_number', '$ifsc_code', 'Active')";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    } 
    elseif ($action === 'update_client') {
        $id = (int)$_POST['id'];
        $client_name = mysqli_real_escape_string($conn, $_POST['client_name']);
        $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
        $mobile_number = mysqli_real_escape_string($conn, $_POST['mobile_number']);
        $gst_number = mysqli_real_escape_string($conn, $_POST['gst_number']);
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);

        $sql = "UPDATE clients SET 
                client_name='$client_name', company_name='$company_name', mobile_number='$mobile_number', 
                gst_number='$gst_number', bank_name='$bank_name', branch_name='$branch_name', 
                account_number='$account_number', ifsc_code='$ifsc_code' 
                WHERE id=$id";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }
    elseif ($action === 'load_clients') {
        $res = mysqli_query($conn, "SELECT * FROM clients ORDER BY id DESC");
        $data = [];
        if($res) { while($row = mysqli_fetch_assoc($res)) { $data[] = $row; } }
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    } 
    elseif ($action === 'delete_client') {
        $id = (int)$_POST['id'];
        if(mysqli_query($conn, "DELETE FROM clients WHERE id=$id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }

    // 2. COMPANY BANK LOGIC
    elseif ($action === 'add_bank') {
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $gst_number = mysqli_real_escape_string($conn, $_POST['gst_number']);

        $sql = "INSERT INTO company_banks (bank_name, account_number, ifsc_code, phone_number, gst_number) 
                VALUES ('$bank_name', '$account_number', '$ifsc_code', '$phone_number', '$gst_number')";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }
    elseif ($action === 'update_bank') {
        $id = (int)$_POST['id'];
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $ifsc_code = mysqli_real_escape_string($conn, $_POST['ifsc_code']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $gst_number = mysqli_real_escape_string($conn, $_POST['gst_number']);

        $sql = "UPDATE company_banks SET 
                bank_name='$bank_name', account_number='$account_number', 
                ifsc_code='$ifsc_code', phone_number='$phone_number', gst_number='$gst_number' 
                WHERE id=$id";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }
    elseif ($action === 'load_banks') {
        $res = mysqli_query($conn, "SELECT * FROM company_banks ORDER BY id DESC");
        $data = [];
        if($res) { while($row = mysqli_fetch_assoc($res)) { $data[] = $row; } }
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    elseif ($action === 'delete_bank') {
        $id = (int)$_POST['id'];
        if(mysqli_query($conn, "DELETE FROM company_banks WHERE id=$id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
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
    <title>Masters - Clients, Bank & Expenses</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1b5a5a;
            --accent-gold: #D4AF37;
            --bg-light: #f8fafc;
            --border: #e4e4e7;
        }

        .main-content {
            margin-left: 95px; 
            padding: 30px;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: var(--bg-light);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }
        .main-shifted { margin-left: 315px; }

        .header-section { margin-bottom: 25px; }
        .header-section h2 { color: var(--primary-color); font-weight: 800; margin: 0; font-size: 24px; }

        .masters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }

        .card h3 {
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
            font-weight: 700;
        }

        label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
            box-sizing: border-box;
            font-family: inherit;
        }
        input:focus, select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(27,90,90,0.1); }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { background: #144444; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(27,90,90,0.2);}

        .table-responsive { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;}
        th { background: #f8fafc; padding: 14px 16px; color: #475569; font-weight: 700; border-bottom: 1px solid var(--border); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;}
        td { padding: 14px 16px; border-bottom: 1px solid var(--border); color: #1e293b; vertical-align: top;}
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fcfcfd; }

        .action-btns { display: flex; gap: 10px; align-items: center; }
        .del-btn { color: #ef4444; cursor: pointer; font-size: 18px; padding: 4px; border-radius: 4px; transition: 0.2s;}
        .del-btn:hover { background: #fee2e2; }
        .edit-btn { color: #0284c7; cursor: pointer; font-size: 18px; padding: 4px; border-radius: 4px; transition: 0.2s;}
        .edit-btn:hover { background: #e0f2fe; }

        .grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 0 15px; }

        @media (max-width: 768px) {
            .main-content, .main-shifted { margin-left: 0; padding: 15px; padding-top: 20px; }
            .masters-grid { grid-template-columns: 1fr; }
            .grid-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content" id="mainContent">
    <div class="header-section">
        <h2>Company Masters</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Setup clients, company bank accounts, and office expenditure categories.</p>
    </div>

    <div class="card" style="margin-bottom: 30px;">
        <h3><i class="ph ph-users-three" style="font-size: 20px;"></i> Client Master (For Invoicing)</h3>
        <div class="masters-grid" style="grid-template-columns: 1.2fr 2fr;">
            
            <form id="clientForm" onsubmit="event.preventDefault(); submitClient();">
                <input type="hidden" id="c_id"> <div class="grid-form">
                    <div>
                        <label>Client Name *</label>
                        <input type="text" id="c_name" placeholder="John Doe" required>
                    </div>
                    <div>
                        <label>Company Name</label>
                        <input type="text" id="c_company" placeholder="Acme Corp">
                    </div>
                    <div>
                        <label>Mobile Number *</label>
                        <input type="text" id="c_mobile" placeholder="+91 9876543210" required>
                    </div>
                    <div>
                        <label>GST Number</label>
                        <input type="text" id="c_gst" placeholder="22AAAAA0000A1Z5">
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px dashed var(--border); margin: 5px 0 15px 0;">
                
                <div class="grid-form">
                    <div>
                        <label>Bank Name</label>
                        <input type="text" id="c_bank" placeholder="e.g., HDFC Bank">
                    </div>
                    <div>
                        <label>Branch Name</label>
                        <input type="text" id="c_branch" placeholder="e.g., T.Nagar">
                    </div>
                    <div>
                        <label>Account Number</label>
                        <input type="text" id="c_acc" placeholder="Account No">
                    </div>
                    <div>
                        <label>IFSC Code</label>
                        <input type="text" id="c_ifsc" placeholder="HDFC0001234">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="clientSubmitBtn"><i class="ph ph-floppy-disk"></i> Save Client Details</button>
            </form>

            <div class="table-responsive" style="max-height: 400px;">
                <table>
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th>Client Info</th>
                            <th>Bank Details</th>
                            <th>Contact & GST</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="clientTableBody">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="masters-grid">
        <div class="card">
            <h3><i class="ph ph-bank" style="font-size: 20px;"></i> Register Company Bank</h3>
            <form id="bankForm" onsubmit="event.preventDefault(); submitBank();">
                <input type="hidden" id="b_id"> <div class="grid-form">
                    <div>
                        <label>Bank Name *</label>
                        <input type="text" id="b_name" placeholder="e.g. HDFC, SBI..." required>
                    </div>
                    <div>
                        <label>Account Number *</label>
                        <input type="text" id="b_acc" placeholder="Enter Account No" required>
                    </div>
                    <div>
                        <label>IFSC Code *</label>
                        <input type="text" id="b_ifsc" placeholder="e.g. HDFC0001234" required>
                    </div>
                    <div>
                        <label>Phone Number</label>
                        <input type="text" id="b_phone" placeholder="e.g. 9876543210">
                    </div>
                    <div style="grid-column: span 2;">
                        <label>GST Number</label>
                        <input type="text" id="b_gst" placeholder="e.g. 22AAAAA0000A1Z5">
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="bankSubmitBtn" style="margin-top: 5px;"><i class="ph ph-plus-circle"></i> Register Bank</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="ph ph-list-numbers" style="font-size: 20px;"></i> Active Accounts</h3>
            <div class="table-responsive" style="max-height: 400px;">
                <table>
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th>Bank Name</th>
                            <th>Account & IFSC</th>
                            <th>Contact / GST</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bankTableBody">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <h3><i class="ph ph-receipt" style="font-size: 20px;"></i> Quick Expense Entry</h3>
        <div class="masters-grid" style="grid-template-columns: 1fr 2fr;">
            <form id="expenseForm" onsubmit="event.preventDefault(); addExpenseUI();">
                <label>Date</label>
                <input type="date" id="expDate" value="<?= date('Y-m-d') ?>" required>
                
                <label>Item Description</label>
                <input type="text" id="expItem" placeholder="Printer Paper..." required>
                
                <label>Category</label>
                <select id="catSelect" onchange="checkOther(this)">
                    <option value="Office Supplies">Office Supplies</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Others">Others</option>
                </select>
                
                <div id="otherInput" style="display:none;">
                    <label>Specify Category</label>
                    <input type="text" id="customCat" placeholder="Type category name...">
                </div>

                <label>Amount (₹)</label>
                <input type="number" id="expAmount" placeholder="0.00" required>
                
                <button type="submit" class="btn-submit"><i class="ph ph-floppy-disk"></i> Save Expense</button>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="expenseTableBody">
                        <tr>
                            <td>30-Jan-2026</td>
                            <td>Ink Cartridge</td>
                            <td><span style="background:#eefcfd; color:#1b5a5a; padding:3px 8px; border-radius:4px; font-weight: 600; font-size: 11px;">Supplies</span></td>
                            <td><strong>₹ 550.00</strong></td>
                            <td><i class="ph ph-trash del-btn" onclick="this.closest('tr').remove()"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    // Global Arrays to hold fetched records
    let globalClients = [];
    let globalBanks = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadClients();
        loadBanks();
    });

    // ==========================================
    // CLIENT MASTER LOGIC
    // ==========================================
    function loadClients() {
        const fd = new FormData();
        fd.append('action', 'load_clients');
        
        fetch('', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('clientTableBody');
                tbody.innerHTML = '';
                
                if(data.success && data.data && data.data.length > 0) {
                    globalClients = data.data; // store globally for editing
                    data.data.forEach(c => {
                        const comp = c.company_name ? c.company_name : 'Individual';
                        const bank = c.bank_name ? c.bank_name : 'N/A';
                        const branch = c.branch_name ? `(${c.branch_name})` : '';
                        const acc = c.account_number ? c.account_number : 'N/A';
                        const ifsc = c.ifsc_code ? c.ifsc_code : 'N/A';
                        const gst = c.gst_number ? c.gst_number : 'N/A';

                        tbody.innerHTML += `
                            <tr>
                                <td>
                                    <strong style="color:var(--primary-color); font-size:14px;">${c.client_name}</strong><br>
                                    <span style="color:#64748b; font-size:11px; font-weight:600;">${comp}</span>
                                </td>
                                <td>
                                    <span style="font-weight:600;">${bank} ${branch}</span><br>
                                    <span style="color:#64748b; font-size:11px; font-weight:600;">A/C: ${acc} | IFSC: ${ifsc}</span>
                                </td>
                                <td>
                                    <span style="font-weight:600;">${c.mobile_number}</span><br>
                                    <span style="color:#64748b; font-size:11px; font-weight:600;">GST: ${gst}</span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <i class="ph ph-pencil-simple edit-btn" onclick="editClient(${c.id})"></i>
                                        <i class="ph ph-trash del-btn" onclick="deleteClient(${c.id})"></i>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    globalClients = [];
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color:#94a3b8;">No clients added yet.</td></tr>`;
                }
            });
    }

    function editClient(id) {
        const c = globalClients.find(client => client.id == id);
        if(!c) return;

        // Populate fields
        document.getElementById('c_id').value = c.id;
        document.getElementById('c_name').value = c.client_name || '';
        document.getElementById('c_company').value = c.company_name || '';
        document.getElementById('c_mobile').value = c.mobile_number || '';
        document.getElementById('c_gst').value = c.gst_number || '';
        document.getElementById('c_bank').value = c.bank_name || '';
        document.getElementById('c_branch').value = c.branch_name || '';
        document.getElementById('c_acc').value = c.account_number || '';
        document.getElementById('c_ifsc').value = c.ifsc_code || '';

        // Change Button Text
        const btn = document.getElementById('clientSubmitBtn');
        btn.innerHTML = '<i class="ph ph-pencil-simple"></i> Update Client Details';
        document.getElementById('clientForm').scrollIntoView({ behavior: 'smooth' });
    }

    function submitClient() {
        const btn = document.getElementById('clientSubmitBtn');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Saving...';
        btn.disabled = true;

        const id = document.getElementById('c_id').value;
        const fd = new FormData();
        
        fd.append('action', id ? 'update_client' : 'add_client');
        if(id) fd.append('id', id);

        fd.append('client_name', document.getElementById('c_name').value);
        fd.append('company_name', document.getElementById('c_company').value);
        fd.append('mobile_number', document.getElementById('c_mobile').value);
        fd.append('gst_number', document.getElementById('c_gst').value);
        fd.append('bank_name', document.getElementById('c_bank').value);
        fd.append('branch_name', document.getElementById('c_branch').value);
        fd.append('account_number', document.getElementById('c_acc').value);
        fd.append('ifsc_code', document.getElementById('c_ifsc').value);

        fetch('', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: id ? 'Client Updated' : 'Client Saved', showConfirmButton: false, timer: 2000 });
                    document.getElementById('clientForm').reset();
                    document.getElementById('c_id').value = ''; // Clear hidden ID
                    btn.innerHTML = '<i class="ph ph-floppy-disk"></i> Save Client Details';
                    loadClients();
                } else { Swal.fire('Error', data.error, 'error'); }
            })
            .finally(() => { 
                if(btn.innerHTML.includes('Saving')) { btn.innerHTML = ogText; }
                btn.disabled = false; 
            });
    }

    function deleteClient(id) {
        Swal.fire({
            title: 'Delete Client?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete_client');
                fd.append('id', id);
                fetch('', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted', showConfirmButton: false, timer: 1500 });
                            loadClients();
                        } else { Swal.fire('Error', 'Could not delete client.', 'error'); }
                    });
            }
        });
    }

    // ==========================================
    // COMPANY BANK AJAX LOGIC
    // ==========================================
    function loadBanks() {
        const fd = new FormData();
        fd.append('action', 'load_banks');
        
        fetch('', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('bankTableBody');
                tbody.innerHTML = '';
                
                if(data.success && data.data && data.data.length > 0) {
                    globalBanks = data.data; // Store globally
                    data.data.forEach(b => {
                        const phone = b.phone_number ? b.phone_number : 'N/A';
                        const gst = b.gst_number ? b.gst_number : 'N/A';
                        
                        tbody.innerHTML += `
                            <tr>
                                <td><strong style="color:var(--primary-color); font-size:14px;">${b.bank_name}</strong></td>
                                <td>
                                    <span style="font-weight:600;">A/C: ${b.account_number}</span><br>
                                    <span style="color:#64748b; font-size:11px; font-weight:600;">IFSC: ${b.ifsc_code}</span>
                                </td>
                                <td>
                                    <span style="font-weight:600;"><i class="ph ph-phone"></i> ${phone}</span><br>
                                    <span style="color:#64748b; font-size:11px; font-weight:600;">GST: ${gst}</span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <i class="ph ph-pencil-simple edit-btn" onclick="editBank(${b.id})"></i>
                                        <i class="ph ph-trash del-btn" onclick="deleteBank(${b.id})"></i>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    globalBanks = [];
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color:#94a3b8;">No banks registered yet.</td></tr>`;
                }
            });
    }

    function editBank(id) {
        const b = globalBanks.find(bank => bank.id == id);
        if(!b) return;

        // Populate fields
        document.getElementById('b_id').value = b.id;
        document.getElementById('b_name').value = b.bank_name || '';
        document.getElementById('b_acc').value = b.account_number || '';
        document.getElementById('b_ifsc').value = b.ifsc_code || '';
        document.getElementById('b_phone').value = b.phone_number || '';
        document.getElementById('b_gst').value = b.gst_number || '';

        // Change Button Text
        const btn = document.getElementById('bankSubmitBtn');
        btn.innerHTML = '<i class="ph ph-pencil-simple"></i> Update Bank Details';
        document.getElementById('bankForm').scrollIntoView({ behavior: 'smooth' });
    }

    function submitBank() {
        const btn = document.getElementById('bankSubmitBtn');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Saving...';
        btn.disabled = true;

        const id = document.getElementById('b_id').value;
        const fd = new FormData();
        
        fd.append('action', id ? 'update_bank' : 'add_bank');
        if(id) fd.append('id', id);

        fd.append('bank_name', document.getElementById('b_name').value);
        fd.append('account_number', document.getElementById('b_acc').value);
        fd.append('ifsc_code', document.getElementById('b_ifsc').value);
        fd.append('phone_number', document.getElementById('b_phone').value);
        fd.append('gst_number', document.getElementById('b_gst').value);

        fetch('', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: id ? 'Bank Updated' : 'Bank Registered', showConfirmButton: false, timer: 2000 });
                    document.getElementById('bankForm').reset();
                    document.getElementById('b_id').value = ''; // clear hidden ID
                    btn.innerHTML = '<i class="ph ph-plus-circle"></i> Register Bank';
                    loadBanks();
                } else { Swal.fire('Error', data.error, 'error'); }
            })
            .finally(() => { 
                if(btn.innerHTML.includes('Saving')) { btn.innerHTML = ogText; }
                btn.disabled = false; 
            });
    }

    function deleteBank(id) {
        Swal.fire({
            title: 'Delete Bank?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete_bank');
                fd.append('id', id);
                fetch('', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted', showConfirmButton: false, timer: 1500 });
                            loadBanks();
                        } else { Swal.fire('Error', 'Could not delete bank.', 'error'); }
                    });
            }
        });
    }

    // --- QUICK EXPENSE ENTRY UI LOGIC ---
    function checkOther(select) {
        const otherDiv = document.getElementById('otherInput');
        otherDiv.style.display = (select.value === 'Others') ? 'block' : 'none';
    }

    function addExpenseUI() {
        const date = document.getElementById('expDate').value;
        const item = document.getElementById('expItem').value;
        const amount = document.getElementById('expAmount').value;
        let cat = document.getElementById('catSelect').value;

        if(cat === 'Others') cat = document.getElementById('customCat').value;

        const table = document.getElementById('expenseTableBody');
        const row = `<tr>
            <td>${date}</td>
            <td>${item}</td>
            <td><span style="background:#eefcfd; color:#1b5a5a; padding:3px 8px; border-radius:4px; font-weight: 600; font-size: 11px;">${cat}</span></td>
            <td><strong>₹ ${parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong></td>
            <td><i class="ph ph-trash del-btn" onclick="this.closest('tr').remove()"></i></td>
        </tr>`;
        table.insertAdjacentHTML('afterbegin', row);
        document.getElementById('expenseForm').reset();
        document.getElementById('otherInput').style.display = 'none';
    }
</script>

</body>
</html>