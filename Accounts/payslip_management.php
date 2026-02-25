<?php
// payslip_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// --- Determine User Role (Fallback to Accounts if not set) ---
$user_role = $_SESSION['role'] ?? 'Accounts'; // Can be 'CFO' or 'Accounts'

// --- 2. AJAX: FETCH PAYSLIP DETAILS FOR PRINTING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_payslip') {
    header('Content-Type: application/json');
    $req_id = mysqli_real_escape_string($conn, $_POST['req_id']);
    
    // Get request and user details
    $req_res = mysqli_query($conn, "SELECT p.*, u.name, u.employee_id, u.department, u.role as designation FROM payslip_requests p JOIN users u ON p.user_id = u.id WHERE p.request_id = '$req_id'");
    
    if ($req_row = mysqli_fetch_assoc($req_res)) {
        $userId = $req_row['user_id'];
        $month = date('Y-m', strtotime($req_row['from_date']));
        
        // Find the corresponding salary record for that month
        $sal_res = mysqli_query($conn, "SELECT * FROM employee_salary WHERE user_id = $userId AND salary_month = '$month'");
        $sal_row = mysqli_fetch_assoc($sal_res);
        
        if (!$sal_row) {
            // Fallback empty values if payroll wasn't processed yet
            $sal_row = ['basic'=>0, 'hra'=>0, 'da'=>0, 'conveyance'=>0, 'allowance'=>0, 'pf'=>0, 'esi'=>0, 'tds'=>0, 'leave_deduction'=>0, 'gross_salary'=>0, 'total_deductions'=>0, 'net_salary'=>0];
        }
        
        echo json_encode(['status' => 'success', 'employee' => $req_row, 'salary' => $sal_row]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
    }
    exit;
}

// --- 3. HANDLE APPROVAL ACTIONS BASED ON ROLES ---
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = mysqli_real_escape_string($conn, $_GET['req_id']);
    $action = $_GET['action'];
    
    if ($action == 'ask_cfo') {
        $updateSql = "UPDATE payslip_requests SET status = 'Pending CFO Approval', accounts_reply = 'Sent to CFO for verification' WHERE request_id = '$req_id'";
        mysqli_query($conn, $updateSql);
    } elseif ($action == 'approve_cfo') {
        $updateSql = "UPDATE payslip_requests SET status = 'Approved', accounts_reply = 'Payslip Approved by CFO.' WHERE request_id = '$req_id'";
        mysqli_query($conn, $updateSql);
    } elseif ($action == 'reject_cfo') {
        $updateSql = "UPDATE payslip_requests SET status = 'Rejected', accounts_reply = 'Request rejected by CFO.' WHERE request_id = '$req_id'";
        mysqli_query($conn, $updateSql);
    } elseif ($action == 'reject_accounts') {
        $updateSql = "UPDATE payslip_requests SET status = 'Rejected', accounts_reply = 'Request rejected by Accounts.' WHERE request_id = '$req_id'";
        mysqli_query($conn, $updateSql);
    }
    
    header("Location: payslip_management.php?success=1");
    exit();
}

// --- 4. FETCH ALL REQUESTS (Combined Table) ---
$sql_requests = "SELECT p.*, u.name as emp_name, u.employee_id as emp_code FROM payslip_requests p 
                 JOIN users u ON p.user_id = u.id 
                 ORDER BY p.requested_date DESC";
