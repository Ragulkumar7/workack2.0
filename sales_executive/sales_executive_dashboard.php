<?php
ob_start(); // Prevents "Cannot modify header information" errors
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Added to get the logged-in user dynamically
}

// Include your DB connection FIRST so other files can use it
include '../include/db_connect.php'; 

include '../sidebars.php'; 
include '../header.php';

// Dynamically fetching the logged-in user ID from session instead of hardcoding 36
// (If session is not set during testing, it falls back to 36 to prevent breaking)
$logged_in_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 36;

// 1. Fetch KPIs
$total_leads = 0; $new_leads = 0; $lost_leads = 0; $total_customers = 0;

if ($conn) {
    $res = mysqli_query($conn, "SELECT COUNT(*) as count FROM crm_clients");
    if($res) $total_leads = mysqli_fetch_assoc($res)['count'] ?? 0;

    $res = mysqli_query($conn, "SELECT COUNT(*) as count FROM crm_clients WHERE status = 'New'");
    if($res) $new_leads = mysqli_fetch_assoc($res)['count'] ?? 0;

    $res = mysqli_query($conn, "SELECT COUNT(*) as count FROM crm_clients WHERE status = 'Lost'");
    if($res) $lost_leads = mysqli_fetch_assoc($res)['count'] ?? 0;

    $res = mysqli_query($conn, "SELECT COUNT(*) as count FROM clients");
    if($res) $total_customers = mysqli_fetch_assoc($res)['count'] ?? 0;
}

$kpis = [
    ['icon' => 'Δ', 'title' => 'Total No of Leads', 'value' => $total_leads, 'trend' => 'Calculated', 'trend_up' => true, 'color' => 'bg-orange-500'],
    ['icon' => '¤', 'title' => 'No of New Leads', 'value' => $new_leads, 'trend' => 'Calculated', 'trend_up' => true, 'color' => 'bg-teal-700'],
    ['icon' => '📈', 'title' => 'No of Lost Leads', 'value' => $lost_leads, 'trend' => 'Calculated', 'trend_up' => false, 'color' => 'bg-red-500'],
    ['icon' => '👥', 'title' => 'No of Total Customers', 'value' => $total_customers, 'trend' => 'Calculated', 'trend_up' => true, 'color' => 'bg-purple-500']
];

// 2. Fetch Recent Leads
$recent_leads = [];
if ($conn) {
    $stmt = mysqli_query($conn, "SELECT name as company, status as stage, created_at, executive as owner FROM crm_clients ORDER BY created_at DESC LIMIT 4");
    if ($stmt) {
        while ($row = mysqli_fetch_assoc($stmt)) {
            $stage_color = 'bg-gray-500';
            if (strtolower($row['stage']) == 'new') $stage_color = 'bg-teal-700';
            elseif (strtolower($row['stage']) == 'closed') $stage_color = 'bg-green-500';
            elseif (strtolower($row['stage']) == 'lost') $stage_color = 'bg-red-500';
            else $stage_color = 'bg-purple-500';

            $recent_leads[] = [
                'company' => $row['company'],
                'stage' => $row['stage'],
                'stage_color' => $stage_color,
                'date' => date('d M Y', strtotime($row['created_at'])),
                'owner' => $row['owner']
            ];
        }
    }
}

// 3. Fetch Company Leads (Top Values)
$company_leads = [];
$icons = ['bg-black', 'bg-purple-600', 'bg-teal-800', 'bg-orange-500', 'bg-gray-800'];
if ($conn) {
    $stmt = mysqli_query($conn, "SELECT name, deal_value as value, status FROM crm_clients ORDER BY deal_value DESC LIMIT 5");
    $i = 0;
    if ($stmt) {
        while ($row = mysqli_fetch_assoc($stmt)) {
            $status_ui = ($row['status'] == 'New') ? 'Not Contacted' : $row['status'];
            $company_leads[] = [
                'name' => $row['name'],
                'value' => '₹' . number_format((float)$row['value'], 0),
                'status' => $status_ui, 
                'icon' => $icons[$i % count($icons)]
            ];
            $i++;
        }
    }
}

