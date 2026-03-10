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

// --- 2. AJAX INTERCEPTOR FOR FETCHING PAYSLIPS ---
$is_ajax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']));
if ($is_ajax) {
    error_reporting(0); ini_set('display_errors', 0); ob_clean(); header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'get_employee_payslips') {
        $emp_code = $_POST['emp_code'];
        
        $stmt = $conn->prepare("
            SELECT s.id, s.salary_month, s.net_salary 
            FROM employee_salary s
            JOIN employee_onboarding e ON s.user_id = e.id
            WHERE e.emp_id_code = ? AND (s.approval_status = 'Approved' OR s.credit_status = 'Credited') AND s.is_deleted = 0 
            ORDER BY s.salary_month DESC
        ");
        $stmt->bind_param("s", $emp_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payslips = [];
        while ($row = $result->fetch_assoc()) {
            $payslips[] = [
                'id' => $row['id'],
                'month_label' => date('F Y', strtotime($row['salary_month'])),
                'net' => $row['net_salary']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'payslips' => $payslips]);
        exit;
    }
}

// --- SMART DATABASE PATCHER ---
$check_col = $conn->query("SHOW COLUMNS FROM `payslip_requests` LIKE 'attached_file'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `payslip_requests` ADD COLUMN `attached_file` TEXT DEFAULT NULL");
} else {
    $conn->query("ALTER TABLE `payslip_requests` MODIFY COLUMN `attached_file` TEXT DEFAULT NULL");
}

$user_role = $_SESSION['role'] ?? 'Accounts'; 

// --- 3. HANDLE POST ACTIONS (Sending System Payslips) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'send_payslip_file') {
        $req_id = mysqli_real_escape_string($conn, $_POST['req_id']);
        $selected_payslips = $_POST['selected_payslips'] ?? [];
        $reply = "Your requested payslip(s) have been generated and securely attached.";
        
        if (!empty($selected_payslips)) {
            $attachedLinks = [];
            foreach ($selected_payslips as $salary_id) {
                // FIXED: Use the exact absolute path requested
                $attachedLinks[] = "./api/generate_payslip.php?id=" . intval($salary_id);
            }
            
            $filesJson = json_encode($attachedLinks);
            $stmt = $conn->prepare("UPDATE payslip_requests SET status = 'Sent', accounts_reply = ?, attached_file = ? WHERE request_id = ?");
            $stmt->bind_param("sss", $reply, $filesJson, $req_id);
            $stmt->execute();
            $stmt->close();
            header("Location: payslip_management.php?success=sent");
        } else {
            header("Location: payslip_management.php?error=no_selection");
        }
        exit;
    }
}

// --- 4. HANDLE GET REJECTION ACTIONS ---
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = mysqli_real_escape_string($conn, $_GET['req_id']);
    $action = $_GET['action'];
    $newStatus = ""; $reply = "";

    if ($action === 'reject_accounts') {
        $newStatus = 'Rejected'; $reply = 'Payslip request declined by Accounts team. Contact HR for details.';
    }

    if ($newStatus !== "") {
        mysqli_query($conn, "UPDATE payslip_requests SET status = '$newStatus', accounts_reply = '$reply' WHERE request_id = '$req_id'");
        header("Location: payslip_management.php?success=status_updated");
        exit();
    }
}

