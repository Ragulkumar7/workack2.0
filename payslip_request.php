<?php
// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$dbPath = './include/db_connect.php';
$sidebarPath = './sidebars.php';
$headerPath = './header.php';

if (file_exists($dbPath)) {
    require_once $dbPath;
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: db_connect.php not found.");
}

// Check Login session
if (!isset($_SESSION['user_id'])) { 
    header("Location: ./index.php"); 
    exit(); 
}
$current_user_id = $_SESSION['user_id'];

// --- 2. SECURE FILE DOWNLOAD & SMART ROUTING LOGIC ---
if (isset($_GET['download_file'])) {
    $req_file = urldecode($_GET['download_file']);
    
    $filename = basename($req_file);
    $possible_paths = [
        './uploads/payslips/' . $filename,
        '../uploads/payslips/' . $filename,
        '../../uploads/payslips/' . $filename
    ];
    
    $actual_file_path = '';
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $actual_file_path = $path;
            break;
        }
    }

    if ($actual_file_path !== '') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($actual_file_path));
        readfile($actual_file_path);
        exit;
    } else {
        die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'><h2>Error: File not found on the server.</h2><p>The file might have been moved or deleted. Please contact HR.</p></div>");
    }
}

// --- 3. HANDLE FORM SUBMISSION (Using YYYY-MM format) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $from = $_POST['from_month'] . '-01';
    $to = $_POST['to_month'] . '-01';
    $priority = $_POST['priority'];
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $req_id = "REQ-" . strtoupper(substr(md5(time()), 0, 4)); 

    $stmt = $conn->prepare("INSERT INTO payslip_requests (user_id, request_id, from_date, to_date, priority, status, note) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
    $stmt->bind_param("isssss", $current_user_id, $req_id, $from, $to, $priority, $note);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
    }
}

