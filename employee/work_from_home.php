<?php
// employee_wfh_request.php - Enterprise Employee View

// 1. SESSION START & SECURITY
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// SECURITY FIX: CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- FIXED PATHS FOR YOUR ENVIRONMENT ---
$db_connect_path = '../include/db_connect.php';
$sidebar_path    = '../sidebars.php';
$header_path     = '../header.php';

if (file_exists($db_connect_path)) {
    include_once($db_connect_path);
} else {
    $db_connect_path = 'C:/xampp/htdocs/workack2.0/include/db_connect.php';
    if (file_exists($db_connect_path)) { include_once($db_connect_path); } 
    else { die("Error: db_connect.php not found."); }
}

if (!isset($_SESSION['id']) && !isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : $_SESSION['user_id'];

// --- HANDLE FORM SUBMISSION (WITH SMART UPWARD ROUTING) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_wfh'])) {
    
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security validation failed. Please refresh the page.");
    }

    $emp_name_manual = trim($_POST['employee_name']);
    $shift = trim($_POST['shift']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $reason = trim($_POST['reason']);

    // FETCH APPLICANT'S EXACT ROLE FOR ROUTING
    $role_query = $conn->query("SELECT role FROM users WHERE id = $current_user_id");
    $applicant_role = $role_query->fetch_assoc()['role'];

    // DEFAULT ROUTING (For standard employees)
    $init_tl = 'Pending';
    $init_mgr = 'Pending';
    $init_hr = 'Pending';
    $tl_rev = NULL;
    $mgr_rev = NULL;

    // SMART HIERARCHY AUTO-BYPASS
    if (in_array($applicant_role, ['Team Lead', 'TL'])) {
        // Bypass TL approval, go straight to Manager
        $init_tl = 'Approved';
        $tl_rev = 'System (Hierarchy Auto-Bypass)';
    } 
    if (in_array($applicant_role, ['Manager', 'Project Manager', 'General Manager'])) {
        // Bypass TL and Manager, go straight to HR/Admin
        $init_tl = 'Approved';
        $init_mgr = 'Approved';
        $tl_rev = 'System (Hierarchy Auto-Bypass)';
        $mgr_rev = 'System (Hierarchy Auto-Bypass)';
    } 
    if ($applicant_role === 'IT Executive') {
        // Bypass TL, goes to IT Admin (Mapped in Manager Status)
        $init_tl = 'Approved';
        $tl_rev = 'System (Hierarchy Auto-Bypass)';
    }
    if (in_array($applicant_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])) {
        // Bypass TL and Manager entirely, straight to HR
        $init_tl = 'Approved';
        $init_mgr = 'Approved';
        $tl_rev = 'System (Hierarchy Auto-Bypass)';
        $mgr_rev = 'System (Hierarchy Auto-Bypass)';
    }
    if (in_array($applicant_role, ['Admin', 'System Admin', 'CEO'])) {
        // Top level goes straight to final peer/admin review
        $init_tl = 'Approved';
        $init_mgr = 'Approved';
        $init_hr = 'Approved';
        $tl_rev = 'System (Hierarchy Auto-Bypass)';
        $mgr_rev = 'System (Hierarchy Auto-Bypass)';
    }

    // INSERT WITH SMART STATE
    $sql = "INSERT INTO wfh_requests (user_id, employee_name, start_date, end_date, shift, reason, status, tl_status, manager_status, hr_status, tl_reviewer, manager_reviewer, reviewer_name) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, 'Awaiting Review')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssssss", $current_user_id, $emp_name_manual, $start_date, $end_date, $shift, $reason, $init_tl, $init_mgr, $init_hr, $tl_rev, $mgr_rev);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
    }
}

