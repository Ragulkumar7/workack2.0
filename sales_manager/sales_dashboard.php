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
    $sidebarPath = '';
    $headerPath = '';
}

// --- Dynamic Variables Fetching Logic ---
$user_id = $_SESSION['user_id'] ?? 35; // Defaulting to Varshini M if session not found

// =========================================================================================
// AJAX HANDLER FOR FILTERING PIPELINE AND WON DEALS
// =========================================================================================
if (isset($_GET['ajax_filter'])) {
    header('Content-Type: application/json');
    $filter_type = $_GET['filter_type'] ?? 'pipeline'; // 'pipeline' or 'won_deals'
    $timeframe = $_GET['timeframe'] ?? 'week'; // 'week', 'month', 'year'
    
    $date_condition = "";
    if ($timeframe === 'week') {
        $date_condition = "AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($timeframe === 'month') {
        $date_condition = "AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    } elseif ($timeframe === 'year') {
        $date_condition = "AND YEAR(created_at) = YEAR(CURDATE())";
    }

    if ($filter_type === 'pipeline' && isset($conn)) {
        $p_stages = ['Marketing'=>0, 'Sales'=>0, 'Email'=>0, 'Chat'=>0, 'Operational'=>0, 'Calls'=>0];
        $p_vals = ['Marketing'=>0, 'Sales'=>0, 'Email'=>0, 'Chat'=>0, 'Operational'=>0, 'Calls'=>0];
        
        $q_ajax = mysqli_query($conn, "SELECT status, source, COUNT(*) as count, SUM(deal_value) as value FROM crm_clients WHERE 1=1 $date_condition GROUP BY status, source");
        if ($q_ajax) {
            while ($r = mysqli_fetch_assoc($q_ajax)) {
                $src = strtolower($r['source'] ?? '');
                if(strpos($src, 'web') !== false) { $p_stages['Marketing'] += $r['count']; $p_vals['Marketing'] += $r['value']; }
                elseif(strpos($src, 'referral') !== false) { $p_stages['Sales'] += $r['count']; $p_vals['Sales'] += $r['value']; }
                elseif(strpos($src, 'email') !== false) { $p_stages['Email'] += $r['count']; $p_vals['Email'] += $r['value']; }
                elseif(strpos($src, 'social') !== false) { $p_stages['Chat'] += $r['count']; $p_vals['Chat'] += $r['value']; }
                elseif(strpos($src, 'call') !== false) { $p_stages['Calls'] += $r['count']; $p_vals['Calls'] += $r['value']; }
                else { $p_stages['Operational'] += $r['count']; $p_vals['Operational'] += $r['value']; }
            }
        }
        echo json_encode(['stages' => $p_stages, 'values' => $p_vals]);
        exit;
    }

    if ($filter_type === 'won_deals' && isset($conn)) {
        $w_total = 0;
        $w_count = 0;
        $all_count = 0;
        $w_sources = ['Calls' => 0, 'Email' => 0, 'Chat' => 0, 'Referrals' => 0];
        
        // Get total count for conversion rate
        $q_all = mysqli_query($conn, "SELECT COUNT(*) as total FROM crm_clients WHERE 1=1 $date_condition");
        if ($q_all && $r = mysqli_fetch_assoc($q_all)) { $all_count = $r['total']; }

        $q_won = mysqli_query($conn, "SELECT source, COUNT(*) as count, SUM(deal_value) as value FROM crm_clients WHERE status='Won' $date_condition GROUP BY source");
        if ($q_won) {
            while ($r = mysqli_fetch_assoc($q_won)) {
                $w_total += $r['value'];
                $w_count += $r['count'];
                
                $src = strtolower($r['source'] ?? '');
                if(strpos($src, 'call') !== false) { $w_sources['Calls'] += $r['count']; }
                elseif(strpos($src, 'email') !== false) { $w_sources['Email'] += $r['count']; }
                elseif(strpos($src, 'social') !== false) { $w_sources['Chat'] += $r['count']; }
                else { $w_sources['Referrals'] += $r['count']; }
            }
        }
        
        $conv_rate = ($all_count > 0) ? round(($w_count / $all_count) * 100, 2) : 0;
        
        // Calculate percentages for sources
        $src_pct = ['Calls' => 0, 'Email' => 0, 'Chat' => 0, 'Referrals' => 0];
        if ($w_count > 0) {
            $src_pct['Calls'] = round(($w_sources['Calls'] / $w_count) * 100);
            $src_pct['Email'] = round(($w_sources['Email'] / $w_count) * 100);
            $src_pct['Chat'] = round(($w_sources['Chat'] / $w_count) * 100);
            $src_pct['Referrals'] = round(($w_sources['Referrals'] / $w_count) * 100);
        }

        echo json_encode([
            'total_value' => $w_total,
            'conversion' => $conv_rate,
            'sources' => $src_pct
        ]);
        exit;
    }
}
// =========================================================================================

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
$leave_total = 2;
$leave_taken = 0;
$leave_left = 2;
$lop_days = 0;