// --- 5. FETCH ALL REQUESTS ---
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
        
        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: 0.3s ease; padding-top: 10px; }
        @media (max-width: 991px) { #mainContent { margin-left: 0; width: 100%; padding: 20px; padding-top: 80px; } }
        
        .table-row:hover { background-color: #f8fafc; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 50; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: white; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 90vh; display: flex; flex-direction: column;}
        
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px;}
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include '../sidebars.php'; ?>
    <?php include '../header.php'; ?>

    <main id="mainContent" class="p-8 min-h-screen">
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-[#1b5a5a] tracking-tight">Payslip Requests</h1>
            <p class="text-sm text-slate-500 mt-1">Role: <strong class="text-teal-700"><?php echo htmlspecialchars($user_role); ?></strong> - Review employee reasons and dispatch official payslips.</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-200 font-bold flex items-center gap-2">
                <i class="ph-fill ph-check-circle text-xl"></i> Action completed successfully.
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'no_selection'): ?>
            <div class="mb-6 bg-rose-50 text-rose-700 p-4 rounded-xl border border-rose-200 font-bold flex items-center gap-2">
                <i class="ph-fill ph-warning-circle text-xl"></i> You must select at least one month to send!
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-x-auto">
            <table class="w-full text-left min-w-[900px]">
                <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="p-5 w-1/4">Employee Details</th>
                        <th class="p-5 w-1/3">Requested Period & Reason</th>
                        <th class="p-5 text-center">Priority</th>
                        <th class="p-5 text-center">Status</th>
                        <th class="p-5 text-right">Action Center</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100">
                    <?php if($res_requests && mysqli_num_rows($res_requests) > 0): while($row = mysqli_fetch_assoc($res_requests)): 
                        // Format the requested period beautifully
                        $from = date('M Y', strtotime($row['from_date']));
                        $to = date('M Y', strtotime($row['to_date']));
                        $periodStr = ($from === $to) ? $from : $from . '  -  ' . $to;
                        
                        // FIXED: DB column is 'note', fallback to 'reason'
                        $reason = !empty($row['note']) ? $row['note'] : (!empty($row['reason']) ? $row['reason'] : 'No reason provided');
                    ?>
                    <tr class="table-row transition-colors">
                        <td class="p-5">
                            <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($row['emp_name']); ?></div>
                            <div class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mt-1">ID: <?php echo htmlspecialchars($row['emp_code']); ?> • Req: <?php echo htmlspecialchars($row['request_id']); ?></div>
                        </td>
                        
                        <td class="p-5 bg-slate-50/50">
                            <div class="flex items-center gap-2 font-bold text-indigo-700 text-sm">
                                <i class="ph-bold ph-calendar-blank"></i> <?php echo $periodStr; ?>
                            </div>
                            <div class="text-xs text-slate-600 mt-1.5 max-w-sm whitespace-normal leading-relaxed" title="<?php echo htmlspecialchars($reason); ?>">
                                <strong class="text-slate-400 uppercase tracking-wider text-[10px] mr-1">Reason:</strong> 
                                <?php echo htmlspecialchars($reason); ?>
                            </div>
                        </td>
                        
                        <td class="p-5 text-center">
                            <span class="px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?php echo ($row['priority'] == 'High') ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-amber-50 text-amber-600 border border-amber-100'; ?>">
                                <?php echo htmlspecialchars($row['priority']); ?>
                            </span>
                        </td>
                        <td class="p-5 text-center">
                            <div class="flex justify-center">
                            <?php 
                                $s = $row['status'];
                                if($s == 'Pending') {
                                    echo '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[11px] font-bold">Awaiting Action</span>';
                                } elseif($s == 'Sent') {
                                    echo '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[11px] font-bold flex items-center gap-1"><i class="ph-bold ph-check-circle"></i> Sent to Employee</span>';
                                } else {
                                    echo '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1 rounded-full text-[11px] font-bold">Rejected</span>';
                                }
                            ?>
                            </div>
                        </td>
                        <td class="p-5 text-right">
                            <div class="flex justify-end gap-2 items-center">
                                <?php if($s == 'Pending'): ?>
                                    <a href="?action=reject_accounts&req_id=<?php echo $row['request_id']; ?>" onclick="return confirm('Are you sure you want to reject this request?');" class="bg-white border border-rose-200 text-rose-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-rose-50 transition">Reject</a>
                                    
                                    <button onclick="openSendModal('<?php echo $row['request_id']; ?>', '<?php echo htmlspecialchars($row['emp_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['emp_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($periodStr, ENT_QUOTES); ?>')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition shadow-sm flex items-center gap-1">
                                        <i class="ph-bold ph-list-magnifying-glass"></i> Select & Send
                                    </button>
                                <?php endif; ?>

                                <?php if($s == 'Sent' && !empty($row['attached_file'])): 
                                    $files = json_decode($row['attached_file'], true);
                                    if (!is_array($files)) { $files = [$row['attached_file']]; }
                                    $filesJson = htmlspecialchars(json_encode($files), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <?php if(count($files) == 1): ?>
                                        <a href="javascript:void(0)" onclick="window.open('<?php echo htmlspecialchars($files[0]); ?>', '_blank')" class="bg-teal-50 text-teal-700 border border-teal-200 px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-100 transition flex items-center gap-1"><i class="ph-bold ph-file-pdf"></i> View Slip</a>
                                    <?php else: ?>
                                        <button onclick="viewAttachments(<?= $filesJson ?>)" class="bg-teal-50 text-teal-700 border border-teal-200 px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-100 transition flex items-center gap-1"><i class="ph-bold ph-files"></i> View Slips (<?= count($files) ?>)</button>
                                    <?php endif; ?>
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
        <div class="modal-content p-6 relative">
            <button type="button" onclick="closeSendModal()" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 text-xl"><i class="ph-bold ph-x"></i></button>
            
            <div class="flex items-center gap-3 mb-2">
                <div class="bg-indigo-100 p-3 rounded-xl text-indigo-600"><i class="ph-fill ph-paper-plane-right text-2xl"></i></div>
                <div>
                    <h3 class="text-xl font-extrabold text-slate-800">Send System Payslip</h3>
                    <p class="text-xs font-bold text-indigo-600 uppercase tracking-wider mt-1" id="modalEmpName">To: Employee</p>
                </div>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 p-3 rounded-lg mb-4 mt-2">
                <p class="text-xs text-indigo-800 font-medium">
                    <i class="ph-bold ph-info"></i> They requested: <strong id="modalReqPeriod" class="font-black bg-indigo-200 px-2 py-0.5 rounded"></strong>
                </p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="send_payslip_file">
                <input type="hidden" name="req_id" id="send_req_id">
                
                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Select Generated Payslips to Send <span class="text-rose-500">*</span></label>
                    
                    <div id="availablePayslipsContainer" class="space-y-2 max-h-[250px] overflow-y-auto custom-scroll border border-slate-200 rounded-xl p-3 bg-slate-50">
                        <div class="text-center p-4 text-slate-400"><i class="ph-bold ph-spinner animate-spin text-2xl"></i> Loading data...</div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeSendModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                    <button type="submit" id="btnSubmitSend" class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-md flex items-center gap-2" disabled><i class="ph-bold ph-paper-plane-right"></i> Send Selected</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="viewAttachmentsModal">
        <div class="modal-content relative flex flex-col h-full max-h-[80vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-16 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="bg-teal-100 p-2.5 rounded-xl text-teal-700"><i class="ph-fill ph-files text-2xl"></i></div>
                    <h3 class="text-lg font-black text-slate-800">Sent Payslips</h3>
                </div>
                <button onclick="closeAttachmentsModal()" class="text-slate-400 hover:text-red-500 text-xl"><i class="ph-bold ph-x"></i></button>
            </div>
            
            <div class="p-6 overflow-y-auto custom-scroll flex-grow">
                <div id="attachmentsList" class="flex flex-col gap-3">
                    </div>
            </div>
            
            <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end rounded-b-16 flex-shrink-0">
                <button onclick="closeAttachmentsModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-200 hover:bg-slate-300 rounded-xl transition">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Fetch & Show Available System Payslips using the Employee Code
        async function openSendModal(reqId, empCode, empName, reqPeriod) {
            document.getElementById('send_req_id').value = reqId;
            document.getElementById('modalEmpName').innerText = `Sending To: ${empName} (${empCode})`;
            
            // Inject the requested period so the Accounts person knows what to select
            document.getElementById('modalReqPeriod').innerText = reqPeriod;
            
            document.getElementById('sendPayslipModal').classList.add('active');
            
            const container = document.getElementById('availablePayslipsContainer');
            const submitBtn = document.getElementById('btnSubmitSend');
            
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50');
            container.innerHTML = '<div class="text-center p-6 text-slate-400"><i class="ph-bold ph-spinner animate-spin text-3xl mb-2"></i><br>Fetching approved salaries...</div>';
            
            const formData = new FormData();
            formData.append('ajax_action', 'get_employee_payslips');
            formData.append('emp_code', empCode); 
            
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const result = await res.json();
                
                if (result.success) {
                    if (result.payslips.length === 0) {
                        container.innerHTML = '<div class="text-center p-6 text-rose-500 bg-rose-50 rounded-lg font-semibold"><i class="ph-fill ph-warning-circle text-2xl mb-1"></i><br>No approved or credited salaries found in the system for this employee.</div>';
                    } else {
                        container.innerHTML = '';
                        result.payslips.forEach(ps => {
                            container.innerHTML += `
                                <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition shadow-sm">
                                    <input type="checkbox" name="selected_payslips[]" value="${ps.id}" class="w-4 h-4 accent-indigo-600 rounded cursor-pointer" onchange="checkSelection()">
                                    <div class="flex-1">
                                        <p class="text-sm font-extrabold text-slate-800">${ps.month_label}</p>
                                        <p class="text-xs font-bold text-emerald-600">Net Pay: ₹${parseFloat(ps.net).toLocaleString('en-IN', {minimumFractionDigits: 2})}</p>
                                    </div>
                                    <a href="./api/generate_payslip.php?id=${ps.id}" target="_blank" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-md text-xs font-bold border border-indigo-100" onclick="event.stopPropagation()">Preview</a>
                                </label>
                            `;
                        });
                    }
                } else {
                    container.innerHTML = '<div class="text-center p-4 text-rose-500 font-medium">Failed to load data.</div>';
                }
            } catch (err) {
                container.innerHTML = '<div class="text-center p-4 text-rose-500 font-medium">Network Error.</div>';
            }
        }
        
        function checkSelection() {
            const boxes = document.querySelectorAll('input[name="selected_payslips[]"]:checked');
            const submitBtn = document.getElementById('btnSubmitSend');
            if (boxes.length > 0) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50');
            }
        }

        function closeSendModal() {
            document.getElementById('sendPayslipModal').classList.remove('active');
        }

        // View Multiple Attachments Modal (For the Accounts Team)
        function viewAttachments(filesArray) {
            const list = document.getElementById('attachmentsList');
            list.innerHTML = '';
            
            filesArray.forEach((filePath, index) => {
                let isSystemLink = filePath.includes('generate_payslip');
                let displayFileName = isSystemLink ? 'System Generated Payslip ' + (index + 1) : filePath.split('/').pop();
                
                list.innerHTML += `
                    <a href="${filePath}" target="_blank" class="flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-xl hover:border-teal-400 hover:shadow-md transition-all group">
                        <div class="bg-red-50 p-3 rounded-lg text-red-500 group-hover:bg-red-100 transition-colors">
                            <i class="ph-fill ph-file-pdf text-2xl"></i>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <h4 class="text-sm font-bold text-slate-800 truncate">Document ${index + 1}</h4>
                            <p class="text-xs font-medium text-slate-500 truncate mt-0.5">${displayFileName}</p>
                        </div>
                        <div class="text-teal-600 font-bold text-xs bg-teal-50 px-3 py-1.5 rounded-lg flex items-center gap-1 group-hover:bg-teal-600 group-hover:text-white transition-colors">
                            Open <i class="ph-bold ph-arrow-square-out"></i>
                        </div>
                    </a>
                `;
            });
            
            document.getElementById('viewAttachmentsModal').classList.add('active');
        }
        function closeAttachmentsModal() {
            document.getElementById('viewAttachmentsModal').classList.remove('active');
        }
    </script>
</body>
</html>