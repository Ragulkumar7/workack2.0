<?php
// payslip_management.php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// --- Determine User Role ---
$user_role = $_SESSION['role'] ?? 'Accounts'; // Can be 'CFO' or 'Accounts'

// --- 2. HANDLE POST ACTIONS (AJAX Fetch & File Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // A. FETCH PAYSLIP DETAILS
    if ($_POST['action'] === 'fetch_payslip') {
        header('Content-Type: application/json');
        $req_id = mysqli_real_escape_string($conn, $_POST['req_id']);
        
        $req_res = mysqli_query($conn, "SELECT p.*, u.name, u.employee_id, ep.department, ep.designation FROM payslip_requests p JOIN users u ON p.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE p.request_id = '$req_id'");
        
        if ($req_row = mysqli_fetch_assoc($req_res)) {
            $userId = $req_row['user_id'];
            $month = date('Y-m', strtotime($req_row['from_date']));
            
            $sal_res = mysqli_query($conn, "SELECT * FROM employee_salary WHERE user_id = $userId AND salary_month = '$month'");
            $sal_row = mysqli_fetch_assoc($sal_res) ?: ['basic'=>0, 'hra'=>0, 'da'=>0, 'conveyance'=>0, 'allowance'=>0, 'pf'=>0, 'esi'=>0, 'tds'=>0, 'leave_deduction'=>0, 'gross_salary'=>0, 'total_deductions'=>0, 'net_salary'=>0];
            
            echo json_encode(['status' => 'success', 'employee' => $req_row, 'salary' => $sal_row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
        }
        exit;
    }

    // B. UPLOAD AND SEND PAYSLIP
    if ($_POST['action'] === 'send_payslip_file') {
        $req_id = mysqli_real_escape_string($conn, $_POST['req_id']);
        $reply = "Payslip sent to employee successfully.";
        
        // Handle File Upload securely
        if (isset($_FILES['payslip_file']) && $_FILES['payslip_file']['error'] == 0) {
            $uploadDir = '../uploads/payslips/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            
            // Clean filename and save
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['payslip_file']['name']));
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['payslip_file']['tmp_name'], $targetPath)) {
                $reply = 'Payslip Attached: ' . $targetPath; // Stores path in DB for future reference
            }
        }
        
        mysqli_query($conn, "UPDATE payslip_requests SET status = 'Sent', accounts_reply = '$reply' WHERE request_id = '$req_id'");
        header("Location: payslip_management.php?success=sent");
        exit;
    }
}

// --- 3. HANDLE GET APPROVAL ACTIONS ---
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = mysqli_real_escape_string($conn, $_GET['req_id']);
    $action = $_GET['action'];
    $newStatus = ""; $reply = "";

    if ($action === 'ask_cfo') {
        $newStatus = 'Pending CFO Approval'; $reply = 'Sent to CFO for verification';
    } elseif ($action === 'approve_cfo') {
        $newStatus = 'Approved'; $reply = 'Payslip Approved by CFO.';
    } elseif ($action === 'reject_cfo') {
        $newStatus = 'Rejected'; $reply = 'Request rejected by CFO.';
    } elseif ($action === 'reject_accounts') {
        $newStatus = 'Rejected'; $reply = 'Request rejected by Accounts.';
    }

    if ($newStatus !== "") {
        mysqli_query($conn, "UPDATE payslip_requests SET status = '$newStatus', accounts_reply = '$reply' WHERE request_id = '$req_id'");
    }
    header("Location: payslip_management.php?success=1");
    exit();
}

