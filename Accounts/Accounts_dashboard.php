<?php
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// FIX TIMEZONE 
date_default_timezone_set('Asia/Kolkata');

require_once '../include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$username = $_SESSION['username'] ?? 'User';

// -------------------------------------------------------------------------
// 2. INITIALIZE ALL VARIABLES
// -------------------------------------------------------------------------
$employee_name = "Employee";
$employee_role = "Accountant";
$employee_phone = "Not Set";
$employee_email = "";
$joining_date = "Not Set";
$db_joining_date = $today;
$profile_img = "";
$experience_label = "N/A";
$department = "Accounts";
$shift_timings = '09:00 AM - 06:00 PM';
$reporting_to = "Management"; // Default fallback

$attendance_record = null;
$total_hours_today = "00:00:00";
$display_punch_in = "--:--";
$total_seconds_worked = 0;
$is_on_break = false; 

$stats_ontime = 0;
$stats_late = 0;
$stats_wfh = 0;
$stats_absent = 0;
$stats_sick = 0;

$leaves_total = 2; // Default changed from 16 to 2 (will be updated from DB)
$leaves_taken = 0;
$leaves_remaining = 2;

// -------------------------------------------------------------------------
// 3. FETCH PROFILE & LEAVE DATA
// -------------------------------------------------------------------------
$sql_profile = "SELECT u.username, u.role, p.full_name, p.phone, p.joining_date, p.designation, p.email, p.profile_img, p.department, p.experience_label, p.shift_timings, p.casual_leaves, m.name AS reporting_to_name 
                FROM users u 
                LEFT JOIN employee_profiles p ON u.id = p.user_id 
                LEFT JOIN users m ON p.reporting_to = m.id
                WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
$user_res = mysqli_stmt_get_result($stmt);

if ($user_info = mysqli_fetch_assoc($user_res)) {
    $employee_name = $user_info['full_name'] ?? $user_info['username'];
    $employee_role = $user_info['designation'] ?? $user_info['role'] ?? 'Accountant';
    $employee_phone = $user_info['phone'] ?? '+91 00000 00000';
    $employee_email = $user_info['email'] ?? $user_info['username'];
    $department = $user_info['department'] ?? 'Accounts';
    $experience_label = $user_info['experience_label'] ?? '1+ Years';
    $shift_timings = $user_info['shift_timings'] ?? $shift_timings;
    
    // Fetching the casual leaves directly from database
    $leaves_total = $user_info['casual_leaves'] ?? 2;
    
    if (!empty($user_info['reporting_to_name'])) {
        $reporting_to = $user_info['reporting_to_name'];
    }
    
    $db_joining_date = $user_info['joining_date'] ?? $today;
    $joining_date = $user_info['joining_date'] ? date("d M Y", strtotime($user_info['joining_date'])) : "Not Set";
    
    $profile_img = "https://ui-avatars.com/api/?name=" . urlencode($employee_name) . "&background=0d9488&color=fff&size=128&bold=true";
    if (!empty($user_info['profile_img']) && $user_info['profile_img'] !== 'default_user.png') {
        if (str_starts_with($user_info['profile_img'], 'http')) {
            $profile_img = $user_info['profile_img'];
        } else {
            $profile_img = '../assets/profiles/' . $user_info['profile_img'];
        }
    }
}

$time_parts = explode('-', $shift_timings);
$shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';