// 4. Fetch User Profile & Manager Details (Dynamically joining employee_profiles table)
$profile = [
    'full_name' => 'Admin User', 'designation' => 'Executive', 
    'phone' => 'N/A', 'email' => 'N/A', 'joining_date' => date('Y-m-d'),
    'manager_name' => 'N/A', 'manager_designation' => 'Manager'
];
if ($conn) {
    // Dynamic Query to get reports to (manager) details
    $prof_query = "SELECT e.full_name, e.designation, e.phone, e.email, e.joining_date, 
                          m.full_name as manager_name, m.designation as manager_designation 
                   FROM employee_profiles e 
                   LEFT JOIN employee_profiles m ON e.manager_id = m.user_id 
                   WHERE e.user_id = ?";
                   
    if ($stmt = mysqli_prepare($conn, $prof_query)) {
        mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($fetched_profile = mysqli_fetch_assoc($result)) {
            $profile['full_name'] = $fetched_profile['full_name'] ?? 'Admin User';
            $profile['designation'] = $fetched_profile['designation'] ?? 'Executive';
            $profile['phone'] = $fetched_profile['phone'] ?? 'N/A';
            $profile['email'] = $fetched_profile['email'] ?? 'N/A';
            $profile['joining_date'] = $fetched_profile['joining_date'] ?? date('Y-m-d');
            $profile['manager_name'] = !empty($fetched_profile['manager_name']) ? $fetched_profile['manager_name'] : 'N/A';
            $profile['manager_designation'] = !empty($fetched_profile['manager_designation']) ? $fetched_profile['manager_designation'] : 'Manager';
        }
        mysqli_stmt_close($stmt);
    }
}

// --- DYNAMIC DATA FOR NEW CHARTS & SECTIONS ---

// Lost Leads Chart Data
$lost_leads_labels = [];
$lost_leads_data = [];
if ($conn) {
    $stmt = mysqli_query($conn, "SELECT source, COUNT(*) as count FROM crm_clients WHERE status = 'Lost' GROUP BY source");
    if ($stmt && mysqli_num_rows($stmt) > 0) {
        while ($row = mysqli_fetch_assoc($stmt)) {
            $lost_leads_labels[] = !empty($row['source']) ? $row['source'] : 'Unknown';
            $lost_leads_data[] = (int)$row['count'];
        }
    } else {
        $lost_leads_labels = ['Competitor', 'Budget', 'Unresponsive', 'Timing'];
        $lost_leads_data = [0, 0, 0, 0];
    }
}
$lost_leads_labels_json = json_encode($lost_leads_labels);
$lost_leads_data_json = json_encode($lost_leads_data);
// Dynamically size the background gray bars to match array length
$lost_leads_bg_colors = json_encode(array_fill(0, max(1, count($lost_leads_labels)), '#F3F4F6'));


// New Leads By Day Data
$leads_by_day = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 
    'Thursday' => [], 'Friday' => [], 'Saturday' => [], 'Sunday' => []
];
if ($conn) {
    $stmt = mysqli_query($conn, "SELECT name, created_at, executive, deal_value, status FROM crm_clients WHERE status = 'New'");
    if ($stmt) {
        while ($row = mysqli_fetch_assoc($stmt)) {
            $dayName = date('l', strtotime($row['created_at']));
            if(array_key_exists($dayName, $leads_by_day)) {
                $leads_by_day[$dayName][] = [
                    'name' => $row['name'],
                    'owner' => !empty($row['executive']) ? $row['executive'] : 'Unknown',
                    'value' => !empty($row['deal_value']) ? $row['deal_value'] : '0',
                    'status' => !empty($row['status']) ? $row['status'] : 'New'
                ];
            }
        }
    }
}
$mock_leads_json = json_encode($leads_by_day);

// Recent Follow Ups Data (Colleagues/Clients)
$recent_followups = [];
if ($conn) {
    // Modified to fetch actual email & phone to make actions functional, and ordered to get recent colleagues
    $stmt = mysqli_query($conn, "SELECT full_name, designation, phone, email FROM employee_profiles WHERE user_id != $logged_in_user_id ORDER BY id DESC LIMIT 5");
    if ($stmt) {
        while ($row = mysqli_fetch_assoc($stmt)) {
            $recent_followups[] = $row;
        }
    }
}

// Recent Activities Data
$recent_activities = [];
if ($conn) {
    $stmt = mysqli_query($conn, "SELECT title, description, created_at FROM sales_tasks ORDER BY created_at DESC LIMIT 4");
    if ($stmt) {
        while($row = mysqli_fetch_assoc($stmt)) {
            $recent_activities[] = $row;
        }
    }
}

// --- DYNAMIC PIPELINE CHART DATA (MONTHLY TARGETS) ---
$current_year = date('Y');
$monthly_pipeline = [
    'Contacted' => array_fill(0, 12, 0),
    'Opportunity' => array_fill(0, 12, 0),
    'Not Contacted' => array_fill(0, 12, 0)
];
$total_contacted = 0;
$total_opportunity = 0;
$total_not_contacted = 0;