// --- FETCH STATS & HISTORY ---
if ($conn) {
    $res_pending = $conn->query("SELECT COUNT(*) as total FROM wfh_requests WHERE user_id = $current_user_id AND status = 'Pending'");
    $pending_count = ($res_pending) ? $res_pending->fetch_assoc()['total'] : 0;

    $res_used = $conn->query("SELECT SUM(DATEDIFF(end_date, start_date) + 1) as total FROM wfh_requests WHERE user_id = $current_user_id AND status = 'Approved' AND MONTH(start_date) = MONTH(CURRENT_DATE())");
    $days_used = ($res_used) ? ($res_used->fetch_assoc()['total'] ?? 0) : 0;

    // Fetch History with Multi-Level Tracking Columns and Exact Roles
    $history = [];
    $res_history = $conn->query("
        SELECT w.id, w.applied_date, w.start_date, w.end_date, w.shift, w.reason, w.status, w.reviewer_name,
               COALESCE(w.tl_status, 'Pending') as tl_status,
               COALESCE(w.manager_status, 'Pending') as manager_status,
               COALESCE(w.hr_status, 'Pending') as hr_status,
               u.role as actual_role
        FROM wfh_requests w 
        LEFT JOIN users u ON w.user_id = u.id
        WHERE w.user_id = $current_user_id 
        ORDER BY w.applied_date DESC
    ");
    if ($res_history) {
        while($row = $res_history->fetch_assoc()) {
            // Legacy Data Sync for older requests
            if ($row['status'] === 'Approved') {
                if ($row['tl_status'] === 'Pending') $row['tl_status'] = 'Approved';
                if ($row['manager_status'] === 'Pending') $row['manager_status'] = 'Approved';
                if ($row['hr_status'] === 'Pending') $row['hr_status'] = 'Approved';
            } elseif ($row['status'] === 'Rejected' && $row['hr_status'] === 'Pending' && $row['manager_status'] === 'Pending' && $row['tl_status'] === 'Pending') {
                $row['hr_status'] = 'Rejected';
            }
            $history[] = $row;
        }
    }

    // 🚀 MODIFIED: Fetching shift_type and shift_timings alongside full_name and designation
    $stmt_profile = $conn->prepare("SELECT full_name, designation, shift_type, shift_timings FROM employee_profiles WHERE user_id = ?");
    $stmt_profile->bind_param("i", $current_user_id);
    $stmt_profile->execute();
    $res_profile = $stmt_profile->get_result();
    $profile = $res_profile->fetch_assoc();
} else {
    die("Database connection failed.");
}

$user_role = (!empty($profile['designation'])) ? $profile['designation'] : "Staff";
$user_name = (!empty($profile['full_name'])) ? $profile['full_name'] : "";

// 🚀 NEW: Dynamically assign variables for the user's shift
$user_shift_type = (!empty($profile['shift_type'])) ? $profile['shift_type'] : "Regular";
$user_shift_timings = (!empty($profile['shift_timings'])) ? $profile['shift_timings'] : "";
$display_shift = $user_shift_type . ($user_shift_timings ? " (" . $user_shift_timings . ")" : "");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Work From Home - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #0f172a; overflow-x: hidden;}
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

        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Approval Chain UI */
        .approval-chain { display: flex; align-items: center; gap: 4px; }
        .chain-node { width: 22px; height: 22px; border-radius: 50%; color: white; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: help; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .chain-link { width: 12px; height: 2px; background: #e2e8f0; border-radius: 2px; }
        
        .node-approved { background: #10b981; } 
        .node-pending { background: #f59e0b; }  
        .node-rejected { background: #ef4444; } 

        /* Modals */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; opacity: 0; transition: opacity 0.3s ease;}
        .modal-overlay.active { display: flex; opacity: 1;}
        .modal-box { background: white; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; transform: scale(0.95); transition: transform 0.3s ease;}
        .modal-overlay.active .modal-box { transform: scale(1); }
    </style>
</head>
<body>

    <?php 
        if (file_exists($sidebar_path)) { include($sidebar_path); } 
        if (file_exists($header_path)) { include($header_path); } 
    ?>

    <div class="main-content" id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 mt-2">
            <div>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-slate-800 tracking-tight">My WFH Requests</h1>
                <div class="flex items-center gap-2 text-sm text-slate-500 font-medium mt-1">
                    <i data-lucide="home" class="w-4 h-4"></i>
                    <span>/</span> <span>Attendance</span> <span>/</span> <span class="text-slate-800 font-bold">WFH Application</span>
                </div>
            </div>
            <button onclick="openModal()" class="bg-teal-700 hover:bg-teal-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition-colors flex items-center gap-2">
                <i data-lucide="send" class="w-4 h-4"></i> Apply WFH
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                <i data-lucide="calendar-clock" class="absolute right-6 top-6 w-8 h-8 text-blue-500 opacity-80"></i>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1 relative z-10">Monthly Limit</p>
                <h3 class="text-3xl font-black text-slate-800 relative z-10">04 <span class="text-sm font-bold text-slate-400">Days</span></h3>
            </div>
            
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                <i data-lucide="check-circle-2" class="absolute right-6 top-6 w-8 h-8 text-emerald-500 opacity-80"></i>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1 relative z-10">Used This Month</p>
                <h3 class="text-3xl font-black text-slate-800 relative z-10"><?= sprintf("%02d", $days_used) ?> <span class="text-sm font-bold text-slate-400">Days</span></h3>
            </div>
            
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-amber-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                <i data-lucide="clock" class="absolute right-6 top-6 w-8 h-8 text-amber-500 opacity-80"></i>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1 relative z-10">Pending Approval</p>
                <h3 class="text-3xl font-black text-slate-800 relative z-10"><?= sprintf("%02d", $pending_count) ?> <span class="text-sm font-bold text-slate-400">Req</span></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
                <h3 class="text-lg font-extrabold text-slate-800">My Request History</h3>
                
                <div class="flex gap-3 w-full sm:w-auto">
                    <div class="relative w-full sm:w-64">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                        <input type="text" id="personalSearch" placeholder="Search reason..." onkeyup="filterTable()" class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 shadow-sm">
                    </div>
                    <select id="statusFilter" onchange="filterTable()" class="border border-slate-200 rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-500 shadow-sm outline-none bg-white">
                        <option value="">All Status</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto custom-scroll">
                <table class="w-full text-left whitespace-nowrap" id="myWfhTable">
                    <thead class="bg-white border-b border-slate-100 uppercase text-[10px] text-slate-400 font-black tracking-widest">
                        <tr>
                            <th class="px-6 py-4">Applied Date</th>
                            <th class="px-6 py-4">WFH Dates</th>
                            <th class="px-6 py-4">Shift</th>
                            <th class="px-6 py-4">Reason</th>
                            <th class="px-6 py-4">Live Pipeline</th>
                            <th class="px-6 py-4 text-right">Final Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-sm">
                        <?php if(count($history) > 0): ?>
                            <?php foreach($history as $row): 
                                $req_role = $row['actual_role'] ?? 'Employee';
                                
                                $b_class = 'bg-rose-50 text-rose-600 border-rose-200';
                                if ($row['status'] === 'Approved') $b_class = 'bg-emerald-50 text-emerald-600 border-emerald-200';
                                elseif ($row['status'] === 'Pending') $b_class = 'bg-amber-50 text-amber-600 border-amber-200';

                                $tl_node = $row['tl_status'] == 'Approved' ? 'node-approved' : ($row['tl_status'] == 'Rejected' ? 'node-rejected' : 'node-pending');
                                $mgr_node = $row['manager_status'] == 'Approved' ? 'node-approved' : ($row['manager_status'] == 'Rejected' ? 'node-rejected' : 'node-pending');
                                $hr_node = $row['hr_status'] == 'Approved' ? 'node-approved' : ($row['hr_status'] == 'Rejected' ? 'node-rejected' : 'node-pending');
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-700"><?= date('d M Y', strtotime($row['applied_date'])) ?></td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-slate-800"><?= date('d M', strtotime($row['start_date'])) ?></span> 
                                    <span class="text-slate-400 text-xs mx-1">to</span> 
                                    <span class="font-semibold text-slate-800"><?= date('d M Y', strtotime($row['end_date'])) ?></span>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-500"><?= htmlspecialchars($row['shift']) ?></td>
                                <td class="px-6 py-4 text-slate-600 truncate max-w-[200px]" title="<?= htmlspecialchars($row['reason']) ?>">
                                    <?= htmlspecialchars($row['reason']) ?>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="approval-chain">
                                        <?php if (in_array($req_role, ['Manager', 'Project Manager', 'General Manager'])): ?>
                                            <span class="chain-node <?= $hr_node ?>" title="HR: <?= $row['hr_status'] ?>">H</span>
                                            
                                        <?php elseif (in_array($req_role, ['Team Lead', 'TL'])): ?>
                                            <span class="chain-node <?= $mgr_node ?>" title="Manager: <?= $row['manager_status'] ?>">M</span>
                                            <div class="chain-link"></div>
                                            <span class="chain-node <?= $hr_node ?>" title="HR: <?= $row['hr_status'] ?>">H</span>
                                            
                                        <?php elseif ($req_role === 'IT Executive'): ?>
                                            <span class="chain-node <?= $mgr_node ?>" title="IT Admin: <?= $row['manager_status'] ?>">A</span>
                                            <div class="chain-link"></div>
                                            <span class="chain-node <?= $hr_node ?>" title="HR: <?= $row['hr_status'] ?>">H</span>
                                            
                                        <?php elseif (in_array($req_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive', 'Admin', 'System Admin', 'CEO'])): ?>
                                            <span class="chain-node <?= $hr_node ?>" title="HR: <?= $row['hr_status'] ?>">H</span>
                                            
                                        <?php else: ?>
                                            <span class="chain-node <?= $tl_node ?>" title="Team Lead: <?= $row['tl_status'] ?>">T</span>
                                            <div class="chain-link"></div>
                                            <span class="chain-node <?= $mgr_node ?>" title="Manager: <?= $row['manager_status'] ?>">M</span>
                                            <div class="chain-link"></div>
                                            <span class="chain-node <?= $hr_node ?>" title="HR: <?= $row['hr_status'] ?>">H</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border <?= $b_class ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-12">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <i data-lucide="inbox" class="w-12 h-12 mb-3 text-slate-300"></i>
                                        <p class="font-bold text-slate-600">No WFH History</p>
                                        <p class="text-xs mt-1">You haven't submitted any Work From Home requests yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="requestModal">
        <div class="modal-box flex flex-col">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-2">
                    <i data-lucide="send" class="w-5 h-5 text-teal-600"></i> Submit WFH Request
                </h3>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-rose-500 bg-white p-1.5 rounded-lg border border-slate-200 shadow-sm transition-colors"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            
            <div class="p-6 overflow-y-auto custom-scroll">
                <form id="wfhForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="submit_wfh" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-4">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Employee Name</label>
                        <input type="text" name="employee_name" value="<?= htmlspecialchars($user_name) ?>" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 text-slate-500 font-semibold" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Assigned Shift</label>
                        <input type="text" value="<?= htmlspecialchars($display_shift) ?>" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 text-slate-500 font-semibold cursor-not-allowed" readonly title="Your assigned shift is automatically fetched.">
                        <input type="hidden" name="shift" value="<?= htmlspecialchars($user_shift_type) ?>">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Start Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="start_date" id="startDate" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition font-medium text-slate-700 bg-white" required onchange="setEndDateMin()">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">End Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="end_date" id="endDate" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition font-medium text-slate-700 bg-white" required>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex justify-between items-end mb-2">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest">Reason / Justification <span class="text-rose-500">*</span></label>
                            <span id="charCount" class="text-[10px] font-bold text-slate-400">0 / 250</span>
                        </div>
                        <textarea name="reason" rows="3" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition custom-scroll resize-none" placeholder="Provide a valid reason for working from home..." required maxlength="250" oninput="document.getElementById('charCount').innerText = this.value.length + ' / 250'"></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="closeModal()" class="px-6 py-2.5 bg-slate-100 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-200 transition border border-slate-200 shadow-sm flex items-center justify-center">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 bg-teal-700 text-white font-bold text-sm rounded-xl hover:bg-teal-800 shadow-md transition flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Check for success trigger in URL to fire SweetAlert
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            Swal.fire({
                title: 'Request Submitted!',
                text: 'Your WFH request has been sent for approval.',
                icon: 'success',
                confirmButtonColor: '#0f766e' // teal-700
            }).then(() => {
                // Clean URL after showing alert
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }

        function openModal() {
            const modal = document.getElementById('requestModal');
            modal.classList.add('active');
            
            // Set today as min date for Start Date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').setAttribute('min', today);
        }

        function closeModal() {
            document.getElementById('requestModal').classList.remove('active');
        }
        
        // Smart Date Validation: End Date cannot be before Start Date
        function setEndDateMin() {
            const startVal = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate');
            endDate.setAttribute('min', startVal);
            
            if(endDate.value && endDate.value < startVal) {
                endDate.value = startVal;
            }
        }

        function filterTable() {
            let input = document.getElementById("personalSearch").value.toUpperCase();
            let status = document.getElementById("statusFilter").value.toUpperCase();
            let tr = document.getElementById("myWfhTable").getElementsByTagName("tbody")[0].getElementsByTagName("tr");
            
            for (let i = 0; i < tr.length; i++) {
                if (tr[i].cells.length < 5) continue; // skip empty state row
                
                let reason = tr[i].cells[3].textContent.toUpperCase();
                let statText = tr[i].cells[5].textContent.toUpperCase(); // Final status cell
                
                if ((reason.indexOf(input) > -1) && (status === "" || statText.indexOf(status) > -1)) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>