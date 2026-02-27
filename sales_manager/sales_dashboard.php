<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database and Path configuration
if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    // Suppress error for UI demonstration purposes if DB file isn't found
    $sidebarPath = '';
    $headerPath = '';
}

// --- Dynamic Variables Fetching Logic ---
$user_id = $_SESSION['user_id'] ?? 35; // Defaulting to Varshini M if session not found

// Default UI Fallbacks
$emp_initials = "CD";
$emp_name = "Charlie Davis";
$emp_role = "Project Manager";
$emp_phone = "+91 00000 00000";
$emp_email = "charlie.mgr@gmail.com";
$emp_joined = "15 Mar 2023";
$attendance_time = date('h:i A, d M Y');

$prod_time_str = "00:00:00";
$punch_in = "--:--";
$stroke_dashoffset = 414;

$att_stats = ['on_time'=>0, 'late'=>0, 'absent'=>0, 'wfh'=>0];
$leave_total = 16;
$leave_taken = 0;
$leave_left = 16;

$total_deals = 0;
$deal_value = 0;
$total_customers = 0;
$active_customers = 0;
$conversion_rate = 0;
$monthly_revenue = 0;

$won_deals = 0;

// Pipeline stages mapped for UI
$pipeline_stages = ['Marketing'=>0, 'Sales'=>0, 'Email'=>0, 'Chat'=>0, 'Operational'=>0, 'Calls'=>0];
$pipeline_vals = ['Marketing'=>0, 'Sales'=>0, 'Email'=>0, 'Chat'=>0, 'Operational'=>0, 'Calls'=>0];
$pipeline_details = ['Marketing'=>[], 'Sales'=>[], 'Email'=>[], 'Chat'=>[], 'Operational'=>[], 'Calls'=>[]];

$recent_deals = [];
$recent_activities = [];
$quick_contacts = [];

