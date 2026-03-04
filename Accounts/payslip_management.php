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

// --- SMART DATABASE PATCHER (Auto-Upgrades to support JSON Multiple Files) ---
$check_col = $conn->query("SHOW COLUMNS FROM `payslip_requests` LIKE 'attached_file'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `payslip_requests` ADD COLUMN `attached_file` TEXT DEFAULT NULL");
} else {
    // Ensures column is TEXT (not VARCHAR) so it can safely hold large JSON arrays of multiple files
    $conn->query("ALTER TABLE `payslip_requests` MODIFY COLUMN `attached_file` TEXT DEFAULT NULL");
}

// --- Determine User Role ---
$user_role = $_SESSION['role'] ?? 'Accounts'; // Can be 'CFO' or 'Accounts'

// --- 2. HANDLE POST ACTIONS (Multiple File Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // UPLOAD AND SEND MULTIPLE PAYSLIPS
    if ($_POST['action'] === 'send_payslip_file') {
        $req_id = mysqli_real_escape_string($conn, $_POST['req_id']);
        $reply = "Payslip(s) sent to employee successfully.";
        $uploadedFiles = [];
        
        // Handle Multiple File Upload securely
        if (isset($_FILES['payslip_file'])) {
            $uploadDir = '../uploads/payslips/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            
            $fileCount = count($_FILES['payslip_file']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['payslip_file']['error'][$i] == 0) {
                    // Clean filename, append timestamp and index to avoid overwriting
                    $cleanName = preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['payslip_file']['name'][$i]));
                    $fileName = time() . '_' . $i . '_' . $cleanName;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['payslip_file']['tmp_name'][$i], $targetPath)) {
                        $uploadedFiles[] = $targetPath; 
                    }
                }
            }
        }
        
        if (!empty($uploadedFiles)) {
            // Save array of file paths as JSON in the database
            $filesJson = json_encode($uploadedFiles);
            $stmt = $conn->prepare("UPDATE payslip_requests SET status = 'Sent', accounts_reply = ?, attached_file = ? WHERE request_id = ?");
            $stmt->bind_param("sss", $reply, $filesJson, $req_id);
            $stmt->execute();
            $stmt->close();
            header("Location: payslip_management.php?success=sent");
        } else {
            header("Location: payslip_management.php?error=upload_failed");
        }
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
    header("Location: payslip_management.php?success=status_updated");
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
        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: 0.3s ease; padding-top: 10px; }
        @media (max-width: 991px) { #mainContent { margin-left: 0; width: 100%; padding: 20px; padding-top: 80px; } }
        
        .table-row:hover { background-color: #f8fafc; }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 50; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: white; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 90vh; display: flex; flex-direction: column;}
        
        /* Custom Scrollbar for attachments list */
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
            <p class="text-sm text-slate-500 mt-1">Role: <strong class="text-teal-700"><?php echo htmlspecialchars($user_role); ?></strong> - Manage, approve, and send official employee payslips.</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="mb-6 bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-200 font-bold flex items-center gap-2">
                <i class="ph-fill ph-check-circle text-xl"></i> Action completed successfully.
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-x-auto">
            <table class="w-full text-left min-w-[800px]">
                <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-200">
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
                            <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($row['emp_name']); ?></div>
                            <div class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mt-1">ID: <?php echo htmlspecialchars($row['emp_code']); ?> • Req: <?php echo htmlspecialchars($row['request_id']); ?></div>
                        </td>
                        <td class="p-5 text-center font-bold text-slate-700 bg-slate-50/50">
                            <?php echo date('M Y', strtotime($row['from_date'])); ?>
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
                                if($s == 'Pending') echo '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[11px] font-bold">Review Needed</span>';
                                elseif($s == 'Pending CFO Approval') echo '<span class="bg-sky-50 text-sky-700 border border-sky-200 px-3 py-1 rounded-full text-[11px] font-bold">Waiting on CFO</span>';
                                elseif($s == 'Approved') echo '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[11px] font-bold flex items-center gap-1"><i class="ph-bold ph-check"></i> Approved</span>';
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
                                        <a href="?action=ask_cfo&req_id=<?php echo $row['request_id']; ?>" class="bg-[#1b5a5a] text-white px-5 py-2 rounded-lg text-xs font-bold hover:bg-teal-900 shadow-sm transition">Ask CFO</a>
                                    <?php endif; ?>
                                    
                                    <?php if($s == 'Approved'): ?>
                                        <button onclick="openSendModal('<?php echo $row['request_id']; ?>')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition shadow-sm flex items-center gap-1"><i class="ph-bold ph-paperclip"></i> Attach & Send</button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if($s == 'Sent' && !empty($row['attached_file'])): 
                                    // Parse JSON array of files. Fallback to array if it's an old legacy string
                                    $files = json_decode($row['attached_file'], true);
                                    if (!is_array($files)) { $files = [$row['attached_file']]; }
                                    $filesJson = htmlspecialchars(json_encode($files), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <?php if(count($files) == 1): ?>
                                        <a href="<?php echo htmlspecialchars($files[0]); ?>" target="_blank" class="bg-teal-50 text-teal-700 border border-teal-200 px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-100 transition flex items-center gap-1"><i class="ph-bold ph-file-pdf"></i> View Slip</a>
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
            <button onclick="closeSendModal()" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 text-xl"><i class="ph-bold ph-x"></i></button>
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-indigo-100 p-3 rounded-xl text-indigo-600"><i class="ph-fill ph-paper-plane-right text-2xl"></i></div>
                <div>
                    <h3 class="text-xl font-extrabold text-slate-800">Send Official Payslip</h3>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-1">Secure Multi-PDF Upload</p>
                </div>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 text-amber-700 text-xs p-3 rounded-lg font-medium mb-6">
                <i class="ph-fill ph-info"></i> <b>Pro Tip:</b> You can hold <kbd class="bg-amber-200 px-1 rounded">Ctrl</kbd> or <kbd class="bg-amber-200 px-1 rounded">Shift</kbd> while selecting files to upload multiple payslips at once.
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send_payslip_file">
                <input type="hidden" name="req_id" id="send_req_id">
                
                <div class="mb-8">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-widest mb-2">Attach PDF Document(s) <span class="text-rose-500">*</span></label>
                    <input type="file" name="payslip_file[]" accept=".pdf" multiple required class="w-full border border-slate-300 p-2 rounded-xl text-sm file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeSendModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-md flex items-center gap-2"><i class="ph-bold ph-paper-plane-right"></i> Upload & Send</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="viewAttachmentsModal">
        <div class="modal-content relative flex flex-col h-full max-h-[80vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-16 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="bg-teal-100 p-2.5 rounded-xl text-teal-700"><i class="ph-fill ph-files text-2xl"></i></div>
                    <h3 class="text-lg font-black text-slate-800">Multiple Payslips Sent</h3>
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
        // Send Upload Modal
        function openSendModal(reqId) {
            document.getElementById('send_req_id').value = reqId;
            document.getElementById('sendPayslipModal').classList.add('active');
        }
        function closeSendModal() {
            document.getElementById('sendPayslipModal').classList.remove('active');
        }

        // View Multiple Attachments Modal
        function viewAttachments(filesArray) {
            const list = document.getElementById('attachmentsList');
            list.innerHTML = '';
            
            filesArray.forEach((filePath, index) => {
                // Extract clean filename from the path
                let displayFileName = filePath.split('/').pop();
                
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