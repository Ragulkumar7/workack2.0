<?php
// payslip_request.php

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'include/db_connect.php'; // Ensure this path matches your file structure

// CHECK LOGIN
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$my_id = $_SESSION['user_id'];
$my_role = $_SESSION['role'];

// --- 2. HANDLE FORM SUBMISSION (Request Payslip) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $from = $_POST['from_date'];
    $to = $_POST['to_date'];
    $priority = $_POST['priority'];
    $note = trim($_POST['note']);
    $req_id = "REQ-" . strtoupper(substr(md5(uniqid()), 0, 6)); // Generate Unique ID

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO payslip_requests (user_id, request_id, from_date, to_date, priority, status, note) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
    $stmt->bind_param("isssss", $my_id, $req_id, $from, $to, $priority, $note);
    
    if ($stmt->execute()) {
        header("Location: payslip_request.php?success=1");
        exit();
    } else {
        $error = "Error submitting request.";
    }
}

// --- 3. FETCH REQUEST HISTORY ---
// Logic: Accounts/CFO/Admin see ALL requests. Others see ONLY THEIR OWN.
if (in_array($my_role, ['Accounts', 'CFO', 'System Admin'])) {
    // Admin View: Show User Name & Role
    $sql = "SELECT r.*, u.username, u.role, u.employee_id 
            FROM payslip_requests r 
            JOIN users u ON r.user_id = u.id 
            ORDER BY r.requested_date DESC";
    $result = $conn->query($sql);
} else {
    // Employee View: Show only my requests
    $sql = "SELECT * FROM payslip_requests WHERE user_id = ? ORDER BY requested_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Payslip | SmartHR</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        #mainContent { 
            margin-left: 95px; padding: 30px; 
            width: calc(100% - 95px); transition: 0.3s; 
            min-height: 100vh;
        }

        /* Badge Styles */
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-High { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .badge-Medium { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
        .badge-Low { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .status-Pending { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .status-Approved { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Modal Animation */
        .modal { transition: opacity 0.25s ease; }
        .modal-active { overflow: hidden; }
    </style>
</head>
<body class="text-slate-600">

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        <?php include 'header.php'; ?>

        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Payslip Requests</h1>
                <p class="text-sm text-slate-500 mt-1">Manage and track your monthly payslip requests.</p>
            </div>
            <button onclick="openModal('requestModal')" class="bg-[#1b5a5a] hover:bg-[#144343] text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> New Request
            </button>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-5 h-5"></i> Request submitted successfully!
        </div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h3 class="font-bold text-slate-700">Request History</h3>
                <?php if(in_array($my_role, ['Accounts', 'CFO', 'System Admin'])): ?>
                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">Admin View (All Requests)</span>
                <?php endif; ?>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase text-slate-500 font-bold border-b border-slate-200">
                            <th class="px-6 py-4">Request ID</th>
                            <?php if(in_array($my_role, ['Accounts', 'CFO', 'System Admin'])): ?>
                                <th class="px-6 py-4">Employee</th>
                            <?php endif; ?>
                            <th class="px-6 py-4">Requested Date</th>
                            <th class="px-6 py-4">Period</th>
                            <th class="px-6 py-4">Priority</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Accounts Reply</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-bold text-[#1b5a5a]"><?php echo $row['request_id']; ?></td>
                                
                                <?php if(in_array($my_role, ['Accounts', 'CFO', 'System Admin'])): ?>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['username']); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo $row['role']; ?></div>
                                    </td>
                                <?php endif; ?>

                                <td class="px-6 py-4 text-slate-600"><?php echo date('d M Y', strtotime($row['requested_date'])); ?></td>
                                <td class="px-6 py-4 text-slate-600 font-medium">
                                    <?php echo date('M d', strtotime($row['from_date'])) . ' - ' . date('M d', strtotime($row['to_date'])); ?>
                                </td>
                                <td class="px-6 py-4"><span class="badge badge-<?php echo $row['priority']; ?>"><?php echo $row['priority']; ?></span></td>
                                <td class="px-6 py-4"><span class="badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                <td class="px-6 py-4 text-slate-500 text-xs italic">
                                    <?php echo ($row['accounts_reply'] && $row['accounts_reply'] != '-') ? $row['accounts_reply'] : '<span class="text-slate-300">No reply yet</span>'; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-8 text-slate-400">No payslip requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="requestModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm modal">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-lg text-slate-800">Request Payslip</h3>
                <button onclick="closeModal('requestModal')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="" method="POST" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">From Date</label>
                        <input type="date" name="from_date" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">To Date</label>
                        <input type="date" name="to_date" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a]">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Note to Accounts</label>
                    <textarea name="note" rows="3" placeholder="Reason for request..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a]"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('requestModal')" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 border border-slate-300 hover:bg-slate-50">Cancel</button>
                    <button type="submit" name="send_request" class="bg-[#1b5a5a] hover:bg-[#144343] text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md">Send Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    </script>
</body>
</html>