if (isset($conn)) {
    // 1. Profile Data
    $q = mysqli_query($conn, "SELECT u.name, u.role, ep.phone, ep.email, ep.joining_date FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = '$user_id'");
    if ($q && $row = mysqli_fetch_assoc($q)) {
        $emp_name = $row['name'] ?: $emp_name;
        $emp_initials = strtoupper(substr(trim($emp_name), 0, 2));
        $emp_role = $row['role'] ?: $emp_role;
        $emp_phone = $row['phone'] ?: $emp_phone;
        $emp_email = $row['email'] ?: $emp_email;
        if (!empty($row['joining_date'])) $emp_joined = date('d M Y', strtotime($row['joining_date']));
    }

    // 2. Attendance Data
    $q_att = mysqli_query($conn, "SELECT punch_in, production_hours FROM attendance WHERE user_id = '$user_id' AND date = CURRENT_DATE() LIMIT 1");
    if ($q_att && $att_data = mysqli_fetch_assoc($q_att)) {
        if (!empty($att_data['punch_in'])) $punch_in = date('h:i A', strtotime($att_data['punch_in']));
        $prod_hours_decimal = (float)($att_data['production_hours'] ?? 0);
        $prod_h = floor($prod_hours_decimal);
        $prod_m = floor(($prod_hours_decimal - $prod_h) * 60);
        $prod_time_str = sprintf("%02d:%02d:00", $prod_h, $prod_m);
        $stroke_dashoffset = max(0, 414 - (414 * ($prod_hours_decimal / 8)));
    }

    // 3. Leaves & Attendance Stats
    $q_leave = mysqli_query($conn, "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = '$user_id' AND status = 'Approved' AND YEAR(start_date) = YEAR(CURRENT_DATE())");
    if ($q_leave && $row = mysqli_fetch_assoc($q_leave)) {
        $leave_taken = (int)$row['taken'];
        $leave_left = max(0, $leave_total - $leave_taken);
    }

    $q_astats = mysqli_query($conn, "SELECT
        SUM(CASE WHEN status='On Time' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status='WFH' THEN 1 ELSE 0 END) as wfh
        FROM attendance WHERE user_id='$user_id' AND YEAR(date) = YEAR(CURRENT_DATE())");
    if ($q_astats && $r = mysqli_fetch_assoc($q_astats)) {
        $att_stats = $r;
    }

    // 4. CRM & Pipeline Metrics
    $q_crm = mysqli_query($conn, "SELECT status, source, COUNT(*) as count, SUM(deal_value) as value FROM crm_clients GROUP BY status, source");
    if ($q_crm) {
        while ($r = mysqli_fetch_assoc($q_crm)) {
            $total_deals += $r['count'];
            $deal_value += $r['value'];
            if ($r['status'] === 'Won') {
                $won_deals += $r['count'];
            }
            
            $src = strtolower($r['source'] ?? '');
            if(strpos($src, 'web') !== false) { $pipeline_stages['Marketing'] += $r['count']; $pipeline_vals['Marketing'] += $r['value']; }
            elseif(strpos($src, 'referral') !== false) { $pipeline_stages['Sales'] += $r['count']; $pipeline_vals['Sales'] += $r['value']; }
            elseif(strpos($src, 'email') !== false) { $pipeline_stages['Email'] += $r['count']; $pipeline_vals['Email'] += $r['value']; }
            elseif(strpos($src, 'social') !== false) { $pipeline_stages['Chat'] += $r['count']; $pipeline_vals['Chat'] += $r['value']; }
            elseif(strpos($src, 'call') !== false) { $pipeline_stages['Calls'] += $r['count']; $pipeline_vals['Calls'] += $r['value']; }
            else { $pipeline_stages['Operational'] += $r['count']; $pipeline_vals['Operational'] += $r['value']; }
        }
    }
    $conversion_rate = ($total_deals > 0) ? round(($won_deals / $total_deals) * 100, 2) : 0;

    // 4.1 Detailed Pipeline Fetching for Context Menu
    $q_crm_details = mysqli_query($conn, "SELECT name, status, source, deal_value, executive, created_at FROM crm_clients ORDER BY created_at DESC");
    if ($q_crm_details) {
        while ($r = mysqli_fetch_assoc($q_crm_details)) {
            $src = strtolower($r['source'] ?? '');
            $stage = 'Operational'; 
            if(strpos($src, 'web') !== false) { $stage = 'Marketing'; }
            elseif(strpos($src, 'referral') !== false) { $stage = 'Sales'; }
            elseif(strpos($src, 'email') !== false) { $stage = 'Email'; }
            elseif(strpos($src, 'social') !== false) { $stage = 'Chat'; }
            elseif(strpos($src, 'call') !== false) { $stage = 'Calls'; }
            
            $pipeline_details[$stage][] = $r;
        }
    }

    // 5. Monthly Revenue
    $q_rev = mysqli_query($conn, "SELECT SUM(grand_total) as monthly_revenue FROM invoices WHERE status='Approved' AND MONTH(invoice_date) = MONTH(CURRENT_DATE()) AND YEAR(invoice_date) = YEAR(CURRENT_DATE())");
    if ($q_rev && $r = mysqli_fetch_assoc($q_rev)) {
        $monthly_revenue = (float)$r['monthly_revenue'];
    }

    // 6. Customers Stats
    $q_cust = mysqli_query($conn, "SELECT COUNT(*) as total_customers, SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active_customers FROM clients");
    if ($q_cust && $r = mysqli_fetch_assoc($q_cust)) {
        $total_customers = $r['total_customers'] ?? 0;
        $active_customers = $r['active_customers'] ?? 0;
    }

    // 7. Recent Deals
    $q_rd = mysqli_query($conn, "SELECT name, status, deal_value, executive, created_at FROM crm_clients ORDER BY created_at DESC LIMIT 4");
    if ($q_rd) {
        while ($r = mysqli_fetch_assoc($q_rd)) {
            $recent_deals[] = $r;
        }
    }

    // 8. Recent Activities
    $q_ra = mysqli_query($conn, "SELECT title, description, created_at FROM sales_tasks ORDER BY created_at DESC LIMIT 4");
    if ($q_ra) {
        while ($r = mysqli_fetch_assoc($q_ra)) {
            $recent_activities[] = $r;
        }
    }
    
    // 9. Quick Contacts
    $q_qc = mysqli_query($conn, "SELECT name, role FROM users WHERE id != '$user_id' LIMIT 4");
    if ($q_qc) {
        while ($r = mysqli_fetch_assoc($q_qc)) {
            $quick_contacts[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Deals Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #fcfcfd; }
        .funnel-step { transition: all 0.3s ease; cursor: pointer; position: relative; }
        .funnel-step:hover { filter: brightness(1.1); transform: translateX(5px); }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="text-slate-800 flex h-screen overflow-hidden">

    <?php 
    if(file_exists($sidebarPath)) {
        require_once $sidebarPath; 
    }
    ?>

    <div class="flex-1 flex flex-col h-screen overflow-y-auto pb-10 ml-[100px]">
        
        <?php 
        if(file_exists($headerPath)) {
            require_once $headerPath; 
        }
        ?>

        <header class="p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white border-b border-slate-100 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Deals Dashboard</h1>
                <div class="flex items-center text-xs text-slate-400 font-medium mt-1">
                    <i class="fa-solid fa-house-chimney mr-2"></i> Dashboard 
                    <i class="fa-solid fa-chevron-right mx-2 text-[8px]"></i> Deals Dashboard
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-bold flex items-center shadow-sm hover:bg-slate-50 transition">
                    <i class="fa-solid fa-download mr-2 text-slate-400"></i> Export <i class="fa-solid fa-chevron-down ml-2 text-[8px]"></i>
                </button>
                <button class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-bold flex items-center shadow-sm hover:bg-slate-50 transition">
                    <i class="fa-solid fa-calendar-range mr-2 text-slate-400"></i> <?= date('m/d/Y', strtotime('-7 days')) ?> - <?= date('m/d/Y') ?>
                </button>
            </div>
        </header>

        <main class="max-w-[1600px] w-full mx-auto px-4 md:px-6 space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-4 bg-white rounded-[1.5rem] border border-slate-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] p-6 flex flex-col items-center justify-between text-center">
                    <div class="w-full mb-2">
                        <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Today's Attendance</p>
                        <h2 class="text-[20px] font-black text-[#1e293b]" id="liveDateTime"><?php echo $attendance_time; ?></h2>
                    </div>
                    
                    <div class="relative w-44 h-44 my-5 flex items-center justify-center">
                        <svg class="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 160 160">
                            <circle cx="80" cy="80" r="66" stroke="#f1f5f9" stroke-width="14" fill="none"></circle>
                            <circle cx="80" cy="80" r="66" stroke="#0f766e" stroke-width="14" fill="none" stroke-dasharray="414" stroke-dashoffset="<?= $stroke_dashoffset ?>" stroke-linecap="round" class="transition-all duration-500"></circle>
                        </svg>
                        <div class="flex flex-col items-center justify-center z-10 mt-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Hours</p>
                            <p class="text-3xl font-black text-[#0f172a] tracking-tight"><?= $prod_time_str ?></p>
                        </div>
                    </div>

                    <div class="w-full space-y-4">
                        <button class="w-full bg-[#118B7E] hover:bg-[#0f7a6f] text-white font-bold py-3.5 rounded-2xl transition flex items-center justify-center gap-2 shadow-md shadow-teal-500/10">
                            <i class="fa-solid fa-right-to-bracket"></i> Punch In
                        </button>
                        <div class="flex items-center justify-center gap-1.5 text-xs font-bold text-slate-400">
                            <i class="fa-solid fa-fingerprint text-orange-400 text-sm"></i> Punched In at: <?= $punch_in ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 flex flex-col gap-6">
                    <div class="bg-white rounded-[1.5rem] border border-slate-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] p-6 flex-1 flex flex-col">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-extrabold text-[#1e293b] text-[17px]">Leave Details</h3>
                            <span class="bg-slate-50 text-slate-500 text-[10px] font-extrabold px-2.5 py-1.5 rounded border border-slate-100"><?= date('Y') ?></span>
                        </div>
                        <div class="flex items-center justify-between flex-1">
                            <ul class="space-y-3.5 text-[13px] font-bold text-slate-500 w-full">
                                <li class="flex items-center gap-3"><span class="w-2 h-2 rounded-full bg-[#0f766e]"></span> <span class="w-4 text-slate-700 font-black"><?= (int)$att_stats['on_time'] ?></span> On Time</li>
                                <li class="flex items-center gap-3"><span class="w-2 h-2 rounded-full bg-[#22c55e]"></span> <span class="w-4 text-slate-700 font-black"><?= (int)$att_stats['late'] ?></span> Late</li>
                                <li class="flex items-center gap-3"><span class="w-2 h-2 rounded-full bg-[#f97316]"></span> <span class="w-4 text-slate-700 font-black"><?= (int)$att_stats['wfh'] ?></span> Work From Home</li>
                                <li class="flex items-center gap-3"><span class="w-2 h-2 rounded-full bg-[#ef4444]"></span> <span class="w-4 text-slate-700 font-black"><?= (int)$att_stats['absent'] ?></span> Absent</li>
                                <li class="flex items-center gap-3"><span class="w-2 h-2 rounded-full bg-[#eab308]"></span> <span class="w-4 text-slate-700 font-black">0</span> Sick Leave</li>
                            </ul>
                            <div class="w-[105px] h-[105px] rounded-full border-[18px] border-[#118B7E] border-r-transparent transform -rotate-45 shrink-0 ml-4"></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-[1.5rem] border border-slate-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] p-5">
                         <h3 class="font-extrabold text-[#1e293b] mb-4 text-[15px]">Leave Balance</h3>
                         <div class="grid grid-cols-3 gap-3 text-center">
                             <div class="bg-emerald-50/50 py-3.5 rounded-[1rem] border border-emerald-100/50">
                                 <p class="text-[9px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Total</p>
                                 <p class="text-xl font-black text-[#0f766e]"><?= $leave_total ?></p>
                             </div>
                             <div class="bg-blue-50/50 py-3.5 rounded-[1rem] border border-blue-100/50">
                                 <p class="text-[9px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Taken</p>
                                 <p class="text-xl font-black text-blue-600"><?= $leave_taken ?></p>
                             </div>
                             <div class="bg-green-50/50 py-3.5 rounded-[1rem] border border-green-100/50">
                                 <p class="text-[9px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Left</p>
                                 <p class="text-xl font-black text-green-600"><?= $leave_left ?></p>
                             </div>
                         </div>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white rounded-[1.5rem] border border-slate-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] overflow-hidden flex flex-col">
                    <div class="bg-[#117B6F] p-8 pb-10 flex flex-col items-center text-center relative">
                        <div class="relative mt-2">
                            <div class="w-28 h-28 rounded-full border-2 border-white flex items-center justify-center text-white text-[38px] tracking-tight font-extrabold bg-transparent">
                                <?php echo $emp_initials; ?>
                            </div>
                            <div class="absolute bottom-1 right-2 w-6 h-6 bg-[#4ade80] rounded-full border-[4px] border-[#117B6F]"></div>
                        </div>
                        <h2 class="text-2xl font-extrabold text-white mt-5"><?php echo $emp_name; ?></h2>
                        <p class="text-[14px] font-medium text-emerald-300 mt-1"><?php echo $emp_role; ?></p>
                        <button class="mt-5 bg-white/20 hover:bg-white/30 transition text-white text-[12px] font-bold px-5 py-2 rounded-full backdrop-blur-sm shadow-sm">Verified Account</button>
                    </div>
                    <div class="p-6 space-y-4 flex-grow bg-white -mt-2">
                        <div class="flex items-center gap-4 bg-slate-50/70 p-3.5 rounded-2xl border border-slate-50">
                            <div class="w-11 h-11 rounded-xl bg-teal-50/80 text-[#117B6F] flex items-center justify-center text-base shrink-0 shadow-sm">
                                <i class="fa-solid fa-phone transform rotate-90"></i>
                            </div>
                            <div class="min-w-0 pt-0.5">
                                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-0.5">Phone</p>
                                <p class="text-[14px] font-extrabold text-[#1e293b] truncate"><?php echo $emp_phone; ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4 bg-slate-50/70 p-3.5 rounded-2xl border border-slate-50">
                            <div class="w-11 h-11 rounded-xl bg-teal-50/80 text-[#117B6F] flex items-center justify-center text-base shrink-0 shadow-sm">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div class="min-w-0 pt-0.5">
                                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-0.5">Email</p>
                                <p class="text-[14px] font-extrabold text-[#1e293b] truncate"><?php echo $emp_email; ?></p>
                            </div>
                        </div>
                        
                        <div class="border-t border-dashed border-slate-200/80 my-5"></div>
                        
                        <div class="flex items-center justify-between bg-emerald-50/40 p-4 rounded-2xl border border-emerald-100/50">
                            <div class="flex items-center gap-2.5 text-[#117B6F] text-[14px] font-extrabold">
                                <i class="fa-solid fa-calendar-check"></i> Joined
                            </div>
                            <p class="text-[14px] font-extrabold text-[#1e293b]"><?php echo $emp_joined; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] overflow-hidden flex flex-col">
                    <div class="p-6 pb-0 border-b border-slate-100 flex justify-between items-center mb-6">
                        <h3 class="text-[17px] font-bold text-[#1e293b] pb-6">Pipeline Stages</h3>
                        <button class="text-xs font-medium border border-slate-200 px-3 py-1.5 rounded-md flex items-center gap-1.5 text-slate-600 mb-6 hover:bg-slate-50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            This Week
                        </button>
                    </div>

                    <div class="px-6 flex flex-col items-center gap-[2px] mb-10 w-full relative">
                        <div class="absolute w-[90%] border-l border-r border-slate-100 h-full top-0 -z-10 border-dashed"></div>

                        <div class="funnel-step w-[90%] bg-[#eb7943] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Marketing')">Marketing : <?= number_format($pipeline_stages['Marketing']) ?></div>
                        <div class="funnel-step w-[75%] bg-[#f19262] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Sales')">Sales : <?= number_format($pipeline_stages['Sales']) ?></div>
                        <div class="funnel-step w-[62%] bg-[#f5aa81] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Email')">Email : <?= number_format($pipeline_stages['Email']) ?></div>
                        <div class="funnel-step w-[50%] bg-[#f9c2a0] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Chat')">Chat : <?= number_format($pipeline_stages['Chat']) ?></div>
                        <div class="funnel-step w-[38%] bg-[#fcdabe] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Operational')">Operational : <?= number_format($pipeline_stages['Operational']) ?></div>
                        <div class="funnel-step w-[28%] bg-[#fee5cf] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Calls')">Calls : <?= number_format($pipeline_stages['Calls']) ?></div>
                    </div>

                    <div class="px-6 pb-6 mt-auto">
                        <h4 class="text-[15px] font-bold text-[#1e293b] mb-4">Leads Values By Stages</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#eb7943]"></span> Marketing
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Marketing'], 2) ?></p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f19262]"></span> Sales
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Sales'], 2) ?></p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f5aa81]"></span> Email
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Email'], 2) ?></p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f9c2a0]"></span> Chat
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Chat'], 2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7 grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Total Deals</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]"><?= $total_deals ?></h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#e85b2b] flex items-center justify-center text-white shadow-sm mt-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4l-8 12h16l-8-12z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 18h8"></path></svg>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#e85b2b] h-full rounded-full" style="width: 55%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-red-500">~ -4.01%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Total Customers</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]"><?= $total_customers ?></h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#9b51e0] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-solid fa-users text-[14px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#9b51e0] h-full rounded-full" style="width: 50%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-emerald-500">~ +55%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Deal Value</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">₹<?= number_format($deal_value, 2) ?></h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#0d4752] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-brands fa-connectdevelop text-[16px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#0d4752] h-full rounded-full" style="width: 45%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-emerald-500">~ +20.01%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Conversion Rate</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]"><?= $conversion_rate ?>%</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#2563eb] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-solid fa-layer-group text-[14px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#2563eb] h-full rounded-full" style="width: 55%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-red-500">~ -6.01%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Revenue this month</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">₹<?= number_format($monthly_revenue, 2) ?></h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#ec4899] flex items-center justify-center text-white shadow-sm mt-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#ec4899] h-full rounded-full" style="width: 65%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-emerald-500">~ +55%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Active Customers</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]"><?= $active_customers ?></h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#f59e0b] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-regular fa-star text-[16px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#f59e0b] h-full rounded-full" style="width: 80%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-red-500">~ -3.22%</span> from last week
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-extrabold text-slate-800">Deals By Country</h3>
                        <button class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition">View All</button>
                    </div>
                    <div class="space-y-5">
                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇺🇸</span>
                                <div><p class="text-sm font-extrabold text-slate-800">USA</p><p class="text-[10px] font-bold text-slate-400">Deals : <?= $total_deals ?></p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                <svg class="w-8 h-6 text-emerald-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">₹<?= number_format($deal_value, 2) ?></p></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇦🇪</span>
                                <div><p class="text-sm font-extrabold text-slate-800">UAE</p><p class="text-[10px] font-bold text-slate-400">Deals : 0</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                 <svg class="w-8 h-6 text-emerald-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">₹0.00</p></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇸🇬</span>
                                <div><p class="text-sm font-extrabold text-slate-800">Singapore</p><p class="text-[10px] font-bold text-slate-400">Deals : 0</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                 <svg class="w-8 h-6 text-red-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline><polyline points="16 17 22 17 22 11"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">₹0.00</p></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇫🇷</span>
                                <div><p class="text-sm font-extrabold text-slate-800">France</p><p class="text-[10px] font-bold text-slate-400">Deals : 0</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                 <svg class="w-8 h-6 text-emerald-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">₹0.00</p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-extrabold text-slate-800">Won Deals Stage</h3>
                        <span class="text-[10px] font-bold bg-slate-50 px-2 py-1 rounded-lg border border-slate-100"><i class="fa-solid fa-calendar text-slate-400 mr-1"></i> This Week</span>
                    </div>
                    <div class="text-center mb-6 mt-4">
                        <p class="text-[10px] text-slate-400 uppercase font-bold tracking-widest">Stages Won This Year</p>
                        <h2 class="text-3xl font-black text-slate-900 mt-1">₹<?= number_format($won_deals) ?> <span class="text-xs bg-red-50 text-red-500 px-2 py-0.5 rounded-md font-bold align-middle">↓ 12%</span></h2>
                    </div>
                    <div class="relative flex-grow flex items-center justify-center min-h-[220px]">
                        <div class="absolute top-0 left-0 md:left-4 w-36 h-36 rounded-full bg-[#0d3b44] shadow-2xl flex flex-col items-center justify-center text-white ring-4 ring-white hover:scale-105 transition cursor-pointer z-10">
                            <span class="text-[10px] font-medium opacity-80">Conversion</span><span class="text-3xl font-black"><?= $conversion_rate ?>%</span>
                        </div>
                        <div class="absolute top-0 right-4 md:right-10 w-24 h-24 rounded-full bg-red-600 shadow-xl flex flex-col items-center justify-center text-white ring-4 ring-white z-20 hover:scale-105 transition cursor-pointer">
                            <span class="text-[9px] font-medium opacity-80">Calls</span><span class="text-xl font-black">24%</span>
                        </div>
                        <div class="absolute bottom-4 right-0 md:right-6 w-32 h-32 rounded-full bg-amber-400 shadow-xl flex flex-col items-center justify-center text-white ring-4 ring-white z-10 hover:scale-105 transition cursor-pointer">
                            <span class="text-[11px] font-medium opacity-80">Email</span><span class="text-2xl font-black">39%</span>
                        </div>
                        <div class="absolute bottom-0 left-16 md:left-24 w-20 h-20 rounded-full bg-emerald-500 shadow-xl flex flex-col items-center justify-center text-white ring-4 ring-white z-20 hover:scale-105 transition cursor-pointer">
                            <span class="text-[9px] font-medium opacity-80">Chats</span><span class="text-lg font-black">20%</span>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-extrabold text-slate-800">Recent Follow Up</h3>
                        <button class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition">View All</button>
                    </div>
                    <div class="space-y-4">
                        <?php if (!empty($quick_contacts)): ?>
                            <?php $colors = ['blue', 'pink', 'orange', 'slate']; ?>
                            <?php foreach($quick_contacts as $index => $contact): ?>
                            <?php $color = $colors[$index % 4]; ?>
                            <div class="flex items-center justify-between p-2 -mx-2 hover:bg-slate-50 rounded-2xl transition group cursor-pointer border border-transparent hover:border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-<?= $color ?>-100 border-2 border-white shadow-sm overflow-hidden flex items-center justify-center font-bold text-<?= $color ?>-600">
                                        <?= strtoupper(substr($contact['name'], 0, 1)) ?>
                                    </div>
                                    <div><p class="text-sm font-extrabold text-slate-800"><?= htmlspecialchars($contact['name']) ?></p><p class="text-[11px] font-bold text-slate-400"><?= htmlspecialchars($contact['role']) ?></p></div>
                                </div>
                                <div class="w-8 h-8 flex items-center justify-center bg-slate-50 rounded-xl text-slate-400 group-hover:bg-orange-500 group-hover:text-white transition shadow-sm"><i class="fa-solid fa-envelope text-[11px]"></i></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-xs text-slate-400 italic">No contacts found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-8 bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-white">
                        <h3 class="font-extrabold text-slate-800">Recent Deals</h3>
                        <button class="bg-slate-50 text-[11px] font-extrabold px-4 py-1.5 rounded-xl hover:bg-slate-100 transition text-slate-500">View All</button>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar flex-grow">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50/50 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4">Deal Name</th>
                                    <th class="px-6 py-4 text-center">Stage</th>
                                    <th class="px-6 py-4">Deal Value</th>
                                    <th class="px-6 py-4">Owner</th>
                                    <th class="px-6 py-4">Closed Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if(!empty($recent_deals)): ?>
                                    <?php foreach($recent_deals as $deal): ?>
                                    <tr class="hover:bg-slate-50/80 transition cursor-pointer">
                                        <td class="px-6 py-5 font-extrabold text-slate-800"><?= htmlspecialchars($deal['name']) ?></td>
                                        <td class="px-6 py-5 text-center"><span class="bg-slate-100 text-slate-500 px-3 py-1.5 rounded-lg text-[10px] font-bold"><?= htmlspecialchars($deal['status']) ?></span></td>
                                        <td class="px-6 py-5 font-black text-slate-900">₹<?= number_format($deal['deal_value'], 2) ?></td>
                                        <td class="px-6 py-5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shadow-sm border border-white flex justify-center items-center font-bold text-xs text-slate-600">
                                                    <?= strtoupper(substr($deal['executive'], 0, 1)) ?>
                                                </div>
                                                <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($deal['executive']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 text-[11px] font-bold text-slate-400"><?= date('d M Y', strtotime($deal['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="px-6 py-5 text-center text-sm text-slate-400">No recent deals found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="font-extrabold text-slate-800">Recent Activities</h3>
                        <button class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition">View All</button>
                    </div>
                    <div class="space-y-8 relative before:absolute before:left-[19px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                        
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach($recent_activities as $act): ?>
                            <div class="relative flex gap-5 group cursor-pointer">
                                <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center z-10 shadow-lg shadow-emerald-100 ring-4 ring-white group-hover:scale-110 transition"><i class="fa-solid fa-bell text-xs"></i></div>
                                <div class="pt-1">
                                    <p class="text-xs font-extrabold text-slate-800 leading-relaxed pr-4"><?= htmlspecialchars($act['title']) ?> - <?= htmlspecialchars($act['description']) ?></p>
                                    <p class="text-[11px] font-bold text-slate-400 mt-1"><?= date('h:i A', strtotime($act['created_at'])) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-xs text-slate-400 pl-10 italic">No recent activities found.</div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </main>

        <div class="fixed right-0 top-1/2 -translate-y-1/2 bg-orange-500 p-3 rounded-l-xl shadow-xl shadow-orange-200 text-white cursor-pointer z-50 hover:bg-orange-600 transition">
            <i class="fa-solid fa-gear text-lg animate-spin-slow" style="animation: spin 6s linear infinite;"></i>
        </div>

    </div>

    <div id="contextMenu" class="absolute hidden bg-white rounded-xl shadow-2xl border border-slate-100 w-72 z-[100] overflow-hidden transition-all duration-200 transform scale-95 opacity-0 origin-top-left">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-sm" id="contextTitle">Stage Details</h3>
            <span class="text-[10px] font-bold bg-slate-200 text-slate-600 px-2 py-0.5 rounded-md" id="contextCount">0 Deals</span>
        </div>
        <div class="max-h-64 overflow-y-auto custom-scrollbar" id="contextBody">
            </div>
    </div>

    <script>
        // Data injected from PHP for the pipeline context menu details
        const pipelineData = <?php echo json_encode($pipeline_details); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            function updateClock() {
                const now = new Date();
                const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: true };
                const dateOptions = { day: '2-digit', month: 'short', year: 'numeric' };
                
                const timeStr = now.toLocaleTimeString('en-US', timeOptions);
                const dateStr = now.toLocaleDateString('en-GB', dateOptions);
                
                const clockEl = document.getElementById('liveDateTime');
                if(clockEl) {
                    clockEl.innerText = `${timeStr}, ${dateStr}`;
                }
            }
            updateClock();
            setInterval(updateClock, 1000);
        });

        // Custom Context Menu Function
        function showContextMenu(e, stage) {
            e.preventDefault();
            e.stopPropagation(); // Prevents document click from hiding it immediately

            const menu = document.getElementById('contextMenu');
            const title = document.getElementById('contextTitle');
            const body = document.getElementById('contextBody');
            const count = document.getElementById('contextCount');

            // Setup Data
            title.innerText = stage + ' Deals';
            const data = pipelineData[stage] || [];
            count.innerText = data.length + (data.length === 1 ? ' Deal' : ' Deals');
            body.innerHTML = '';

            if(data.length === 0) {
                body.innerHTML = '<div class="px-4 py-6 text-center text-xs text-slate-400 italic">No deals found in this stage.</div>';
            } else {
                data.forEach(deal => {
                    const value = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(deal.deal_value);
                    body.innerHTML += `
                        <div class="px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition cursor-pointer">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-slate-800 text-sm truncate max-w-[150px]">${deal.name}</span>
                                <span class="font-black text-emerald-600 text-xs">${value}</span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-[10px] text-slate-500 font-medium"><i class="fa-solid fa-user-tie mr-1"></i>${deal.executive || 'Unassigned'}</span>
                                <span class="text-[9px] font-bold bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded">${deal.status}</span>
                            </div>
                        </div>
                    `;
                });
            }

            // Temporarily show to get dimensions
            menu.classList.remove('hidden');

            let x = e.clientX;
            let y = e.clientY;

            // Adjust position so it doesn't go off-screen
            const menuWidth = menu.offsetWidth;
            const menuHeight = menu.offsetHeight;
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;

            if (x + menuWidth > windowWidth) {
                x = windowWidth - menuWidth - 10;
            }
            if (y + menuHeight > windowHeight) {
                y = windowHeight - menuHeight - 10;
            }

            menu.style.left = `${x}px`;
            menu.style.top = `${y}px`;

            // Animate popup
            setTimeout(() => {
                menu.classList.remove('scale-95', 'opacity-0');
                menu.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        // Global click listener to close the context menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('contextMenu');
            if (menu && !menu.classList.contains('hidden')) {
                // If the click is not inside the menu, hide it
                if (!menu.contains(e.target)) {
                    menu.classList.remove('scale-100', 'opacity-100');
                    menu.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        menu.classList.add('hidden');
                    }, 200); // match the tailwind transition duration
                }
            }
        });
    </script>
</body>
</html>