$res_requests = mysqli_query($conn, $sql_requests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Management | Workack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: 0.3s ease; }
        
        .table-row:hover { background-color: #f8fafc; }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 50; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: white; border-radius: 12px; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        
        /* PERFECT PRINT TEMPLATE ALIGNMENT */
        @media print {
            @page { size: A4; margin: 0; }
            body * { visibility: hidden; } 
            .modal-overlay, .modal-content { position: static !important; overflow: visible !important; background: white !important; box-shadow: none !important; max-width: 100% !important; padding: 0 !important; }
            #printablePayslip, #printablePayslip * { visibility: visible; }
            #printablePayslip { position: absolute; left: 0; top: 0; width: 100%; padding: 20mm; }
            .no-print { display: none !important; }
            .print-border { border: 1px solid #000 !important; }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../sidebars.php'; ?>
    <?php include '../header.php'; ?>

    <main id="mainContent" class="p-8 min-h-screen">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-[#1b5a5a]">Payslip Request Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1">Role: <strong class="text-[#1b5a5a]"><?php echo $user_role; ?></strong> - Manage and approve employee payslip requests.</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold border-b border-gray-200">
                    <tr>
                        <th class="p-5">Request Details</th>
                        <th class="p-5">Period</th>
                        <th class="p-5">Priority</th>
                        <th class="p-5">Status</th>
                        <th class="p-5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100">
                    <?php if($res_requests && mysqli_num_rows($res_requests) > 0): while($row = mysqli_fetch_assoc($res_requests)): ?>
                    <tr class="table-row">
                        <td class="p-5">
                            <div class="font-bold text-slate-800"><?php echo $row['emp_name']; ?></div>
                            <div class="text-xs text-slate-500">ID: <?php echo $row['emp_code']; ?> | Req: <?php echo $row['request_id']; ?></div>
                        </td>
                        <td class="p-5 font-medium text-slate-600">
                            <?php echo date('M Y', strtotime($row['from_date'])); ?>
                        </td>
                        <td class="p-5">
                            <span class="px-2 py-1 rounded border <?php echo ($row['priority'] == 'High') ? 'bg-red-50 border-red-200 text-red-600' : 'bg-orange-50 border-orange-200 text-orange-600'; ?> text-[10px] font-bold uppercase tracking-wider">
                                <?php echo $row['priority']; ?>
                            </span>
                        </td>
                        <td class="p-5">
                            <?php 
                                if($row['status'] == 'Pending') echo '<span class="px-3 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-full text-xs font-bold flex items-center gap-1 w-max"><i class="ph-fill ph-clock"></i> Accounts Review</span>';
                                elseif($row['status'] == 'Pending CFO Approval') echo '<span class="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full text-xs font-bold flex items-center gap-1 w-max"><i class="ph-bold ph-spinner-gap ph-spin"></i> Waiting on CFO</span>';
                                elseif($row['status'] == 'Approved') echo '<span class="px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-full text-xs font-bold flex items-center gap-1 w-max"><i class="ph-bold ph-check-circle"></i> Approved</span>';
                                else echo '<span class="px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-full text-xs font-bold flex items-center gap-1 w-max"><i class="ph-bold ph-x-circle"></i> Rejected</span>';
                            ?>
                        </td>
                        <td class="p-5 text-right">
                            
                            <?php if ($user_role === 'CFO'): ?>
                                <?php if($row['status'] == 'Pending CFO Approval'): ?>
                                    <div class="flex justify-end gap-2">
                                        <a href="?action=reject_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-white border border-red-200 text-red-600 px-3 py-2 rounded-lg text-xs font-bold hover:bg-red-50 transition"><i class="ph-bold ph-x"></i> Reject</a>
                                        <a href="?action=approve_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-green-700 transition shadow-sm flex items-center gap-1"><i class="ph-bold ph-check"></i> Approve</a>
                                    </div>
                                <?php elseif($row['status'] == 'Approved'): ?>
                                    <button onclick="viewPayslip('<?php echo $row['request_id']; ?>')" class="bg-[#e0f2f1] text-[#1b5a5a] border border-[#b2dfdb] px-4 py-2 rounded-lg text-xs font-bold hover:bg-[#b2dfdb] transition flex items-center gap-1 ml-auto"><i class="ph-bold ph-file-pdf"></i> View & Download</button>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">No action needed</span>
                                <?php endif; ?>

                            <?php else: ?>
                                <?php if($row['status'] == 'Pending'): ?>
                                    <div class="flex justify-end gap-2">
                                        <a href="?action=reject_accounts&req_id=<?php echo $row['request_id']; ?>" class="bg-white border border-red-200 text-red-600 px-3 py-2 rounded-lg text-xs font-bold hover:bg-red-50 transition"><i class="ph-bold ph-x"></i> Reject</a>
                                        <a href="?action=ask_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-[#1b5a5a] text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-800 transition shadow-sm flex items-center gap-1"><i class="ph-bold ph-paper-plane-right"></i> Ask CFO Approval</a>
                                    </div>
                                <?php elseif($row['status'] == 'Pending CFO Approval'): ?>
                                    <button disabled class="bg-slate-100 text-slate-400 px-4 py-2 rounded-lg text-xs font-bold cursor-not-allowed border border-slate-200"><i class="ph-bold ph-lock"></i> Locked (At CFO)</button>
                                <?php elseif($row['status'] == 'Approved'): ?>
                                    <button onclick="viewPayslip('<?php echo $row['request_id']; ?>')" class="bg-[#e0f2f1] text-[#1b5a5a] border border-[#b2dfdb] px-4 py-2 rounded-lg text-xs font-bold hover:bg-[#b2dfdb] transition flex items-center gap-1 ml-auto"><i class="ph-bold ph-file-pdf"></i> View & Download</button>
                                <?php endif; ?>
                            <?php endif; ?>

                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan='5' class='p-10 text-center text-slate-400 font-medium'>No payslip requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal-overlay" id="payslipModal">
        <div class="modal-content relative">
            <div class="p-5 bg-slate-50 border-b border-gray-200 flex justify-between items-center no-print sticky top-0 z-10">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2"><i class="ph-fill ph-file-text text-[#1b5a5a]"></i> Payslip Preview</h3>
                <div class="flex gap-3">
                    <button onclick="window.print()" class="bg-[#1b5a5a] text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-teal-800 transition flex items-center gap-2 shadow-sm"><i class="ph-bold ph-printer"></i> Print / Save PDF</button>
                    <button onclick="closeModal()" class="text-slate-400 hover:text-red-500 transition text-xl"><i class="ph-bold ph-x"></i></button>
                </div>
            </div>
            
            <div id="printablePayslip" class="p-10 bg-white text-black mx-auto" style="font-family: 'Arial', sans-serif;">
                
                <div class="flex justify-between items-center border-b-2 border-black pb-4 mb-6">
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">NEOERA INFOTECH</h1>
                        <p class="text-xs text-slate-600 mt-1">9/96 h, Post, Village Nagar, Coimbatore 641107</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-2xl font-bold uppercase tracking-widest text-slate-800">Payslip</h2>
                        <p class="text-sm font-semibold text-slate-600 mt-1">For the month of <span id="ps_period" class="text-black"></span></p>
                    </div>
                </div>

                <table class="w-full text-sm mb-8 print-border">
                    <tr>
                        <td class="p-3 border border-gray-300 font-bold bg-slate-50 w-1/4">Employee Name</td>
                        <td class="p-3 border border-gray-300 w-1/4 font-semibold" id="ps_name"></td>
                        <td class="p-3 border border-gray-300 font-bold bg-slate-50 w-1/4">Employee ID</td>
                        <td class="p-3 border border-gray-300 w-1/4 font-semibold" id="ps_id"></td>
                    </tr>
                    <tr>
                        <td class="p-3 border border-gray-300 font-bold bg-slate-50">Department</td>
                        <td class="p-3 border border-gray-300" id="ps_dept"></td>
                        <td class="p-3 border border-gray-300 font-bold bg-slate-50">Designation</td>
                        <td class="p-3 border border-gray-300" id="ps_desig"></td>
                    </tr>
                </table>

                <div class="flex print-border border border-gray-300">
                    
                    <div class="w-1/2 border-r border-gray-300">
                        <div class="bg-slate-100 font-bold p-3 text-center border-b border-gray-300 uppercase tracking-wide text-xs">Earnings</div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>Basic Pay</span><span class="font-semibold" id="ps_basic"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>Dearness Allowance (DA)</span><span class="font-semibold" id="ps_da"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>House Rent Allowance (HRA)</span><span class="font-semibold" id="ps_hra"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>Conveyance</span><span class="font-semibold" id="ps_conv"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>Other Allowance</span><span class="font-semibold" id="ps_allow"></span></div>
                    </div>
                    
                    <div class="w-1/2">
                        <div class="bg-slate-100 font-bold p-3 text-center border-b border-gray-300 uppercase tracking-wide text-xs">Deductions</div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>Provident Fund (PF)</span><span class="font-semibold" id="ps_pf"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>ESI</span><span class="font-semibold" id="ps_esi"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100"><span>TDS (Tax)</span><span class="font-semibold" id="ps_tds"></span></div>
                        <div class="p-3 flex justify-between text-sm border-b border-gray-100 text-red-600"><span>Leave Deductions (LOP)</span><span class="font-semibold" id="ps_lop"></span></div>
                    </div>
                </div>

                <div class="flex border border-t-0 border-gray-300 bg-slate-50 font-bold text-sm">
                    <div class="w-1/2 p-3 flex justify-between border-r border-gray-300"><span>Gross Earnings</span><span id="ps_gross"></span></div>
                    <div class="w-1/2 p-3 flex justify-between"><span>Total Deductions</span><span id="ps_deduct"></span></div>
                </div>

                <div class="mt-6 border-2 border-black p-5 flex justify-between items-center bg-slate-50 rounded-lg">
                    <span class="font-extrabold text-lg tracking-wider">NET PAYABLE AMOUNT</span>
                    <span class="font-extrabold text-2xl" id="ps_net"></span>
                </div>
                
                <div class="mt-12 text-center text-xs text-gray-400 border-t border-gray-200 pt-4">
                    This is a computer-generated document. No signature is required.
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewPayslip(reqId) {
            const fd = new FormData();
            fd.append('action', 'fetch_payslip');
            fd.append('req_id', reqId);

            fetch('', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const emp = data.employee;
                    const sal = data.salary;

                    // Format Date
                    const dateObj = new Date(emp.from_date);
                    document.getElementById('ps_period').innerText = dateObj.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

                    // Employee Details
                    document.getElementById('ps_name').innerText = emp.name;
                    document.getElementById('ps_id').innerText = emp.employee_id;
                    document.getElementById('ps_dept').innerText = emp.department || 'N/A';
                    document.getElementById('ps_desig').innerText = emp.designation || 'Employee';

                    // Money formatting function
                    const fmt = (val) => '₹ ' + parseFloat(val || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});

                    // Earnings
                    document.getElementById('ps_basic').innerText = fmt(sal.basic);
                    document.getElementById('ps_da').innerText = fmt(sal.da);
                    document.getElementById('ps_hra').innerText = fmt(sal.hra);
                    document.getElementById('ps_conv').innerText = fmt(sal.conveyance);
                    document.getElementById('ps_allow').innerText = fmt(sal.allowance);
                    
                    // Deductions
                    document.getElementById('ps_pf').innerText = fmt(sal.pf);
                    document.getElementById('ps_esi').innerText = fmt(sal.esi);
                    document.getElementById('ps_tds').innerText = fmt(sal.tds);
                    document.getElementById('ps_lop').innerText = fmt(sal.leave_deduction);

                    // Totals
                    document.getElementById('ps_gross').innerText = fmt(sal.gross_salary);
                    document.getElementById('ps_deduct').innerText = fmt(sal.total_deductions);
                    document.getElementById('ps_net').innerText = fmt(sal.net_salary);

                    // Show Modal
                    document.getElementById('payslipModal').classList.add('active');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function closeModal() {
            document.getElementById('payslipModal').classList.remove('active');
        }

        // Close when clicking outside
        document.getElementById('payslipModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>