// --- 4. FETCH REQUEST HISTORY ---
$requests = [];
$query = "SELECT * FROM payslip_requests WHERE user_id = ? ORDER BY requested_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reply_text = $row['accounts_reply'];
    $files = [];
    
    if ($row['status'] === 'Sent') {
        if (!empty($row['attached_file'])) {
            $decoded = json_decode($row['attached_file'], true);
            if (is_array($decoded)) {
                $files = $decoded;
            } else {
                $files = [$row['attached_file']];
            }
        } 
        elseif (strpos($reply_text, 'Payslip Attached:') !== false) {
            $parts = explode('Payslip Attached: ', $reply_text);
            if (isset($parts[1])) {
                $files = [trim($parts[1])];
                $reply_text = trim($parts[0]);
            }
        }
    }

    $requests[] = [
        'id' => $row['request_id'],
        'date' => date('d M Y', strtotime($row['requested_date'])),
        'period' => date('M Y', strtotime($row['from_date'])) . ' - ' . date('M Y', strtotime($row['to_date'])),
        'priority' => $row['priority'],
        'status' => $row['status'],
        'reply' => empty($reply_text) ? 'Request processed successfully.' : $reply_text,
        'files' => $files
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payslip Requests | SmartHR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }, colors: { primary: '#1b5a5a', primaryHover: '#144343' } } } }
    </script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; margin: 0; }
        #mainContent { margin-left: 100px; width: calc(100% - 100px); transition: 0.3s ease; padding-top: 80px; }
        @media (max-width: 991px) { #mainContent { margin-left: 0; width: 100%; padding-top: 70px; } }
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid; }
        .badge-High { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }
        .badge-Medium { background: #fff7ed; color: #ea580c; border-color: #fdba74; }
        .badge-Low { background: #f0fdf4; color: #16a34a; border-color: #86efac; }
        .status-Pending { background: #fef9c3; color: #ca8a04; border-color: #fde047; }
        .status-Sent { background: #dcfce7; color: #15803d; border-color: #86efac; }
        .status-Rejected { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        .fade-out { opacity: 0; transition: opacity 0.5s ease-out; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 100; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: white; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 90vh; display: flex; flex-direction: column; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px;}
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>
    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <main id="mainContent" class="p-4 md:p-8 min-h-screen">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 tracking-tight">My Payslip Requests</h1>
                <p class="text-sm text-slate-500 mt-2 font-medium">Track your requested salary slips and securely download generated PDFs.</p>
            </div>
            <button onclick="openModal('requestModal')" class="bg-primary hover:bg-primaryHover text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all flex items-center gap-2 w-full md:w-auto justify-center">
                <i class="ph-bold ph-plus"></i> New Request
            </button>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div id="successAlert" class="mb-8 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold rounded-xl shadow-sm flex items-center gap-3 transition-opacity duration-500">
                <i class="ph-fill ph-check-circle text-xl text-emerald-600"></i>
                <span>Your request has been sent successfully! It is now pending review.</span>
            </div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm uppercase tracking-widest">
                    <i class="ph-bold ph-clock-counter-clockwise text-slate-400 text-lg"></i> Request History
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-100">
                            <th class="px-6 py-5">Request Details</th>
                            <th class="px-6 py-5 text-center">Period</th>
                            <th class="px-6 py-5 text-center">Priority</th>
                            <th class="px-6 py-5 text-center">Status</th>
                            <th class="px-6 py-5">System Note</th>
                            <th class="px-6 py-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if(empty($requests)): ?>
                            <tr><td colspan="6" class="px-6 py-16 text-center text-slate-400 font-semibold italic">No requests made yet.</td></tr>
                        <?php else: foreach($requests as $req): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-5">
                                <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($req['id']); ?></div>
                                <div class="text-[11px] text-slate-400 font-medium uppercase tracking-tight mt-1">Submitted: <?php echo $req['date']; ?></div>
                            </td>
                            <td class="px-6 py-5 text-center font-bold text-slate-600">
                                <?php echo $req['period']; ?>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="badge badge-<?php echo htmlspecialchars($req['priority']); ?>"><?php echo htmlspecialchars($req['priority']); ?></span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php 
                                    $s = $req['status'];
                                    if($s == 'Pending') echo '<span class="badge status-Pending">Processing</span>';
                                    elseif($s == 'Sent') echo '<span class="badge status-Sent gap-1"><i class="ph-bold ph-check"></i> Completed</span>';
                                    else echo '<span class="badge status-Rejected">Declined</span>';
                                ?>
                            </td>
                            <td class="px-6 py-5 text-slate-500 text-xs italic max-w-[200px] truncate" title="<?php echo htmlspecialchars($req['reply']); ?>">
                                <?php echo htmlspecialchars($req['reply'] ?: 'Awaiting review from accounts...'); ?>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <?php if(!empty($req['files'])): ?>
                                    <?php if(count($req['files']) == 1): ?>
                                        <button onclick="triggerDownload('<?php echo htmlspecialchars($req['files'][0], ENT_QUOTES); ?>')" class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 border border-indigo-200 px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm">
                                            <i class="ph-bold ph-download-simple text-lg"></i> Download Payslip
                                        </button>
                                    <?php else: ?>
                                        <?php $filesJson = htmlspecialchars(json_encode($req['files']), ENT_QUOTES, 'UTF-8'); ?>
                                        <button onclick="viewAttachments(<?= $filesJson ?>)" class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 border border-indigo-200 px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm">
                                            <i class="ph-bold ph-download-simple text-lg"></i> Download Slips (<?= count($req['files']) ?>)
                                        </button>
                                    <?php endif; ?>
                                <?php elseif($s == 'Pending'): ?>
                                    <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-100">Reviewing...</span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 font-medium">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="requestModal" class="hidden modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-extrabold text-lg text-slate-800 flex items-center gap-2"><i class="ph-fill ph-file-plus text-primary"></i> Request Payslip</h3>
                <button onclick="closeModal('requestModal')" class="text-slate-400 hover:text-rose-500 bg-slate-200 hover:bg-rose-100 p-1.5 rounded-lg transition-colors"><i class="ph-bold ph-x"></i></button>
            </div>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="p-6 space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Select Salary Month(s)</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block mb-1">From Month</span>
                            <input type="month" name="from_month" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none transition-all">
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block mb-1">To Month</span>
                            <input type="month" name="to_month" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none transition-all">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Urgency Level</label>
                    <div class="relative">
                        <select name="priority" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none appearance-none transition-all font-semibold text-slate-700 cursor-pointer">
                            <option value="Low">Low - Standard Processing</option>
                            <option value="Medium" selected>Medium - Needed Soon</option>
                            <option value="High">High - Urgent Requirement</option>
                        </select>
                        <i class="ph-bold ph-caret-down absolute right-4 top-3.5 text-slate-400 pointer-events-none text-lg"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Additional Notes (Optional)</label>
                    <textarea name="note" rows="3" placeholder="E.g., Need it for a bank loan process..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none resize-none transition-all placeholder:text-slate-400"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal('requestModal')" class="px-6 py-3 rounded-xl text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" name="send_request" class="bg-primary text-white px-8 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/30 hover:bg-primaryHover hover:-translate-y-0.5 transition-all flex items-center gap-2">
                        <i class="ph-bold ph-paper-plane-right"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="viewAttachmentsModal">
        <div class="modal-content">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-100 p-2.5 rounded-xl text-indigo-700"><i class="ph-fill ph-files text-2xl"></i></div>
                    <h3 class="text-lg font-black text-slate-800">Download Requested Payslips</h3>
                </div>
                <button onclick="closeAttachmentsModal()" class="text-slate-400 hover:text-red-500 text-xl transition-colors"><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="p-6 overflow-y-auto custom-scroll flex-grow">
                <div id="attachmentsList" class="flex flex-col gap-3"></div>
            </div>
            <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end rounded-b-2xl flex-shrink-0">
                <button onclick="closeAttachmentsModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-200 hover:bg-slate-300 rounded-xl transition">Close Dialog</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('active'); }

        window.onload = function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.classList.add('fade-out');
                    setTimeout(() => { successAlert.style.display = 'none'; }, 500); 
                }, 4000); 
            }
        };

        // --- THE MAGIC FIX: Auto-Download Parameter ---
        // This instantly downloads the PDF instead of making the user stare at the HTML page
        function triggerDownload(filePath) {
            if (filePath.includes('generate_payslip')) {
                let params = filePath.split('?')[1] || '';
                // Append the &auto_download=1 trigger to the exact Accounts path
                let targetUrl = './Accounts/api/generate_payslip.php?' + params + '&auto_download=1';
                window.open(targetUrl, '_blank');
            } else {
                window.location.href = "?download_file=" + encodeURIComponent(filePath);
            }
        }

        function viewAttachments(filesArray) {
            const list = document.getElementById('attachmentsList');
            list.innerHTML = '';
            
            filesArray.forEach((filePath, index) => {
                let isSystemLink = filePath.includes('generate_payslip');
                let displayFileName = isSystemLink ? 'System Generated Payslip ' + (index + 1) : filePath.split('/').pop();
                
                list.innerHTML += `
                    <div onclick="triggerDownload('${filePath}')" class="cursor-pointer flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-xl hover:border-indigo-400 hover:shadow-md transition-all group">
                        <div class="bg-indigo-50 p-3 rounded-lg text-indigo-500 group-hover:bg-indigo-100 transition-colors">
                            <i class="ph-fill ph-file-pdf text-2xl"></i>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <h4 class="text-sm font-bold text-slate-800 truncate">Payslip Document ${index + 1}</h4>
                            <p class="text-xs font-medium text-slate-500 truncate mt-0.5">${displayFileName}</p>
                        </div>
                        <div class="text-indigo-600 font-bold text-xs bg-indigo-50 px-4 py-2 rounded-lg flex items-center gap-2 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                            <i class="ph-bold ph-download-simple text-sm"></i> Download
                        </div>
                    </div>
                `;
            });
            document.getElementById('viewAttachmentsModal').classList.add('active');
        }
        
        function closeAttachmentsModal() { document.getElementById('viewAttachmentsModal').classList.remove('active'); }
    </script>
</body>
</html>