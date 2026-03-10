<?php
// Fixes "headers already sent" error by turning on output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// =========================================================================
// 0. AJAX INTERCEPTOR (Strict JSON Enforcement)
// =========================================================================
$is_ajax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']));
if ($is_ajax) {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Keeping Paths untouched as requested
$sidebarPath = ''; $headerPath = '';
if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php'; $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php'; $headerPath = '../header.php';
}

// =========================================================================
// ENTERPRISE SECURITY: Role-Based Access Control (RBAC)
// =========================================================================
$user_role = $_SESSION['role'] ?? 'HR'; 
$current_user_id = $_SESSION['user_id'] ?? 0;

$is_hr = ($user_role === 'HR' || $user_role === 'HR Executive'); 
$can_generate = in_array($user_role, ['HR', 'HR Executive', 'Admin']);
$can_credit = in_array($user_role, ['Accounts', 'CFO', 'Admin']);
$can_approve = in_array($user_role, ['CFO', 'Admin']);

if (isset($conn)) {
    
    // Only run database structure patches on normal page load
    if (!$is_ajax) {
        $check_col = $conn->query("SHOW COLUMNS FROM `employee_onboarding` LIKE 'salary_type'");
        if ($check_col && $check_col->num_rows == 0) {
            $conn->query("ALTER TABLE `employee_onboarding` ADD COLUMN `salary_type` ENUM('Monthly','Annual') DEFAULT 'Annual'");
        }

        $create_table = "CREATE TABLE IF NOT EXISTS `employee_salary` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `salary_month` DATE NOT NULL,
          `basic` decimal(10,2) DEFAULT 0.00,
          `da` decimal(10,2) DEFAULT 0.00,
          `hra` decimal(10,2) DEFAULT 0.00,
          `allowance` decimal(10,2) DEFAULT 0.00,
          `esi` decimal(10,2) DEFAULT 0.00,
          `pf` decimal(10,2) DEFAULT 0.00,
          `leave_deduction` decimal(10,2) DEFAULT 0.00,
          `professional_tax` decimal(10,2) DEFAULT 0.00,
          `gross_salary` decimal(12,2) DEFAULT 0.00,
          `net_salary` decimal(12,2) DEFAULT 0.00,
          `credit_status` ENUM('Pending', 'Credited') DEFAULT 'Pending',
          `credit_date` date DEFAULT NULL,
          `approval_status` ENUM('Draft', 'Pending', 'Approved', 'Rejected') DEFAULT 'Draft',
          `is_deleted` TINYINT(1) DEFAULT 0,
          `created_by` INT DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `approved_by` INT DEFAULT NULL,
          `approved_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_salary` (`user_id`, `salary_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $conn->query($create_table);

        $salary_columns = [
            'is_deleted' => 'TINYINT(1) DEFAULT 0',
            'conveyance' => 'decimal(10,2) DEFAULT 0.00',
            'medical' => 'decimal(10,2) DEFAULT 0.00',
            'others_earnings' => 'decimal(10,2) DEFAULT 0.00',
            'tds' => 'decimal(10,2) DEFAULT 0.00',
            'labour_welfare' => 'decimal(10,2) DEFAULT 0.00',
            'others_deductions' => 'decimal(10,2) DEFAULT 0.00',
            'payment_mode' => "varchar(50) DEFAULT 'Bank Transfer'",
            'transaction_reference' => 'varchar(100) DEFAULT NULL',
            'credited_by' => 'INT DEFAULT NULL',
            'credited_at' => 'DATETIME DEFAULT NULL'
        ];

        foreach ($salary_columns as $col => $def) {
            $chk = $conn->query("SHOW COLUMNS FROM `employee_salary` LIKE '$col'");
            if ($chk && $chk->num_rows == 0) {
                $conn->query("ALTER TABLE `employee_salary` ADD COLUMN `$col` $def");
            }
        }

        $conn->query("UPDATE employee_salary SET approval_status = 'Approved' WHERE credit_status = 'Credited' AND approval_status != 'Approved'");
    }

    // 4. INTERNAL API LOGIC
    if ($is_ajax) {
        ob_clean(); 
        header('Content-Type: application/json');
        $action = $_POST['ajax_action'];

        if (!function_exists('sendJson')) {
            function sendJson($success, $data = []) {
                ob_clean();
                $response = ['success' => $success];
                if (is_array($data)) $response = array_merge($response, $data);
                else $response['message'] = $data;
                echo json_encode($response);
                exit;
            }
        }

        try {
            // --- SAVE OR UPDATE SALARY ---
            if ($action === 'save_salary') {
                if (!$can_generate && !$can_credit) sendJson(false, 'Unauthorized Role.');

                $id = (int)($_POST['id'] ?? 0);
                $user_id = (int)($_POST['user_id'] ?? 0);
                $salary_month_raw = $_POST['salary_month'] ?? '';
                
                if (!preg_match('/^\d{4}-\d{2}$/', $salary_month_raw)) sendJson(false, 'Invalid month format.');
                $salary_month_db = $salary_month_raw . '-01'; 

                if ($id === 0) {
                    $chk_exist = $conn->prepare("SELECT id FROM employee_salary WHERE user_id = ? AND salary_month = ?");
                    $chk_exist->bind_param("is", $user_id, $salary_month_db);
                    $chk_exist->execute();
                    $res_exist = $chk_exist->get_result();
                    if ($res_exist->num_rows > 0) $id = $res_exist->fetch_assoc()['id']; 
                    $chk_exist->close();
                }

                $credit_status = 'Pending';
                $credit_date = null;
                $payment_mode = null;
                $trans_ref = null;
                $credited_by = null;
                $credited_at = null;

                if ($can_credit && isset($_POST['credit_status'])) {
                    $credit_status = $_POST['credit_status'];
                    if ($credit_status === 'Credited') {
                        if ($id > 0) {
                            $app_check_stmt = $conn->prepare("SELECT approval_status FROM employee_salary WHERE id = ? AND is_deleted = 0");
                            $app_check_stmt->bind_param("i", $id);
                            $app_check_stmt->execute();
                            $app_stat = $app_check_stmt->get_result()->fetch_assoc()['approval_status'] ?? 'Draft';
                            $app_check_stmt->close();
                            
                            if ($app_stat !== 'Approved') sendJson(false, 'Workflow Error: Salary must be Approved by CFO before it can be Credited.');
                        } else {
                            sendJson(false, 'Workflow Error: Cannot credit a new draft salary directly.');
                        }

                        $credit_date = !empty($_POST['credit_date']) ? $_POST['credit_date'] : null;
                        $payment_mode = $_POST['payment_mode'] ?? 'Bank Transfer';
                        $trans_ref = $_POST['transaction_reference'] ?? '';
                        $credited_by = $current_user_id;
                        $credited_at = date('Y-m-d H:i:s');
                    }
                }

                $basic = (float)($_POST['basic'] ?? 0);
                $da = (float)($_POST['da'] ?? 0);
                $hra = (float)($_POST['hra'] ?? 0);
                $conveyance = (float)($_POST['conveyance'] ?? 0);
                $allowance = (float)($_POST['allowance'] ?? 0);
                $medical = (float)($_POST['medical'] ?? 0);
                $others_earnings = (float)($_POST['others_earnings'] ?? 0);

                $tds = (float)($_POST['tds'] ?? 0);
                $esi = (float)($_POST['esi'] ?? 0);
                $pf = (float)($_POST['pf'] ?? 0);
                $leave_deduction = (float)($_POST['leave_deduction'] ?? 0);
                $professional_tax = (float)($_POST['professional_tax'] ?? 0);
                $labour_welfare = (float)($_POST['labour_welfare'] ?? 0);
                $others_deductions = (float)($_POST['others_deductions'] ?? 0);

                $gross_salary = $basic + $da + $hra + $conveyance + $allowance + $medical + $others_earnings;
                $total_deductions = $tds + $esi + $pf + $leave_deduction + $professional_tax + $labour_welfare + $others_deductions;
                $net_salary = $gross_salary - $total_deductions;

                if ($net_salary < 0) sendJson(false, 'Financial Integrity Error: Net salary cannot be negative.');

                if ($id > 0) { 
                    $lock_stmt = $conn->prepare("SELECT credit_status, credit_date, payment_mode, transaction_reference, credited_by, credited_at FROM employee_salary WHERE id = ?");
                    $lock_stmt->bind_param("i", $id);
                    $lock_stmt->execute();
                    $lock_res = $lock_stmt->get_result()->fetch_assoc();
                    $lock_stmt->close();
                    
                    if ($lock_res && $lock_res['credit_status'] === 'Credited' && $credit_status !== 'Pending') {
                        sendJson(false, 'Payroll Locked. This salary has already been credited.');
                    }
                    
                    if (!$can_credit && $lock_res) {
                        $credit_status = $lock_res['credit_status'];
                        $credit_date = $lock_res['credit_date'];
                        $payment_mode = $lock_res['payment_mode'];
                        $trans_ref = $lock_res['transaction_reference'];
                        $credited_by = $lock_res['credited_by'];
                        $credited_at = $lock_res['credited_at'];
                    }

                    $new_approval_status = 'Draft';
                    if ($credit_status === 'Credited') { $new_approval_status = 'Approved'; }

                    $stmt = $conn->prepare("UPDATE employee_salary SET basic=?, da=?, hra=?, conveyance=?, allowance=?, medical=?, others_earnings=?, tds=?, esi=?, pf=?, leave_deduction=?, professional_tax=?, labour_welfare=?, others_deductions=?, gross_salary=?, net_salary=?, credit_status=?, credit_date=?, payment_mode=?, transaction_reference=?, credited_by=?, credited_at=?, approval_status=?, is_deleted=0 WHERE id=?");
                    if(!$stmt) throw new Exception("DB Prepare Error: " . $conn->error);
                    
                    $stmt->bind_param("ddddddddddddddddssssissi", $basic, $da, $hra, $conveyance, $allowance, $medical, $others_earnings, $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions, $gross_salary, $net_salary, $credit_status, $credit_date, $payment_mode, $trans_ref, $credited_by, $credited_at, $new_approval_status, $id);
                } else { 
                    $stmt = $conn->prepare("INSERT INTO employee_salary (user_id, salary_month, basic, da, hra, conveyance, allowance, medical, others_earnings, tds, esi, pf, leave_deduction, professional_tax, labour_welfare, others_deductions, gross_salary, net_salary, credit_status, credit_date, payment_mode, transaction_reference, approval_status, created_by, credited_by, credited_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?, ?)");
                    if(!$stmt) throw new Exception("DB Prepare Error: " . $conn->error);
                    $stmt->bind_param("isddddddddddddddddssssiis", $user_id, $salary_month_db, $basic, $da, $hra, $conveyance, $allowance, $medical, $others_earnings, $tds, $esi, $pf, $leave_deduction, $professional_tax, $labour_welfare, $others_deductions, $gross_salary, $net_salary, $credit_status, $credit_date, $payment_mode, $trans_ref, $current_user_id, $credited_by, $credited_at);
                }
                
                if ($stmt->execute()) { sendJson(true); } 
                else { sendJson(false, 'DB Execute Error: ' . $stmt->error); }
                $stmt->close();
            }
            
            // --- SOFT DELETE SALARY ---
            if ($action === 'delete_salary') {
                if (!$can_generate) sendJson(false, 'Unauthorized Action');
                $id = (int)($_POST['id'] ?? 0);
                
                $lock_stmt = $conn->prepare("SELECT credit_status FROM employee_salary WHERE id = ?");
                $lock_stmt->bind_param("i", $id);
                $lock_stmt->execute();
                $lock_res = $lock_stmt->get_result()->fetch_assoc();
                $lock_stmt->close();

                if ($lock_res && $lock_res['credit_status'] === 'Credited') sendJson(false, 'Payroll Locked. Cannot delete a credited salary.');

                $del_stmt = $conn->prepare("UPDATE employee_salary SET is_deleted = 1 WHERE id = ?");
                if(!$del_stmt) throw new Exception("DB Prepare Error: " . $conn->error);
                $del_stmt->bind_param("i", $id);
                $del_stmt->execute();
                $del_stmt->close();
                
                sendJson(true);
            }

            // --- DIRECT APPROVE ACTION (CFO Only) ---
            if ($action === 'approve_salary') {
                if (!$can_approve) sendJson(false, 'Unauthorized Action');
                $id = (int)($_POST['id'] ?? 0);
                $app_stmt = $conn->prepare("UPDATE employee_salary SET approval_status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND is_deleted = 0");
                if(!$app_stmt) throw new Exception("DB Prepare Error: " . $conn->error);
                $app_stmt->bind_param("ii", $current_user_id, $id);
                $app_stmt->execute();
                $app_stmt->close();
                sendJson(true);
            }

            // --- SMART BULK CFO APPROVAL ---
            if ($action === 'ask_approval_bulk_selected') {
                if (!$can_generate) sendJson(false, 'Unauthorized Action');
                
                $raw_ids = $_POST['ids'] ?? '[]';
                $ids = json_decode($raw_ids, true);
                if (empty($ids) || !is_array($ids)) sendJson(false, 'No salaries selected for approval.');

                $id_list = implode(',', array_map('intval', $ids));
                $sql = "UPDATE employee_salary SET approval_status = 'Pending' WHERE id IN ($id_list) AND approval_status = 'Draft' AND is_deleted = 0";
                
                if ($conn->query($sql)) {
                    $affected = $conn->affected_rows;
                    sendJson(true, ['updated' => $affected]);
                } else {
                    sendJson(false, 'Database update failed: ' . $conn->error);
                }
            }

            // --- AUTO GENERATE ALL (With Smart LOP Engine) ---
            if ($action === 'auto_generate') {
                if (!$can_generate) { echo json_encode(['success' => false, 'message' => 'Unauthorized Action']); exit; }
                
                $month = $_POST['month'] ?? '';
                if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid month format.']); exit;
                }
                $month_db = $month . '-01';
                $month_end = date('Y-m-t', strtotime($month_db));
                $days_in_month = date('t', strtotime($month_db)); 
                
                // Standardize Sundays Calculation
                $sundays = 0;
                for ($i = 1; $i <= $days_in_month; $i++) {
                    if (date('N', strtotime($month . '-' . sprintf('%02d', $i))) == 7) $sundays++;
                }
                
                $conn->begin_transaction();
                try {
                    // SMART FIX: Count actual Present days instead of Absent rows
                    $pres_stmt = $conn->prepare("SELECT user_id, COUNT(*) as cnt FROM attendance WHERE date >= ? AND date <= ? AND status != 'Absent' GROUP BY user_id");
                    $pres_stmt->bind_param("ss", $month_db, $month_end);
                    $pres_stmt->execute();
                    $pres_res = $pres_stmt->get_result();
                    $presents = [];
                    while($pr = $pres_res->fetch_assoc()) $presents[$pr['user_id']] = $pr['cnt'];
                    $pres_stmt->close();

                    $lr_stmt = $conn->prepare("SELECT user_id, SUM(DATEDIFF(LEAST(end_date, ?), GREATEST(start_date, ?)) + 1) as cnt FROM leave_requests WHERE status = 'Approved' AND start_date <= ? AND end_date >= ? GROUP BY user_id");
                    $lr_stmt->bind_param("ssss", $month_end, $month_db, $month_end, $month_db);
                    $lr_stmt->execute();
                    $lr_res = $lr_stmt->get_result();
                    $leaves = [];
                    while($lr = $lr_res->fetch_assoc()) $leaves[$lr['user_id']] = $lr['cnt'];
                    $lr_stmt->close();

                    $emps = $conn->query("SELECT id, salary, IFNULL(salary_type, 'Annual') as salary_type FROM employee_onboarding WHERE status = 'Completed'");
                    
                    while($emp = $emps->fetch_assoc()) {
                        $uid = $emp['id'];
                        $ctc = (float)$emp['salary'];
                        $sal_type = $emp['salary_type'];
                        
                        $check_stmt = $conn->prepare("SELECT id, is_deleted FROM employee_salary WHERE user_id = ? AND salary_month = ?");
                        $check_stmt->bind_param("is", $uid, $month_db);
                        $check_stmt->execute();
                        $exist_data = $check_stmt->get_result()->fetch_assoc();
                        $check_stmt->close();
                        
                        if (!$exist_data || $exist_data['is_deleted'] == 1) {
                            $present_days = $presents[$uid] ?? 0;
                            $approved_leaves = $leaves[$uid] ?? 0;
                            
                            // Calculate Payable & LOP days exactly
                            $payable_days = $present_days + $approved_leaves + $sundays;
                            if ($payable_days > $days_in_month) $payable_days = $days_in_month;
                            
                            $lop_days = $days_in_month - $payable_days;
                            if ($lop_days < 0) $lop_days = 0;

                            // Salary Math
                            $monthlyGross = ($sal_type === 'Annual') ? ($ctc / 12) : $ctc;
                            $basic = round($monthlyGross * 0.50, 2); 
                            $da = round($basic * 0.40, 2); 
                            $hra = round($basic * 0.15, 2);
                            $allowance = round($monthlyGross - ($basic + $da + $hra), 2);
                            if ($allowance < 0) $allowance = 0;
                            $gross = $basic + $da + $hra + $allowance;
                            
                            $per_day_salary = $gross / $days_in_month;
                            $leave_deduction = round($per_day_salary * $lop_days, 2);
                            
                            $pf = round($basic * 0.12, 2);
                            $esi = ($gross <= 21000) ? round($gross * 0.0075, 2) : 0;
                            $pt = ($gross > 15000) ? 200 : 0;
                            $deductions = $pf + $esi + $pt + $leave_deduction;
                            $net = $gross - $deductions;
                            if ($net < 0) $net = 0;

                            if ($exist_data && $exist_data['is_deleted'] == 1) {
                                $upd = $conn->prepare("UPDATE employee_salary SET basic=?, da=?, hra=?, allowance=?, esi=?, pf=?, professional_tax=?, leave_deduction=?, gross_salary=?, net_salary=?, is_deleted=0, approval_status='Draft' WHERE id=?");
                                $upd->bind_param("ddddddddddi", $basic, $da, $hra, $allowance, $esi, $pf, $pt, $leave_deduction, $gross, $net, $exist_data['id']);
                                $upd->execute();
                                $upd->close();
                            } else {
                                $insert_stmt = $conn->prepare("INSERT INTO employee_salary (user_id, salary_month, basic, da, hra, allowance, esi, pf, professional_tax, leave_deduction, gross_salary, net_salary, credit_status, approval_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Draft', ?)");
                                $insert_stmt->bind_param("isddddddddddi", $uid, $month_db, $basic, $da, $hra, $allowance, $esi, $pf, $pt, $leave_deduction, $gross, $net, $current_user_id);
                                $insert_stmt->execute();
                                $insert_stmt->close();
                            }
                        }
                    }
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => "Salaries Generated as Drafts successfully!"]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
                }
                exit;
            }
            
        } catch (Exception $globalError) {
            sendJson(false, 'System Error: ' . $globalError->getMessage());
        }
    }
}

// 3. MAIN DATA FETCHING (Now uses Smart Calendar LOP Logic)
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$month_db = $month_filter . '-01';
$month_end = date('Y-m-t', strtotime($month_db));
$days_in_month = date('t', strtotime($month_db));

// Pre-calculate Sundays for the month
$sundays = 0;
for ($i = 1; $i <= $days_in_month; $i++) {
    if (date('N', strtotime($month_filter . '-' . sprintf('%02d', $i))) == 7) $sundays++;
}

$employees_data = []; $grouped_data = [];
$tot_payroll = 0; $tot_credited = 0; $tot_pending = 0; $tot_deductions = 0;

if (isset($conn)) {
    // Note: We now fetch `present_days` instead of `absent_days` to perfectly calculate Un-punched LOPs
    $query = "SELECT e.id as user_id, CONCAT(e.first_name, ' ', IFNULL(e.last_name, '')) as name, 
                     e.emp_id_code as emp_code, e.profile_img, e.designation, e.department, 
                     e.salary as ctc, IFNULL(e.salary_type, 'Annual') as salary_type, IFNULL(e.total_leaves, 12) as total_leaves,
                     s.id as salary_id, s.salary_month, s.gross_salary, s.net_salary, s.credit_status, s.approval_status,
                     s.credit_date, s.payment_mode, s.transaction_reference,
                     s.basic, s.da, s.hra, s.conveyance, s.allowance, s.medical, s.others_earnings,
                     s.tds, s.esi, s.pf, s.leave_deduction, s.professional_tax, s.labour_welfare, s.others_deductions,
                     IFNULL(att.present_days, 0) as present_days,
                     IFNULL(lr.approved_leaves, 0) as approved_leaves
              FROM employee_onboarding e
              LEFT JOIN employee_salary s ON e.id = s.user_id AND s.salary_month = ? AND s.is_deleted = 0
              LEFT JOIN (
                  SELECT user_id, COUNT(*) as present_days FROM attendance 
                  WHERE date >= ? AND date <= ? AND status != 'Absent' 
                  GROUP BY user_id
              ) att ON att.user_id = e.id
              LEFT JOIN (
                  SELECT user_id, SUM(DATEDIFF(LEAST(end_date, ?), GREATEST(start_date, ?)) + 1) as approved_leaves
                  FROM leave_requests
                  WHERE status = 'Approved' AND start_date <= ? AND end_date >= ?
                  GROUP BY user_id
              ) lr ON lr.user_id = e.id
              WHERE e.status = 'Completed'";
              
    if (!empty($status_filter)) {
        $query .= " AND s.approval_status = ?";
        $stmt = $conn->prepare($query . " ORDER BY e.department ASC, e.first_name ASC");
        $stmt->bind_param("ssssssss", $month_db, $month_db, $month_end, $month_end, $month_db, $month_end, $month_db, $status_filter);
    } else {
        $stmt = $conn->prepare($query . " ORDER BY e.department ASC, e.first_name ASC");
        $stmt->bind_param("sssssss", $month_db, $month_db, $month_end, $month_end, $month_db, $month_end, $month_db);
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $dept = !empty($row['department']) ? trim($row['department']) : 'Unassigned Department';
            $row['department'] = $dept;
            
            // SMART CALENDAR LOGIC: Calculate exact LOP taking weekends into account
            $present = (int)$row['present_days'];
            $leaves = (int)$row['approved_leaves'];
            $payable = $present + $leaves + $sundays;
            if ($payable > $days_in_month) $payable = $days_in_month; // Cap to max days
            
            $lop = $days_in_month - $payable;
            if ($lop < 0) $lop = 0;

            // Push to row so JS can use it dynamically
            $row['days_in_month'] = $days_in_month;
            $row['sundays'] = $sundays;
            $row['payable_days'] = $payable;
            $row['lop_days'] = $lop;

            $employees_data[] = $row;
            if (!isset($grouped_data[$dept])) $grouped_data[$dept] = [];
            $grouped_data[$dept][] = $row;
            
            if (!empty($row['salary_id'])) {
                $gross = (float)$row['gross_salary'];
                $net = (float)$row['net_salary'];
                $deductions = (float)($row['tds'] + $row['esi'] + $row['pf'] + $row['leave_deduction'] + $row['professional_tax'] + $row['labour_welfare'] + $row['others_deductions']);
                
                $tot_payroll += $gross;
                $tot_deductions += $deductions;
                if ($row['credit_status'] === 'Credited') $tot_credited += $net;
                if ($row['approval_status'] === 'Pending') $tot_pending += $net;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Payroll Management | WorkAck HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #f97316; --success: #22c55e; --warning: #1b5a5a; --danger: #ef4444; --gray: #6b7280; --bg: #f3f4f6; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; overflow-x: hidden; }
        .main-content { margin-left: 100px; padding-top: 10px; padding-left: 25px; padding-right: 25px; padding-bottom: 30px; min-height: 100vh; box-sizing: border-box; transition: all 0.3s ease; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-left: 15px; padding-right: 15px; padding-top: 80px; } }
        .dashboard { max-width: 1400px; margin: 0 auto; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card { background: #fff; padding: 22px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
        .card h3 { margin: 0 0 10px; font-size: 13px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px;}
        .card p { margin: 0; font-size: 26px; font-weight: 700; color: #1f2937; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 18px 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; color: #fff; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 13px; }
        .btn-primary { background: #3b82f6; }
        .btn-success { background: var(--success); }
        .btn-warning { background: var(--warning); }
        .btn-danger { background: var(--danger); }
        .btn-dark { background: #1f2937; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
        .btn:hover:not(:disabled) { filter: brightness(92%); transform: translateY(-1px); }
        
        .btn:disabled { opacity: 0.4; cursor: not-allowed; filter: grayscale(50%); transform: none; }
        
        .table-responsive { overflow-x: auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { background: #f9fafb; padding: 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray); text-transform: uppercase; border-bottom: 1px solid #edf2f7; }
        td { padding: 16px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; border-radius: 50%; background: #eee; object-fit: cover; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.Draft { background: #f1f5f9; color: #475569; border: 1px dashed #cbd5e1; }
        .badge.Pending { background: #fef3c7; color: #d97706; }
        .badge.Credited { background: #e0f2fe; color: #0284c7; }
        .badge.Approved { background: #dcfce7; color: #16a34a; }
        .badge.Rejected { background: #fee2e2; color: #dc2626; }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
        .modal-content { background: #fff; padding: 30px; width: 100%; max-width: 850px; border-radius: 16px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        .section-title { grid-column: 1 / -1; margin: 20px 0 10px; font-weight: 800; color: #111827; font-size: 16px; border-bottom: 2px solid #f3f4f6; padding-bottom: 8px; }

        .swal2-container { z-index: 100000 !important; }
        .custom-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #f59e0b; }
    </style>
</head>
<body>

<?php 
if (!empty($sidebarPath) && file_exists($sidebarPath)) require_once $sidebarPath; 
if (!empty($headerPath) && file_exists($headerPath)) require_once $headerPath; 
?>

<div class="main-content">
    <div class="dashboard">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="margin:0; color: #111827; font-weight: 800;">Salary & Payroll Management</h2>
            <div style="color: var(--gray); font-size: 14px; font-weight: 600; background: #fff; padding: 8px 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" id="current-date"></div>
        </div>
        
        <?php if(!$is_hr): ?>
        <div class="summary-cards">
            <div class="card" style="border-left-color: #3b82f6;"><h3>Total Payroll (This Month)</h3><p>₹<?php echo number_format($tot_payroll, 2); ?></p></div>
            <div class="card" style="border-left-color: var(--success);"><h3>Net Credited</h3><p>₹<?php echo number_format($tot_credited, 2); ?></p></div>
            <div class="card" style="border-left-color: var(--warning);"><h3>Pending Approval</h3><p>₹<?php echo number_format($tot_pending, 2); ?></p></div>
            <div class="card" style="border-left-color: #8b5cf6;"><h3>Total Deductions</h3><p>₹<?php echo number_format($tot_deductions, 2); ?></p></div>
        </div>
        <?php endif; ?>

        <div class="top-bar">
            <form method="GET" action="" style="display:flex; gap: 12px; align-items:center; flex-wrap: wrap; margin: 0;">
                <input type="month" name="month" id="filter-month" value="<?php echo htmlspecialchars($month_filter); ?>" onchange="this.form.submit()" style="padding:10px; border:1px solid #ddd; border-radius:8px;">
                <select name="status" onchange="this.form.submit()" style="padding:10px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">All Approval Status</option>
                    <option value="Draft" <?php if($status_filter == 'Draft') echo 'selected'; ?>>Draft</option>
                    <option value="Pending" <?php if($status_filter == 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Approved" <?php if($status_filter == 'Approved') echo 'selected'; ?>>Approved</option>
                </select>
                <input type="text" id="search-emp" onkeyup="filterTable()" placeholder="Search Employee Name or ID..." style="padding:10px; border:1px solid #ddd; border-radius:8px; min-width: 250px;">
            </form>
            <div style="display:flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                
                <?php if(!$is_hr): ?>
                <button class="btn btn-outline" onclick="exportCSV()"><i class="fa-solid fa-download"></i> Export CSV</button>
                <?php endif; ?>
                
                <?php if($can_generate): ?>
                    <button class="btn btn-dark" onclick="runAutoGenerate()"><i class="fa-solid fa-calculator"></i> Auto-Generate Salaries</button>
                    <button id="btnAskCfoBulk" class="btn" onclick="askCFOBulkSelected()" disabled style="background: #f59e0b; color: white;">
                        <i class="fa-solid fa-paper-plane"></i> Ask CFO Approval (<span id="selectedCount">0</span>)
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="selectAll" class="custom-checkbox" onchange="toggleAllCheckboxes(this)" title="Select All Drafts">
                        </th>
                        <th>Employee Details</th>
                        <th>Month</th>
                        <th>Salary Breakdown</th>
                        <th>Credit Status</th>
                        <th>CFO Approval</th>
                        <th style="text-align: right;">Action Center</th>
                    </tr>
                </thead>
                <tbody id="salary-body">
                    <?php if (empty($grouped_data)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:50px; color:#94a3b8; font-size:16px;">No records found for the selected period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($grouped_data as $dept => $employees): ?>
                            <tr class="dept-header">
                                <td colspan="7" style="background:#f3f4f6; font-weight:800; color:#1f2937; padding:12px 20px; text-transform:uppercase; font-size:13px; letter-spacing:0.5px;">
                                    <i class="fa-solid fa-building" style="margin-right:8px; color:var(--primary);"></i> <?php echo htmlspecialchars($dept); ?>
                                </td>
                            </tr>
                            
                            <?php foreach ($employees as $row): 
                                $avatar = 'https://ui-avatars.com/api/?name='.urlencode($row['name']).'&background=0ea5e9&color=fff';
                                if (!empty($row['profile_img']) && $row['profile_img'] !== 'default_user.png') {
                                    $img = $row['profile_img'];
                                    if (strpos($img, 'http') === 0) { $avatar = $img; } 
                                    elseif (strpos($img, 'uploads/') === 0 || strpos($img, 'assets/') === 0) { $avatar = '../' . $img; } 
                                    else { $avatar = '../assets/profiles/' . $img; }
                                }
                                
                                $hasSalary = !empty($row['salary_id']);
                                $gross = $hasSalary ? (float)$row['gross_salary'] : 0;
                                $net = $hasSalary ? (float)$row['net_salary'] : 0;
                                $appStatus = $hasSalary ? ($row['approval_status'] ?: 'Draft') : 'N/A';
                                $credStatus = $hasSalary ? ($row['credit_status'] ?: 'Pending') : 'N/A';
                                $isLocked = ($credStatus === 'Credited');
                                
                                $ctc = (float)($row['ctc'] ?? 0);
                                $sal_type = $row['salary_type'];
                            ?>
                            <tr class="emp-row">
                                <td style="text-align: center;">
                                    <?php if($hasSalary && $appStatus === 'Draft'): ?>
                                        <input type="checkbox" class="salary-checkbox custom-checkbox" value="<?php echo $row['salary_id']; ?>" onchange="updateBulkAskCfoButton()">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="user-avatar" alt="Avatar" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=User&background=0ea5e9&color=fff';">
                                        <div>
                                            <div class="emp-name" style="font-weight:700; color:#1e293b; font-size:15px;"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <div style="font-size:12px; color:var(--gray); margin-top:3px;">
                                                <span class="emp-code" style="color:var(--primary); font-weight:600;"><?php echo htmlspecialchars($row['emp_code'] ?: 'N/A'); ?></span> | <?php echo htmlspecialchars($row['designation'] ?: 'N/A'); ?>
                                            </div>
                                            <div style="font-size:11px; margin-top:4px; font-weight:700; color:#059669;">
                                                Base: ₹<?php echo number_format($ctc, 2); ?> (<?php echo htmlspecialchars($sal_type); ?>)
                                                
                                                <?php if($row['lop_days'] > 0) { 
                                                    $tooltip = "Present: {$row['present_days']} | Approved Leaves: {$row['approved_leaves']} | Weekends: {$row['sundays']}";
                                                    echo "<span style='color:#ef4444; margin-left:8px; cursor:help;' title='{$tooltip}'><i class='fa-solid fa-triangle-exclamation'></i> LOP: {$row['lop_days']} Days</span>"; 
                                                } ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight:600; color:#475569;"><?php echo htmlspecialchars($month_filter); ?></td>
                                <td>
                                    <?php if($hasSalary): ?>
                                        <div style="font-weight:800; color:#111; font-size:16px;">₹<?php echo number_format($net, 2); ?></div>
                                        <div style="font-size:11px; color:var(--gray); margin-top:4px;">Gross: ₹<?php echo number_format($gross, 2); ?></div>
                                    <?php else: ?>
                                        <div style="font-weight:700; color:#9ca3af; font-size:13px; font-style:italic;">Not Generated</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($hasSalary): ?>
                                        <span class="badge <?php echo htmlspecialchars($credStatus); ?>"><?php echo htmlspecialchars($credStatus); ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f1f5f9; color:#94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($hasSalary): ?>
                                        <span class="badge <?php echo htmlspecialchars($appStatus); ?>"><?php echo htmlspecialchars($appStatus); ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f1f5f9; color:#94a3b8;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                                        <?php if($hasSalary): ?>
                                            <?php if($isLocked): ?>
                                                <button class="btn btn-dark" style="padding:6px 12px; font-size:12px;" onclick="window.location.href='api/generate_payslip.php?id=<?php echo $row['salary_id']; ?>'"><i class="fa-solid fa-file-pdf"></i> Payslip</button>
                                                <span style="font-size:11px; font-weight:700; color:var(--success); margin-left: 8px;"><i class="fa-solid fa-lock"></i> Locked</span>
                                            <?php else: ?>
                                                <?php if($appStatus === 'Approved'): ?>
                                                    <button class="btn btn-dark" style="padding:6px 12px; font-size:12px;" onclick="window.location.href='api/generate_payslip.php?id=<?php echo $row['salary_id']; ?>'"><i class="fa-solid fa-file-pdf"></i> Payslip</button>
                                                <?php elseif($appStatus === 'Pending' && $can_approve): ?>
                                                    <button class="btn btn-success" style="padding:6px 12px; font-size:12px;" onclick="confirmPayrollApprove(<?php echo $row['salary_id']; ?>)"><i class="fa-solid fa-check"></i> Approve</button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline" style="padding:6px 12px;" onclick="editSalary(<?php echo $row['salary_id']; ?>)" title="Review / Edit"><i class="fa-solid fa-pen"></i></button>
                                                
                                                <?php if($can_generate): ?>
                                                <button class="btn btn-danger" style="padding:6px 12px;" onclick="removePayrollRecord(<?php echo $row['salary_id']; ?>)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php elseif($can_generate): ?>
                                            <button class="btn btn-primary" style="padding:6px 14px;" onclick="generateManual(<?php echo $row['user_id']; ?>)"><i class="fa-solid fa-plus"></i> Generate</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="salaryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title" style="margin:0; font-weight: 800; color: #111827;">Process Salary Record</h3>
            <button type="button" onclick="closeModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
        </div>
        <form id="salaryForm" onsubmit="submitSalary(event)">
            <input type="hidden" name="ajax_action" value="save_salary">
            <input type="hidden" name="id" id="salary_db_id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="user_id" id="employeeSelect" required style="background: #f3f4f6; pointer-events: none; border-color: #e5e7eb;"></select>
                </div>
                <div class="form-group">
                    <label>Salary Month</label>
                    <input type="month" name="salary_month" id="form_salary_month" required readonly style="background: #f3f4f6; border-color: #e5e7eb;">
                </div>
                
                <div class="form-group">
                    <label>Credit Status</label>
                    <select name="credit_status" id="creditStatus" onchange="updateSubmitButtonText(this.value); toggleDate(this.value)" <?php if(!$can_credit) echo 'disabled style="background: #f3f4f6;" title="Only Accounts/CFO can update credit status"'; ?>>
                        <option value="Pending">Pending</option>
                        <option value="Credited">Credited</option>
                    </select>
                    <?php if(!$can_credit): ?>
                        <input type="hidden" name="credit_status" id="hiddenCreditStatus" value="Pending">
                    <?php endif; ?>
                </div>

                <div class="form-group" id="paymentModeDiv" style="display: none;">
                    <label>Payment Mode</label>
                    <select name="payment_mode" id="paymentMode">
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                    </select>
                </div>
                <div class="form-group" id="creditDateDiv" style="display: none;">
                    <label>Date of Credit</label>
                    <input type="date" name="credit_date" id="creditDate">
                </div>
                <div class="form-group" id="transRefDiv" style="display: none; grid-column: 1 / -1;">
                    <label>Bank Transaction ID / UTR No.</label>
                    <input type="text" name="transaction_reference" id="transRef" placeholder="e.g. UTR-123456789">
                </div>

                <div class="section-title" style="color: var(--gray);"><i class="fa-solid fa-calendar-check"></i> Smart Attendance Logic</div>
                <div class="form-group"><label>Total Days in Month</label><input type="number" id="form_days_in_month" readonly style="background: #f3f4f6; border-color: #e5e7eb; color: #6b7280; font-weight: bold;"></div>
                <div class="form-group"><label>Payable Days</label><input type="number" id="form_payable_days" readonly style="background: #f3f4f6; border-color: #e5e7eb; color: var(--success); font-weight: bold;" title="(Present + Leaves + Sundays)"></div>
                <div class="form-group"><label>LOP Days (Missing/Auto-Absent)</label><input type="number" id="form_lop_days" readonly style="background: #fef2f2; border-color: #fecaca; color: var(--danger); font-weight: bold;" title="Total Days - Payable Days"></div>

                <div class="section-title" style="color: var(--primary);">Earnings (In ₹)</div>
                <div class="form-group"><label>Basic Pay</label><input type="number" name="basic" id="form_basic" value="0" step="0.01" oninput="recalcSalary()"></div>
                <div class="form-group"><label>DA (40%)</label><input type="number" name="da" id="form_da" value="0" step="0.01" oninput="recalcSalary()"></div>
                <div class="form-group"><label>HRA (15%)</label><input type="number" name="hra" id="form_hra" value="0" step="0.01" oninput="recalcSalary()"></div>
                <div class="form-group"><label>Conveyance</label><input type="number" name="conveyance" id="form_conveyance" value="0" step="0.01" oninput="recalcSalary()"></div>
                <div class="form-group"><label>Special Allowance</label><input type="number" name="allowance" id="form_allowance" value="0" step="0.01" oninput="recalcSalary()"></div>
                <div class="form-group"><label>Medical Allowance</label><input type="number" name="medical" id="form_medical" value="0" step="0.01" oninput="recalcSalary()"></div>
                <div class="form-group"><label>Others Earnings</label><input type="number" name="others_earnings" id="form_others_earnings" value="0" step="0.01" oninput="recalcSalary()"></div>

                <div class="section-title" style="color: var(--danger);">Deductions (In ₹)</div>
                <div class="form-group"><label>TDS (Tax)</label><input type="number" name="tds" id="form_tds" value="0" step="0.01"></div>
                <div class="form-group"><label>ESI (0.75%)</label><input type="number" name="esi" id="form_esi" value="0" step="0.01"></div>
                <div class="form-group"><label>Provident Fund (PF - 12%)</label><input type="number" name="pf" id="form_pf" value="0" step="0.01"></div>
                <div class="form-group">
                    <label>Leave / LOP Deduction (Auto-Calculated)</label>
                    <input type="number" name="leave_deduction" id="form_leave_deduction" value="0" step="0.01" style="background: #fef2f2; border-color: #fecaca; color: var(--danger); font-weight: bold;">
                </div>
                <div class="form-group"><label>Professional Tax</label><input type="number" name="professional_tax" id="form_professional_tax" value="0" step="0.01"></div>
                <div class="form-group"><label>Labour Welfare</label><input type="number" name="labour_welfare" id="form_labour_welfare" value="0" step="0.01"></div>
                <div class="form-group"><label>Other Deductions</label><input type="number" name="others_deductions" id="form_others_deductions" value="0" step="0.01"></div>
                
                <div class="form-group" style="grid-column: 1 / -1; margin-top:20px; border-top:1px solid #f3f4f6; padding-top: 20px; display:flex; flex-direction:row; justify-content:flex-end; gap:12px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-btn"><i class="fa-solid fa-save"></i> Save Details as Draft</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const serverData = <?php echo json_encode($employees_data); ?>;
    document.getElementById('current-date').innerText = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    function updateSubmitButtonText(val) {
        if(val === 'Credited') {
            document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-check-double"></i> Confirm & Credit Salary';
        } else {
            document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-save"></i> Save Details as Draft';
        }
    }

    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.salary-checkbox');
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (row.style.display !== 'none') {
                cb.checked = source.checked;
            }
        });
        updateBulkAskCfoButton();
    }

    function updateBulkAskCfoButton() {
        const checkboxes = document.querySelectorAll('.salary-checkbox:checked');
        const btn = document.getElementById('btnAskCfoBulk');
        const countSpan = document.getElementById('selectedCount');
        
        if (btn && countSpan) {
            const count = checkboxes.length;
            countSpan.innerText = count;
            
            if (count > 0) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            } else {
                btn.disabled = true;
                btn.style.opacity = '0.4';
                btn.style.cursor = 'not-allowed';
                const selectAllCb = document.getElementById('selectAll');
                if(selectAllCb) selectAllCb.checked = false;
            }
        }
    }

    async function runAutoGenerate() {
        const month = document.getElementById('filter-month').value;
        const formData = new FormData();
        formData.append('ajax_action', 'auto_generate');
        formData.append('month', month);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const raw = await res.text();
            try {
                const result = JSON.parse(raw);
                if(result.success) window.location.reload();
                else Swal.fire('Error', result.message, 'error');
            } catch(e) {
                Swal.fire('Database Error', 'System failed to parse response. Check console.', 'error');
            }
        } catch(err) { Swal.fire("Network Error", "Check connection.", "error"); }
    }

    async function askCFOBulkSelected() {
        const checkboxes = document.querySelectorAll('.salary-checkbox:checked');
        if (checkboxes.length === 0) return;

        const ids = Array.from(checkboxes).map(cb => cb.value);

        Swal.fire({
            title: 'Push to CFO?',
            text: `You are sending ${ids.length} verified draft salaries to the CFO for final approval.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fa-solid fa-paper-plane"></i> Yes, Send to CFO'
        }).then(async (result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('ajax_action', 'ask_approval_bulk_selected');
                formData.append('ids', JSON.stringify(ids));
                
                try {
                    const res = await fetch(window.location.href, { method: 'POST', body: formData });
                    const raw = await res.text();
                    try {
                        const resJson = JSON.parse(raw);
                        if(resJson.success) {
                            Swal.fire('Success!', `${resJson.updated} salaries pushed to CFO successfully.`, 'success').then(()=> window.location.reload());
                        } else {
                            Swal.fire('Error', resJson.message, 'error');
                        }
                    } catch(e) {
                        Swal.fire('Database Error', 'A backend error occurred. Check browser console for details.', 'error');
                    }
                } catch(err) { Swal.fire("Network Error", "Check connection.", "error"); }
            }
        });
    }

    function recalcSalary() {
        let basic = parseFloat(document.getElementById('form_basic').value) || 0;
        let da = parseFloat(document.getElementById('form_da').value) || 0;
        let hra = parseFloat(document.getElementById('form_hra').value) || 0;
        let conveyance = parseFloat(document.getElementById('form_conveyance').value) || 0;
        let allowance = parseFloat(document.getElementById('form_allowance').value) || 0;
        let medical = parseFloat(document.getElementById('form_medical').value) || 0;
        let others_earnings = parseFloat(document.getElementById('form_others_earnings').value) || 0;

        let grossPay = basic + da + hra + conveyance + allowance + medical + others_earnings;
        
        let daysInMonth = parseInt(document.getElementById('form_days_in_month').value) || 30;
        let lopDays = parseFloat(document.getElementById('form_lop_days').value) || 0;
        
        // Exact LOP math based on dynamic missing days
        let perDaySalary = grossPay / daysInMonth;
        let leave_deduction = perDaySalary * lopDays;
        
        document.getElementById('form_leave_deduction').value = leave_deduction.toFixed(2);
        
        let pf = basic * 0.12;
        let esi = grossPay <= 21000 ? grossPay * 0.0075 : 0;
        let pt = grossPay > 15000 ? 200 : 0;
        
        document.getElementById('form_pf').value = pf.toFixed(2);
        document.getElementById('form_esi').value = esi.toFixed(2);
        document.getElementById('form_professional_tax').value = pt.toFixed(2);
    }

    function toggleDate(status) {
        const dateDiv = document.getElementById('creditDateDiv');
        const modeDiv = document.getElementById('paymentModeDiv');
        const refDiv = document.getElementById('transRefDiv');
        
        const isCredited = (status === 'Credited');
        dateDiv.style.display = isCredited ? 'flex' : 'none';
        modeDiv.style.display = isCredited ? 'flex' : 'none';
        refDiv.style.display = isCredited ? 'flex' : 'none';

        if (isCredited && !document.getElementById('creditDate').value) {
            document.getElementById('creditDate').value = new Date().toISOString().slice(0, 10);
        }
    }

    function filterTable() {
        const term = document.getElementById('search-emp').value.toLowerCase();
        const rows = document.querySelectorAll('#salary-body .emp-row');
        rows.forEach(row => {
            const name = row.querySelector('.emp-name').innerText.toLowerCase();
            const code = row.querySelector('.emp-code').innerText.toLowerCase();
            row.style.display = (name.includes(term) || code.includes(term)) ? '' : 'none';
        });

        document.querySelectorAll('#salary-body .dept-header').forEach(header => {
            let next = header.nextElementSibling;
            let hasVisibleEmp = false;
            while (next && next.classList.contains('emp-row')) {
                if (next.style.display !== 'none') { hasVisibleEmp = true; break; }
                next = next.nextElementSibling;
            }
            header.style.display = hasVisibleEmp ? '' : 'none';
        });
        
        const selectAllCb = document.getElementById('selectAll');
        if(selectAllCb) { selectAllCb.checked = false; toggleAllCheckboxes(selectAllCb); }
    }

    function generateManual(userId) {
        const item = serverData.find(d => d.user_id == userId);
        if(!item) return;

        document.getElementById('modal-title').innerText = `Generate Salary: ${item.name}`;
        document.getElementById('salary_db_id').value = 0; 
        
        const empSelect = document.getElementById('employeeSelect');
        empSelect.innerHTML = `<option value="${item.user_id}" selected>${item.name} (${item.emp_code})</option>`;
        
        document.getElementById('form_salary_month').value = document.getElementById('filter-month').value;
        
        const credSelect = document.getElementById('creditStatus');
        if(!credSelect.disabled) credSelect.value = 'Pending';
        if(document.getElementById('hiddenCreditStatus')) document.getElementById('hiddenCreditStatus').value = 'Pending';
        toggleDate('Pending');
        updateSubmitButtonText('Pending');
        
        // Feed the smartly calculated LOP values into the UI
        let daysInMonth = parseInt(item.days_in_month) || 30;
        let lopDays = parseFloat(item.lop_days) || 0;
        let payableDays = parseFloat(item.payable_days) || daysInMonth;

        document.getElementById('form_days_in_month').value = daysInMonth;
        document.getElementById('form_payable_days').value = payableDays;
        document.getElementById('form_lop_days').value = lopDays;

        let rawCTC = parseFloat(item.ctc) || 0;
        let salType = item.salary_type || 'Annual';
        let monthlyGross = (salType === 'Annual') ? (rawCTC / 12) : rawCTC;

        let basicPay = monthlyGross * 0.50;
        let daPay = basicPay * 0.40;
        let hraPay = basicPay * 0.15;
        let allowancePay = monthlyGross - (basicPay + daPay + hraPay);
        if(allowancePay < 0) allowancePay = 0;
        
        let grossPay = basicPay + daPay + hraPay + allowancePay;
        let perDaySalary = grossPay / daysInMonth;
        let lopDeduct = perDaySalary * lopDays;

        let pfDeduct = basicPay * 0.12;
        let esiDeduct = grossPay <= 21000 ? (grossPay * 0.0075) : 0;
        let ptDeduct = grossPay > 15000 ? 200 : 0;

        document.getElementById('form_basic').value = basicPay.toFixed(2);
        document.getElementById('form_da').value = daPay.toFixed(2);
        document.getElementById('form_hra').value = hraPay.toFixed(2);
        document.getElementById('form_allowance').value = allowancePay.toFixed(2);
        
        document.getElementById('form_conveyance').value = 0;
        document.getElementById('form_medical').value = 0;
        document.getElementById('form_others_earnings').value = 0;

        document.getElementById('form_pf').value = pfDeduct.toFixed(2);
        document.getElementById('form_esi').value = esiDeduct.toFixed(2);
        document.getElementById('form_professional_tax').value = ptDeduct.toFixed(2);

        document.getElementById('form_leave_deduction').value = lopDeduct.toFixed(2);
        
        document.getElementById('form_tds').value = 0;
        document.getElementById('form_labour_welfare').value = 0;
        document.getElementById('form_others_deductions').value = 0;
        
        openModal();
    }

    function editSalary(salaryId) {
        const item = serverData.find(d => d.salary_id == salaryId);
        if(!item) return;

        document.getElementById('modal-title').innerText = `Update Salary: ${item.name}`;
        document.getElementById('salary_db_id').value = item.salary_id;
        
        const empSelect = document.getElementById('employeeSelect');
        empSelect.innerHTML = `<option value="${item.user_id}" selected>${item.name} (${item.emp_code})</option>`;

        document.getElementById('form_salary_month').value = item.salary_month.substring(0, 7);
        
        const credSelect = document.getElementById('creditStatus');
        if(!credSelect.disabled) credSelect.value = item.credit_status || 'Pending';
        if(document.getElementById('hiddenCreditStatus')) document.getElementById('hiddenCreditStatus').value = item.credit_status || 'Pending';

        if(item.credit_date) document.getElementById('creditDate').value = item.credit_date.substring(0, 10);
        if(item.payment_mode) document.getElementById('paymentMode').value = item.payment_mode;
        if(item.transaction_reference) document.getElementById('transRef').value = item.transaction_reference;
        
        toggleDate(item.credit_status);
        updateSubmitButtonText(item.credit_status || 'Pending');
        
        // Feed the smartly calculated LOP values into the UI
        let daysInMonth = parseInt(item.days_in_month) || 30;
        let lopDays = parseFloat(item.lop_days) || 0;
        let payableDays = parseFloat(item.payable_days) || daysInMonth;

        document.getElementById('form_days_in_month').value = daysInMonth;
        document.getElementById('form_payable_days').value = payableDays;
        document.getElementById('form_lop_days').value = lopDays;

        const fields = ['basic', 'da', 'hra', 'conveyance', 'allowance', 'medical', 'others_earnings', 'tds', 'esi', 'pf', 'leave_deduction', 'professional_tax', 'labour_welfare', 'others_deductions'];
        fields.forEach(f => {
            const el = document.getElementById('form_' + f);
            if(el) el.value = item[f] ? parseFloat(item[f]).toFixed(2) : 0;
        });
        
        openModal();
    }

    async function submitSalary(e) {
        e.preventDefault();
        const empSelect = document.getElementById('employeeSelect');
        empSelect.style.pointerEvents = 'auto';
        
        const formData = new FormData(e.target);
        
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const rawText = await res.text();
            
            try {
                const result = JSON.parse(rawText);
                if(result.success) {
                    closeModal();
                    Swal.fire('Saved!', 'Salary details successfully saved.', 'success').then(()=> window.location.reload());
                } else {
                    Swal.fire('Database Error', result.message, 'error');
                    empSelect.style.pointerEvents = 'none'; 
                }
            } catch(parseErr) {
                Swal.fire('Database Error', 'A backend error occurred. Check browser console for details.', 'error');
                empSelect.style.pointerEvents = 'none'; 
            }
        } catch(err) {
            Swal.fire('Network error', 'Please try again.', 'error');
            empSelect.style.pointerEvents = 'none'; 
        }
    }

    async function removePayrollRecord(id) {
        const formData = new FormData();
        formData.append('ajax_action', 'delete_salary');
        formData.append('id', id);
        
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const raw = await res.text();
            try {
                const result = JSON.parse(raw);
                if(result.success) window.location.reload();
                else Swal.fire('Error', result.message, 'error');
            } catch(e) {
                Swal.fire('Database Error', 'Check console.', 'error');
            }
        } catch(err) { Swal.fire('Network error', 'Please try again.', 'error'); }
    }

    async function confirmPayrollApprove(id) {
        const formData = new FormData();
        formData.append('ajax_action', 'approve_salary');
        formData.append('id', id);
        
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const raw = await res.text();
            try {
                const result = JSON.parse(raw);
                if(result.success) window.location.reload();
                else Swal.fire('Error', result.message, 'error');
            } catch(e) {
                Swal.fire('Database Error', 'Check console.', 'error');
            }
        } catch(err) { Swal.fire("Network Error", "Check connection.", "error"); }
    }

    function exportCSV() {
        if(!serverData.length) return alert("No data to export");
        let csv = "Employee ID,Name,Department,Month,Gross Salary,Net Salary,Credit Status,Transaction Ref,Approval Status\n";
        let hasData = false;
        
        serverData.forEach(r => {
            if(r.salary_id) { 
                hasData = true;
                const gross = parseFloat(r.gross_salary) || 0;
                const net = parseFloat(r.net_salary) || 0;
                csv += `"${r.emp_code}","${r.name}","${r.department}","${r.salary_month.substring(0,7)}","${gross}","${net}","${r.credit_status}","${r.transaction_reference || ''}","${r.approval_status}"\n`;
            }
        });
        
        if(!hasData) return Swal.fire('Notice', 'No generated salaries to export for this month.', 'info');
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = window.URL.createObjectURL(blob);
        a.download = `Payroll_Report_${document.getElementById('filter-month').value}.csv`;
        a.click();
    }

    function openModal() { document.getElementById('salaryModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('salaryModal').style.display = 'none'; document.getElementById('salaryForm').reset(); }
</script>
</body>
</html>