// --- 4. FETCH ALL REQUESTS ---
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1e293b; }
        
        /* Correct Professional Sidebar Alignment */
        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: 0.3s ease; padding-top: 80px; }
        @media (max-width: 991px) { #mainContent { margin-left: 0; width: 100%; } }
        
        .table-row:hover { background-color: #f1f5f9; }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 50; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: white; border-radius: 16px; width: 100%; max-width: 850px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        
        /* Perfect Print Styling */
        @media print {
            @page { size: A4; margin: 10mm; }
            body * { visibility: hidden; } 
            .modal-overlay, .modal-content { position: static !important; display: block !important; overflow: visible !important; background: white !important; box-shadow: none !important; max-width: 100% !important; padding: 0 !important; }
            #printablePayslip, #printablePayslip * { visibility: visible; }
            #printablePayslip { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../sidebars.php'; ?>
    <?php include '../header.php'; ?>

    <main id="mainContent" class="p-8 min-h-screen">
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-[#1b5a5a] tracking-tight">Payslip Requests</h1>
            <p class="text-sm text-slate-500 mt-1">Role: <strong class="text-teal-700"><?php echo $user_role; ?></strong> - Manage, approve, and send employee payslips.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-[11px] uppercase text-slate-400 font-bold border-b border-slate-200">
                    <tr>
                        <th class="p-5">Request Details</th>
                        <th class="p-5 text-center">Period</th>
                        <th class="p-5 text-center">Priority</th>
                        <th class="p-5 text-center">Status</th>
                        <th class="p-5 text-right">Action Center</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100">
                    <?php if($res_requests && mysqli_num_rows($res_requests) > 0): while($row = mysqli_fetch_assoc($res_requests)): ?>
                    <tr class="table-row transition-colors">
                        <td class="p-5">
                            <div class="font-bold text-slate-800"><?php echo $row['emp_name']; ?></div>
                            <div class="text-[11px] font-medium uppercase tracking-tight text-slate-400">ID: <?php echo $row['emp_code']; ?> • Req: <?php echo $row['request_id']; ?></div>
                        </td>
                        <td class="p-5 text-center font-bold text-slate-600">
                            <?php echo date('M Y', strtotime($row['from_date'])); ?>
                        </td>
                        <td class="p-5 text-center">
                            <span class="px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-wider <?php echo ($row['priority'] == 'High') ? 'bg-red-50 text-red-600' : 'bg-orange-50 text-orange-600'; ?>">
                                <?php echo $row['priority']; ?>
                            </span>
                        </td>
                        <td class="p-5 text-center">
                            <div class="flex justify-center">
                            <?php 
                                $s = $row['status'];
                                if($s == 'Pending') echo '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[11px] font-bold">Review Needed</span>';
                                elseif($s == 'Pending CFO Approval') echo '<span class="bg-sky-50 text-sky-700 border border-sky-200 px-3 py-1 rounded-full text-[11px] font-bold">Waiting on CFO</span>';
                                elseif($s == 'Approved') echo '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[11px] font-bold">Authorized (Ready)</span>';
                                elseif($s == 'Sent') echo '<span class="bg-purple-50 text-purple-700 border border-purple-200 px-3 py-1 rounded-full text-[11px] font-bold flex items-center gap-1"><i class="ph-bold ph-paper-plane-right"></i> Sent to Emp</span>';
                                else echo '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1 rounded-full text-[11px] font-bold">Declined</span>';
                            ?>
                            </div>
                        </td>
                        <td class="p-5 text-right">
                            <div class="flex justify-end gap-2 items-center">
                                <?php if ($user_role === 'CFO'): ?>
                                    <?php if($s == 'Pending CFO Approval'): ?>
                                        <a href="?action=reject_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-white border border-rose-200 text-rose-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-rose-50 transition">Reject</a>
                                        <a href="?action=approve_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-emerald-600 text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-emerald-700 shadow-sm transition">Approve</a>
                                    <?php endif; ?>
                                <?php else: // Accounts Role ?>
                                    <?php if($s == 'Pending'): ?>
                                        <a href="?action=reject_accounts&req_id=<?php echo $row['request_id']; ?>" class="bg-white border border-rose-200 text-rose-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-rose-50 transition">Reject</a>
                                        <a href="?action=ask_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-[#1b5a5a] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-teal-900 shadow-sm transition">Ask CFO Approval</a>
                                    <?php endif; ?>
                                    
                                    <?php if($s == 'Approved'): ?>
                                        <button onclick="openSendModal('<?php echo $row['request_id']; ?>')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition shadow-sm flex items-center gap-1"><i class="ph-bold ph-paperclip"></i> Attach & Send</button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if($s == 'Approved' || $s == 'Sent'): ?>
                                    <button onclick="viewPayslip('<?php echo $row['request_id']; ?>')" class="bg-teal-50 text-teal-700 border border-teal-200 px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-100 transition flex items-center gap-1"><i class="ph-bold ph-eye"></i> View Slip</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan='5' class='p-20 text-center text-slate-400 font-semibold italic'>No payslip requests found in the system.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal-overlay" id="sendPayslipModal">
        <div class="modal-content !max-w-md p-6 relative">
            <button onclick="closeSendModal()" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 text-xl"><i class="ph-bold ph-x"></i></button>
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-indigo-100 p-2.5 rounded-xl text-indigo-600"><i class="ph-fill ph-paper-plane-right text-2xl"></i></div>
                <h3 class="text-xl font-extrabold text-slate-800">Send Payslip</h3>
            </div>
            <p class="text-sm text-slate-500 mb-6">Please download the generated PDF first, then attach it here to send to the employee's registered email.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send_payslip_file">
                <input type="hidden" name="req_id" id="send_req_id">
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">Attach PDF Document</label>
                    <input type="file" name="payslip_file" accept=".pdf" required class="w-full border border-slate-300 p-3 rounded-xl text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeSendModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-md flex items-center gap-2"><i class="ph-bold ph-paper-plane-right"></i> Send Email</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="payslipModal">
        <div class="modal-content relative">
            <div class="p-6 bg-slate-50 border-b border-slate-200 flex justify-between items-center no-print sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <div class="bg-teal-100 p-2.5 rounded-xl text-teal-700"><i class="ph-fill ph-file-text text-2xl"></i></div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight">Payslip Preview</h3>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="bg-[#1b5a5a] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-teal-900 transition flex items-center gap-2 shadow-lg shadow-teal-900/20"><i class="ph-bold ph-printer"></i> Print / Save PDF</button>
                    <button onclick="closePreviewModal()" class="bg-slate-200 text-slate-600 p-2.5 rounded-xl hover:bg-slate-300 transition"><i class="ph-bold ph-x"></i></button>
                </div>
            </div>
            
            <div id="printablePayslip" class="p-16 bg-white text-black">
                <div class="flex justify-between items-start border-b-4 border-slate-900 pb-8 mb-10">
                    <div class="flex items-center gap-4">
                        <div class="bg-teal-600 w-14 h-14 rounded-2xl flex items-center justify-center text-white text-3xl font-black no-print">N</div>
                        <div>
                            <h1 class="text-4xl font-black tracking-tighter text-slate-900 leading-none">NEOERA INFOTECH</h1>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-2">Coimbatore, Tamil Nadu • HR Center</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-teal-600 uppercase tracking-[0.2em] mb-1">Official Document</div>
                        <h2 class="text-3xl font-black uppercase text-slate-900">PAYSLIP</h2>
                        <p class="text-sm font-bold text-slate-700 mt-1" id="ps_period"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8 mb-10">
                    <div class="space-y-4">
                        <div class="flex border-b border-slate-100 pb-2"><span class="w-32 text-[11px] font-black text-slate-400 uppercase">Employee</span><span class="font-black text-slate-900" id="ps_name"></span></div>
                        <div class="flex border-b border-slate-100 pb-2"><span class="w-32 text-[11px] font-black text-slate-400 uppercase">Employee ID</span><span class="font-bold text-slate-700" id="ps_id"></span></div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex border-b border-slate-100 pb-2"><span class="w-32 text-[11px] font-black text-slate-400 uppercase">Department</span><span class="font-bold text-slate-700" id="ps_dept"></span></div>
                        <div class="flex border-b border-slate-100 pb-2"><span class="w-32 text-[11px] font-black text-slate-400 uppercase">Designation</span><span class="font-bold text-slate-700" id="ps_desig"></span></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 border-2 border-slate-900 rounded-2xl overflow-hidden mb-8">
                    <div class="border-r-2 border-slate-900">
                        <div class="bg-slate-900 text-white font-black p-4 text-center text-xs uppercase tracking-widest">Earnings</div>
                        <div class="p-5 space-y-4">
                            <div class="flex justify-between text-sm"><span>Basic Salary</span><span class="font-bold" id="ps_basic"></span></div>
                            <div class="flex justify-between text-sm"><span>Dearness Allowance</span><span class="font-bold" id="ps_da"></span></div>
                            <div class="flex justify-between text-sm"><span>HRA</span><span class="font-bold" id="ps_hra"></span></div>
                            <div class="flex justify-between text-sm"><span>Conveyance</span><span class="font-bold" id="ps_conv"></span></div>
                            <div class="flex justify-between text-sm"><span>Other Allowance</span><span class="font-bold" id="ps_allow"></span></div>
                        </div>
                    </div>
                    <div>
                        <div class="bg-slate-100 text-slate-900 font-black p-4 text-center text-xs uppercase tracking-widest">Deductions</div>
                        <div class="p-5 space-y-4">
                            <div class="flex justify-between text-sm"><span>Provident Fund</span><span class="font-bold" id="ps_pf"></span></div>
                            <div class="flex justify-between text-sm"><span>ESI</span><span class="font-bold" id="ps_esi"></span></div>
                            <div class="flex justify-between text-sm"><span>TDS (Tax)</span><span class="font-bold" id="ps_tds"></span></div>
                            <div class="flex justify-between text-sm text-red-600 font-bold"><span>Leave (LOP)</span><span id="ps_lop"></span></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 mb-10">
                    <div class="p-6 bg-slate-50 rounded-l-2xl border-2 border-slate-900 border-r-0 flex justify-between items-center"><span class="text-xs font-black uppercase">Gross Earnings</span><span class="text-xl font-black" id="ps_gross"></span></div>
                    <div class="p-6 bg-slate-50 rounded-r-2xl border-2 border-slate-900 flex justify-between items-center"><span class="text-xs font-black uppercase">Total Deductions</span><span class="text-xl font-black" id="ps_deduct"></span></div>
                </div>

                <div class="bg-teal-600 text-white p-8 rounded-3xl flex justify-between items-center">
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-[0.3em] opacity-80 mb-1">Net Monthly Payable</div>
                        <div class="text-4xl font-black tracking-tighter" id="ps_net"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal Handlers
        function openSendModal(reqId) {
            document.getElementById('send_req_id').value = reqId;
            document.getElementById('sendPayslipModal').classList.add('active');
        }
        function closeSendModal() {
            document.getElementById('sendPayslipModal').classList.remove('active');
        }

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
                    
                    const dateObj = new Date(emp.from_date);
                    document.getElementById('ps_period').innerText = dateObj.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    document.getElementById('ps_name').innerText = emp.name;
                    document.getElementById('ps_id').innerText = emp.employee_id;
                    document.getElementById('ps_dept').innerText = emp.department || 'Management';
                    document.getElementById('ps_desig').innerText = emp.designation || 'Staff';

                    const fmt = (val) => '₹ ' + parseFloat(val || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
                    
                    document.getElementById('ps_basic').innerText = fmt(sal.basic);
                    document.getElementById('ps_da').innerText = fmt(sal.da);
                    document.getElementById('ps_hra').innerText = fmt(sal.hra);
                    document.getElementById('ps_conv').innerText = fmt(sal.conveyance);
                    document.getElementById('ps_allow').innerText = fmt(parseFloat(sal.allowance) + parseFloat(sal.medical));
                    document.getElementById('ps_pf').innerText = fmt(sal.pf);
                    document.getElementById('ps_esi').innerText = fmt(sal.esi);
                    document.getElementById('ps_tds').innerText = fmt(sal.tds);
                    document.getElementById('ps_lop').innerText = fmt(sal.leave_deduction);
                    document.getElementById('ps_gross').innerText = fmt(sal.gross_salary);
                    document.getElementById('ps_deduct').innerText = fmt(sal.total_deductions);
                    document.getElementById('ps_net').innerText = fmt(sal.net_salary);

                    document.getElementById('payslipModal').classList.add('active');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        function closePreviewModal() {
            document.getElementById('payslipModal').classList.remove('active');
        }
    </script>
</body>
</html>