$total_deals = 0;
$deal_value = 0;
$total_customers = 0;
$active_customers = 0;
$conversion_rate = 0;
$monthly_revenue = 0;
$won_deals = 0;

// Pipeline stages mapped for UI (Default load is THIS WEEK)
$pipeline_stages = ['Marketing'=>0, 'Sales'=>0, 'Email'=>0, 'Chat'=>0, 'Operational'=>0, 'Calls'=>0];
$pipeline_vals = ['Marketing'=>0, 'Sales'=>0, 'Email'=>0, 'Chat'=>0, 'Operational'=>0, 'Calls'=>0];
$pipeline_details = ['Marketing'=>[], 'Sales'=>[], 'Email'=>[], 'Chat'=>[], 'Operational'=>[], 'Calls'=>[]];

// Won deals default load (THIS YEAR)
$won_sources_pct = ['Calls' => 24, 'Email' => 39, 'Chat' => 20, 'Referrals' => 17]; 

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
        $leave_left = $leave_total - $leave_taken;
        if ($leave_left < 0) {
            $lop_days = abs($leave_left);
            $leave_left = 0; 
        }
    }

    $q_astats = mysqli_query($conn, "SELECT
        SUM(CASE WHEN status='On Time' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status='WFH' THEN 1 ELSE 0 END) as wfh
        FROM attendance WHERE user_id='$user_id' AND YEAR(date) = YEAR(CURRENT_DATE())");
    if ($q_astats && $r = mysqli_fetch_assoc($q_astats)) {
        $att_stats = $r;
        $att_stats['absent'] = (int)$att_stats['absent'] + $leave_taken;
    }

    // 4. CRM & Pipeline Metrics (Default: This Week)
    $q_crm = mysqli_query($conn, "SELECT status, source, COUNT(*) as count, SUM(deal_value) as value FROM crm_clients WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) GROUP BY status, source");
    if ($q_crm) {
        while ($r = mysqli_fetch_assoc($q_crm)) {
            $src = strtolower($r['source'] ?? '');
            if(strpos($src, 'web') !== false) { $pipeline_stages['Marketing'] += $r['count']; $pipeline_vals['Marketing'] += $r['value']; }
            elseif(strpos($src, 'referral') !== false) { $pipeline_stages['Sales'] += $r['count']; $pipeline_vals['Sales'] += $r['value']; }
            elseif(strpos($src, 'email') !== false) { $pipeline_stages['Email'] += $r['count']; $pipeline_vals['Email'] += $r['value']; }
            elseif(strpos($src, 'social') !== false) { $pipeline_stages['Chat'] += $r['count']; $pipeline_vals['Chat'] += $r['value']; }
            elseif(strpos($src, 'call') !== false) { $pipeline_stages['Calls'] += $r['count']; $pipeline_vals['Calls'] += $r['value']; }
            else { $pipeline_stages['Operational'] += $r['count']; $pipeline_vals['Operational'] += $r['value']; }
        }
    }
    
    // Overall Stats (All time)
    $q_overall = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(deal_value) as value, SUM(CASE WHEN status='Won' THEN 1 ELSE 0 END) as won_count FROM crm_clients");
    if ($q_overall && $r = mysqli_fetch_assoc($q_overall)) {
        $total_deals = $r['count'] ?? 0;
        $deal_value = $r['value'] ?? 0;
        $conversion_rate = ($total_deals > 0) ? round(($r['won_count'] / $total_deals) * 100, 2) : 0;
    }

    // Won Deals specific stats (Default: This Year)
    $q_won_yr = mysqli_query($conn, "SELECT source, COUNT(*) as count, SUM(deal_value) as value FROM crm_clients WHERE status='Won' AND YEAR(created_at) = YEAR(CURDATE()) GROUP BY source");
    if ($q_won_yr) {
        $w_c = 0; $w_sources = ['Calls' => 0, 'Email' => 0, 'Chat' => 0, 'Referrals' => 0];
        while ($r = mysqli_fetch_assoc($q_won_yr)) {
            $won_deals += $r['value'];
            $w_c += $r['count'];
            $src = strtolower($r['source'] ?? '');
            if(strpos($src, 'call') !== false) { $w_sources['Calls'] += $r['count']; }
            elseif(strpos($src, 'email') !== false) { $w_sources['Email'] += $r['count']; }
            elseif(strpos($src, 'social') !== false) { $w_sources['Chat'] += $r['count']; }
            else { $w_sources['Referrals'] += $r['count']; }
        }
        if($w_c > 0) {
            $won_sources_pct['Calls'] = round(($w_sources['Calls'] / $w_c) * 100);
            $won_sources_pct['Email'] = round(($w_sources['Email'] / $w_c) * 100);
            $won_sources_pct['Chat'] = round(($w_sources['Chat'] / $w_c) * 100);
            $won_sources_pct['Referrals'] = round(($w_sources['Referrals'] / $w_c) * 100);
        }
    }


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
    $q_qc = @mysqli_query($conn, "SELECT id, name, email, phone, source, deal_value FROM crm_clients ORDER BY created_at DESC LIMIT 4");
    if (!$q_qc) {
        $q_qc = @mysqli_query($conn, "SELECT id, name, executive as email, source, deal_value FROM crm_clients ORDER BY created_at DESC LIMIT 4");
    }
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
        
        /* Progress Bar Animation */
        @keyframes fillProgress {
            from { width: 0; }
        }
        .progress-animate {
            animation: fillProgress 1s ease-out forwards;
        }

        /* Filter Dropdown transition */
        .filter-dropdown { display: none; position: absolute; top: 100%; right: 0; z-index: 50; background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); width: 120px; padding: 4px; mt-1; }
        .filter-dropdown.show { display: block; }
        .filter-option { display: block; width: 100%; text-align: left; padding: 6px 12px; font-size: 11px; font-weight: 600; color: #475569; border-radius: 0.25rem; transition: all 0.2s; }
        .filter-option:hover { background-color: #f8fafc; color: #0f172a; }
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
                    <i class="fa-solid fa-calendar-range mr-2 text-slate-400"></i> <?= date('m/d/Y', strtotime('-7 days')) ?> - <?= date('m/d/Y') ?>
                </button>
            </div>
        </header>

        <main class="max-w-[1600px] w-full mx-auto px-4 md:px-6 space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
                
               <div class="lg:col-span-4 bg-white rounded-[1.5rem] border border-slate-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] p-6 flex flex-col h-full">
                        <?php include '../attendance_card.php'; ?>
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
                         <?php if($lop_days > 0): ?>
                             <div class="bg-rose-50 border border-rose-200 rounded-lg p-2.5 mt-4 flex items-center gap-3">
                                 <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 flex-shrink-0"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                 <p class="text-xs font-semibold text-rose-700 leading-tight">Leave limit exceeded! <b><?php echo $lop_days; ?> Days</b> considered as LOP.</p>
                             </div>
                         <?php endif; ?>
                         <div class="mt-4">
                             <a href="../employee/leave_request.php" class="block w-full bg-[#117B6F] hover:bg-[#0f665c] text-white font-bold py-2.5 rounded-xl text-center transition shadow-sm text-[13px]">
                                 <i class="fa-solid fa-plus mr-1.5"></i> APPLY FOR LEAVE
                             </a>
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
                    <div class="p-6 pb-0 border-b border-slate-100 flex justify-between items-center mb-6 relative">
                        <h3 class="text-[17px] font-bold text-[#1e293b] pb-6">Pipeline Stages</h3>
                        
                        <div class="relative">
                            <button id="btnPipelineFilter" onclick="toggleDropdown('dropdownPipeline')" class="text-xs font-medium border border-slate-200 px-3 py-1.5 rounded-md flex items-center gap-1.5 text-slate-600 mb-6 hover:bg-slate-50 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span id="lblPipelineFilter">This Week</span>
                                <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i>
                            </button>
                            <div id="dropdownPipeline" class="filter-dropdown top-[35px]">
                                <button onclick="updateFilter('pipeline', 'week', 'This Week')" class="filter-option">This Week</button>
                                <button onclick="updateFilter('pipeline', 'month', 'This Month')" class="filter-option">This Month</button>
                                <button onclick="updateFilter('pipeline', 'year', 'This Year')" class="filter-option">This Year</button>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 flex flex-col items-center gap-[2px] mb-10 w-full relative" id="pipelineBars">
                        <div class="absolute w-[90%] border-l border-r border-slate-100 h-full top-0 -z-10 border-dashed"></div>

                        <div id="pipe-marketing" class="funnel-step w-[90%] bg-[#eb7943] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Marketing')">Marketing : <?= number_format($pipeline_stages['Marketing']) ?></div>
                        <div id="pipe-sales" class="funnel-step w-[75%] bg-[#f19262] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Sales')">Sales : <?= number_format($pipeline_stages['Sales']) ?></div>
                        <div id="pipe-email" class="funnel-step w-[62%] bg-[#f5aa81] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Email')">Email : <?= number_format($pipeline_stages['Email']) ?></div>
                        <div id="pipe-chat" class="funnel-step w-[50%] bg-[#f9c2a0] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Chat')">Chat : <?= number_format($pipeline_stages['Chat']) ?></div>
                        <div id="pipe-operational" class="funnel-step w-[38%] bg-[#fcdabe] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Operational')">Operational : <?= number_format($pipeline_stages['Operational']) ?></div>
                        <div id="pipe-calls" class="funnel-step w-[28%] bg-[#fee5cf] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm" onclick="showContextMenu(event, 'Calls')">Calls : <?= number_format($pipeline_stages['Calls']) ?></div>
                    </div>

                    <div class="px-6 pb-6 mt-auto">
                        <h4 class="text-[15px] font-bold text-[#1e293b] mb-4">Leads Values By Stages</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#eb7943]"></span> Marketing
                                </p>
                                <p id="val-marketing" class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Marketing'], 2) ?></p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f19262]"></span> Sales
                                </p>
                                <p id="val-sales" class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Sales'], 2) ?></p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f5aa81]"></span> Email
                                </p>
                                <p id="val-email" class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Email'], 2) ?></p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f9c2a0]"></span> Chat
                                </p>
                                <p id="val-chat" class="font-bold text-[#1e293b] text-sm">₹<?= number_format($pipeline_vals['Chat'], 2) ?></p>
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
                                <span class="text-emerald-500">~ +4.01%</span> from last week
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
                                <span class="text-emerald-500">~ +6.01%</span> from last week
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
                                <span class="text-emerald-500">~ +3.22%</span> from last week
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-6 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col relative">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-extrabold text-slate-800">Won Deals Sources</h3>
                        
                        <div class="relative">
                            <button id="btnWonFilter" onclick="toggleDropdown('dropdownWon')" class="text-[10px] font-bold bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 flex items-center gap-1.5 text-slate-600 hover:bg-slate-100 transition">
                                <i class="fa-solid fa-calendar text-slate-400"></i>
                                <span id="lblWonFilter">This Year</span>
                                <i class="fa-solid fa-chevron-down text-[8px] ml-0.5"></i>
                            </button>
                            <div id="dropdownWon" class="filter-dropdown top-[30px]">
                                <button onclick="updateFilter('won_deals', 'week', 'This Week')" class="filter-option">This Week</button>
                                <button onclick="updateFilter('won_deals', 'month', 'This Month')" class="filter-option">This Month</button>
                                <button onclick="updateFilter('won_deals', 'year', 'This Year')" class="filter-option">This Year</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-end mb-6">
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase font-bold tracking-widest mb-1">Total Value</p>
                            <h2 class="text-2xl font-black text-[#117B6F]" id="wonTotalValue">₹<?= number_format($won_deals) ?></h2>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 uppercase font-bold tracking-widest mb-1">Conversion</p>
                            <h2 class="text-xl font-black text-slate-800" id="wonConvRate"><?= $conversion_rate ?>%</h2>
                        </div>
                    </div>

                    <div class="space-y-5 flex-grow mt-2" id="wonSourcesContainer">
                        <div>
                            <div class="flex justify-between text-xs font-bold mb-2">
                                <span class="text-slate-600 flex items-center gap-2"><i class="fa-solid fa-phone text-blue-500 w-4"></i> Calls</span>
                                <span class="text-slate-800" id="wonPctCalls"><?= $won_sources_pct['Calls'] ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div id="wonBarCalls" class="bg-blue-500 h-full rounded-full transition-all duration-700 ease-out" style="width: <?= $won_sources_pct['Calls'] ?>%;"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs font-bold mb-2">
                                <span class="text-slate-600 flex items-center gap-2"><i class="fa-solid fa-envelope text-amber-500 w-4"></i> Email</span>
                                <span class="text-slate-800" id="wonPctEmail"><?= $won_sources_pct['Email'] ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div id="wonBarEmail" class="bg-amber-500 h-full rounded-full transition-all duration-700 ease-out" style="width: <?= $won_sources_pct['Email'] ?>%;"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs font-bold mb-2">
                                <span class="text-slate-600 flex items-center gap-2"><i class="fa-solid fa-comments text-emerald-500 w-4"></i> Chats</span>
                                <span class="text-slate-800" id="wonPctChat"><?= $won_sources_pct['Chat'] ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div id="wonBarChat" class="bg-emerald-500 h-full rounded-full transition-all duration-700 ease-out" style="width: <?= $won_sources_pct['Chat'] ?>%;"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between text-xs font-bold mb-2">
                                <span class="text-slate-600 flex items-center gap-2"><i class="fa-solid fa-users text-purple-500 w-4"></i> Referrals</span>
                                <span class="text-slate-800" id="wonPctRef"><?= $won_sources_pct['Referrals'] ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div id="wonBarRef" class="bg-purple-500 h-full rounded-full transition-all duration-700 ease-out" style="width: <?= $won_sources_pct['Referrals'] ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-6 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm relative">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-extrabold text-slate-800">Recent Follow Up</h3>
                        <a href="client_management.php" class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition inline-block text-center">View All</a>
                    </div>
                    <div class="space-y-4">
                        <?php if (!empty($quick_contacts)): ?>
                            <?php $colors = ['blue', 'pink', 'orange', 'slate']; ?>
                            <?php foreach($quick_contacts as $index => $contact): ?>
                            <?php 
                                $color = $colors[$index % 4]; 
                                // Safely encode data for JavaScript
                                $clientData = htmlspecialchars(json_encode([
                                    'name' => $contact['name'] ?? 'Unknown',
                                    'email' => $contact['email'] ?? 'No email',
                                    'phone' => $contact['phone'] ?? 'No phone',
                                    'source' => $contact['source'] ?? 'Unknown Source',
                                    'value' => number_format($contact['deal_value'] ?? 0, 2)
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="flex items-center justify-between p-2 -mx-2 hover:bg-slate-50 rounded-2xl transition group border border-transparent hover:border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-<?= $color ?>-100 border-2 border-white shadow-sm overflow-hidden flex items-center justify-center font-bold text-<?= $color ?>-600">
                                        <?= strtoupper(substr($contact['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-extrabold text-slate-800"><?= htmlspecialchars($contact['name']) ?></p>
                                        <p class="text-[11px] font-bold text-slate-400"><?= htmlspecialchars($contact['email'] ?? 'No Email') ?></p>
                                    </div>
                                </div>
                                <button onclick="openClientModal(<?= $clientData ?>)" class="w-8 h-8 flex items-center justify-center bg-slate-50 rounded-xl text-slate-400 hover:bg-[#117B6F] hover:text-white transition shadow-sm cursor-pointer">
                                    <i class="fa-solid fa-envelope text-[11px]"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-xs text-slate-400 italic">No clients found for follow up.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-8 bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-white">
                        <h3 class="font-extrabold text-slate-800">Recent Deals</h3>
                        <a href="client_management.php" class="bg-slate-50 text-[11px] font-extrabold px-4 py-1.5 rounded-xl hover:bg-slate-100 transition text-slate-500 inline-block text-center">View All</a>
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
                        <a href="sales_assigntask.php" class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition inline-block text-center">View All</a>
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

       
    </div>

    <div id="clientModal" class="fixed inset-0 z-[200] hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 transform scale-95 transition-transform duration-300 overflow-hidden" id="clientModalContent">
            <div class="bg-[#117B6F] p-5 flex justify-between items-center text-white">
                <h3 class="font-bold flex items-center gap-2"><i class="fa-solid fa-address-card"></i> Client Details</h3>
                <button onclick="closeClientModal()" class="text-white/70 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-4 border-b border-slate-100 pb-4">
                    <div class="w-14 h-14 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-xl font-black shadow-sm" id="modalInitial">
                        C
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-800" id="modalName">Client Name</h2>
                        <span class="text-[10px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded uppercase" id="modalSource">Source</span>
                    </div>
                </div>
                
                <div class="space-y-3 pt-2">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email Address</p>
                            <p class="text-sm font-bold text-slate-700" id="modalEmail">email@example.com</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center"><i class="fa-solid fa-phone"></i></div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Phone Number</p>
                            <p class="text-sm font-bold text-slate-700" id="modalPhone">+91 00000 00000</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div>
                            <p class="text-[10px] font-bold text-emerald-600/70 uppercase tracking-wider">Deal Value</p>
                            <p class="text-sm font-black text-emerald-600" id="modalValue">₹0.00</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                <a href="mailto:" id="modalMailBtn" class="bg-[#117B6F] hover:bg-[#0f665c] text-white text-xs font-bold px-5 py-2 rounded-lg shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Send Email
                </a>
            </div>
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
        // Initial Pipeline Data
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

        // --- FILTER & AJAX LOGIC ---
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            // close others
            document.querySelectorAll('.filter-dropdown').forEach(el => {
                if (el.id !== id) el.classList.remove('show');
            });
            dropdown.classList.toggle('show');
        }

        async function updateFilter(type, timeframe, label) {
            // Update Label UI
            if (type === 'pipeline') {
                document.getElementById('lblPipelineFilter').innerText = label;
                document.getElementById('dropdownPipeline').classList.remove('show');
            } else {
                document.getElementById('lblWonFilter').innerText = label;
                document.getElementById('dropdownWon').classList.remove('show');
            }

            try {
                // Fetch new data via AJAX
                const response = await fetch(`?ajax_filter=1&filter_type=${type}&timeframe=${timeframe}`);
                const data = await response.json();

                if (type === 'pipeline') {
                    // Update Pipeline Bars Text
                    document.getElementById('pipe-marketing').innerText = `Marketing : ${data.stages.Marketing}`;
                    document.getElementById('pipe-sales').innerText = `Sales : ${data.stages.Sales}`;
                    document.getElementById('pipe-email').innerText = `Email : ${data.stages.Email}`;
                    document.getElementById('pipe-chat').innerText = `Chat : ${data.stages.Chat}`;
                    document.getElementById('pipe-operational').innerText = `Operational : ${data.stages.Operational}`;
                    document.getElementById('pipe-calls').innerText = `Calls : ${data.stages.Calls}`;
                    
                    // Update Value Text
                    const fmt = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 2 });
                    document.getElementById('val-marketing').innerText = `₹${fmt.format(data.values.Marketing)}`;
                    document.getElementById('val-sales').innerText = `₹${fmt.format(data.values.Sales)}`;
                    document.getElementById('val-email').innerText = `₹${fmt.format(data.values.Email)}`;
                    document.getElementById('val-chat').innerText = `₹${fmt.format(data.values.Chat)}`;

                    // Retrigger animation
                    const bars = document.getElementById('pipelineBars');
                    bars.style.opacity = 0;
                    setTimeout(() => { bars.style.opacity = 1; }, 200);

                } else if (type === 'won_deals') {
                    // Update Top Stats
                    const fmt = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });
                    document.getElementById('wonTotalValue').innerText = `₹${fmt.format(data.total_value)}`;
                    document.getElementById('wonConvRate').innerText = `${data.conversion}%`;

                    // Update Bars and Percentages
                    document.getElementById('wonPctCalls').innerText = `${data.sources.Calls}%`;
                    document.getElementById('wonBarCalls').style.width = `${data.sources.Calls}%`;

                    document.getElementById('wonPctEmail').innerText = `${data.sources.Email}%`;
                    document.getElementById('wonBarEmail').style.width = `${data.sources.Email}%`;

                    document.getElementById('wonPctChat').innerText = `${data.sources.Chat}%`;
                    document.getElementById('wonBarChat').style.width = `${data.sources.Chat}%`;

                    document.getElementById('wonPctRef').innerText = `${data.sources.Referrals}%`;
                    document.getElementById('wonBarRef').style.width = `${data.sources.Referrals}%`;
                }

            } catch (error) {
                console.error("Error updating filter:", error);
            }
        }


        // --- Pipeline Context Menu Functions ---
        function showContextMenu(e, stage) {
            e.preventDefault();
            e.stopPropagation();

            const menu = document.getElementById('contextMenu');
            const title = document.getElementById('contextTitle');
            const body = document.getElementById('contextBody');
            const count = document.getElementById('contextCount');

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

            menu.classList.remove('hidden');

            let x = e.clientX;
            let y = e.clientY;
            const menuWidth = menu.offsetWidth;
            const menuHeight = menu.offsetHeight;
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;

            if (x + menuWidth > windowWidth) { x = windowWidth - menuWidth - 10; }
            if (y + menuHeight > windowHeight) { y = windowHeight - menuHeight - 10; }

            menu.style.left = `${x}px`;
            menu.style.top = `${y}px`;

            setTimeout(() => {
                menu.classList.remove('scale-95', 'opacity-0');
                menu.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        // --- Client Modal Functions ---
        function openClientModal(client) {
            const modal = document.getElementById('clientModal');
            const content = document.getElementById('clientModalContent');
            
            document.getElementById('modalName').innerText = client.name;
            document.getElementById('modalInitial').innerText = client.name.charAt(0).toUpperCase();
            document.getElementById('modalSource').innerText = client.source;
            document.getElementById('modalEmail').innerText = client.email;
            document.getElementById('modalPhone').innerText = client.phone !== 'No phone' ? client.phone : 'Not Provided';
            document.getElementById('modalValue').innerText = '₹' + client.value;
            
            document.getElementById('modalMailBtn').href = `mailto:${client.email}?subject=Follow up regarding your deal`;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeClientModal() {
            const modal = document.getElementById('clientModal');
            const content = document.getElementById('clientModalContent');
            
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        // --- Global Click Listeners ---
        document.addEventListener('click', function(e) {
            // Close Context Menu
            const contextMenu = document.getElementById('contextMenu');
            if (contextMenu && !contextMenu.classList.contains('hidden') && !contextMenu.contains(e.target)) {
                contextMenu.classList.remove('scale-100', 'opacity-100');
                contextMenu.classList.add('scale-95', 'opacity-0');
                setTimeout(() => { contextMenu.classList.add('hidden'); }, 200);
            }
            
            // Close Dropdowns
            if (!e.target.closest('#btnPipelineFilter') && !e.target.closest('#dropdownPipeline')) {
                document.getElementById('dropdownPipeline')?.classList.remove('show');
            }
            if (!e.target.closest('#btnWonFilter') && !e.target.closest('#dropdownWon')) {
                document.getElementById('dropdownWon')?.classList.remove('show');
            }

            // Close Modal 
            const modal = document.getElementById('clientModal');
            const content = document.getElementById('clientModalContent');
            if (modal && !modal.classList.contains('hidden') && !content.contains(e.target) && !e.target.closest('button[onclick^="openClientModal"]')) {
                closeClientModal();
            }
        });
    </script>
</body>
</html>