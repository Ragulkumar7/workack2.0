<?php
// 1. Start Session & Set Timezone
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Kolkata');

// --- 2. AJAX HANDLER FOR NOTIFICATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['header_action']) && $_POST['header_action'] === 'mark_notifications_read') {
    if (isset($conn) && isset($_SESSION['user_id'])) {
        ob_clean(); // Prevent HTML corruption in JSON response
        header('Content-Type: application/json');
        
        $uid = (int)$_SESSION['user_id'];
        $u_role = $_SESSION['role'] ?? '';
        
        // Mark personal notifications as read
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $uid AND is_read = 0");
        
        // Mark role-based broadcast notifications as read for THIS specific user
        $role_notifs = $conn->query("
            SELECT n.id FROM notifications n
            LEFT JOIN notification_reads nr ON n.id = nr.notif_id AND nr.user_id = $uid
            WHERE (n.target_role = '$u_role' OR n.target_role LIKE '%All%') AND nr.notif_id IS NULL
        ");
        
        if ($role_notifs && $role_notifs->num_rows > 0) {
            while($rn = $role_notifs->fetch_assoc()) {
                $nid = (int)$rn['id'];
                $conn->query("INSERT IGNORE INTO notification_reads (user_id, notif_id) VALUES ($uid, $nid)");
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// --- 3. DYNAMIC PATH ---
$script_path = $_SERVER['SCRIPT_NAME']; 
$path_parts = explode('/', trim($script_path, '/'));
$folder_depth = count($path_parts) - 2; 
$base_path = ($folder_depth > 0) ? str_repeat('../', $folder_depth) : './';

// --- 4. GET LOGGED-IN USER DATA & PROFILE IMAGE ---
$current_user_id = $_SESSION['user_id'] ?? 0;
$user_email      = $_SESSION['username'] ?? 'Guest';
$user_role       = $_SESSION['role'] ?? 'User';
$display_name    = ucfirst(explode('@', $user_email)[0]); 
$full_name       = $display_name;

$db_designation = '';
$db_department  = '';

// Fetch actual Profile Image & Exact Roles from DB
if (isset($conn) && $current_user_id > 0) {
    $p_sql = "SELECT full_name, profile_img, designation, department FROM employee_profiles WHERE user_id = $current_user_id";
    $p_res = $conn->query($p_sql);
    if ($p_res && $p_row = $p_res->fetch_assoc()) {
        if (!empty($p_row['full_name'])) {
            $full_name = $p_row['full_name'];
            $display_name = explode(' ', trim($full_name))[0]; 
        }
        if (!empty($p_row['profile_img']) && $p_row['profile_img'] !== 'default_user.png') {
            $profile_img = (strpos($p_row['profile_img'], 'http') === 0) ? $p_row['profile_img'] : $base_path . 'assets/profiles/' . $p_row['profile_img'];
        } else {
            $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($full_name) . "&background=1e293b&color=fff&bold=true";
        }
        
        $db_designation = $p_row['designation'] ?? '';
        $db_department  = $p_row['department'] ?? '';
    } else {
        $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($display_name) . "&background=1e293b&color=fff&bold=true";
    }
} else {
    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($display_name) . "&background=1e293b&color=fff&bold=true";
}

// --- 5. UNIFIED NOTIFICATION ENGINE ---
$unread_count = 0;
$notifications = [];

if (isset($conn) && $current_user_id > 0) {
    
    // A. Ensure Master Notification Tables Exist
    $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($check_table && $check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `target_role` varchar(50) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `type` varchar(50) DEFAULT 'info',
            `link` varchar(255) DEFAULT '#',
            `is_read` tinyint(1) DEFAULT 0,
            `source_type` varchar(50) DEFAULT 'system',
            `source_id` int(11) DEFAULT NULL,
            `created_at` timestamp DEFAULT current_timestamp(),
            PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `target_role` (`target_role`),
            KEY `src_idx` (`source_type`, `source_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    $check_reads = $conn->query("SHOW TABLES LIKE 'notification_reads'");
    if ($check_reads && $check_reads->num_rows == 0) {
        $conn->query("CREATE TABLE `notification_reads` (`user_id` int(11) NOT NULL, `notif_id` int(11) NOT NULL, `read_at` timestamp DEFAULT current_timestamp(), PRIMARY KEY (`user_id`, `notif_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // =========================================================================
    // B. BULLETPROOF SYNC ENGINE (SELECT * prevents all SQL Column Errors)
    // =========================================================================
    if (!isset($_SESSION['last_notif_sync']) || (time() - $_SESSION['last_notif_sync']) > 5) { // 5-second fast sync
        $uid_safe = (int)$current_user_id;
        $username_safe = $user_email; 
        $fullname_safe = $full_name; 
        
        $u_name_db = '';
        $u_q = $conn->query("SELECT name FROM users WHERE id = $uid_safe LIMIT 1");
        if($u_q && $u_row = $u_q->fetch_assoc()) {
            $u_name_db = $u_row['name'];
        }
        
        function createNotif($conn, $uid, $role, $title, $msg, $type, $link, $srcType, $srcId) {
            $uid_val = $uid ? (int)$uid : "NULL";
            $role_val = $role ? "'".$conn->real_escape_string($role)."'" : "NULL";
            $title_val = "'".$conn->real_escape_string($title)."'";
            $msg_val = "'".$conn->real_escape_string($msg)."'";
            
            $uid_check = $uid ? "= $uid_val" : "IS NULL";
            $role_check = $role ? "= $role_val" : "IS NULL";
            
            $check = $conn->query("SELECT id FROM notifications WHERE source_id = $srcId AND source_type = '$srcType' AND user_id $uid_check AND target_role $role_check LIMIT 1");
            if ($check && $check->num_rows == 0) {
                $conn->query("INSERT INTO notifications (user_id, target_role, title, message, type, link, source_type, source_id) 
                              VALUES ($uid_val, $role_val, $title_val, $msg_val, '$type', '$link', '$srcType', $srcId)");
            }
        }

        // 1. ANNOUNCEMENTS
        $chk = $conn->query("SHOW TABLES LIKE 'announcements'");
        if ($chk && $chk->num_rows > 0) {
            $anns = $conn->query("SELECT * FROM announcements WHERE is_archived = 0 ORDER BY id DESC LIMIT 15");
            if($anns) while($a = $anns->fetch_assoc()) {
                $target = $a['target_audience'] ?? 'All';
                if(strtolower($target) == 'all' || strtolower($target) == 'all employees') $target = 'All';
                createNotif($conn, null, $target, '📢 ' . ($a['category'] ?? 'Announcement'), $a['title'] ?? 'New Update', 'announcement', '../view_announcements.php', 'ann', $a['id']);
            }
        }

        // 2. WFH & LEAVES & PAYSLIPS
        $chk = $conn->query("SHOW TABLES LIKE 'wfh_requests'");
        if ($chk && $chk->num_rows > 0) {
            $wfh = $conn->query("SELECT * FROM wfh_requests WHERE user_id = $uid_safe ORDER BY id DESC LIMIT 10");
            if($wfh) while($w = $wfh->fetch_assoc()) {
                $st = $w['status'] ?? 'Pending';
                if ($st != 'Pending') createNotif($conn, $uid_safe, null, '🏠 WFH ' . $st, 'Your WFH request was ' . $st, $st == 'Approved' ? 'success' : 'danger', '../work_from_home.php', 'wfh_emp_'.$st, $w['id']);
            }
        }

        $chk = $conn->query("SHOW TABLES LIKE 'leave_requests'");
        if ($chk && $chk->num_rows > 0) {
            $lvs = $conn->query("SELECT * FROM leave_requests WHERE user_id = $uid_safe ORDER BY id DESC LIMIT 10");
            if($lvs) while($l = $lvs->fetch_assoc()) {
                $st = $l['status'] ?? 'Pending';
                if ($st != 'Pending') createNotif($conn, $uid_safe, null, '✈️ Leave ' . $st, 'Your ' . ($l['leave_type']??'Leave') . ' was ' . $st, $st == 'Approved' ? 'success' : 'danger', '../leave_request.php', 'lv_emp_'.$st, $l['id']);
            }
        }

        $chk = $conn->query("SHOW TABLES LIKE 'payslip_requests'");
        if ($chk && $chk->num_rows > 0) {
            $pays = $conn->query("SELECT * FROM payslip_requests WHERE user_id = $uid_safe ORDER BY id DESC LIMIT 10");
            if($pays) while($p = $pays->fetch_assoc()) {
                $st = $p['status'] ?? 'Pending';
                if (in_array($st, ['Approved', 'Sent', 'Completed', 'Sent to Emp'])) {
                    createNotif($conn, $uid_safe, null, '📄 Payslip Ready', 'Your requested payslip is ready.', 'success', '../payslip_request.php', 'pay_emp_ready', $p['id']);
                }
            }
        }

        $chk = $conn->query("SHOW TABLES LIKE 'sales_expenses'");
        if ($chk && $chk->num_rows > 0) {
            $exps = $conn->query("SELECT * FROM sales_expenses WHERE user_id = $uid_safe ORDER BY id DESC LIMIT 10");
            if($exps) while($e = $exps->fetch_assoc()) {
                $st = $e['status'] ?? 'Pending';
                if ($st != 'Pending') createNotif($conn, $uid_safe, null, '💸 Expense ' . $st, 'Your expense claim was ' . $st, $st == 'Approved' ? 'success' : 'danger', '../sales_executive/my_expenses.php', 'exp_emp_'.$st, $e['id']);
            }
        }

        // 3. TASKS (Bulletproof Dynamic Column Checking)
        $task_tables = [
            'team_tasks' => '../my_tasks.php',
            'project_tasks' => '../my_tasks.php',
            'tasks' => '../my_tasks.php',
            'sales_tasks' => '../sales_executive/my_tasks.php',
            'personal_taskboard' => '../my_tasks.php'
        ];

        foreach ($task_tables as $tbl => $link) {
            $chk = $conn->query("SHOW TABLES LIKE '$tbl'");
            if ($chk && $chk->num_rows > 0) {
                $res = $conn->query("SELECT * FROM `$tbl` ORDER BY id DESC LIMIT 20");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $status = $row['status'] ?? $row['task_status'] ?? '';
                        if (strtolower($status) == 'completed') continue;

                        $assigned = $row['assigned_to'] ?? $row['user_id'] ?? '';
                        
                        $is_mine = false;
                        if ((string)$assigned === (string)$uid_safe) $is_mine = true;
                        elseif (strcasecmp((string)$assigned, $username_safe) === 0) $is_mine = true;
                        elseif (strcasecmp((string)$assigned, $fullname_safe) === 0) $is_mine = true;
                        elseif (!empty($u_name_db) && strcasecmp((string)$assigned, $u_name_db) === 0) $is_mine = true;

                        if ($is_mine) {
                            $tname = $row['task_name'] ?? $row['task_title'] ?? $row['title'] ?? 'New Task';
                            createNotif($conn, $uid_safe, null, '📋 Task Assigned', $tname, 'task', $link, $tbl.'_emp', $row['id']);
                        }
                    }
                }
            }
        }

        // 4. APPROVER NOTIFICATIONS (HR, Manager, Accounts)
        $combined_str = strtolower($user_role . ' ' . $db_designation . ' ' . $db_department);
        $is_approver = strpos($combined_str, 'hr') !== false || strpos($combined_str, 'manager') !== false || strpos($combined_str, 'lead') !== false || strpos($combined_str, 'tl') !== false || strpos($combined_str, 'account') !== false || strpos($combined_str, 'cfo') !== false;
        
        if ($is_approver) {
            // Safe Leave Approvals
            $chk = $conn->query("SHOW TABLES LIKE 'leave_requests'");
            if ($chk && $chk->num_rows > 0) {
                $pend = $conn->query("SELECT * FROM leave_requests WHERE status = 'Pending' ORDER BY id DESC LIMIT 5");
                if($pend) while($l = $pend->fetch_assoc()) {
                    $u = $l['user_id'] ?? 0;
                    $name = "Employee";
                    if ($u) {
                        $uq = $conn->query("SELECT name, username FROM users WHERE id = $u LIMIT 1");
                        if ($uq && $ur = $uq->fetch_assoc()) $name = $ur['name'] ?? $ur['username'] ?? "Employee";
                    }
                    $msg = $name . ' applied for ' . ($l['leave_type'] ?? 'Leave');
                    createNotif($conn, null, 'HR', '✈️ Leave Request', $msg, 'warning', '../HR/leave_approval.php', 'lv_hr', $l['id']);
                    createNotif($conn, null, 'Manager', '✈️ Team Leave', $msg, 'warning', '../leave_approval.php', 'lv_mgr', $l['id']);
                }
            }

            // Safe WFH Approvals
            $chk = $conn->query("SHOW TABLES LIKE 'wfh_requests'");
            if ($chk && $chk->num_rows > 0) {
                $pend = $conn->query("SELECT * FROM wfh_requests WHERE status = 'Pending' ORDER BY id DESC LIMIT 5");
                if($pend) while($w = $pend->fetch_assoc()) {
                    $u = $w['user_id'] ?? 0;
                    $name = "Employee";
                    if ($u) {
                        $uq = $conn->query("SELECT name, username FROM users WHERE id = $u LIMIT 1");
                        if ($uq && $ur = $uq->fetch_assoc()) $name = $ur['name'] ?? $ur['username'] ?? "Employee";
                    }
                    $msg = $name . ' requested WFH';
                    createNotif($conn, null, 'HR', '🏠 WFH Request', $msg, 'info', '../HR/wfh_approvals.php', 'wfh_hr', $w['id']);
                    createNotif($conn, null, 'Manager', '🏠 Team WFH', $msg, 'info', '../work_from_home.php', 'wfh_mgr', $w['id']);
                }
            }
        }

        $_SESSION['last_notif_sync'] = time();
    }

    // =========================================================================
    // C. FETCH UNREAD COUNT & FEED FOR CURRENT USER (STRICT ROLE MATCHING)
    // =========================================================================
    
    $roles_array = ['All', 'All Employees'];
    if (!empty($user_role)) $roles_array[] = $user_role;
    if (!empty($db_designation)) $roles_array[] = $db_designation;
    if (!empty($db_department)) $roles_array[] = $db_department;

    $combined_role_str = strtolower($user_role . ' ' . $db_designation . ' ' . $db_department);
    
    if (strpos($combined_role_str, 'hr') !== false) { array_push($roles_array, 'HR', 'HR Executive'); }
    if (strpos($combined_role_str, 'admin') !== false) { array_push($roles_array, 'Admin', 'System Admin'); }
    if (strpos($combined_role_str, 'cfo') !== false) { array_push($roles_array, 'CFO'); }
    if (strpos($combined_role_str, 'account') !== false || strpos($combined_role_str, 'finance') !== false) { array_push($roles_array, 'Accounts', 'Accountant', 'Finance'); }
    if (strpos($combined_role_str, 'lead') !== false || $combined_role_str === 'tl') { array_push($roles_array, 'Team Lead', 'TL'); }
    
    if (strpos($combined_role_str, 'manager') !== false) { 
        array_push($roles_array, 'Manager'); 
    }
    
    if (strpos($combined_role_str, 'sales') !== false) {
        if (strpos($combined_role_str, 'executive') !== false) {
            array_push($roles_array, 'Sales Executive', 'Sales');
        } elseif (strpos($combined_role_str, 'manager') !== false) {
            array_push($roles_array, 'Sales Manager'); 
        } else {
            array_push($roles_array, 'Sales');
        }
    }

    $roles_array = array_unique(array_filter($roles_array));
    $role_in = implode(',', array_map(function($r) use ($conn) {
        return "'" . $conn->real_escape_string(trim($r)) . "'";
    }, $roles_array));

    $cnt_query = "
        SELECT COUNT(*) as cnt 
        FROM notifications n
        LEFT JOIN notification_reads nr ON n.id = nr.notif_id AND nr.user_id = $current_user_id
        WHERE ((n.user_id = $current_user_id AND n.is_read = 0) OR (n.target_role IN ($role_in) AND nr.notif_id IS NULL))
    ";
    $cnt_res = $conn->query($cnt_query);
    if ($cnt_res) $unread_count = (int)$cnt_res->fetch_assoc()['cnt'];

    $notif_query = "
        SELECT n.*, IF(n.user_id = $current_user_id, n.is_read, IF(nr.notif_id IS NOT NULL, 1, 0)) as actual_read_status
        FROM notifications n
        LEFT JOIN notification_reads nr ON n.id = nr.notif_id AND nr.user_id = $current_user_id
        WHERE (n.user_id = $current_user_id OR n.target_role IN ($role_in))
        ORDER BY n.id DESC LIMIT 40
    ";
    $notif_res = $conn->query($notif_query);
    if ($notif_res) {
        while ($row = $notif_res->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
}

// Helper function for "Time Ago" format
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime; $ago = new DateTime($datetime); $diff = $now->diff($ago);
        $diff->w = floor($diff->d / 7); $diff->d -= $diff->w * 7;
        $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hr', 'i' => 'min', 's' => 'sec');
        foreach ($string as $k => &$v) { if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } else { unset($string[$k]); } }
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
    /* --- HEADER STYLING (FIXED TOP) --- */
    #mainHeader {
        position: fixed; top: 0; right: 0; left: 95px; width: calc(100% - 95px); 
        height: 64px; background-color: #ffffff; border-bottom: 1px solid #e5e7eb;
        z-index: 50; transition: left 0.3s ease, width 0.3s ease; 
        display: flex; align-items: center; justify-content: flex-end;
        padding: 0 24px; box-sizing: border-box;
    }

    /* --- DYNAMIC SHIFTING (When Sidebar Opens) --- */
    #mainHeader.main-shifted { left: 315px; width: calc(100% - 315px); }

    /* Notification Pulse Animation */
    @keyframes pulse-ring {
        0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .pulse-dot { animation: pulse-ring 2s infinite; }

    /* Custom Scrollbar for Notifications */
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }

    /* --- RESPONSIVE (Mobile) --- */
    @media (max-width: 768px) {
        #mainHeader { left: 0 !important; width: 100% !important; }
        #mainHeader.main-shifted { left: 0 !important; width: 100% !important; }
    }
</style>

<header id="mainHeader">
  <div class="flex items-center gap-3">
    
    <a href="<?php echo $base_path; ?>settings.php" class="hidden md:inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition-all mr-2">
      <i data-lucide="user" class="w-4 h-4 text-gray-500"></i>
      <span>View Profile</span>
    </a>

    <button id="fullscreenBtn" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors hidden lg:block" title="Toggle Fullscreen">
      <i data-lucide="maximize" class="w-5 h-5"></i>
    </button>
    
    <div class="relative">
      <button id="notifBtn" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors relative">
        <i data-lucide="bell" class="w-5 h-5"></i>
        <?php if($unread_count > 0): ?>
            <span id="notifBadge" class="absolute top-1 right-1.5 w-2 h-2 bg-red-500 rounded-full pulse-dot" title="<?php echo $unread_count; ?> new notifications"></span>
        <?php endif; ?>
      </button>

      <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-[360px] bg-white border border-gray-100 rounded-2xl shadow-xl z-50 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
          <h3 class="font-bold text-slate-800 text-[15px]">
              Notifications
          </h3>
          <?php if($unread_count > 0): ?>
              <button onclick="markNotificationsRead()" class="text-[11px] text-blue-600 hover:text-blue-800 font-semibold transition-colors" id="markReadBtn">Mark all read</button>
          <?php endif; ?>
        </div>
        
        <div class="max-h-[400px] overflow-y-auto custom-scroll bg-white" id="notifList">
            <?php if (empty($notifications)): ?>
                <div class="p-8 text-center text-gray-400 flex flex-col items-center">
                    <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                        <i data-lucide="bell-off" class="w-5 h-5 opacity-50"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-500">You're all caught up!</p>
                    <p class="text-xs text-gray-400 mt-1">No new alerts to display.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    // STYLING TO MATCH THE IMAGE
                    $type = strtolower($notif['type']);
                    
                    // Default Icon Style
                    $bg = 'bg-blue-50'; $text = 'text-blue-500'; $icon = 'bell';
                    
                    if (strpos($type, 'alert') !== false || strpos($type, 'danger') !== false || strpos($type, 'rejected') !== false) { 
                        $bg = 'bg-red-50'; $text = 'text-red-500'; $icon = 'alert-circle'; 
                    }
                    elseif (strpos($type, 'success') !== false || strpos($type, 'salary') !== false || strpos($type, 'approved') !== false || strpos($type, 'ready') !== false) { 
                        $bg = 'bg-emerald-50'; $text = 'text-emerald-500'; $icon = 'check-circle'; 
                    }
                    elseif (strpos($type, 'warning') !== false || strpos($type, 'leave') !== false) { 
                        $bg = 'bg-orange-50'; $text = 'text-orange-500'; $icon = 'clock'; 
                    }
                    elseif ($type == 'announcement') { 
                        // EXACT MATCH TO IMAGE: Light purple background, purple outline megaphone
                        $bg = 'bg-[#f5efff]'; $text = 'text-[#b08df8]'; $icon = 'megaphone'; 
                    }
                    elseif ($type == 'task') { 
                        $bg = 'bg-indigo-50'; $text = 'text-indigo-500'; $icon = 'clipboard-list'; 
                    }
                    
                    // Unread styling logic
                    $is_read_styling = $notif['actual_read_status'] == 1 ? 'opacity-80' : 'bg-slate-50/50';
                ?>
                <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" class="flex gap-4 items-start p-5 border-b border-gray-100 hover:bg-gray-50 transition-colors <?php echo $is_read_styling; ?>">
                    <div class="w-10 h-10 shrink-0 <?php echo $bg; ?> rounded-full flex items-center justify-center <?php echo $text; ?>">
                        <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                      <p class="text-sm font-bold text-slate-700 leading-snug truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                      <p class="text-[13px] text-gray-500 mt-1 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($notif['message']); ?></p>
                      <p class="text-[11px] text-gray-400 mt-2 font-medium"><?php echo time_elapsed_string($notif['created_at']); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="relative pl-3 ml-1">
      <button id="profileBtn" class="flex items-center gap-2 focus:outline-none">
        <div class="relative">
            <img src="<?php echo $profile_img; ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=1e293b&color=fff'" alt="Profile" class="w-9 h-9 rounded-full object-cover shadow-sm border border-gray-200">
            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-[#22c55e] border-2 border-white rounded-full z-10"></span>
        </div>
      </button>

      <div id="profileDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white border border-gray-100 rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center gap-3 bg-gray-50/50">
          <img src="<?php echo $profile_img; ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=1e293b&color=fff'" alt="Profile" class="w-10 h-10 rounded-full object-cover border border-gray-200">
          <div class="overflow-hidden">
            <h4 class="font-bold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($full_name); ?></h4>
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest truncate mt-0.5"><?php echo htmlspecialchars($user_role); ?></p>
          </div>
        </div>
        
        <div class="py-1.5">
          <a href="<?php echo $base_path; ?>settings.php" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors font-medium">
            <i data-lucide="settings" class="w-4 h-4 text-gray-400"></i>
            Account Settings
          </a>
        </div>

        <div class="border-t border-gray-100 py-1.5">
          <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors font-bold">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            Secure Logout
          </a>
        </div>
      </div>
    </div>

  </div>
</header>

<div style="height: 84px; width: 100%; flex-shrink: 0;"></div>

<script>
  // Initialize Icons
  if (typeof lucide !== 'undefined') { lucide.createIcons(); }

  // --- DROPDOWN LOGIC ---
  const setupDropdown = (btnId, dropdownId) => {
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    if (btn && dropdown) {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          dropdown.classList.toggle('hidden');
          // Close other dropdown
          const otherId = btnId === 'notifBtn' ? 'profileDropdown' : 'notifDropdown';
          document.getElementById(otherId)?.classList.add('hidden');
        });
        document.addEventListener('click', (e) => {
          if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.add('hidden');
          }
        });
    }
  };
  setupDropdown('notifBtn', 'notifDropdown');
  setupDropdown('profileBtn', 'profileDropdown');

  // --- AJAX: MARK NOTIFICATIONS READ ---
  function markNotificationsRead() {
      const btn = document.getElementById('markReadBtn');
      if(btn) btn.innerText = "Marking...";
      
      const formData = new FormData();
      formData.append('header_action', 'mark_notifications_read');

      fetch(window.location.href, {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if(data.success) {
              // Hide the red badge immediately
              const badge = document.getElementById('notifBadge');
              if(badge) badge.style.display = 'none';
              
              if(btn) btn.style.display = 'none';

              // Remove unread styling from list
              const items = document.querySelectorAll('#notifList a');
              items.forEach(item => {
                  item.classList.remove('bg-slate-50/50');
                  item.classList.add('opacity-80');
              });
          }
      }).catch(err => console.log('Notification update failed.', err));
  }

  // --- FULLSCREEN LOGIC ---
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen().catch(err => { console.log(err); });
        } else {
          if (document.exitFullscreen) document.exitFullscreen();
        }
      });
  }

  // --- SYNC HEADER WITH SIDEBAR (Auto-Width Adjustment) ---
  const headerObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName === 'class') {
        const mainContent = document.getElementById('mainContent');
        const mainHeader = document.getElementById('mainHeader');
        if (mainContent && mainHeader) {
          if (mainContent.classList.contains('main-shifted')) {
            mainHeader.classList.add('main-shifted');
          } else {
            mainHeader.classList.remove('main-shifted');
          }
        }
      }
    });
  });

  const targetNode = document.getElementById('mainContent');
  if (targetNode) {
    headerObserver.observe(targetNode, { attributes: true });
  }
</script>