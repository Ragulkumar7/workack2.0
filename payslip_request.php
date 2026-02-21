<?php
// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * PATH CONFIGURATION
 * This file is in the ROOT folder.
 */
$dbPath = './include/db_connect.php';
$sidebarPath = './sidebars.php';
$headerPath = './header.php';

if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    die("Critical Error: db_connect.php not found.");
}

// Check Login session
if (!isset($_SESSION['user_id'])) { 
    header("Location: ./index.php"); 
    exit(); 
}
$current_user_id = $_SESSION['user_id'];

// --- 2. HANDLE FORM SUBMISSION ---
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

// --- 3. FETCH REQUEST HISTORY ---
$requests = [];
$query = "SELECT * FROM payslip_requests WHERE user_id = ? ORDER BY requested_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = [
        'id' => $row['request_id'],
        'date' => date('d M Y', strtotime($row['requested_date'])),
        'period' => date('M d', strtotime($row['from_date'])) . ' - ' . date('M d, Y', strtotime($row['to_date'])),
        'priority' => $row['priority'],
        'status' => $row['status'],
        'reply' => $row['accounts_reply']
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#1b5a5a', primaryHover: '#144343' }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: 0.3s ease; }
        .badge { @apply px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border tracking-wider; }
        .badge-High { @apply bg-red-50 text-red-600 border-red-100; }
        .badge-Medium { @apply bg-orange-50 text-orange-600 border-orange-100; }
        .badge-Low { @apply bg-green-50 text-green-600 border-green-100; }
        .status-Pending { @apply bg-yellow-50 text-yellow-700 border-yellow-200; }
        .status-Approved { @apply bg-teal-50 text-teal-700 border-teal-200; }
        .status-Rejected { @apply bg-gray-100 text-gray-600 border-gray-200; }
        
        /* Smooth Fade Out Animation */
        .fade-out {
            opacity: 0;
            transition: opacity 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-slate-50">

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>
    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <div id="mainContent" class="p-8 min-h-screen">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">My Payslip Requests</h1>
                <p class="text-sm text-slate-500 mt-1">Track and manage your requested salary slips.</p>
            </div>
            <button onclick="openModal('requestModal')" class="bg-primary hover:bg-primaryHover text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> New Request
            </button>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div id="successAlert" class="mb-6 p-4 bg-teal-50 border-l-4 border-teal-500 text-teal-700 text-sm font-medium rounded-r-lg shadow-sm flex items-center gap-3 transition-opacity duration-500">
                <i class="fas fa-check-circle text-lg"></i>
                <span>Your request has been sent! It is now visible to the Accounts team for processing.</span>
            </div>
        <?php endif; ?>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-slate-50/30">
                <h3 class="font-bold text-slate-700 flex items-center gap-2">
                    <i class="fas fa-history text-slate-400"></i> Request History
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-100">
                            <th class="px-6 py-4">Request ID</th>
                            <th class="px-6 py-4">Submitted Date</th>
                            <th class="px-6 py-4">Period Requested</th>
                            <th class="px-6 py-4">Priority</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Accounts Note</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php foreach($requests as $req): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-primary"><?php echo $req['id']; ?></td>
                            <td class="px-6 py-4 text-slate-500"><?php echo $req['date']; ?></td>
                            <td class="px-6 py-4 text-slate-700 font-semibold"><?php echo $req['period']; ?></td>
                            <td class="px-6 py-4"><span class="badge badge-<?php echo $req['priority']; ?>"><?php echo $req['priority']; ?></span></td>
                            <td class="px-6 py-4"><span class="badge status-<?php echo $req['status']; ?>"><?php echo $req['status']; ?></span></td>
                            <td class="px-6 py-4 text-slate-400 text-xs italic"><?php echo htmlspecialchars($req['reply'] ?: 'Awaiting review...'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="requestModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800">Request New Payslip</h3>
                <button onclick="closeModal('requestModal')" class="text-gray-400 hover:text-red-500 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="p-6 space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-bold text-slate-400 uppercase mb-2">From Date</label><input type="date" name="from_date" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-sm outline-none"></div>
                    <div><label class="block text-xs font-bold text-slate-400 uppercase mb-2">To Date</label><input type="date" name="to_date" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-sm outline-none"></div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Priority Level</label>
                    <select name="priority" class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-sm outline-none">
                        <option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option>
                    </select>
                </div>
                <div><label class="block text-xs font-bold text-slate-400 uppercase mb-2">Note for Accountant</label><textarea name="note" rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-sm outline-none resize-none"></textarea></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('requestModal')" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500">Cancel</button>
                    <button type="submit" name="send_request" class="bg-primary text-white px-8 py-2.5 rounded-xl text-sm font-bold shadow-lg hover:bg-primaryHover transition-all">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        // --- NEW DISAPPEARING MESSAGE LOGIC ---
        window.onload = function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                // Wait 4 seconds, then fade out
                setTimeout(() => {
                    successAlert.classList.add('fade-out');
                    // Fully remove from display after fade animation
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500); 
                }, 4000); 
            }
        };
    </script>
</body>
</html>