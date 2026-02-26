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

// --- 2. SECURE FILE DOWNLOAD LOGIC (FIXED) ---
if (isset($_GET['download_file'])) {
    // Extract just the filename to prevent directory traversal attacks
    $filename = basename(urldecode($_GET['download_file']));
    
    // Smart Path Resolver: Check all possible locations for the uploads folder
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

// --- 3. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $from = $_POST['from_date'];
    $to = $_POST['to_date'];
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
    $file_url = null;
    
    // Check if the reply contains the attached file path
    if ($row['status'] === 'Sent' && strpos($reply_text, 'Payslip Attached:') !== false) {
        $parts = explode('Payslip Attached: ', $reply_text);
        if (isset($parts[1])) {
            $file_url = trim($parts[1]); 
            $reply_text = "Your payslip is ready.";
        }
    }

    $requests[] = [
        'id' => $row['request_id'],
        'date' => date('d M Y', strtotime($row['requested_date'])),
        'period' => date('M Y', strtotime($row['from_date'])),
        'priority' => $row['priority'],
        'status' => $row['status'],
        'reply' => $reply_text,
        'file_url' => $file_url
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
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: { primary: '#1b5a5a', primaryHover: '#144343' }
                }
            }
        }
    </script>

    <style>
        body { background-color: #f8fafc; color: #1e293b; margin: 0; }
        
        /* Dashboard Alignment */
        #mainContent { margin-left: 100px; width: calc(100% - 100px); transition: 0.3s ease; padding-top: 80px; }
        @media (max-width: 991px) { #mainContent { margin-left: 0; width: 100%; padding-top: 70px; } }
        
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid; }
        .badge-High { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }
        .badge-Medium { background: #fff7ed; color: #ea580c; border-color: #fdba74; }
        .badge-Low { background: #f0fdf4; color: #16a34a; border-color: #86efac; }
        
        .status-Pending { background: #fef9c3; color: #ca8a04; border-color: #fde047; }
        .status-Pending\ CFO\ Approval { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
        .status-Approved { background: #dcfce7; color: #15803d; border-color: #86efac; }
        .status-Sent { background: #f3e8ff; color: #6d28d9; border-color: #d8b4fe; }
        .status-Rejected { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        
        .fade-out { opacity: 0; transition: opacity 0.5s ease-out; }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>
    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <main id="mainContent" class="p-4 md:p-8 min-h-screen">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 tracking-tight">My Payslip Requests</h1>
                <p class="text-sm text-slate-500 mt-2 font-medium">Track your requested salary slips and download generated PDFs.</p>
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
                                <div class="font-bold text-slate-800 text-base"><?php echo $req['id']; ?></div>
                                <div class="text-[11px] text-slate-400 font-medium uppercase tracking-tight mt-1">Submitted: <?php echo $req['date']; ?></div>
                            </td>
                            <td class="px-6 py-5 text-center font-bold text-slate-600">
                                <?php echo $req['period']; ?>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="badge badge-<?php echo $req['priority']; ?>"><?php echo $req['priority']; ?></span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php 
                                    $s = $req['status'];
                                    $s_class = str_replace(' ', '\ ', $s); // Handle spaces for CSS class
                                    if($s == 'Pending') echo '<span class="badge status-Pending">Processing</span>';
                                    elseif($s == 'Pending CFO Approval') echo '<span class="badge status-Pending\ CFO\ Approval">Awaiting Approval</span>';
                                    elseif($s == 'Approved') echo '<span class="badge status-Approved">Authorized</span>';
                                    elseif($s == 'Sent') echo '<span class="badge status-Sent gap-1"><i class="ph-bold ph-check"></i> Completed</span>';
                                    else echo '<span class="badge status-Rejected">Declined</span>';
                                ?>
                            </td>
                            <td class="px-6 py-5 text-slate-500 text-xs italic max-w-[200px] truncate" title="<?php echo htmlspecialchars($req['reply']); ?>">
                                <?php echo htmlspecialchars($req['reply'] ?: 'Awaiting review from accounts...'); ?>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <?php if($req['file_url']): ?>
                                    <a href="?download_file=<?php echo urlencode($req['file_url']); ?>" class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 border border-indigo-200 px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm">
                                        <i class="ph-bold ph-download-simple text-lg"></i> Download PDF
                                    </a>
                                <?php elseif($s == 'Approved'): ?>
                                    <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1.5 rounded-lg border border-teal-100">Generating PDF...</span>
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

    <div id="requestModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-extrabold text-lg text-slate-800 flex items-center gap-2"><i class="ph-fill ph-file-plus text-primary"></i> Request Payslip</h3>
                <button onclick="closeModal('requestModal')" class="text-slate-400 hover:text-rose-500 bg-slate-200 hover:bg-rose-100 p-1.5 rounded-lg transition-colors"><i class="ph-bold ph-x"></i></button>
            </div>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="p-6 space-y-6">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Select Salary Month</label>
                    <div class="grid grid-cols-2 gap-4">
                        <input type="date" name="from_date" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none transition-all" title="Start Date">
                        <input type="date" name="to_date" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl text-sm outline-none transition-all" title="End Date">
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

    <script>
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        // Fade Out Alert Logic
        window.onload = function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.classList.add('fade-out');
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500); 
                }, 4000); 
            }
        };
    </script>
</body>
</html>