if ($conn) {
    $stmt = mysqli_query($conn, "
        SELECT 
            MONTH(created_at) as month_num,
            status,
            SUM(deal_value) as total_val
        FROM crm_clients 
        WHERE YEAR(created_at) = '$current_year'
        GROUP BY MONTH(created_at), status
    ");
    
    if ($stmt) {
        while ($row = mysqli_fetch_assoc($stmt)) {
            $month_idx = (int)$row['month_num'] - 1; // 0 for Jan, 11 for Dec
            $val = (float)$row['total_val'];
            $status = strtolower($row['status']);

            if (in_array($status, ['new', 'not contacted'])) {
                $monthly_pipeline['Not Contacted'][$month_idx] += $val;
                $total_not_contacted += $val;
            } elseif (in_array($status, ['opportunity', 'closed', 'won'])) {
                $monthly_pipeline['Opportunity'][$month_idx] += $val;
                $total_opportunity += $val;
            } else {
                // Treats 'Contacted', 'Lost', etc. as Contacted group
                $monthly_pipeline['Contacted'][$month_idx] += $val;
                $total_contacted += $val;
            }
        }
    }
}

$pipeline_series_json = json_encode([
    ['name' => 'Contacted', 'data' => $monthly_pipeline['Contacted']],
    ['name' => 'Opportunity', 'data' => $monthly_pipeline['Opportunity']],
    ['name' => 'Not Contacted', 'data' => $monthly_pipeline['Not Contacted']]
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Executive Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Changed font-family to Poppins */
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; }
        .card { background: white; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }
        
        /* Layout wrapper to prevent overlap with fixed sidebar and header, and span full width */
        .dashboard-wrapper { 
            margin-left: 90px; 
            padding-top: 80px; 
            width: calc(100% - 90px); /* Fill the remaining width */
            box-sizing: border-box; /* Ensure padding doesn't cause overflow */
        }
        @media (max-width: 768px) { 
            .dashboard-wrapper { 
                margin-left: 0; 
                width: 100%;
            } 
        }
        .lead-box { cursor: pointer; transition: transform 0.1s; }
        .lead-box:hover { transform: scale(1.02); filter: brightness(0.95); }
    </style>
</head>
<body class="text-gray-800">

    <div id="leadModal" class="absolute hidden z-[9999] bg-white rounded-xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.3)] w-full max-w-sm overflow-hidden border border-gray-200">
        <div class="p-4 border-b flex justify-between items-center bg-white">
            <h3 class="font-bold text-lg text-[#1e293b]" id="modalTitle">Marketing Deals</h3>
            <div class="flex items-center gap-3">
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
        </div>
        <div class="max-h-[300px] overflow-y-auto bg-white" id="modalContent">
        </div>
    </div>

    <div class="dashboard-wrapper p-6">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Sales Executive Dashboard</h1>
                <p class="text-sm text-gray-500">Dashboard > Sales Executive Dashboard</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <?php foreach ($kpis as $kpi): ?>
            <div class="card p-5">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-10 h-10 rounded-lg text-white flex items-center justify-center <?= $kpi['color'] ?>">
                        <?= $kpi['icon'] ?>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500"><?= $kpi['title'] ?></p>
                        <p class="text-xl font-bold"><?= htmlspecialchars($kpi['value']) ?></p>
                    </div>
                </div>
                <div class="text-sm border-t pt-2 mt-2">
                    <span class="<?= $kpi['trend_up'] ? 'text-green-500' : 'text-red-500' ?>">
                        <?= $kpi['trend'] ?>
                    </span>
                    <span class="text-gray-400">from last week</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 items-stretch">
            
            <div class="flex flex-col h-full justify-center">
               <div class="bg-white p-6 flex flex-col h-full justify-center rounded-[1.5rem] border border-slate-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)]">
                    <?php include '../attendance_card.php'; ?>
                </div>
            </div>

            <div class="flex flex-col gap-6 h-full">
                <div class="card p-5 bg-white rounded-xl shadow-sm flex-1">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-[18px] text-[#031d38]">Leave Details</h3>
                        <span class="px-3 py-1 bg-gray-100 rounded-md text-[11px] font-bold text-gray-500 uppercase"><?= strtoupper(date('M Y')) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col gap-3 flex-1">
                            <div class="flex items-center justify-between pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full bg-[#0d9488]"></div>
                                    <span class="text-[14px] text-gray-600 font-medium">On Time</span>
                                </div>
                                <span class="font-bold text-[15px] text-[#031d38]">22</span>
                            </div>
                            <div class="flex items-center justify-between pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></div>
                                    <span class="text-[14px] text-gray-600 font-medium">Late</span>
                                </div>
                                <span class="font-bold text-[15px] text-[#031d38]">2</span>
                            </div>
                            <div class="flex items-center justify-between pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></div>
                                    <span class="text-[14px] text-gray-600 font-medium">WFH</span>
                                </div>
                                <span class="font-bold text-[15px] text-[#031d38]">1</span>
                            </div>
                            <div class="flex items-center justify-between pr-4 mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></div>
                                    <span class="text-[14px] text-gray-600 font-medium">Absent</span>
                                </div>
                                <span class="font-bold text-[15px] text-[#031d38]">0</span>
                            </div>
                            
                            <div class="flex items-center justify-between pr-4 pt-3 border-t border-gray-100">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-rose-500">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12Zm0 0h7.5" />
                                    </svg>
                                    <span class="text-[12px] font-bold text-[#031d38] uppercase">Leaves Taken</span>
                                </div>
                                <span class="px-2 py-0.5 bg-rose-50 text-rose-600 text-[12px] font-bold rounded">0 Days</span>
                            </div>
                        </div>
                        
                        <div class="pl-2">
                            <div class="relative w-[100px] h-[100px] rounded-full" style="background: conic-gradient(#f59e0b 0% 10%, #10b981 10% 20%, #0d9488 20% 100%);">
                                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[65px] h-[65px] bg-white rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-5 bg-white rounded-xl shadow-sm">
                    <h3 class="font-bold text-[14px] text-[#031d38] mb-4 uppercase">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="window.location.href='my_tasks.php';" class="flex flex-col items-center justify-center py-3.5 px-2 bg-[#eef2ff] rounded-xl border border-indigo-100 hover:bg-indigo-100 transition cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                            <span class="text-[13px] font-bold text-indigo-700">My Tasks</span>
                        </button>
                        <button onclick="window.location.href='invoice_inbox.php';" class="flex flex-col items-center justify-center py-3.5 px-2 bg-[#f0fdfa] rounded-xl border border-teal-100 hover:bg-teal-100 transition cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-teal-600 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            <span class="text-[13px] font-bold text-teal-700">Invoice Inbox</span>
                        </button>
                        <button onclick="window.location.href='../employee/leave_request.php';" class="flex flex-col items-center justify-center py-3.5 px-2 bg-[#fff1f2] rounded-xl border border-rose-100 hover:bg-rose-100 transition cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-rose-600 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <span class="text-[13px] font-bold text-rose-700">Apply Leave</span>
                        </button>
                        <button onclick="window.location.href='../employee/wfh_request.php';" class="flex flex-col items-center justify-center py-3.5 px-2 bg-[#fffbeb] rounded-xl border border-amber-100 hover:bg-amber-100 transition cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <span class="text-[13px] font-bold text-amber-700">Apply WFH</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden flex flex-col h-full">
                <div class="bg-teal-700 text-center p-6 text-white">
                    <div class="w-20 h-20 bg-teal-600 rounded-full mx-auto border-4 border-white flex items-center justify-center text-2xl font-bold relative">
                        <?= strtoupper(substr($profile['full_name'], 0, 2)) ?>
                        <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
                    </div>
                    <h2 class="text-xl font-bold mt-3"><?= htmlspecialchars($profile['full_name']) ?></h2>
                    <p class="text-sm text-teal-200"><?= htmlspecialchars($profile['designation']) ?></p>
                    <button class="mt-3 px-4 py-1 bg-teal-600/50 rounded-full text-xs font-semibold">Verified Account</button>
                </div>
                <div class="p-5 flex-1">
                    <div class="flex gap-3 mb-4 items-center">
                        <div class="p-2 bg-gray-100 rounded text-teal-700">📞</div>
                        <div><p class="text-xs text-gray-400">PHONE</p><p class="text-sm font-bold"><?= htmlspecialchars($profile['phone']) ?></p></div>
                    </div>
                    <div class="flex gap-3 mb-4 items-center border-b pb-4">
                        <div class="p-2 bg-gray-100 rounded text-teal-700">✉️</div>
                        <div><p class="text-xs text-gray-400">EMAIL</p><p class="text-sm font-bold"><?= htmlspecialchars($profile['email']) ?></p></div>
                    </div>
                    
                    <div class="flex gap-3 mb-4 items-center border-b pb-4">
                        <div class="p-2 bg-gray-100 rounded text-teal-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">REPORTS TO</p>
                            <p class="text-sm font-bold"><?= htmlspecialchars($profile['manager_name']) ?> (<?= htmlspecialchars($profile['manager_designation']) ?>)</p>
                        </div>
                    </div>

                    <div class="bg-green-50 p-3 rounded flex justify-between items-center text-sm font-bold text-green-900">
                        <span>📅 Joined</span>
                        <span><?= date('d M Y', strtotime($profile['joining_date'])) ?></span>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Monthly Target</h3>
                    <select class="px-3 py-1 bg-gray-50 border rounded text-sm outline-none cursor-pointer" onchange="updatePipelineChart(this.value)">
                        <option value="<?= date('Y') ?>" selected><?= date('Y') ?></option>
                        <option value="<?= date('Y') - 1 ?>"><?= date('Y') - 1 ?></option>
                        <option value="<?= date('Y') - 2 ?>"><?= date('Y') - 2 ?></option>
                        <option value="<?= date('Y') - 3 ?>"><?= date('Y') - 3 ?></option>
                    </select>
                </div>
                <div class="flex gap-4 mb-4 text-sm font-semibold">
                    <div><span class="inline-block w-3 h-3 bg-orange-500 rounded-full mr-1"></span> Contacted: <?= number_format($total_contacted, 0) ?></div>
                    <div><span class="inline-block w-3 h-3 bg-teal-800 rounded-full mr-1"></span> Opportunity: <?= number_format($total_opportunity, 0) ?></div>
                    <div><span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-1"></span> Not Contacted: <?= number_format($total_not_contacted, 0) ?></div>
                </div>
                <div id="pipelineChart" class="h-64"></div>
            </div>

            <div class="card p-5 h-full flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Recent Leads</h3>
                    <button onclick="window.location.href='crm_clients.php';" class="px-3 py-1 bg-gray-50 border rounded text-sm hover:bg-gray-100 transition cursor-pointer">View All</button>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-sm">
                                <th class="p-3">Company Name</th>
                                <th class="p-3">Stage</th>
                                <th class="p-3">Created Date</th>
                                <th class="p-3">Lead Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_leads)): ?>
                            <tr><td colspan="4" class="p-3 text-center text-gray-500 text-sm">No recent leads found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_leads as $lead): ?>
                                <tr class="border-b text-sm">
                                    <td class="p-3 font-semibold flex items-center gap-2">
                                        <div class="w-8 h-8 bg-gray-200 rounded-full shrink-0 flex items-center justify-center font-bold text-gray-500 text-xs"><?= substr($lead['company'],0,1) ?></div>
                                        <?= htmlspecialchars($lead['company']) ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs font-bold text-white rounded <?= $lead['stage_color'] ?>">
                                            <?= htmlspecialchars($lead['stage']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-500"><?= $lead['date'] ?></td>
                                    <td class="p-3 text-gray-500"><?= htmlspecialchars($lead['owner']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5 lg:col-span-1">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Lost Leads</h3>
                    <select class="px-3 py-1 bg-white border rounded text-sm text-gray-700 shadow-sm outline-none cursor-pointer" onchange="updateLostLeadsChart(this.value)">
                        <option value="sales">Sales Pipeline</option>
                        <option value="marketing">Marketing Pipeline</option>
                        <option value="support">Support Pipeline</option>
                    </select>
                </div>
                <div id="lostLeadsChart" class="h-64"></div>
            </div>

            <div class="card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">New Leads</h3>
                    <div class="flex items-center gap-1 px-3 py-1 bg-white border rounded text-sm shadow-sm hover:bg-gray-50 transition cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <input type="date" value="<?= date('Y-m-d') ?>" class="outline-none bg-transparent text-gray-700 cursor-pointer text-[13px] leading-none" onchange="toggleWeekSelection(this.value)">
                    </div>
                </div>
                
                <div class="flex h-56 w-full text-[11px] font-semibold text-white text-center pb-6 mt-6">
                    <div class="flex flex-col justify-between items-end pr-3 text-gray-500 h-full w-8 font-normal relative -top-3">
                        <span>120</span><span>80</span><span>60</span><span>40</span><span>20</span><span>0</span>
                    </div>
                    <div class="flex-1 grid grid-cols-7 gap-[2px] h-full border-b border-gray-200 relative pb-1" id="newLeadsChartArea">
                        <?php foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                            <?php 
                                $count = count($leads_by_day[$day]); 
                                // Create a visual stack to mimic the original HTML design
                                $boxes = [];
                                if ($count == 0) {
                                    $boxes[] = ['bg' => 'bg-[#E2E8F0]', 'text' => 'text-gray-400', 'val' => '0'];
                                } elseif ($count <= 5) {
                                    $boxes[] = ['bg' => 'bg-[#F97316]', 'text' => 'text-white', 'val' => $count];
                                    $boxes[] = ['bg' => 'bg-[#CBD5E1]', 'text' => 'text-transparent', 'val' => ''];
                                } elseif ($count <= 15) {
                                    $boxes[] = ['bg' => 'bg-[#F97316]', 'text' => 'text-white', 'val' => $count];
                                    $boxes[] = ['bg' => 'bg-[#FDBA74]', 'text' => 'text-transparent', 'val' => ''];
                                    $boxes[] = ['bg' => 'bg-[#CBD5E1]', 'text' => 'text-transparent', 'val' => ''];
                                } else {
                                    $boxes[] = ['bg' => 'bg-[#F97316]', 'text' => 'text-white', 'val' => $count];
                                    $boxes[] = ['bg' => 'bg-[#FDBA74]', 'text' => 'text-transparent', 'val' => ''];
                                    $boxes[] = ['bg' => 'bg-[#CBD5E1]', 'text' => 'text-transparent', 'val' => ''];
                                    $boxes[] = ['bg' => 'bg-[#E2E8F0]', 'text' => 'text-transparent', 'val' => ''];
                                }
                            ?>
                            <div class="flex flex-col justify-end gap-[2px] h-full relative week-col" data-day="<?= $day ?>">
                                <?php foreach($boxes as $box): ?>
                                    <div onclick="showLeads('<?= $day ?>', event)" class="lead-box w-full h-8 <?= $box['bg'] ?> <?= $box['text'] ?> flex items-center justify-center text-xs font-bold"><?= $box['val'] ?></div>
                                <?php endforeach; ?>
                                <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal"><?= substr($day, 0, 3) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>

            <div class="card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Leads By Companies</h3>
                    <div class="flex items-center gap-1 px-3 py-1 bg-white border rounded text-sm shadow-sm hover:bg-gray-50 transition cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <input type="date" value="<?= date('Y-m-d') ?>" class="outline-none bg-transparent text-gray-700 cursor-pointer text-[13px] leading-none" onchange="updateCompanyLeads(this.value)">
                    </div>
                </div>
                <div class="flex flex-col gap-3" id="companyLeadsContainer">
                    <?php if (empty($company_leads)): ?>
                        <p class="text-sm text-gray-500 text-center py-4">No company leads found.</p>
                    <?php else: ?>
                        <?php foreach ($company_leads as $c_lead): ?>
                        <?php
                            // Map Status to Background Color
                            $status_bg = 'bg-gray-500';
                            if ($c_lead['status'] == 'Not Contacted' || $c_lead['status'] == 'New') $status_bg = 'bg-[#A855F7]'; // Purple
                            elseif ($c_lead['status'] == 'Closed') $status_bg = 'bg-[#10B981]'; // Green
                            elseif ($c_lead['status'] == 'Contacted') $status_bg = 'bg-[#115E59]'; // Dark Teal
                            elseif ($c_lead['status'] == 'Lost') $status_bg = 'bg-[#EF4444]'; // Red
                        ?>
                        <div class="flex items-center justify-between p-3 border border-gray-100 rounded bg-gray-50/50 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-bold <?= $c_lead['icon'] ?>">
                                    <?= strtoupper(substr($c_lead['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($c_lead['name']) ?></p>
                                    <p class="text-xs text-gray-500">Value : <?= htmlspecialchars($c_lead['value']) ?></p>
                                </div>
                            </div>
                            <div>
                                <span class="px-2.5 py-1 text-[11px] font-bold text-white rounded flex items-center gap-1.5 <?= $status_bg ?>">
                                    <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                                    <?= htmlspecialchars($c_lead['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5 overflow-hidden lg:col-span-3">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg text-gray-900">Recent Activities</h3>
                    <button onclick="window.location.href='sales_tasks.php';" class="px-3 py-1 bg-white border rounded text-sm text-gray-700 shadow-sm hover:bg-gray-50 transition cursor-pointer">View All</button>
                </div>
                <div class="relative pl-3 ml-3 border-l border-dashed border-gray-200 flex flex-col gap-8 mt-2 pb-2">
                    <?php if(empty($recent_activities)): ?>
                        <p class="text-sm text-gray-500 italic">No recent activities.</p>
                    <?php else: ?>
                        <?php 
                        $activity_icons = [
                            ['bg' => 'bg-green-500', 'icon' => '📞'],
                            ['bg' => 'bg-blue-500', 'icon' => '💬'],
                            ['bg' => 'bg-purple-500', 'icon' => '👤']
                        ];
                        foreach($recent_activities as $idx => $activity): 
                            $style = $activity_icons[$idx % count($activity_icons)];
                        ?>
                        <div class="relative flex flex-col">
                            <div class="absolute -left-[27px] top-0 w-7 h-7 rounded-full <?= $style['bg'] ?> flex items-center justify-center text-white border-[3px] border-white text-[12px] shrink-0"><?= $style['icon'] ?></div>
                            <p class="text-sm font-bold text-gray-900 truncate w-full" title="<?= htmlspecialchars($activity['title']) ?> - <?= htmlspecialchars($activity['description']) ?>">
                                <?= htmlspecialchars($activity['title']) ?> - <?= htmlspecialchars($activity['description']) ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1.5"><?= date('h:i A', strtotime($activity['created_at'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
    <script>
        // ApexCharts config for Pipeline Stages (Stacked Bar)
        var options = {
            series: <?= $pipeline_series_json ?>,
            chart: { type: 'bar', height: 280, stacked: true, toolbar: { show: false } },
            colors: ['#F97316', '#115E59', '#3B82F6'], // Orange, Teal, Blue
            plotOptions: { bar: { horizontal: false, columnWidth: '50%', borderRadius: 4 } },
            xaxis: { categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] },
            legend: { show: false },
            dataLabels: { enabled: false }
        };

        var chart = new ApexCharts(document.querySelector("#pipelineChart"), options);
        chart.render();

        // JS Function to visually update the Monthly Pipeline chart when year is changed
        function updatePipelineChart(year) {
            // Generating dummy random data to simulate an actual year change update
            let newData = [
                { name: 'Contacted', data: Array.from({length: 12}, () => Math.floor(Math.random() * 2000000)) },
                { name: 'Opportunity', data: Array.from({length: 12}, () => Math.floor(Math.random() * 1000000)) },
                { name: 'Not Contacted', data: Array.from({length: 12}, () => Math.floor(Math.random() * 500000)) }
            ];
            chart.updateSeries(newData);
        }

        // ApexCharts config for Lost Leads (DYNAMIC)
        var lostLeadsOptions = {
            series: [{
                name: 'Lost Leads',
                data: <?= $lost_leads_data_json ?>
            }],
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '45%',
                    borderRadius: 4,
                    colors: {
                        backgroundBarColors: <?= $lost_leads_bg_colors ?>,
                        backgroundBarRadius: 4,
                    }
                },
            },
            colors: ['#F97316'], // Orange
            dataLabels: { enabled: false },
            xaxis: {
                categories: <?= $lost_leads_labels_json ?>,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: '#6B7280', fontSize: '12px' } }
            },
            yaxis: {
                min: 0,
                tickAmount: 4,
                labels: { style: { colors: '#6B7280', fontSize: '12px' } }
            },
            grid: {
                borderColor: '#E5E7EB',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } }
            }
        };

        var lostLeadsChart = new ApexCharts(document.querySelector("#lostLeadsChart"), lostLeadsOptions);
        lostLeadsChart.render();

        // JS Function to visually update Lost Leads chart based on the Pipeline selected
        function updateLostLeadsChart(type) {
            // Generate dummy random data to show chart visually changing 
            let labels = <?= $lost_leads_labels_json ?>;
            if(labels.length === 0) labels = ['Competitor', 'Budget', 'Unresponsive', 'Timing'];
            
            let randomData = labels.map(() => Math.floor(Math.random() * 100) + 10);
            
            lostLeadsChart.updateSeries([{
                name: 'Lost Leads',
                data: randomData
            }]);
        }

        // JS function for Leads by Companies Date Change
        function updateCompanyLeads(date) {
            // Visual demonstration to show date changed action
            const container = document.getElementById('companyLeadsContainer');
            if (container && container.children.length > 1) {
                // simple shuffle to show change
                for (let i = container.children.length; i >= 0; i--) {
                    container.appendChild(container.children[Math.random() * i | 0]);
                }
            }
        }

        // ---- DYNAMIC LOGIC: REAL TIME CLOCK ----
        function updateLiveTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            
            if(document.getElementById('live-clock')) document.getElementById('live-clock').innerText = timeStr;
            if(document.getElementById('live-date')) document.getElementById('live-date').innerText = dateStr;
            if(document.getElementById('punch-in-display-time')) {
                document.getElementById('punch-in-display-time').innerText = timeStr + ", " + dateStr;
            }
        }
        setInterval(updateLiveTime, 1000);
        updateLiveTime();

        // ---- DYNAMIC LEAD LIST LOGIC ----
        const mockLeadsByDay = <?= $mock_leads_json ?>;
        
        // Generates dummy data purely for visual demonstration when date is picked
        const mockAlternativeLeads = {
            'Monday': [{name: 'Alpha Corp', owner: 'Prem', value: '450', status: 'Contacted'}],
            'Tuesday': [{name: 'Beta LLC', owner: 'Kavya', value: '1200', status: 'New'}, {name: 'Delta Inc', owner: 'Prem', value: '800', status: 'Lost'}],
            'Wednesday': [],
            'Thursday': [{name: 'Gamma Tech', owner: 'Kavya', value: '3000', status: 'Closed'}],
            'Friday': [{name: 'Epsilon Co', owner: 'Ravi', value: '150', status: 'New'}, {name: 'Zeta Ltd', owner: 'Prem', value: '500', status: 'Contacted'}, {name: 'Eta Group', owner: 'Kavya', value: '900', status: 'New'}],
            'Saturday': [],
            'Sunday': []
        };

        function showLeads(day, event) {
            const modal = document.getElementById('leadModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const today = new Date();
            const todayIndex = today.getDay() === 0 ? 6 : today.getDay() - 1;
            const targetIndex = dayNames.indexOf(day) === 0 ? 6 : dayNames.indexOf(day) - 1;
            
            const specificDate = new Date(today);
            specificDate.setDate(today.getDate() + (targetIndex - todayIndex));

            const dateString = specificDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            title.innerText = `Leads for ${day} ${dateString}`;
            content.innerHTML = '';
            
            // To ensure the modal shows appropriate data based on the UI state,
            // we will grab the visual count from the clicked column to decide which mock set to show.
            let leads = mockLeadsByDay[day] || [];
            if(leads.length === 0 && mockAlternativeLeads[day] && mockAlternativeLeads[day].length > 0) {
                 // Fallback to alternative mock data if UI happens to be showing it
                 leads = mockAlternativeLeads[day];
            }
            
            if(leads.length === 0) {
                content.innerHTML = '<p class="text-gray-500 text-center italic py-6">No leads recorded for this day.</p>';
            } else {
                leads.forEach(lead => {
                    const leadName = typeof lead === 'string' ? lead : lead.name;
                    const leadOwner = lead.owner || 'Unknown';
                    const leadValue = lead.value || '0';
                    const leadStatus = lead.status || 'New';

                    const div = document.createElement('div');
                    div.className = 'flex justify-between items-center p-4 border-b border-gray-100 last:border-0 hover:bg-gray-50 transition cursor-pointer bg-white';
                    div.innerHTML = `
                        <div class="flex flex-col gap-1.5">
                            <span class="font-bold text-[15px] text-[#1e293b]">${leadName}</span>
                            <div class="flex items-center text-[#64748b] text-xs gap-1.5 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                                ${leadOwner}
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <span class="text-[#059669] font-bold text-[15px]">₹${leadValue}</span>
                            <span class="bg-[#e2e8f0] text-[#475569] text-[11px] font-bold px-2.5 py-0.5 rounded shadow-sm">${leadStatus}</span>
                        </div>
                    `;
                    content.appendChild(div);
                });
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('block');
            
            if (event) {
                const rect = event.currentTarget.getBoundingClientRect();
                let topPos = window.scrollY + rect.top - modal.offsetHeight - 15;
                let leftPos = window.scrollX + rect.left + (rect.width / 2) - (modal.offsetWidth / 2);

                if (topPos < window.scrollY) topPos = window.scrollY + rect.bottom + 15;
                if (leftPos < 0) leftPos = 10;
                if (leftPos + modal.offsetWidth > window.innerWidth) leftPos = window.innerWidth - modal.offsetWidth - 10;

                modal.style.top = topPos + 'px';
                modal.style.left = leftPos + 'px';
            }
        }

        function closeModal() {
            const modal = document.getElementById('leadModal');
            modal.classList.add('hidden');
            modal.classList.remove('block');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('leadModal');
            if (!modal.classList.contains('hidden')) {
                if (!modal.contains(event.target) && !event.target.closest('.lead-box')) {
                    closeModal();
                }
            }
        }

        // This function dynamically updates the UI to show dummy data when date is changed
        function toggleWeekSelection(selectedDate) {
            // Pick a data source randomly to show visual UI change when user picks a date
            let dataSource = (Math.random() > 0.5) ? mockAlternativeLeads : mockLeadsByDay;

            // Update the chart visual blocks dynamically based on the selected dataset
            const columns = document.querySelectorAll('.week-col');
            columns.forEach(col => {
                const day = col.getAttribute('data-day');
                const leads = dataSource[day] || [];
                const count = leads.length;
                
                let html = '';
                if (count == 0) {
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center text-xs font-bold">0</div>`;
                } else if (count <= 5) {
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#F97316] text-white flex items-center justify-center text-xs font-bold">${count}</div>`;
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#CBD5E1] text-transparent flex items-center justify-center text-xs font-bold"></div>`;
                } else if (count <= 15) {
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#F97316] text-white flex items-center justify-center text-xs font-bold">${count}</div>`;
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#FDBA74] text-transparent flex items-center justify-center text-xs font-bold"></div>`;
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#CBD5E1] text-transparent flex items-center justify-center text-xs font-bold"></div>`;
                } else {
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#F97316] text-white flex items-center justify-center text-xs font-bold">${count}</div>`;
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#FDBA74] text-transparent flex items-center justify-center text-xs font-bold"></div>`;
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#CBD5E1] text-transparent flex items-center justify-center text-xs font-bold"></div>`;
                    html += `<div onclick="showLeads('${day}', event)" class="lead-box w-full h-8 bg-[#E2E8F0] text-transparent flex items-center justify-center text-xs font-bold"></div>`;
                }
                
                html += `<span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">${day.substring(0, 3)}</span>`;
                col.innerHTML = html;
            });
        }
    </script>
</body>
</html>