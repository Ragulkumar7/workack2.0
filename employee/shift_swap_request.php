<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../include/db_connect.php');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) { 
    header("Location: index.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'];
$message = "";

// =========================================================
// AJAX ENDPOINT FOR DYNAMIC CURRENT SHIFT FETCHING
// =========================================================
if (isset($_GET['fetch_shift_for_date'])) {
    $check_date = trim($_GET['fetch_shift_for_date']);
    
    // 1. Get user's default shift
    $profile_query = "SELECT p.shift_type, p.shift_timings FROM employee_profiles p WHERE p.user_id = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile_data = $stmt->get_result()->fetch_assoc();
    
    $shift_type = $profile_data['shift_type'] ?? 'Regular';
    $shift_timings = $profile_data['shift_timings'] ?? '';
    $current_shift = $shift_type . ($shift_timings ? " (" . $shift_timings . ")" : "");
    $stmt->close();

    // 2. Check if there's an ALREADY APPROVED swap for this specific date
    // FIXED LOGIC: Checks if either 'status' is Approved OR all 3 hierarchy levels are Approved
    $swap_sql = "SELECT requested_shift FROM shift_swap_requests WHERE user_id = ? AND request_date = ? AND (status = 'Approved' OR (tl_approval = 'Approved' AND manager_approval = 'Approved' AND hr_approval = 'Approved'))";
    $swap_stmt = $conn->prepare($swap_sql);
    $swap_stmt->bind_param("is", $user_id, $check_date);
    $swap_stmt->execute();
    $swap_res = $swap_stmt->get_result();
    
    if ($swap_row = $swap_res->fetch_assoc()) {
        $current_shift = trim($swap_row['requested_shift']);
    }
    $swap_stmt->close();
    
    echo $current_shift;
    exit();
}
// =========================================================

// FETCH USER ROLE & CURRENT SHIFT DETAILS (Initial Load)
$profile_query = "SELECT u.role, p.shift_type, p.shift_timings 
                  FROM users u 
                  LEFT JOIN employee_profiles p ON u.id = p.user_id 
                  WHERE u.id = ?";
$stmt_r = $conn->prepare($profile_query);
$stmt_r->bind_param("i", $user_id);
$stmt_r->execute();
$profile_data = $stmt_r->get_result()->fetch_assoc();

$user_role = $profile_data['role'] ?? 'Employee';
$user_shift_type = $profile_data['shift_type'] ?? 'Regular';
$user_shift_timings = $profile_data['shift_timings'] ?? '';
$current_shift_display = $user_shift_type . ($user_shift_timings ? " (" . $user_shift_timings . ")" : "");

$stmt_r->close();

// --- NEW FIX: OVERRIDE CURRENT SHIFT FOR TODAY ON PAGE LOAD ---
$today_date = date('Y-m-d');
$swap_override_sql = "SELECT requested_shift FROM shift_swap_requests WHERE user_id = ? AND request_date = ? AND (status = 'Approved' OR (tl_approval = 'Approved' AND manager_approval = 'Approved' AND hr_approval = 'Approved'))";
$swap_override_stmt = $conn->prepare($swap_override_sql);
$swap_override_stmt->bind_param("is", $user_id, $today_date);
$swap_override_stmt->execute();
$swap_override_res = $swap_override_stmt->get_result();

if ($swap_override_row = $swap_override_res->fetch_assoc()) {
    $current_shift_display = trim($swap_override_row['requested_shift']);
}
$swap_override_stmt->close();
// --------------------------------------------------------------

if (isset($_SESSION['success_msg'])) {
    $message = "<div class='alert alert-success border-0 shadow-sm'>" . $_SESSION['success_msg'] . "</div>";
    unset($_SESSION['success_msg']); 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_SESSION['last_submit_time']) && (time() - $_SESSION['last_submit_time'] < 2)) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $_SESSION['last_submit_time'] = time();

    $req_date = trim($_POST['request_date']);
    $curr_shift = trim($_POST['current_shift']);
    $swap_shift = trim($_POST['requested_shift']);
    $reason = trim($_POST['reason']);
    
    // Handle Custom Requested Shift
    if ($swap_shift === 'Other' && !empty(trim($_POST['custom_requested_shift'] ?? ''))) {
        $swap_shift = trim($_POST['custom_requested_shift']);
    }

    // =========================================================
    // SMART HIERARCHY BYPASS LOGIC (ROLE-BASED)
    // =========================================================
    $tl_approval = 'Pending';
    $mgr_approval = 'Pending';
    $hr_approval = 'Pending';
    $final_status = 'Pending';

    // 1. Team Leads bypass the TL approval phase
    if (in_array($user_role, ['Team Lead', 'TL'])) {
        $tl_approval = 'Approved';
    }
    
    // 2. Managers bypass both TL and Manager approval phases
    if (in_array($user_role, ['Manager', 'Project Manager', 'General Manager'])) {
        $tl_approval = 'Approved';
        $mgr_approval = 'Approved';
    }

    // 3. IT Executive Logic: Bypass TL, goes to IT Admin (Mapped in Manager Status), then HR
    if ($user_role === 'IT Executive') {
        $tl_approval = 'Approved';
        $mgr_approval = 'Pending';
        $hr_approval = 'Pending';
    }

    // 4. CFO, Accounts & IT Admin Logic: Bypass TL and Manager entirely, straight to HR
    if (in_array($user_role, ['CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive'])) {
        $tl_approval = 'Approved';
        $mgr_approval = 'Approved';
        $hr_approval = 'Pending';
    }

    // 5. Top-Level Management Auto-Approval (Instant Approval)
    if (in_array($user_role, ['Admin', 'System Admin', 'CEO'])) {
        $tl_approval = 'Approved';
        $mgr_approval = 'Approved';
        $hr_approval = 'Approved';
        $final_status = 'Approved';
    }

    // UPDATED INSERT QUERY WITH STATUS BYPASSES
    $sql = "INSERT INTO shift_swap_requests (user_id, request_date, current_shift, requested_shift, reason, tl_approval, manager_approval, hr_approval, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssss", $user_id, $req_date, $curr_shift, $swap_shift, $reason, $tl_approval, $mgr_approval, $hr_approval, $final_status);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Request sent successfully for approval.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit(); 
    }
}