// Fetch Leave Balance
$leave_sql = "SELECT SUM(total_days) as taken FROM leave_requests WHERE user_id = ? AND status = 'Approved'";
$leave_stmt = mysqli_prepare($conn, $leave_sql);
if ($leave_stmt) {
    mysqli_stmt_bind_param($leave_stmt, "i", $current_user_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_res = mysqli_stmt_get_result($leave_stmt);
    if($leave_data = mysqli_fetch_assoc($leave_res)) {
        $leaves_taken = $leave_data['taken'] ?? 0;
    }
}
$leaves_remaining = max(0, $leaves_total - $leaves_taken);


// =========================================================================
// 4. MONTHLY STATS (EXACT DAY-BY-DAY LOOP ENGINE FOR ACCURATE ABSENT)
// =========================================================================
$total_late_seconds = 0;
$start_date_stat = date('Y-m-01'); // STRICTLY 1st of the month
$end_date_stat = $today;

// 1. Fetch DB Records for the month
$stat_sql = "SELECT date, punch_in, status FROM attendance WHERE user_id = ? AND date >= ? AND date <= ?";
$stat_stmt = mysqli_prepare($conn, $stat_sql);
mysqli_stmt_bind_param($stat_stmt, "iss", $current_user_id, $start_date_stat, $end_date_stat);
mysqli_stmt_execute($stat_stmt);
$stat_res = mysqli_stmt_get_result($stat_stmt);

$month_att_db = [];
while ($stat_row = mysqli_fetch_assoc($stat_res)) {
    $month_att_db[$stat_row['date']] = $stat_row;
}
mysqli_stmt_close($stat_stmt);

// 2. Fetch Approved Leaves safely for stats
$stmt_all_leaves_stat = mysqli_prepare($conn, "SELECT start_date, end_date, leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND start_date <= ?");
mysqli_stmt_bind_param($stmt_all_leaves_stat, "is", $current_user_id, $today);
mysqli_stmt_execute($stmt_all_leaves_stat);
$res_all_leaves_stat = mysqli_stmt_get_result($stmt_all_leaves_stat);
$all_app_leaves_stat = [];
if ($res_all_leaves_stat) {
    while ($l_row = mysqli_fetch_assoc($res_all_leaves_stat)) {
        $curr_l = new DateTime($l_row['start_date']);
        $end_l = new DateTime($l_row['end_date']);
        while ($curr_l <= $end_l) {
            $all_app_leaves_stat[$curr_l->format('Y-m-d')] = $l_row['leave_type'];
            $curr_l->modify('+1 day');
        }
    }
}
mysqli_stmt_close($stmt_all_leaves_stat);

// 3. Exact Date Loop Engine - NO JOIN DATE OVERRIDE (Match Audit Page exactly)
$iter_dt = new DateTime($start_date_stat);
$today_dt = new DateTime($today);

while ($iter_dt <= $today_dt) {
    $d_str = $iter_dt->format('Y-m-d');
    $dow = $iter_dt->format('N'); // 1 (Mon) to 7 (Sun)
    $is_today = ($d_str === $today);
    
    if (isset($month_att_db[$d_str])) {
        // Present in DB
        $r = $month_att_db[$d_str];
        $st = $r['status'];
        $is_absent_db = (stripos($st, 'Absent') !== false && empty($r['punch_in']));

        if ($is_absent_db) {
            $stats_absent++;
        } else {
            if (stripos($st, 'WFH') !== false) { 
                $stats_wfh++; 
            } elseif (stripos($st, 'Sick') !== false && !isset($all_app_leaves_stat[$d_str])) { 
                $stats_sick++; 
            }

            if (!empty($r['punch_in'])) {
                $expected_start_ts = strtotime($r['date'] . ' ' . $shift_start_str);
                $actual_start_ts = strtotime($r['punch_in']);
                if ($actual_start_ts > ($expected_start_ts + 60)) { 
                    $stats_late++; 
                    $total_late_seconds += ($actual_start_ts - $expected_start_ts);
                } else { 
                    if (stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                        $stats_ontime++; 
                    }
                }
            } else {
                // No punch in but not marked absent in DB
                if (!$is_today && stripos($st, 'WFH') === false && stripos($st, 'Sick') === false) {
                    $stats_absent++;
                }
            }
        }
    } else {
        // NOT in DB - check if Sunday or Leave
        if (!$is_today) {
            if ($dow == 7) {
                // Sunday - do nothing
            } elseif (isset($all_app_leaves_stat[$d_str])) {
                // On Approved Leave
                if (stripos($all_app_leaves_stat[$d_str], 'Sick') !== false) {
                    $stats_sick++;
                }
            } else {
                // Working day, not in DB, not on leave => ABSENT
                $stats_absent++;
            }
        } else {
             // TODAY logic - if not punched in and not Sunday/Leave, it is considered absent today
             if ($dow != 7 && !isset($all_app_leaves_stat[$d_str])) {
                 $stats_absent++; 
             }
        }
    }
    $iter_dt->modify('+1 day');
}

$late_hours = floor($total_late_seconds / 3600);
$late_minutes = floor(($total_late_seconds % 3600) / 60);
$late_time_str = $late_hours . 'h ' . $late_minutes . 'm';

// Leaves Taken specifically for UI display text
$current_month_leaves = 0;
foreach ($all_app_leaves_stat as $ld => $ltype) {
    if (strpos($ld, date('Y-m-')) === 0) {
        $current_month_leaves++;
    }
}


// -------------------------------------------------------------------------
// 5. ATTENDANCE TIMER LOGIC (Punch In/Out/Break)
// -------------------------------------------------------------------------
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

$total_break_seconds = 0;
$break_start_ts = 0;

if ($attendance_record) {
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $bk_stmt = mysqli_prepare($conn, $bk_sql);
    mysqli_stmt_bind_param($bk_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($bk_stmt);
    if ($bk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt))) {
        $is_on_break = true;
        $break_start_ts = strtotime($bk_row['break_start']);
    }

    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NOT NULL";
    $sum_stmt = mysqli_prepare($conn, $sum_sql);
    mysqli_stmt_bind_param($sum_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($sum_stmt);
    $sum_res = mysqli_fetch_assoc(mysqli_stmt_get_result($sum_stmt));
    $total_break_seconds = $sum_res['total'] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $now_db = date('Y-m-d H:i:s');
    if ($_POST['action'] == 'punch_in' && !$attendance_record) {
        $ins_sql = "INSERT INTO attendance (user_id, punch_in, date, status) VALUES (?, ?, ?, 'On Time')";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "iss", $current_user_id, $now_db, $today);
        mysqli_stmt_execute($ins_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } elseif ($_POST['action'] == 'break_start' && $attendance_record && !$is_on_break) {
        $ins_bk = "INSERT INTO attendance_breaks (attendance_id, break_start) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $ins_bk);
        mysqli_stmt_bind_param($stmt, "is", $attendance_record['id'], $now_db);
        mysqli_stmt_execute($stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } elseif ($_POST['action'] == 'break_end' && $attendance_record && $is_on_break) {
        $upd_bk = "UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL";
        $stmt = mysqli_prepare($conn, $upd_bk);
        mysqli_stmt_bind_param($stmt, "si", $now_db, $attendance_record['id']);
        mysqli_stmt_execute($stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } elseif ($_POST['action'] == 'punch_out' && $attendance_record && !$attendance_record['punch_out']) {
        if ($is_on_break) {
            mysqli_query($conn, "UPDATE attendance_breaks SET break_end = '$now_db' WHERE attendance_id = {$attendance_record['id']} AND break_end IS NULL");
            $total_break_seconds += (strtotime($now_db) - $break_start_ts);
        }
        $start_ts = strtotime($attendance_record['punch_in']);
        $end_ts = strtotime($now_db);
        $production_seconds = max(0, ($end_ts - $start_ts) - $total_break_seconds);
        $hours = $production_seconds / 3600;

        $upd_sql = "UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?";
        $upd_stmt = mysqli_prepare($conn, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, "sdi", $now_db, $hours, $attendance_record['id']);
        mysqli_stmt_execute($upd_stmt);
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
}

if ($attendance_record) {
    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    $start_ts = strtotime($attendance_record['punch_in']);
    if ($is_on_break) { $now_ts = $break_start_ts; } 
    elseif ($attendance_record['punch_out']) { $now_ts = strtotime($attendance_record['punch_out']); } 
    else { $now_ts = time(); }
    
    $total_seconds_worked = max(0, ($now_ts - $start_ts) - $total_break_seconds);
    $hours = floor($total_seconds_worked / 3600);
    $mins = floor(($total_seconds_worked % 3600) / 60);
    $secs = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
}

// -------------------------------------------------------------------------
// 6. FETCH MODULES (Notifications, Meetings, Tasks) - UPDATED WITH MEETINGS FIX
// -------------------------------------------------------------------------

$all_notifications = [];
$all_today_meetings = [];

$q_leaves = "SELECT leave_type, status, created_at FROM leave_requests WHERE user_id = $current_user_id AND status IN ('Approved', 'Rejected') ORDER BY created_at DESC LIMIT 3";
$r_leaves = mysqli_query($conn, $q_leaves);
if($r_leaves) {
    while($row = mysqli_fetch_assoc($r_leaves)) {
        $icon = $row['status'] == 'Approved' ? 'fa-plane-departure' : 'fa-times-circle'; // Matches Accounts design
        $color = $row['status'] == 'Approved' ? 'text-emerald-500 bg-emerald-100' : 'text-rose-500 bg-rose-100';
        $all_notifications[] = [
            'type' => 'leave', 'title' => 'Leave ' . $row['status'],
            'message' => 'Your ' . htmlspecialchars($row['leave_type']) . ' request was ' . strtolower($row['status']) . '.',
            'time' => $row['created_at'] ?? date('Y-m-d H:i:s'), 
            'icon' => $icon, 'color' => $color,
            'link' => '../employee/leave_request.php'
        ];
    }
}

// 1. Fetch from Announcements table (Manager/HR Scheduled Meetings)
$q_ann_meets = "SELECT a.id, a.title, a.publish_date as meet_date, a.message, a.created_at, COALESCE(u.username, 'Admin') as host_name 
                FROM announcements a 
                LEFT JOIN users u ON a.created_by = u.id 
                WHERE a.category = 'Meeting' AND a.is_archived = 0 
                AND (a.target_audience = 'All' 
                     OR a.target_audience = 'All Employees' 
                     OR a.target_audience LIKE '%" . $conn->real_escape_string($username) . "%' 
                     OR a.message LIKE '%" . $conn->real_escape_string($username) . "%'
                     OR a.message LIKE '%" . $conn->real_escape_string($employee_name) . "%'
                     OR a.target_audience = 'Accounts')"; // Included Accounts department specifically
$r_ann_meets = mysqli_query($conn, $q_ann_meets);
if($r_ann_meets) {
    while($row = mysqli_fetch_assoc($r_ann_meets)) {
        $time = "00:00:00"; 
        if (preg_match('/Time:\s*([^\n]+)/', $row['message'], $matches)) {
            $time = trim($matches[1]);
        }
        $row['meet_time'] = $time;
        
        // Push to My Updates (Live Feed / Notifications)
        $all_notifications[] = [
            'type' => 'meeting',
            'title' => 'Meeting Scheduled',
            'message' => 'By ' . htmlspecialchars($row['host_name']) . ': ' . htmlspecialchars($row['title']),
            'time' => $row['created_at'] ?? ($row['meet_date'] . ' 00:00:00'), 
            'icon' => 'fa-bullhorn', // Matches Accounts design for announcements
            'color' => 'text-orange-500 bg-orange-100',
            'link' => $path_to_root . 'view_announcements.php'
        ];

        // Push to array if meeting is TODAY OR IN THE FUTURE
        if ($row['meet_date'] >= $today) {
            $all_today_meetings[] = $row;
        }
    }
}

// 2. Fetch from old Calendar Meetings table if it exists
$check_meetings = $conn->query("SHOW TABLES LIKE 'calendar_meetings'");
if ($check_meetings && $check_meetings->num_rows > 0) {
    $q_meet_feed = "SELECT cm.id, cm.title, cm.meet_date, cm.meet_time, cm.meet_link, cm.created_at, COALESCE(ep.full_name, 'A team member') as host_name 
                    FROM calendar_meetings cm 
                    JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id 
                    LEFT JOIN employee_profiles ep ON cm.created_by = ep.user_id 
                    WHERE cmp.user_id = $current_user_id 
                    ORDER BY cm.created_at DESC LIMIT 4";
    $r_meet_feed = mysqli_query($conn, $q_meet_feed);
    if($r_meet_feed) {
        while($row = mysqli_fetch_assoc($r_meet_feed)) {
            $meet_datetime = date('d M Y', strtotime($row['meet_date'])) . ' at ' . date('h:i A', strtotime($row['meet_time']));
            $actual_link = trim($row['meet_link']);
            if (strpos($actual_link, '.') !== false) {
                if (!preg_match("~^(?:f|ht)tps?://~i", $actual_link) && strpos($actual_link, '/') !== 0) {
                    $actual_link = "https://" . $actual_link;
                }
            } else {
                $actual_link = $path_to_root . "team_chat.php?room_id=" . urlencode($actual_link);
            }

            $all_notifications[] = [
                'type' => 'meeting_chat',
                'title' => 'Meeting Invite: ' . htmlspecialchars($row['title']),
                'message' => htmlspecialchars($row['host_name']) . ' invited you to a meeting on ' . $meet_datetime . '.',
                'time' => $row['created_at'] ?? date('Y-m-d H:i:s'), 
                'icon' => 'fa-video', 
                'color' => 'text-indigo-600 bg-indigo-100',
                'link' => $actual_link 
            ];
        }
    }
    
    // Fetch list for Meetings Widget (>= CURDATE to show upcoming ones)
    $q_today_meets = "SELECT cm.title, cm.meet_date as meeting_date, cm.meet_time as meeting_time, cm.meet_link as meeting_link 
                      FROM calendar_meetings cm 
                      JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id 
                      WHERE cmp.user_id = $current_user_id AND cm.meet_date >= CURDATE()";
    $r_today = mysqli_query($conn, $q_today_meets);
    if($r_today) {
        while($row = mysqli_fetch_assoc($r_today)) {
            $all_today_meetings[] = [
                'title' => $row['title'],
                'meet_date' => $row['meeting_date'],
                'meet_time' => $row['meeting_time'],
                'meet_link' => $row['meeting_link']
            ];
        }
    }
}

// Sort combined meetings by Date and Time so the closest meeting shows first
usort($all_today_meetings, function($a, $b) {
    $timeA = strtotime($a['meet_date'] . ' ' . $a['meet_time']);
    $timeB = strtotime($b['meet_date'] . ' ' . $b['meet_time']);
    return $timeA - $timeB;
});

// Limit to top 4 meetings for the card
$all_today_meetings = array_slice($all_today_meetings, 0, 4);

usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$all_notifications = array_slice($all_notifications, 0, 6);

// Tasks (Combining Personal, Team, and Project Tasks automatically)
$q_tasks = "SELECT title, priority, status, created_at FROM personal_taskboard WHERE user_id = ? 
            UNION ALL 
            SELECT task_title as title, priority, status, created_at FROM team_tasks WHERE assigned_to = ? 
            UNION ALL 
            SELECT task_title as title, priority, status, created_at FROM project_tasks WHERE assigned_to_user_id = ? 
            ORDER BY created_at DESC LIMIT 4";
$task_stmt = @mysqli_prepare($conn, $q_tasks);
if ($task_stmt) {
    mysqli_stmt_bind_param($task_stmt, "iii", $current_user_id, $current_user_id, $current_user_id);
    mysqli_stmt_execute($task_stmt);
    $tasks_result = mysqli_stmt_get_result($task_stmt);
} else { $tasks_result = null; }

// -------------------------------------------------------------------------
// 7. FETCH FINANCIAL ACCOUNTS DATA (KPIs, Charts, Ledger)
// -------------------------------------------------------------------------
$kpi = ['balance' => 0, 'income' => 0, 'expense' => 0, 'pending' => 0];
$kpi['income'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(credit_amount) as val FROM general_ledger"))['val'] ?? 0;
$kpi['expense'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger"))['val'] ?? 0;
$kpi['balance'] = $kpi['income'] - $kpi['expense'];
$kpi['pending'] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE status = 'Pending Approval'"))['val'] ?? 0;

$recent_transactions = [];
$res_recent = @mysqli_query($conn, "SELECT entry_date, party_name, entry_type, GREATEST(debit_amount, credit_amount) as amount FROM general_ledger ORDER BY entry_date DESC, id DESC LIMIT 5");
if($res_recent){
    while($row = mysqli_fetch_assoc($res_recent)){
        $recent_transactions[] = [ 'date' => date('d M', strtotime($row['entry_date'])), 'party' => $row['party_name'], 'type' => $row['entry_type'], 'amount' => $row['amount'] ];
    }
}

$cash_flow_income = []; $cash_flow_expense = [];
for($m=1; $m<=6; $m++) {
    $cash_flow_income[] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(credit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = $m AND YEAR(entry_date) = YEAR(CURDATE())"))['val'] ?? 0;
    $cash_flow_expense[] = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT SUM(debit_amount) as val FROM general_ledger WHERE MONTH(entry_date) = $m AND YEAR(entry_date) = YEAR(CURDATE())"))['val'] ?? 0;
}

$exp_labels = []; $exp_data = [];
$res_dist = @mysqli_query($conn, "SELECT remarks, SUM(debit_amount) as val FROM general_ledger WHERE debit_amount > 0 GROUP BY remarks ORDER BY val DESC LIMIT 4");
if($res_dist){ while($row = mysqli_fetch_assoc($res_dist)){ $exp_labels[] = $row['remarks']; $exp_data[] = $row['val']; } }
if(empty($exp_labels)){ $exp_labels = ['No Data']; $exp_data = [0]; }

$inv_status_data = [0, 0, 0];
$res_inv = @mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM invoices GROUP BY status");
if($res_inv){
    while($row = mysqli_fetch_assoc($res_inv)){
        if($row['status'] == 'Paid' || $row['status'] == 'Approved') $inv_status_data[0] += $row['cnt'];
        if($row['status'] == 'Pending Approval') $inv_status_data[1] += $row['cnt'];
        if($row['status'] == 'Rejected') $inv_status_data[2] += $row['cnt'];
    }
}

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Dashboard - <?php echo htmlspecialchars($employee_name); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; }
        
        .card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .card-body { padding: 1.5rem; flex-grow: 1; }

        .progress-ring-circle { transition: stroke-dashoffset 0.35s; transform: rotate(-90deg); transform-origin: 50% 50%; }

        /* Meetings Timeline */
        .meeting-timeline { position: relative; }
        .meeting-timeline::before { content: ''; position: absolute; left: 80px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .meeting-row-wrapper { position: relative; margin-bottom: 1.5rem; }
        .meeting-dot { position: absolute; left: 76px; top: 10px; width: 10px; height: 10px; border-radius: 50%; z-index: 10; border: 2px solid white; box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .meeting-flex-container { display: flex; align-items: flex-start; gap: 24px; }
        .meeting-time-label { width: 68px; text-align: right; flex-shrink: 0; font-weight: 700; font-size: 12px; color: #64748b; padding-top: 4px; }
        .meeting-content-box { background-color: #f8fafc; padding: 12px; border-radius: 0.75rem; border: 1px solid #f1f5f9; flex-grow: 1; }

        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .dashboard-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; align-items: stretch; margin-bottom: 1.5rem;}
        
        #mainContent { margin-left: 90px; width: calc(100% - 90px); transition: all 0.3s; }
        @media (max-width: 1024px) {
            .dashboard-container { grid-template-columns: 1fr; }
            #mainContent { margin-left: 0; width: 100%; }
            .col-span-12, .col-span-3, .col-span-4, .col-span-5, .col-span-6, .col-span-8 { grid-column: span 12 !important; }
        }
    </style>
</head>
<body class="bg-slate-100">

    <main id="mainContent" class="p-6 lg:p-8 min-h-screen">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Accounts Dashboard</h1>
                <p class="text-slate-500 text-sm mt-1">Financial & operations overview for <b><?php echo htmlspecialchars($employee_name); ?></b></p>
            </div>
            <div class="flex gap-3">
                <div class="bg-white border border-gray-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 shadow-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-teal-600"></i> <?php echo date("d M Y"); ?>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Net Balance</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">₹<?php echo number_format($kpi['balance']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Income</p>
                            <h3 class="text-2xl font-bold text-emerald-600 mt-1">₹<?php echo number_format($kpi['income']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl"><i class="fa-solid fa-arrow-trend-down"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-3 card">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Expense</p>
                            <h3 class="text-2xl font-bold text-red-600 mt-1">₹<?php echo number_format($kpi['expense']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl"><i class="fa-solid fa-arrow-trend-up"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-3 card border-l-4 border-l-amber-500">
                <div class="card-body p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pending Approvals</p>
                            <h3 class="text-2xl font-bold text-amber-600 mt-1">₹<?php echo number_format($kpi['pending']); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                 <?php include '../attendance_card.php'; ?>
            </div>

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-slate-800 text-lg">Leave Details</h3>
                            <span class="text-xs font-bold bg-slate-100 text-gray-500 px-2 py-1 rounded"><?php echo date('M Y'); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-teal-600"></div><span class="font-bold text-slate-700 w-8"><?php echo $stats_ontime; ?></span><span class="text-sm text-gray-500">On Time</span></div>
                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-green-500"></div><span class="font-bold text-slate-700 w-8"><?php echo $stats_late; ?></span><span class="text-sm text-gray-500">Late</span></div>
                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div><span class="font-bold text-slate-700 w-8"><?php echo $stats_wfh; ?></span><span class="text-sm text-gray-500">Work From Home</span></div>
                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div><span class="font-bold text-slate-700 w-8"><?php echo $stats_absent; ?></span><span class="text-sm text-gray-500">Absent</span></div>
                                
                                <div class="flex items-center gap-3 mt-4 pt-4 border-t border-slate-100">
                                    <i class="fa-solid fa-plane-departure text-rose-400 w-2.5 text-center"></i>
                                    <span class="font-bold text-slate-700 w-8"><?php echo $current_month_leaves; ?></span>
                                    <span class="text-sm text-gray-500">Leaves Taken</span>
                                </div>
                            </div>
                            <div class="relative"><div id="attendanceChart" class="w-32 h-32"></div></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="font-bold text-slate-800 text-lg mb-4">Leave Balance</h3>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-teal-50 p-3 rounded-xl text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Total</p>
                                <p class="text-2xl font-bold text-teal-700"><?php echo $leaves_total; ?></p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Taken</p>
                                <p class="text-2xl font-bold text-blue-700"><?php echo $leaves_taken; ?></p>
                            </div>
                            <div class="bg-green-50 p-3 rounded-xl text-center">
                                <p class="text-[10px] text-gray-500 font-bold uppercase">Left</p>
                                <p class="text-2xl font-bold text-green-700"><?php echo $leaves_remaining; ?></p>
                            </div>
                        </div>
                        <a href="../employee/leave_request.php" class="block w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-3 rounded-lg text-center transition shadow-lg shadow-teal-200">
                            <i class="fa-solid fa-plus mr-2"></i> APPLY NEW LEAVE
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card overflow-hidden">
                    <div class="bg-teal-700 p-8 flex flex-col items-center text-center">
                        <div class="relative mb-3">
                            <img src="<?php echo $profile_img; ?>" class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover">
                            <div class="absolute bottom-1 right-1 w-6 h-6 bg-green-400 border-2 border-white rounded-full"></div>
                        </div>
                        <h2 class="text-white font-bold text-lg"><?php echo htmlspecialchars($employee_name); ?></h2>
                        <p class="text-teal-200 text-sm mb-3"><?php echo htmlspecialchars($employee_role); ?></p>
                        <span class="bg-white/20 text-white text-xs px-3 py-1 rounded-full font-bold">Verified Account</span>
                    </div>
                    <div class="card-body space-y-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700"><i class="fa-solid fa-phone"></i></div>
                            <div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Phone</p>
                                <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($employee_phone); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-700"><i class="fa-solid fa-envelope"></i></div>
                            <div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Email</p>
                                <p class="text-sm font-semibold text-slate-800 truncate w-40" title="<?php echo htmlspecialchars($employee_email); ?>"><?php echo htmlspecialchars($employee_email); ?></p>
                            </div>
                        </div>
                        <hr class="border-dashed border-gray-200">
                        <div class="bg-green-50 p-3 rounded-lg flex justify-between items-center">
                            <div class="flex items-center gap-2"><i class="fa-solid fa-calendar-check text-green-600"></i><span class="text-xs font-bold text-gray-600">Joined</span></div>
                            <span class="text-xs font-bold text-slate-800"><?php echo $joining_date; ?></span>
                        </div>
                        <div class="mt-6 pt-6 border-t border-dashed border-gray-200">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Professional Info</h4>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Experience</p>
                                    <p class="text-xs font-bold text-slate-800 mt-1"><?php echo htmlspecialchars($experience_label); ?></p>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase">Department</p>
                                    <p class="text-xs font-bold text-slate-800 mt-1"><?php echo htmlspecialchars($department); ?></p>
                                </div>
                            </div>
                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                <p class="text-[9px] text-gray-400 font-bold uppercase">Reporting To</p>
                                <p class="text-xs font-bold text-slate-800 mt-1"><?php echo htmlspecialchars($reporting_to); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="dashboard-container">
            <div class="col-span-12 card">
                <div class="card-body">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="fa-solid fa-bolt text-teal-600 text-lg"></i>
                        <h3 class="font-bold text-slate-800 text-lg">Accounts Quick Actions</h3>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <a href="new_invoice.php" class="bg-slate-50 border border-slate-100 rounded-xl p-5 flex flex-col items-center justify-center gap-3 hover:border-teal-500 hover:bg-white hover:shadow-lg transition group">
                            <i class="fa-solid fa-file-invoice text-3xl text-slate-400 group-hover:text-teal-600"></i><span class="text-sm font-semibold text-slate-700">Create Invoice</span>
                        </a>
                        <a href="ledger.php" class="bg-slate-50 border border-slate-100 rounded-xl p-5 flex flex-col items-center justify-center gap-3 hover:border-teal-500 hover:bg-white hover:shadow-lg transition group">
                            <i class="fa-solid fa-book-open text-3xl text-slate-400 group-hover:text-teal-600"></i><span class="text-sm font-semibold text-slate-700">General Ledger</span>
                        </a>
                        <a href="purchase_order.php" class="bg-slate-50 border border-slate-100 rounded-xl p-5 flex flex-col items-center justify-center gap-3 hover:border-teal-500 hover:bg-white hover:shadow-lg transition group">
                            <i class="fa-solid fa-cart-plus text-3xl text-slate-400 group-hover:text-teal-600"></i><span class="text-sm font-semibold text-slate-700">New PO</span>
                        </a>
                        <a href="payslip.php" class="bg-slate-50 border border-slate-100 rounded-xl p-5 flex flex-col items-center justify-center gap-3 hover:border-teal-500 hover:bg-white hover:shadow-lg transition group">
                            <i class="fa-solid fa-users-gear text-3xl text-slate-400 group-hover:text-teal-600"></i><span class="text-sm font-semibold text-slate-700">Payroll Gen</span>
                        </a>
                        <a href="accounts_reports.php" class="bg-slate-50 border border-slate-100 rounded-xl p-5 flex flex-col items-center justify-center gap-3 hover:border-teal-500 hover:bg-white hover:shadow-lg transition group">
                            <i class="fa-solid fa-chart-pie text-3xl text-slate-400 group-hover:text-teal-600"></i><span class="text-sm font-semibold text-slate-700">Reports</span>
                        </a>
                        <a href="masters.php" class="bg-slate-50 border border-slate-100 rounded-xl p-5 flex flex-col items-center justify-center gap-3 hover:border-teal-500 hover:bg-white hover:shadow-lg transition group">
                            <i class="fa-solid fa-database text-3xl text-slate-400 group-hover:text-teal-600"></i><span class="text-sm font-semibold text-slate-700">Masters</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Notifications</h3>
                        <a href="../view_announcements.php" class="text-xs text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded">View All</a>
                    </div>
                    <div class="space-y-4 custom-scroll overflow-y-auto" style="max-height: 240px; padding-right: 4px;">
                        <?php if(!empty($all_notifications)): ?>
                            <?php foreach($all_notifications as $notif): ?>
                            <div class="flex gap-3 items-start border-b border-slate-50 pb-3 last:border-0 hover:bg-slate-50 p-2 rounded-lg transition">
                                <div class="w-8 h-8 rounded-full <?php echo $notif['color']; ?> flex items-center justify-center font-bold text-xs shrink-0">
                                    <i class="fa-solid <?php echo $notif['icon']; ?>"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-[10px] text-gray-400 shrink-0 mt-0.5"><?php echo date("h:i A", strtotime($notif['time'])); ?></p>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class='text-center py-8 text-sm text-slate-400'>No new notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg">Meetings</h3>
                        <button class="text-[10px] text-gray-500 bg-slate-100 px-2 py-1 rounded font-bold uppercase tracking-widest">Today</button>
                    </div>
                    <div class="meeting-timeline custom-scroll overflow-y-auto" style="max-height: 230px;">
                        <?php if(!empty($all_today_meetings)) { 
                            $color_palette = ['bg-teal-500', 'bg-indigo-500', 'bg-rose-500', 'bg-orange-500'];
                            $c_idx = 0;
                            foreach($all_today_meetings as $meet):
                                $is_past = (strtotime($meet['meet_time']) < time() && $meet['meet_date'] == $today) ? 'opacity-50' : '';
                                $dot_color = (strtotime($meet['meet_time']) < time() && $meet['meet_date'] == $today) ? 'bg-slate-300' : $color_palette[$c_idx % 4];
                                $c_idx++;
                        ?>
                        <div class="meeting-row-wrapper <?php echo $is_past; ?>">
                            <div class="meeting-dot <?php echo $dot_color; ?>"></div>
                            <div class="meeting-flex-container">
                                <div class="meeting-time-label">
                                    <span class="block text-[9px] text-teal-600 mb-0.5"><?php echo ($meet['meet_date'] == $today) ? 'Today' : date("d M", strtotime($meet['meet_date'])); ?></span>
                                    <?php echo date("h:i A", strtotime($meet['meet_time'])); ?>
                                </div>
                                <div class="meeting-content-box shadow-sm">
                                    <h4 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($meet['title']); ?></h4>
                                    
                                    <?php if(!empty($meet['meet_link'])): 
                                        $actual_link = trim($meet['meet_link']);
                                        if (strpos($actual_link, '.') !== false) {
                                            if (!preg_match("~^(?:f|ht)tps?://~i", $actual_link) && strpos($actual_link, '/') !== 0) {
                                                $actual_link = "https://" . $actual_link;
                                            }
                                        } else {
                                            $actual_link = $path_to_root . "team_chat.php?room_id=" . urlencode($actual_link);
                                        }
                                    ?>
                                        <a href="<?php echo htmlspecialchars($actual_link); ?>" <?php echo (strpos($actual_link, 'team_chat.php') === false) ? 'target="_blank"' : ''; ?> class="text-[10px] text-indigo-600 font-bold mt-1.5 inline-block hover:underline">
                                            <i class="fa-solid fa-video"></i> Join Meeting
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; } else { echo "<div class='text-center py-8 text-sm text-slate-400'>No meetings today.</div>"; } ?>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">My Tasks</h3>
                        <a href="self_task.php" class="text-xs text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded">View All</a>
                    </div>
                    <div class="space-y-3 custom-scroll overflow-y-auto" style="max-height: 240px; padding-right: 4px;">
                        <?php if($tasks_result && mysqli_num_rows($tasks_result) > 0) { 
                            while($task = mysqli_fetch_assoc($tasks_result)):
                                // Fixed Case Sensitivity logic for marking tasks correctly 
                                $task_status = strtolower($task['status']);
                                $checked = ($task_status == 'completed') ? 'checked' : '';
                                $line_through = ($task_status == 'completed') ? 'line-through text-gray-400' : 'text-slate-700';
                                $p_col = 'bg-slate-100 text-slate-600';
                                if(strtolower($task['priority']) == 'high' || strtolower($task['priority']) == 'critical') $p_col = 'bg-red-50 text-red-600';
                        ?>
                        <label class="flex items-start gap-3 p-3 border border-slate-100 rounded-xl hover:bg-slate-50">
                            <input type="checkbox" class="mt-1 w-4 h-4 text-teal-600 rounded" <?php echo $checked; ?> disabled>
                            <div class="flex-1">
                                <p class="text-sm font-semibold <?php echo $line_through; ?>"><?php echo htmlspecialchars($task['title']); ?></p>
                                <div class="mt-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase <?php echo $p_col; ?>"><?php echo htmlspecialchars($task['priority']); ?></span></div>
                            </div>
                        </label>
                        <?php endwhile; } else { echo "<div class='text-center py-8 text-sm text-slate-400'>No pending tasks.</div>"; } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-8 card">
                <div class="card-body">
                    <h3 class="font-bold text-slate-800 text-lg mb-4">Cash Flow (Current Year)</h3>
                    <div class="relative h-64"><canvas id="cashFlowChart"></canvas></div>
                </div>
            </div>
            
            <div class="col-span-12 lg:col-span-4 card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg">Recent Transactions</h3>
                        <a href="ledger.php" class="text-xs font-bold text-teal-600 hover:underline">Ledger</a>
                    </div>
                    <div class="custom-scroll overflow-y-auto" style="max-height: 250px;">
                        <div class="space-y-4">
                            <?php if(empty($recent_transactions)): echo "<div class='text-center py-8 text-slate-400 text-sm'>No recent transactions.</div>"; endif; ?>
                            <?php foreach($recent_transactions as $txn): 
                                $icon = 'fa-file-invoice'; $bg = 'bg-blue-50 text-blue-600';
                                if(strtolower($txn['type']) == 'expense' || strtolower($txn['type']) == 'expenses') { $icon='fa-receipt'; $bg='bg-red-50 text-red-600'; }
                            ?>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl <?php echo $bg; ?> flex items-center justify-center text-lg"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                                    <div>
                                        <p class="font-bold text-sm text-slate-800 truncate w-32"><?php echo htmlspecialchars($txn['party']); ?></p>
                                        <p class="text-[11px] text-slate-500 font-medium"><?php echo htmlspecialchars($txn['date']); ?> • <?php echo htmlspecialchars($txn['type']); ?></p>
                                    </div>
                                </div>
                                <div class="font-bold text-sm text-slate-800">₹<?php echo number_format($txn['amount']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="col-span-12 lg:col-span-6 card">
                <div class="card-body">
                    <h3 class="font-bold text-slate-800 text-lg mb-4">Expense Distribution</h3>
                    <div class="relative h-56"><canvas id="expenseChart"></canvas></div>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-6 card">
                <div class="card-body">
                    <h3 class="font-bold text-slate-800 text-lg mb-4">Invoice Approval Status</h3>
                    <div class="relative h-56"><canvas id="invoiceBarChart"></canvas></div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // 1. LIVE TIMER & PROGRESS RING 
        const timerElement = document.getElementById('liveTimer');
        const progressRing = document.getElementById('progressRing');
        const isRunning = timerElement ? timerElement.getAttribute('data-running') === 'true' : false;
        let totalSeconds = timerElement ? parseInt(timerElement.getAttribute('data-total')) || 0 : 0;
        const startTime = new Date().getTime(); 

        function updateTimer() {
            if (!isRunning || !timerElement) return; 
            const now = new Date().getTime();
            const diffSeconds = Math.floor((now - startTime) / 1000);
            const currentTotal = totalSeconds + diffSeconds;
            const hours = Math.floor(currentTotal / 3600);
            const minutes = Math.floor((currentTotal % 3600) / 60);
            const seconds = currentTotal % 60;
            
            timerElement.innerText = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            const progress = Math.min(currentTotal / 32400, 1);
            if(progressRing) progressRing.style.strokeDashoffset = 440 - (progress * 440);
        }
        if (isRunning) setInterval(updateTimer, 1000);

        // 2. APEXCHART FOR LEAVE DETAILS (ATTENDANCE STATS)
        const attData = [<?php echo $stats_ontime; ?>, <?php echo $stats_late; ?>, <?php echo $stats_wfh; ?>, <?php echo $stats_absent; ?>];
        const hasData = attData.some(val => val > 0);
        
        var options = {
            series: hasData ? attData : [1],
            labels: hasData ? ['On Time', 'Late', 'WFH', 'Absent'] : ['No Data'],
            colors: hasData ? ['#0d9488', '#22c55e', '#f97316', '#ef4444'] : ['#e2e8f0'],
            chart: { type: 'donut', height: 130 },
            plotOptions: { donut: { size: '75%' } },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { enabled: hasData }
        };
        if(document.querySelector("#attendanceChart")) {
            new ApexCharts(document.querySelector("#attendanceChart"), options).render();
        }

        // 3. FINANCIAL CHARTS
        const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: "'Inter', sans-serif" } } } } };

        if(document.getElementById('cashFlowChart')) {
            new Chart(document.getElementById('cashFlowChart'), {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        { label: 'Income', data: <?php echo json_encode($cash_flow_income); ?>, backgroundColor: '#0d9488', borderRadius: 4 },
                        { label: 'Expense', data: <?php echo json_encode($cash_flow_expense); ?>, backgroundColor: '#ef4444', borderRadius: 4 }
                    ]
                },
                options: { ...commonOptions, scales: { y: { beginAtZero: true, grid: { borderDash: [2, 2], color: '#f1f5f9' } }, x: { grid: { display: false } } } }
            });
        }

        if(document.getElementById('expenseChart')) {
            new Chart(document.getElementById('expenseChart'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($exp_labels); ?>,
                    datasets: [{ data: <?php echo json_encode($exp_data); ?>, backgroundColor: ['#e67e22', '#3b82f6', '#10b981', '#6366f1'], borderWidth: 0 }]
                },
                options: { ...commonOptions, cutout: '70%' }
            });
        }

        if(document.getElementById('invoiceBarChart')) {
            new Chart(document.getElementById('invoiceBarChart'), {
                type: 'bar', indexAxis: 'y',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{ label: 'Invoices', data: <?php echo json_encode($inv_status_data); ?>, backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderRadius: 4, barThickness: 20 }]
                },
                options: { ...commonOptions, scales: { x: { grid: { display: false } }, y: { grid: { display: false } } } }
            });
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>