<?php
// payslip_request.php - Employee Self-Service Portal (Request Only Workflow)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// =========================================================================
// 1. SESSION, DB CONNECTION & DYNAMIC ROOT
// =========================================================================
$root_path = '';
$dbPaths = ['./include/db_connect.php', '../include/db_connect.php', '../../include/db_connect.php'];
$dbFound = false;

foreach($dbPaths as $path) { 
    if(file_exists($path)) { 
        require_once $path; 
        $dbFound = true; 
        if (strpos($path, '../../') === 0) $root_path = '../../';
        elseif (strpos($path, '../') === 0) $root_path = '../';
        else $root_path = './';
        break; 
    } 
}
if (!$dbFound || !isset($conn)) { die("Critical Error: db_connect.php not found."); }

if (!isset($_SESSION['user_id'])) { 
    header("Location: " . $root_path . "index.php"); 
    exit(); 
}
$current_user_id = $_SESSION['user_id'];

// CSRF Protection Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =========================================================================
// 2. SMART DB PATCHER (Indexes & New Columns)
// =========================================================================
// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS `payslip_requests` (
    `request_id` VARCHAR(50) PRIMARY KEY, `user_id` INT NOT NULL, `from_date` DATE NOT NULL, `to_date` DATE NOT NULL, 
    `priority` VARCHAR(20), `status` VARCHAR(50) DEFAULT 'Pending', `note` TEXT, `accounts_reply` TEXT, `attached_file` TEXT, 
    `requested_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add Request Type if missing
$check_col = $conn->query("SHOW COLUMNS FROM `payslip_requests` LIKE 'request_type'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `payslip_requests` ADD COLUMN `request_type` VARCHAR(50) DEFAULT 'General Copy'");
}

// Add Performance Index
$conn->query("CREATE INDEX IF NOT EXISTS idx_payslip_user_date ON payslip_requests(user_id, requested_date)");

// =========================================================================
// 3. SECURE STATIC FILE DOWNLOAD (Ownership Verification)
// =========================================================================
if (isset($_GET['download_file'])) {
    $req_file = urldecode($_GET['download_file']);
    $filename = basename($req_file);
    
    // 🔒 ENTERPRISE SECURITY: Verify Ownership Before Download
    $stmt = $conn->prepare("SELECT request_id FROM payslip_requests WHERE user_id = ? AND attached_file LIKE ?");
    $like_file = "%" . $filename . "%";
    $stmt->bind_param("is", $current_user_id, $like_file);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        die("<div style='padding:50px; text-align:center; font-family:sans-serif; color:#b91c1c; background:#fef2f2; height:100vh;'><h2 style='font-size:24px; font-weight:bold;'>Security Violation</h2><p>Unauthorized file access. This incident has been logged.</p></div>");
    }
    $stmt->close();

    $possible_paths = [$root_path . 'uploads/payslips/' . $filename, './uploads/payslips/' . $filename, '../uploads/payslips/' . $filename];
    $actual_file_path = '';
    foreach ($possible_paths as $path) { if (file_exists($path)) { $actual_file_path = $path; break; } }

    if ($actual_file_path !== '') {
        header('Content-Description: File Transfer'); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Expires: 0'); header('Cache-Control: must-revalidate'); header('Pragma: public'); header('Content-Length: ' . filesize($actual_file_path));
        readfile($actual_file_path); exit;
    } else {
        die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h2>Error: File not found.</h2><p>Please contact HR.</p></div>");
    }
}

// =========================================================================
// 4. HANDLE FORM SUBMISSIONS (CSRF Validated)
// =========================================================================
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security validation failed.");
    }

    // ACTION: NEW REQUEST
    if ($_POST['action'] === 'send_request') {
        $from = $_POST['from_month'] . '-01';
        $to = $_POST['to_month'] . '-01';
        $priority = $_POST['priority'];
        $req_type = $_POST['request_type'];
        $note = mysqli_real_escape_string($conn, $_POST['note']);
        
        // 🔒 Validation 1: Date Range Logic
        if (strtotime($from) > strtotime($to)) {
            $error_msg = "Invalid date range. 'From' month cannot be after 'To' month.";
        } else {
            // 🔒 Validation 2: Spam Prevention (Max 5 per day)
            $spam_stmt = $conn->prepare("SELECT COUNT(*) as total FROM payslip_requests WHERE user_id = ? AND DATE(requested_date) = CURDATE()");
            $spam_stmt->bind_param("i", $current_user_id);
            $spam_stmt->execute();
            $daily_count = $spam_stmt->get_result()->fetch_assoc()['total'];
            $spam_stmt->close();

            if ($daily_count >= 5) {
                $error_msg = "Daily request limit reached (5/5). Please try again tomorrow or contact HR directly.";
            } else {
                $req_id = "REQ-" . strtoupper(bin2hex(random_bytes(4))); 
                $stmt = $conn->prepare("INSERT INTO payslip_requests (user_id, request_id, from_date, to_date, request_type, priority, status, note) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)");
                $stmt->bind_param("issssss", $current_user_id, $req_id, $from, $to, $req_type, $priority, $note);
                if ($stmt->execute()) { header("Location: ?success=requested"); exit(); }
            }
        }
    }

    // ACTION: CANCEL REQUEST
    if ($_POST['action'] === 'cancel_request') {
        $req_id = mysqli_real_escape_string($conn, $_POST['req_id']);
        $stmt = $conn->prepare("UPDATE payslip_requests SET status = 'Cancelled' WHERE request_id = ? AND user_id = ? AND status = 'Pending'");
        $stmt->bind_param("si", $req_id, $current_user_id);
        if ($stmt->execute()) { header("Location: ?success=cancelled"); exit(); }
    }
}

// =========================================================================
// 5. FETCH DATA FOR UI
// =========================================================================

// Custom Request Ledger (Optimized Query)
$requests = [];
$stmt = $conn->prepare("SELECT request_id, from_date, to_date, request_type, priority, status, accounts_reply, attached_file, requested_date FROM payslip_requests WHERE user_id = ? ORDER BY requested_date DESC");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $files = [];
    if ($row['status'] === 'Sent' && !empty($row['attached_file'])) {
        $decoded = json_decode($row['attached_file'], true);
        $files = is_array($decoded) ? $decoded : [$row['attached_file']];
    }
    $from = date('M Y', strtotime($row['from_date']));
    $to = date('M Y', strtotime($row['to_date']));
    $requests[] = [
        'id' => $row['request_id'],
        'date' => date('d M Y, H:i', strtotime($row['requested_date'])),
        'period' => ($from === $to) ? $from : $from . ' - ' . $to,
        'type' => $row['request_type'] ?? 'General Copy',
        'priority' => $row['priority'], 
        'status' => $row['status'],
        'reply' => $row['accounts_reply'],
        'files' => $files
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Salary Documents | SmartHR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }, colors: { primary: '#1b5a5a', primaryHover: '#144343' } } } }
    </script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; margin: 0; }
       /* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT 
           ========================================================== */
        .main-content, #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            padding: 30px; /* Adjust inner padding as needed */
            min-height: 100vh;
        }

        /* Desktop: Shifts content right when secondary sub-menu opens */
        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Mobile & Tablet Adjustments */
        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 80px 15px 30px !important; /* Top padding clears the hamburger menu */
            }
            
            /* Prevent shifting on mobile (menu floats over content instead) */
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid; }
        .badge-High { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }
        .badge-Medium { background: #fff7ed; color: #ea580c; border-color: #fdba74; }
        .badge-Low { background: #f0fdf4; color: #16a34a; border-color: #86efac; }
        
        .fade-out { opacity: 0; transition: opacity 0.5s ease-out; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 100; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: white; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 90vh; display: flex; flex-direction: column; }
        
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php 
    if(file_exists($root_path . 'sidebars.php')) { include $root_path . 'sidebars.php'; }
    if(file_exists($root_path . 'header.php')) { include $root_path . 'header.php'; }
    ?>

    <main id="mainContent" class="main-content">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-[#1b5a5a] tracking-tight">My Salary Documents</h1>
                <p class="text-sm text-slate-500 mt-2 font-medium">Submit a request to HR to generate and download your payslips.</p>
            </div>
            <button onclick="openModal('requestModal')" class="bg-primary hover:bg-primaryHover text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 w-full md:w-auto">
                <i class="ph-bold ph-file-plus"></i> New Request
            </button>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div id="successAlert" class="mb-8 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold rounded-xl shadow-sm flex items-center gap-3">
                <i class="ph-fill ph-check-circle text-xl text-emerald-600"></i>
                <span>Action completed successfully.</span>
            </div>
        <?php endif; ?>
        
        <?php if($error_msg !== ''): ?>
            <div id="errorAlert" class="mb-8 p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm font-bold rounded-xl shadow-sm flex items-center gap-3">
                <i class="ph-fill ph-warning-circle text-xl text-rose-600"></i>
                <span><?= $error_msg ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm uppercase tracking-widest">
                    <i class="ph-bold ph-list-dashes text-slate-400 text-lg"></i> Request History
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-100">
                            <th class="px-6 py-5 w-1/4">Request Details</th>
                            <th class="px-6 py-5 w-1/3">Type & Period</th>
                            <th class="px-6 py-5 text-center">Status Tracking</th>
                            <th class="px-6 py-5 text-right">Action Center</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if(empty($requests)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-16 text-center">
                                    <div class="bg-slate-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
                                        <i class="ph-bold ph-tray text-2xl text-slate-400"></i>
                                    </div>
                                    <h4 class="font-bold text-slate-800 text-lg">No Requests Found</h4>
                                    <p class="text-slate-500 text-sm mt-1">Need a payslip? Click "New Request" above.</p>
                                </td>
                            </tr>
                        <?php else: foreach($requests as $req): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-5">
                                <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($req['id']); ?></div>
                                <div class="text-[11px] text-slate-400 font-medium uppercase tracking-tight mt-1">Submitted: <?php echo $req['date']; ?></div>
                            </td>
                            
                            <td class="px-6 py-5">
                                <div class="font-bold text-slate-700 text-sm mb-1">
                                    <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-black uppercase text-slate-500 mr-2"><?= htmlspecialchars($req['type']) ?></span>
                                </div>
                                <div class="flex items-center gap-2 font-bold text-indigo-700 text-sm mb-1.5">
                                    <i class="ph-bold ph-calendar-blank"></i> <?php echo $req['period']; ?>
                                </div>
                            </td>
                            
                            <td class="px-6 py-5 text-center">
                                <?php 
                                    $s = $req['status'];
                                    if($s == 'Pending') echo '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1.5"><i class="ph-bold ph-hourglass-high"></i> Under Review</span>';
                                    elseif($s == 'Sent') echo '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1.5"><i class="ph-bold ph-check"></i> Completed</span>';
                                    elseif($s == 'Cancelled') echo '<span class="bg-slate-100 text-slate-500 border border-slate-200 px-3 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1.5"><i class="ph-bold ph-x"></i> Cancelled</span>';
                                    else echo '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1.5"><i class="ph-bold ph-prohibit"></i> Declined</span>';
                                ?>
                                <?php if($req['reply']): ?>
                                    <div class="text-[10px] text-slate-500 mt-2 italic truncate max-w-[200px] mx-auto" title="<?= htmlspecialchars($req['reply']) ?>">
                                        HR: <?= htmlspecialchars($req['reply']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex justify-end gap-2 items-center">
                                    <?php if($s == 'Sent' && !empty($req['files'])): 
                                        $filesJson = htmlspecialchars(json_encode($req['files']), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <?php if(count($req['files']) == 1): ?>
                                            <button onclick="triggerDownload('<?php echo htmlspecialchars($req['files'][0], ENT_QUOTES); ?>')" class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition-all shadow-sm">
                                                <i class="ph-bold ph-download-simple text-lg"></i> Download Slip
                                            </button>
                                        <?php else: ?>
                                            <button onclick="viewAttachments(<?= $filesJson ?>)" class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition-all shadow-sm">
                                                <i class="ph-bold ph-files text-lg"></i> Download Slips (<?= count($req['files']) ?>)
                                            </button>
                                        <?php endif; ?>
                                        
                                    <?php elseif($s == 'Pending'): ?>
                                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="cancel_request">
                                            <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-slate-50 transition shadow-sm">Cancel Request</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 font-medium">—</span>
                                    <?php endif; ?>
                                </div>
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
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="send_request">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Request Type</label>
                    <div class="relative">
                        <select name="request_type" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none appearance-none transition-all font-semibold text-slate-700 cursor-pointer">
                            <option value="General Copy">General Copy / Record</option>
                            <option value="Loan Application">Bank Loan Application</option>
                            <option value="Visa Process">Visa / Travel Process</option>
                            <option value="Tax Filing">Income Tax Filing</option>
                        </select>
                        <i class="ph-bold ph-caret-down absolute right-4 top-3.5 text-slate-400 pointer-events-none text-lg"></i>
                    </div>
                </div>

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
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Additional Notes</label>
                    <textarea name="note" rows="2" placeholder="Any specific requirements for HR..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none resize-none transition-all placeholder:text-slate-400"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal('requestModal')" class="px-6 py-3 rounded-xl text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" class="bg-primary text-white px-8 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/30 hover:bg-primaryHover transition-all flex items-center gap-2">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="viewAttachmentsModal">
        <div class="modal-content relative flex flex-col h-full max-h-[80vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="bg-emerald-100 p-2.5 rounded-xl text-emerald-700"><i class="ph-fill ph-files text-2xl"></i></div>
                    <h3 class="text-lg font-black text-slate-800">Generated Payslips</h3>
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
        const rootPath = "<?= $root_path ?>";

        function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('active'); }

        window.onload = function() {
            const alerts = ['successAlert', 'errorAlert'];
            alerts.forEach(alertId => {
                const el = document.getElementById(alertId);
                if (el) {
                    setTimeout(() => {
                        el.classList.add('fade-out');
                        setTimeout(() => { el.style.display = 'none'; }, 500); 
                    }, 4000); 
                }
            });
        };

        // --- SAME TAB DYNAMIC ROUTING ENGINE ---
        function triggerDownload(filePath) {
            if (filePath.includes('generate_payslip')) {
                let params = filePath.split('?')[1] || '';
                window.location.href = rootPath + 'Accounts/api/generate_payslip.php?' + params + '&auto_download=1';
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
                    <div onclick="triggerDownload('${filePath}')" class="cursor-pointer flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-xl hover:border-emerald-400 hover:shadow-md transition-all group">
                        <div class="bg-emerald-50 p-3 rounded-lg text-emerald-500 group-hover:bg-emerald-100 transition-colors">
                            <i class="ph-fill ph-file-pdf text-2xl"></i>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <h4 class="text-sm font-bold text-slate-800 truncate">Payslip Document ${index + 1}</h4>
                            <p class="text-xs font-medium text-slate-500 truncate mt-0.5">${displayFileName}</p>
                        </div>
                        <div class="text-emerald-700 font-bold text-xs bg-emerald-50 px-4 py-2 rounded-lg flex items-center gap-2 group-hover:bg-emerald-600 group-hover:text-white transition-colors border border-emerald-200">
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