// Helper function to render status badges
if (!function_exists('renderSwapBadge')) {
    function renderSwapBadge($status) {
        if ($status === '-') {
            return '<span class="text-slate-300 font-black text-xl leading-none select-none">-</span>';
        }
        $cls = 'badge-' . strtolower($status);
        return "<span class=\"status-badge {$cls}\">{$status}</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Swap - Neoera Infotech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 13px; }
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
        
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #fff; }
        .btn-primary-orange { background: #1b5a5a; border: none; color: white; transition: 0.3s; }
        .btn-primary-orange:hover { background: #134040; color: white; }
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fff7ed; color: #9a3412; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>

    <main id="mainContent" class="main-content">
        <div class="w-full">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-800">Shift Swap Portal</h1>
                <p class="text-slate-500">Submit requests and track hierarchical approvals.</p>
            </div>

            <?php echo $message; ?>

            <div class="card p-8 mb-10">
                <form action="" method="POST" class="space-y-6" onsubmit="this.submitButton.disabled=true; return true;">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Shift Date</label>
                            <input type="date" name="request_date" id="requestDateInput" required class="w-full p-3 border border-slate-200 rounded-lg focus:border-teal-500">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Current Shift (Auto-fetched)</label>
                            <div class="relative">
                                <input type="text" id="currentShiftDisplay" value="<?= htmlspecialchars($current_shift_display) ?>" class="w-full p-3 border border-slate-300 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed font-medium" readonly title="Your assigned shift is automatically fetched based on date.">
                                <input type="hidden" name="current_shift" id="currentShiftHidden" value="<?= htmlspecialchars($current_shift_display) ?>">
                                <div id="shiftLoader" class="hidden absolute right-3 top-3"><i class="fa-solid fa-spinner fa-spin text-teal-500"></i></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Requested Swap Shift</label>
                        <select name="requested_shift" class="w-full p-3 border border-slate-200 rounded-lg focus:border-teal-500" required onchange="if(this.value==='Other'){document.getElementById('custom_req_shift').classList.remove('hidden');document.getElementById('custom_req_shift').required=true;}else{document.getElementById('custom_req_shift').classList.add('hidden');document.getElementById('custom_req_shift').required=false;}">
                            <option value="">Select target shift...</option>
                            <option value="Day Shift (09:00 AM - 06:00 PM)">Day Shift (09:00 AM - 06:00 PM)</option>
                            <option value="Night Shift (09:00 PM - 06:00 AM)">Night Shift (09:00 PM - 06:00 AM)</option>
                            <option value="Afternoon Shift (02:00 PM - 11:00 PM)">Afternoon Shift (02:00 PM - 11:00 PM)</option>
                            <option value="Other" class="font-bold text-teal-600 border-t">➕ Other (Enter Custom Timing)</option>
                        </select>
                        <input type="text" id="custom_req_shift" name="custom_requested_shift" placeholder="e.g. Morning Shift (06:00 AM - 02:00 PM)" class="w-full p-3 border border-slate-200 rounded-lg focus:border-teal-500 hidden mt-2">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Reason</label>
                        <textarea name="reason" rows="3" required class="w-full p-3 border border-slate-200 rounded-lg focus:border-teal-500"></textarea>
                    </div>
                    <button type="submit" name="submitButton" class="btn-primary-orange px-10 py-3 rounded-xl font-bold shadow-lg">Submit Request</button>
                </form>
            </div>

            <div class="card overflow-hidden">
                <div class="p-4 border-b bg-slate-50">
                    <h3 class="font-bold text-slate-700">Request History & Approval Status</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-slate-100/50">
                            <tr class="text-[10px] uppercase font-bold text-slate-400">
                                <th class="px-6 py-4">Request Date</th>
                                <th>Shifts (Current → Swap)</th>
                                <?php if ($user_role === 'IT Executive'): ?>
                                    <th>IT Admin Approval</th>
                                    <th>HR Approval</th>
                                <?php elseif (in_array($user_role, ['IT Admin', 'CFO', 'Accounts', 'Accountant'])): ?>
                                    <th>HR Approval</th>
                                <?php else: ?>
                                    <th>TL Approval</th>
                                    <th>Manager Approval</th>
                                    <th>HR Approval</th>
                                <?php endif; ?>
                                <th>Final Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $sql = "SELECT r.*, 
                                    p1.full_name as tl_name, 
                                    p2.full_name as manager_name, 
                                    p3.full_name as hr_name
                                    FROM shift_swap_requests r
                                    LEFT JOIN employee_profiles p1 ON r.tl_id = p1.user_id
                                    LEFT JOIN employee_profiles p2 ON r.manager_id = p2.user_id
                                    LEFT JOIN employee_profiles p3 ON r.hr_id = p3.user_id
                                    WHERE r.user_id = $user_id ORDER BY r.id DESC";
                            $res = $conn->query($sql);
                            while($row = $res->fetch_assoc()):
                                
                                // CASCADING REJECTION LOGIC
$tl_stat = $row['tl_approval'];
$mgr_stat = $row['manager_approval'];
$hr_stat = $row['hr_approval'];

// Fallback to Pending if empty in database
$final_stat = !empty($row['status']) ? $row['status'] : 'Pending';

// 🚀 SMART FALLBACK: If all stages are approved, force Final Status to Approved
if ($tl_stat === 'Approved' && $mgr_stat === 'Approved' && $hr_stat === 'Approved') {
    $final_stat = 'Approved';
} 
// If any stage is rejected, force Final Status to Rejected
elseif ($tl_stat === 'Rejected' || $mgr_stat === 'Rejected' || $hr_stat === 'Rejected') {
    $final_stat = 'Rejected';
}

// Dash out subsequent steps if rejected early
if ($tl_stat === 'Rejected') {
    $mgr_stat = '-';
    $hr_stat = '-';
} elseif ($mgr_stat === 'Rejected') {
    $hr_stat = '-';
}
                            ?>
                            <tr class="text-slate-700">
                                <td class="px-6 font-semibold"><?php echo date('d M Y', strtotime($row['request_date'])); ?></td>
                                <td>
                                    <div class="text-[11px]">
                                        <span class="text-slate-400"><?php echo htmlspecialchars($row['current_shift']); ?></span>
                                        <i class="fa fa-arrow-right mx-1 text-teal-600"></i>
                                        <span class="text-teal-700 font-bold"><?php echo htmlspecialchars($row['requested_shift']); ?></span>
                                    </div>
                                </td>

                                <?php if ($user_role === 'IT Executive'): ?>
                                    <td>
                                        <?php echo renderSwapBadge($mgr_stat); ?>
                                        <p class="text-[9px] mt-1 text-slate-400 font-bold">
                                            <?php echo ($mgr_stat === '-') ? '---' : ($row['manager_name'] ?? '---'); ?>
                                        </p>
                                    </td>
                                    <td>
                                        <?php echo renderSwapBadge($hr_stat); ?>
                                        <p class="text-[9px] mt-1 text-slate-400 font-bold">
                                            <?php echo ($hr_stat === '-') ? '---' : ($row['hr_name'] ?? '---'); ?>
                                        </p>
                                    </td>
                                <?php elseif (in_array($user_role, ['IT Admin', 'CFO', 'Accounts', 'Accountant'])): ?>
                                    <td>
                                        <?php echo renderSwapBadge($hr_stat); ?>
                                        <p class="text-[9px] mt-1 text-slate-400 font-bold">
                                            <?php echo ($hr_stat === '-') ? '---' : ($row['hr_name'] ?? '---'); ?>
                                        </p>
                                    </td>
                                <?php else: ?>
                                    <td>
                                        <?php echo renderSwapBadge($tl_stat); ?>
                                        <p class="text-[9px] mt-1 text-slate-400 font-bold"><?php echo $row['tl_name'] ?? '---'; ?></p>
                                    </td>
                                    <td>
                                        <?php echo renderSwapBadge($mgr_stat); ?>
                                        <p class="text-[9px] mt-1 text-slate-400 font-bold">
                                            <?php echo ($mgr_stat === '-') ? '---' : ($row['manager_name'] ?? '---'); ?>
                                        </p>
                                    </td>
                                    <td>
                                        <?php echo renderSwapBadge($hr_stat); ?>
                                        <p class="text-[9px] mt-1 text-slate-400 font-bold">
                                            <?php echo ($hr_stat === '-') ? '---' : ($row['hr_name'] ?? '---'); ?>
                                        </p>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <?php echo renderSwapBadge($final_stat); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Set today's date as min for request date
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('requestDateInput');
        dateInput.setAttribute('min', today);

        // Fetch dynamic shift based on selected date
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            const displayEl = document.getElementById('currentShiftDisplay');
            const hiddenEl = document.getElementById('currentShiftHidden');
            const loader = document.getElementById('shiftLoader');
            const originalShift = '<?= htmlspecialchars($current_shift_display) ?>';

            if (!selectedDate) return;

            displayEl.value = 'Fetching shift...';
            loader.classList.remove('hidden');

            fetch('?fetch_shift_for_date=' + selectedDate)
                .then(response => response.text())
                .then(shiftData => {
                    const finalShift = shiftData.trim() || originalShift;
                    displayEl.value = finalShift;
                    hiddenEl.value = finalShift;
                })
                .catch(error => {
                    console.error('Error fetching shift:', error);
                    displayEl.value = originalShift;
                    hiddenEl.value = originalShift;
                })
                .finally(() => {
                    loader.classList.add('hidden');
                });
        });
    </script>
</body>
</html>