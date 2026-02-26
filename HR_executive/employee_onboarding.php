<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php'; 

// ---------------------------------------------------------
// FETCH DEPARTMENTS, MANAGERS, AND TEAM MEMBERS
// ---------------------------------------------------------
$departments = [];
$all_managers = [];
$dept_members = [];

// Fetch distinct departments from the users table
$dept_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''";
$dept_res = $conn->query($dept_sql);
if ($dept_res && $dept_res->num_rows > 0) {
    while($row = $dept_res->fetch_assoc()) {
        $departments[] = trim($row['department']);
    }
}

// Fetch users who hold a supervisory role
$mgr_sql = "SELECT id, name, department, role FROM users WHERE role IN ('Manager', 'Team Lead', 'System Admin', 'HR', 'HR Executive', 'CFO', 'IT Admin', 'Sales Manager', 'CEO') ORDER BY department, name";
$mgr_res = $conn->query($mgr_sql);
if ($mgr_res && $mgr_res->num_rows > 0) {
    while($row = $mgr_res->fetch_assoc()) {
        $all_managers[] = [
            'id' => $row['id'],
            'name' => trim($row['name']),
            'dept' => trim($row['department']),
            'role' => trim($row['role'])
        ];
        if (!in_array(trim($row['department']), $departments)) {
            $departments[] = trim($row['department']);
        }
    }
}

// Fetch all users with their roles to group them by department (for Team Members display)
$members_sql = "SELECT department, name, role FROM users WHERE department IS NOT NULL AND department != ''";
$members_res = $conn->query($members_sql);
if ($members_res && $members_res->num_rows > 0) {
    while($m_row = $members_res->fetch_assoc()) {
        $d = trim($m_row['department']);
        if(!isset($dept_members[$d])) $dept_members[$d] = [];
        if (!empty(trim($m_row['name']))) {
            $dept_members[$d][] = [
                'name' => trim($m_row['name']),
                'role' => trim($m_row['role'])
            ];
        }
    }
}

// ---------------------------------------------------------
// BACKEND AJAX HANDLERS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // --- FETCH SINGLE EMPLOYEE FOR EDITING ---
    if ($action === 'get_employee') {
        $id = intval($_POST['id']);
        $sql = "SELECT eo.*, u.role, p.manager_id 
                FROM employee_onboarding eo 
                LEFT JOIN users u ON eo.emp_id_code = u.employee_id 
                LEFT JOIN employee_profiles p ON u.id = p.user_id 
                WHERE eo.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
        }
        exit();
    }

    // --- ADD OR EDIT EMPLOYEE ---
    if ($action === 'save') {
        $edit_id = !empty($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $fullName = trim($fname . ' ' . $lname);
        $emp_id = trim($_POST['emp_id'] ?? '');
        $join_date = $_POST['join_date'] ?? '';
        $uname = trim($_POST['uname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pwd = $_POST['pwd'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $dept = $_POST['dept'] ?? '';
        $desig = trim($_POST['desig'] ?? '');
        $assigned_role = $_POST['role'] ?? 'Employee'; 
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : NULL;
        $manager_name = ''; 
        $salary = $_POST['salary'] ?? '0';
        $emp_type = $_POST['emp_type'] ?? 'Permanent';

        $pan = strtoupper(trim($_POST['pan'] ?? ''));
        $pf = trim($_POST['pf'] ?? '');
        $esi = trim($_POST['esi'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_acc = trim($_POST['bank_acc'] ?? '');
        $ifsc = strtoupper(trim($_POST['ifsc'] ?? ''));

        // VALIDATIONS
        if(empty($fname) || empty($emp_id) || empty($uname) || empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Required fields are missing.']);
            exit;
        }

        if($edit_id === 0 && empty($pwd)) {
            echo json_encode(['status' => 'error', 'message' => 'Password is required for new employees.']);
            exit;
        }

        $old_emp_id = '';
        if ($edit_id > 0) {
            $stmt_old = $conn->prepare("SELECT emp_id_code, profile_img FROM employee_onboarding WHERE id = ?");
            $stmt_old->bind_param("i", $edit_id);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $old_emp_id = $old_data['emp_id_code'];
            $img = $old_data['profile_img']; 
            $stmt_old->close();
        } else {
            $img = "https://ui-avatars.com/api/?name=" . urlencode($fullName) . "&background=random";
        }

        $chk_emp = $conn->prepare("SELECT id FROM users WHERE employee_id = ? AND employee_id != ?");
        $chk_emp->bind_param("ss", $emp_id, $old_emp_id);
        $chk_emp->execute();
        if($chk_emp->get_result()->num_rows > 0) { echo json_encode(['status'=>'error', 'message'=>'Employee ID already exists.']); exit; }

        $chk_uname = $conn->prepare("SELECT id FROM users WHERE username = ? AND employee_id != ?");
        $chk_uname->bind_param("ss", $uname, $old_emp_id);
        $chk_uname->execute();
        if($chk_uname->get_result()->num_rows > 0) { echo json_encode(['status'=>'error', 'message'=>'Username is already taken.']); exit; }
        
        $chk_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND employee_id != ?");
        $chk_email->bind_param("ss", $email, $old_emp_id);
        $chk_email->execute();
        if($chk_email->get_result()->num_rows > 0) { echo json_encode(['status'=>'error', 'message'=>'Email Address is already registered.']); exit; }

        if($manager_id !== NULL) {
            $stmt_m = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $stmt_m->bind_param("i", $manager_id);
            $stmt_m->execute();
            if($row_m = $stmt_m->get_result()->fetch_assoc()) { $manager_name = $row_m['name']; }
            $stmt_m->close();
        }

        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $fileExt = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
            $fileName = 'EMP_' . time() . '_' . rand(1000,9999) . '.' . $fileExt;
            if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $uploadDir . $fileName)) {
                $img = 'uploads/profiles/' . $fileName; 
            }
        }

        $pass_update_user = ""; $pass_update_onb = "";
        $pwd_hash = NULL;
        if (!empty($pwd)) {
            $pwd_hash = password_hash($pwd, PASSWORD_DEFAULT);
            $pass_update_user = ", password = '$pwd_hash'";
            $pass_update_onb = ", password_hash = '$pwd_hash'";
        }

        $bank_info_arr = ["bank_name" => $bank_name, "acc_no" => $bank_acc, "ifsc" => $ifsc, "pan" => $pan, "pf_no" => $pf, "esi_no" => $esi];
        $bank_info_json = json_encode($bank_info_arr);

        // TRANSACTION START
        $conn->begin_transaction();
        try {
            if ($edit_id > 0) {
                // EDIT EXISTING
                $stmt_user = $conn->prepare("UPDATE users SET name=?, employee_id=?, department=?, username=?, email=?, role=? $pass_update_user WHERE employee_id=?");
                $stmt_user->bind_param("sssssss", $fullName, $emp_id, $dept, $uname, $email, $assigned_role, $old_emp_id);
                if (!$stmt_user->execute()) throw new Exception("Error updating users table.");

                $stmt_uid = $conn->prepare("SELECT id FROM users WHERE employee_id=?");
                $stmt_uid->bind_param("s", $emp_id);
                $stmt_uid->execute();
                $user_id = $stmt_uid->get_result()->fetch_assoc()['id'];

                $stmt_prof = $conn->prepare("UPDATE employee_profiles SET full_name=?, designation=?, department=?, reporting_to=?, manager_id=?, emp_id_code=?, phone=?, joining_date=?, email=?, profile_img=?, bank_info=? WHERE user_id=?");
                $stmt_prof->bind_param("sssiissssssi", $fullName, $desig, $dept, $manager_id, $manager_id, $emp_id, $phone, $join_date, $email, $img, $bank_info_json, $user_id);
                if (!$stmt_prof->execute()) throw new Exception("Error updating employee profiles.");

                $stmt_onb = $conn->prepare("UPDATE employee_onboarding SET emp_id_code=?, first_name=?, last_name=?, email=?, phone=?, department=?, designation=?, manager_name=?, salary=?, employment_type=?, joining_date=?, username=?, pan_no=?, pf_no=?, esi_no=?, bank_name=?, bank_acc_no=?, ifsc_code=?, profile_img=? $pass_update_onb WHERE id=?");
                $stmt_onb->bind_param("sssssssssssssssssssi", $emp_id, $fname, $lname, $email, $phone, $dept, $desig, $manager_name, $salary, $emp_type, $join_date, $uname, $pan, $pf, $esi, $bank_name, $bank_acc, $ifsc, $img, $edit_id);
                if (!$stmt_onb->execute()) throw new Exception("Error updating onboarding details.");

            } else {
                // ADD NEW
                $stmt_user = $conn->prepare("INSERT INTO users (name, employee_id, department, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_user->bind_param("sssssss", $fullName, $emp_id, $dept, $uname, $email, $pwd_hash, $assigned_role);
                if (!$stmt_user->execute()) throw new Exception("Error creating user account.");
                $new_user_id = $stmt_user->insert_id;

                $stmt_prof = $conn->prepare("INSERT INTO employee_profiles (user_id, full_name, designation, department, reporting_to, manager_id, emp_id_code, phone, joining_date, email, profile_img, bank_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_prof->bind_param("isssiissssss", $new_user_id, $fullName, $desig, $dept, $manager_id, $manager_id, $emp_id, $phone, $join_date, $email, $img, $bank_info_json);
                if (!$stmt_prof->execute()) throw new Exception("Error creating employee profile.");

                // Status removed conceptually, but keeping 'Completed' as default to satisfy db constraints if any
                $sql_onb = "INSERT INTO employee_onboarding (
                            emp_id_code, first_name, last_name, email, phone, department, 
                            designation, manager_name, salary, employment_type, joining_date, 
                            username, password_hash, pan_no, pf_no, esi_no, bank_name, bank_acc_no, ifsc_code,
                            status, profile_img
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?)";
                $stmt_onb = $conn->prepare($sql_onb);
                $stmt_onb->bind_param("ssssssssssssssssssss", $emp_id, $fname, $lname, $email, $phone, $dept, $desig, $manager_name, $salary, $emp_type, $join_date, $uname, $pwd_hash, $pan, $pf, $esi, $bank_name, $bank_acc, $ifsc, $img);
                if (!$stmt_onb->execute()) throw new Exception("Error saving onboarding details.");
            }

            $conn->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }
}

$sql = "SELECT * FROM employee_onboarding ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Directory | Workack HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1b5a5a; --primary-light: #2d7a7a; --primary-bg: #f0fdfa; --border: #e2e8f0; --text-muted: #64748b; }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
        main#content-wrapper { margin-left: 95px; padding-top: 80px; padding-bottom: 40px; min-height: 100vh; transition: margin-left 0.3s ease; }
        .sidebar-secondary.open ~ main#content-wrapper { margin-left: calc(95px + 220px); }
        .btn { padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; border: 1px solid var(--border); background: white; color: var(--text-muted); font-weight: 500; transition: all 0.2s; }
        .btn:hover { background: #f8fafc; }
        .btn-primary { background-color: var(--primary) !important; color: white !important; border-color: var(--primary) !important; }
        .btn-primary:hover { background-color: var(--primary-light) !important; border-color: var(--primary-light) !important; transform: translateY(-1px); }
        .d-none { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; transition: all 0.2s; background: #fff; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.12); outline: none; }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal-box { background: white; border-radius: 12px; width: 100%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal-box.small-modal { max-width: 450px; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .modal-tabs { display: flex; border-bottom: 1px solid var(--border); padding: 0 20px; gap: 24px; }
        .tab-item { padding: 15px 0; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 500; font-size: 14px; color: var(--text-muted); transition: all 0.2s; }
        .tab-item.active { color: var(--primary); border-bottom-color: var(--primary); }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-radius: 0 0 12px 12px; }
        
        .img-upload-area { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .preview-circle { width: 70px; height: 70px; border-radius: 50%; background: #f1f5f9; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8; overflow: hidden; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-group label span { color: #ef4444; }
        .password-group { position: relative; }
        .password-toggle { position: absolute; right: 14px; top: 34px; color: #94a3b8; cursor: pointer; }
        .form-section-title { font-size: 16px; font-weight: 700; color: #1e293b; margin: 10px 0 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;}
        @media (max-width: 1024px) { main#content-wrapper { margin-left: 0; padding-top: 70px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">

<?php 
$sidebarPath = '../sidebars.php';
$headerPath = '../header.php';
if(file_exists($sidebarPath)) include $sidebarPath; 
if(file_exists($headerPath)) include $headerPath; 
?>

<main id="content-wrapper">
    <div class="max-w-[96%] mx-auto pt-3 pb-10 px-4 sm:px-6 lg:px-8">
        
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-5">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Employee Directory</h2>
                <p class="text-gray-600 mt-1.5">Manage employees, view teams, and update system access.</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-600 bg-white px-5 py-2.5 rounded-lg shadow-sm border font-medium">
                    <i class="far fa-calendar-alt mr-1"></i> <span id="currentDateDisplay"></span>
                </div>
                <button onclick="openAddModal()" class="btn-primary px-5 py-2.5 rounded-lg font-bold shadow-sm border flex items-center gap-2">
                    <i class="fas fa-user-plus"></i> Add Employee
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div><p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Employees</p><h3 class="text-3xl font-bold text-gray-900 mt-2" id="totalCount">0</h3></div>
                <div class="h-14 w-14 rounded-full bg-teal-50 flex items-center justify-center text-teal-700"><i class="fas fa-users text-2xl"></i></div>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 sm:px-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
                    <h3 class="text-lg font-bold text-gray-900">Employee List</h3>
                    
                    <div class="flex flex-wrap gap-3 w-full sm:w-auto">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" id="filterSearch" placeholder="Search Name or Emp ID..." class="form-control text-sm w-full sm:w-64 pl-9" onkeyup="applyFilters()">
                        </div>
                        <input type="date" id="filterDate" class="form-control text-sm w-full sm:w-48 text-gray-500" onchange="applyFilters()" title="Filter by Joining Date">
                        <button onclick="clearFilters()" class="btn text-sm hover:bg-gray-100 font-medium"><i class="fas fa-times text-gray-400 mr-1"></i> Clear</button>
                    </div>
                </div>

                <div class="hidden sm:flex items-center px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-500 uppercase tracking-wider">
                    <div class="w-1/3">Employee Details</div>
                    <div class="w-1/6 text-right pr-4">Start Date</div>
                    <div class="w-1/6 text-right pr-4">Salary</div>
                    <div class="w-1/6 text-right pr-4">Team</div>
                    <div class="w-1/6 text-center">Action</div>
                </div>

                <div class="divide-y divide-gray-100 custom-scroll overflow-y-auto max-h-[800px]" id="onboardingList">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                            $salaryDisplay = $row['salary'] ? "₹" . number_format((float)$row['salary']) : "—";
                            $profileImage = $row['profile_img'] && !filter_var($row['profile_img'], FILTER_VALIDATE_URL) ? '../' . $row['profile_img'] : $row['profile_img'];
                            
                            // Process Team Members (Exclude current user)
                            $dept = trim($row['department']);
                            $team_list_detailed = [];
                            $team_display_names = [];
                            if(isset($dept_members[$dept])) {
                                foreach($dept_members[$dept] as $tm) {
                                    if(strtolower($tm['name']) !== strtolower($fullName)) {
                                        $team_list_detailed[] = $tm;
                                        $team_display_names[] = htmlspecialchars($tm['name']);
                                    }
                                }
                            }
                            
                            $team_display = empty($team_display_names) ? "No other members" : implode(", ", array_slice($team_display_names, 0, 2));
                            if(count($team_display_names) > 2) {
                                $team_display .= " +" . (count($team_display_names) - 2) . " more";
                            }
                            // JSON string for modal
                            $team_json = htmlspecialchars(json_encode($team_list_detailed), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="onboarding-card p-5 sm:px-6 sm:py-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center hover:bg-slate-50 transition-colors" 
                             data-name="<?= strtolower($fullName) ?>" 
                             data-empid="<?= strtolower($row['emp_id_code']) ?>" 
                             data-joindate="<?= $row['joining_date'] ?>">
                             
                            <div class="flex items-center gap-4 w-full sm:w-1/3">
                                <img src="<?= htmlspecialchars($profileImage) ?>" class="w-12 h-12 rounded-full object-cover border border-gray-300">
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                        <?= $fullName ?> 
                                        <span class="text-[10px] font-bold text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded"><?= htmlspecialchars($row['emp_id_code']) ?></span>
                                    </h4>
                                    <div class="text-xs text-gray-600 mt-0.5"><span class="font-semibold text-teal-700"><?= htmlspecialchars($row['designation']) ?></span> • <?= htmlspecialchars($row['department']) ?></div>
                                    <div class="text-[11px] text-gray-500 mt-1 flex items-center gap-1"><i class="fas fa-user-tie opacity-70"></i> Mgr: <?= htmlspecialchars($row['manager_name'] ?? 'Unassigned') ?></div>
                                </div>
                            </div>
                            
                            <div class="w-full sm:w-1/6 text-left sm:text-right sm:pr-4">
                                <div class="sm:hidden text-xs uppercase font-semibold text-gray-400 mb-1">Start Date</div>
                                <div class="text-sm font-medium text-gray-800"><?= date("M d, Y", strtotime($row['joining_date'])) ?></div>
                            </div>
                            
                            <div class="w-full sm:w-1/6 text-left sm:text-right sm:pr-4">
                                <div class="sm:hidden text-xs uppercase font-semibold text-gray-400 mb-1">Salary</div>
                                <div class="text-sm font-bold text-gray-900"><?= $salaryDisplay ?></div>
                            </div>
                                
                            <div class="w-full sm:w-1/6 text-left sm:text-right sm:pr-4">
                                <div class="sm:hidden text-xs uppercase font-semibold text-gray-400 mb-1">Team</div>
                                <div class="text-xs font-medium text-gray-600 truncate mb-1" title="<?= htmlspecialchars(implode(', ', $team_display_names)) ?>">
                                    <?= $team_display ?>
                                </div>
                                <?php if(!empty($team_list_detailed)): ?>
                                    <button onclick="viewTeam('<?= $team_json ?>', '<?= htmlspecialchars($dept) ?>')" class="inline-flex items-center gap-1 text-[11px] font-bold text-teal-600 hover:text-teal-800 bg-teal-50 px-2 py-1 rounded border border-teal-100 transition-colors sm:ml-auto">
                                        <i class="fas fa-users"></i> View Team
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="w-full sm:w-1/6 flex sm:justify-center mt-2 sm:mt-0">
                                <button onclick="editEmployee(<?= $row['id'] ?>)" class="text-teal-700 hover:text-white bg-white hover:bg-teal-700 px-4 py-1.5 rounded-md transition-all duration-200 border border-teal-600 shadow-sm text-xs font-bold flex items-center gap-2 w-full justify-center sm:w-auto">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div id="emptyState" class="text-center py-16"><div class="text-gray-300 text-5xl mb-3"><i class="fas fa-folder-open"></i></div><p class="text-gray-500 font-medium">No employees found.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="employeeModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Employee</h3>
                <i class="fas fa-times text-gray-400 hover:text-gray-700" style="cursor:pointer; font-size: 18px;" onclick="closeModal()"></i>
            </div>
            
            <div class="modal-tabs">
                <div class="tab-item active" onclick="switchTab(this, 'tab-basic')">Basic Information</div>
                <div class="tab-item" onclick="switchTab(this, 'tab-bank')">Legal & Bank Details</div>
            </div>
            
            <div class="modal-body custom-scroll">
                <form id="empFormDetailed" enctype="multipart/form-data">
                    <input type="hidden" id="editId" value="">
                    
                    <div id="tab-basic" class="tab-content">
                        <div class="img-upload-area">
                            <div class="preview-circle" id="imgPreview"><i class="fas fa-camera text-gray-300" style="font-size: 24px;"></i></div>
                            <div>
                                <h5 style="margin:0 0 4px; font-size:14px; font-weight:700; color:#1e293b;">Profile Photo</h5>
                                <p style="margin:0 0 10px; font-size:12px; color:#64748b;">Supported formats: JPG, PNG (Max 4MB)</p>
                                <input type="file" id="modProfileImg" accept="image/*" class="hidden" onchange="previewImage(this)">
                                <button type="button" class="btn btn-primary shadow-sm" style="padding:6px 14px; font-size:12px;" onclick="document.getElementById('modProfileImg').click()"><i class="fas fa-upload mr-1"></i> Upload Image</button>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group"><label>First Name <span>*</span></label><input type="text" class="form-control" id="modFName" required></div>
                            <div class="form-group"><label>Last Name</label><input type="text" class="form-control" id="modLName"></div>
                            
                            <div class="form-group"><label>Employee ID <span>*</span></label><input type="text" class="form-control" id="modEmpId" required></div>
                            <div class="form-group"><label>Joining Date <span>*</span></label><input type="date" class="form-control" id="modJoinDate" required></div>
                            
                            <div class="form-group"><label>Username <span>*</span></label><input type="text" class="form-control" id="modUName" required></div>
                            <div class="form-group"><label>Email Address <span>*</span></label><input type="email" class="form-control" id="modEmail" placeholder="name@domain.com" required></div>
                            
                            <div class="form-group password-group">
                                <label>Password <span id="pwdAsterisk">*</span></label>
                                <input type="password" class="form-control pr-10" id="modPwd" placeholder="Min. 6 characters">
                                <i class="fas fa-eye-slash password-toggle hover:text-teal-600" onclick="togglePassword('modPwd', this)"></i>
                                <small id="pwdHelpText" style="display:none; color:#64748b; font-size:11px; margin-top:4px;">Leave blank to keep current password</small>
                            </div>
                            <div class="form-group password-group">
                                <label>Confirm Password <span id="pwdConfAsterisk">*</span></label>
                                <input type="password" class="form-control pr-10" id="modPwdConfirm" placeholder="Re-enter password">
                                <i class="fas fa-eye-slash password-toggle hover:text-teal-600" onclick="togglePassword('modPwdConfirm', this)"></i>
                            </div>
                            
                            <div class="form-group"><label>Phone Number <span>*</span></label><input type="text" class="form-control" id="modPhone" placeholder="10-digit number" maxlength="10" required></div>
                            
                            <div class="form-group">
                                <label>Role (System Access) <span>*</span></label>
                                <select class="form-control bg-gray-50" id="modRole" required>
                                    <option value="Employee">Employee</option>
                                    <option value="Manager">Manager</option>
                                    <option value="Team Lead">Team Lead</option>
                                    <option value="HR">HR</option>
                                    <option value="HR Executive">HR Executive</option>
                                    <option value="System Admin">System Admin</option>
                                    <option value="Sales Manager">Sales Manager</option>
                                    <option value="Sales Executive">Sales Executive</option>
                                    <option value="Digital Marketing">Digital Marketing</option>
                                    <option value="IT Admin">IT Admin</option>
                                    <option value="IT Executive">IT Executive</option>
                                    <option value="CFO">CFO</option>
                                    <option value="Accounts">Accounts</option>
                                    <option value="CEO">CEO</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Department <span>*</span></label>
                                <select class="form-control bg-gray-50" id="modDept" onchange="updateModalManager()" required>
                                    <option value="">Select Department</option>
                                    <?php 
                                        $defaults = ['Engineering Dept', 'Development Team', 'Sales', 'Human Resources', 'Design & Creative', 'Accounts', 'IT Department', 'Management'];
                                        foreach($defaults as $def) { if(!in_array($def, $departments)) { $departments[] = $def; } }
                                        foreach($departments as $d) { echo "<option value=\"".htmlspecialchars($d)."\">".htmlspecialchars($d)."</option>"; }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group"><label>Designation (Job Title) <span>*</span></label><input type="text" class="form-control" id="modDesig" placeholder="e.g. Senior Developer" required></div>

                            <div class="form-group">
                                <label>Reporting Manager</label>
                                <select class="form-control bg-gray-50" id="modManager" disabled>
                                    <option value="">Select Department First</option>
                                </select>
                            </div>

                            <div class="form-group"><label>Annual Salary (₹) <span>*</span></label><input type="number" class="form-control" id="modSalary" placeholder="e.g. 1200000" min="0" required></div>
                            <div class="form-group">
                                <label>Employment Type <span>*</span></label>
                                <select class="form-control bg-gray-50" id="modEmpType">
                                    <option value="Permanent">Permanent</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Intern">Intern</option>
                                    <option value="Freelance">Freelance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="tab-bank" class="tab-content" style="display:none;">
                        <h4 class="form-section-title"><i class="fas fa-id-card text-teal-600 mr-2"></i>Legal Information</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>PAN Card No.</label><input type="text" class="form-control" id="modPan" placeholder="ABCDE1234F" style="text-transform: uppercase;" maxlength="10"></div>
                            <div class="form-group"><label>PF Account No.</label><input type="text" class="form-control" id="modPf" placeholder="PF12345678"></div>
                            <div class="form-group"><label>ESI Number</label><input type="text" class="form-control" id="modEsi" placeholder="ESI987654"></div>
                        </div>
                        <h4 class="form-section-title mt-4"><i class="fas fa-university text-teal-600 mr-2"></i>Bank Details</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Bank Name</label><input type="text" class="form-control" id="modBankName" placeholder="e.g. HDFC Bank"></div>
                            <div class="form-group"><label>Bank Account No.</label><input type="text" class="form-control" id="modBankAcc" placeholder="1234567890"></div>
                            <div class="form-group"><label>IFSC Code</label><input type="text" class="form-control" id="modIfsc" placeholder="HDFC0001234" style="text-transform: uppercase;" maxlength="11"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary px-6" id="saveModalBtn"><i class="fas fa-save mr-1"></i> Save Employee</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="teamModal">
        <div class="modal-box small-modal">
            <div class="modal-header bg-gray-50 rounded-t-xl">
                <h3 id="teamModalTitle" class="text-teal-800"><i class="fas fa-users mr-2"></i>Team Members</h3>
                <i class="fas fa-times text-gray-400 hover:text-red-500" style="cursor:pointer; font-size: 18px;" onclick="closeTeamModal()"></i>
            </div>
            <div class="modal-body p-0 custom-scroll max-h-[60vh]">
                <ul id="teamModalList" class="divide-y divide-gray-100">
                    </ul>
            </div>
            <div class="modal-footer py-3 bg-gray-50">
                <button type="button" class="btn text-sm w-full" onclick="closeTeamModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-6 right-6 bg-teal-800 text-white px-6 py-3.5 rounded-lg shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
        <i class="fas fa-check-circle text-lg"></i>
        <span id="toastMsg" class="font-medium">Action completed</span>
    </div>

    <script>
        document.getElementById('currentDateDisplay').textContent = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        const allManagers = <?php echo json_encode($all_managers); ?>;

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { document.getElementById('imgPreview').innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`; }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updateModalManager(selectedManagerId = null) {
            const dept = document.getElementById('modDept').value;
            const mgrSelect = document.getElementById('modManager');
            mgrSelect.innerHTML = '<option value="">Select Manager</option>';
            mgrSelect.disabled = false;
            
            let found = false;
            allManagers.forEach(mgr => {
                if(mgr.dept === dept) {
                    mgrSelect.insertAdjacentHTML('beforeend', `<option value="${mgr.id}">${mgr.name} (${mgr.role})</option>`);
                    found = true;
                }
            });

            if(!found && dept !== "") {
                mgrSelect.innerHTML = '<option value="">No exact match. Select from other Depts:</option>';
                allManagers.forEach(mgr => {
                    mgrSelect.insertAdjacentHTML('beforeend', `<option value="${mgr.id}">${mgr.name} - ${mgr.dept} (${mgr.role})</option>`);
                });
            } else if (dept === "") {
                mgrSelect.innerHTML = '<option value="" disabled selected>Select Department First</option>';
                mgrSelect.disabled = true;
            }

            if(selectedManagerId) {
                mgrSelect.value = selectedManagerId;
            }
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") { input.type = "text"; icon.classList.replace('fa-eye-slash', 'fa-eye'); } 
            else { input.type = "password"; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
        }

        // ADD NEW MODAL
        function openAddModal() { 
            document.getElementById('empFormDetailed').reset();
            document.getElementById('editId').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus text-teal-600 mr-2"></i>Add New Employee';
            document.getElementById('imgPreview').innerHTML = '<i class="fas fa-camera text-gray-300" style="font-size: 24px;"></i>';
            document.getElementById('modManager').innerHTML = '<option value="" disabled selected>Select Department First</option>';
            document.getElementById('modManager').disabled = true;
            
            document.getElementById('pwdHelpText').style.display = 'none';
            document.getElementById('pwdAsterisk').style.display = 'inline';
            document.getElementById('pwdConfAsterisk').style.display = 'inline';
            
            switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
            document.getElementById('employeeModal').style.display = 'flex'; 
        }

        // EDIT MODAL
        function editEmployee(id) {
            fetch('employee_onboarding.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_employee&id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const emp = data.data;
                    document.getElementById('empFormDetailed').reset();
                    document.getElementById('editId').value = emp.id;
                    document.getElementById('modalTitle').innerHTML = `<i class="fas fa-user-edit text-teal-600 mr-2"></i>Edit Profile - ${emp.first_name}`;
                    
                    document.getElementById('modFName').value = emp.first_name;
                    document.getElementById('modLName').value = emp.last_name || '';
                    document.getElementById('modEmpId').value = emp.emp_id_code;
                    document.getElementById('modJoinDate').value = emp.joining_date;
                    document.getElementById('modUName').value = emp.username;
                    document.getElementById('modEmail').value = emp.email;
                    document.getElementById('modPhone').value = emp.phone;
                    document.getElementById('modRole').value = emp.role || 'Employee';
                    document.getElementById('modDept').value = emp.department;
                    document.getElementById('modDesig').value = emp.designation;
                    document.getElementById('modSalary').value = emp.salary;
                    document.getElementById('modEmpType').value = emp.employment_type;
                    
                    document.getElementById('modPan').value = emp.pan_no || '';
                    document.getElementById('modPf').value = emp.pf_no || '';
                    document.getElementById('modEsi').value = emp.esi_no || '';
                    document.getElementById('modBankName').value = emp.bank_name || '';
                    document.getElementById('modBankAcc').value = emp.bank_acc_no || '';
                    document.getElementById('modIfsc').value = emp.ifsc_code || '';
                    
                    // Make passwords optional for edit
                    document.getElementById('pwdHelpText').style.display = 'block';
                    document.getElementById('pwdAsterisk').style.display = 'none';
                    document.getElementById('pwdConfAsterisk').style.display = 'none';
                    
                    if(emp.profile_img) {
                        const imgPath = emp.profile_img.startsWith('http') ? emp.profile_img : '../' + emp.profile_img;
                        document.getElementById('imgPreview').innerHTML = `<img src="${imgPath}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;
                    } else {
                        document.getElementById('imgPreview').innerHTML = '<i class="fas fa-user text-gray-300" style="font-size: 24px;"></i>';
                    }
                    
                    updateModalManager(emp.manager_id);
                    switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
                    document.getElementById('employeeModal').style.display = 'flex';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                showToast("Failed to fetch employee details.", "error");
            });
        }

        function closeModal() { document.getElementById('employeeModal').style.display = 'none'; }

        // TEAM VIEW MODAL
        function viewTeam(teamJson, deptName) {
            const teamData = JSON.parse(teamJson);
            document.getElementById('teamModalTitle').innerHTML = `<i class="fas fa-sitemap mr-2"></i>${deptName} Team`;
            const listEl = document.getElementById('teamModalList');
            
            if(teamData.length === 0) {
                listEl.innerHTML = `<li class="p-6 text-center text-gray-500 font-medium"><i class="fas fa-user-slash text-2xl mb-2 block text-gray-300"></i>No other members found in this department</li>`;
            } else {
                let html = '';
                teamData.forEach(member => {
                    // Decide badge color based on role
                    let badgeClass = 'bg-gray-100 text-gray-600 border-gray-200';
                    const role = member.role.toLowerCase();
                    if(role.includes('manager') || role.includes('ceo') || role.includes('cfo')) badgeClass = 'bg-blue-50 text-blue-700 border-blue-200';
                    else if(role.includes('lead')) badgeClass = 'bg-purple-50 text-purple-700 border-purple-200';
                    else if(role.includes('hr')) badgeClass = 'bg-pink-50 text-pink-700 border-pink-200';

                    html += `<li class="px-6 py-4 flex justify-between items-center hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-xs">${member.name.charAt(0)}</div>
                                    <span class="font-bold text-gray-800">${member.name}</span>
                                </div>
                                <span class="px-3 py-1 text-xs font-bold rounded border ${badgeClass}">${member.role}</span>
                             </li>`;
                });
                listEl.innerHTML = html;
            }
            document.getElementById('teamModal').style.display = 'flex';
        }
        function closeTeamModal() { document.getElementById('teamModal').style.display = 'none'; }


        function switchTab(btn, tabId) {
            document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            btn.classList.add('active');
            document.getElementById(tabId).style.display = 'block';
        }

        // SAVE LOGIC (Handles both Add and Edit)
        document.getElementById('saveModalBtn').addEventListener('click', (e) => {
            e.preventDefault();
            const editId = document.getElementById('editId').value;
            const fName = document.getElementById('modFName').value.trim();
            const lName = document.getElementById('modLName').value.trim();
            const empId = document.getElementById('modEmpId').value.trim();
            const joinDate = document.getElementById('modJoinDate').value;
            const uname = document.getElementById('modUName').value.trim();
            const email = document.getElementById('modEmail').value.trim();
            const pwd = document.getElementById('modPwd').value;
            const pwdConf = document.getElementById('modPwdConfirm').value;
            const phone = document.getElementById('modPhone').value.trim();
            const role = document.getElementById('modRole').value; 
            const dept = document.getElementById('modDept').value;
            const desig = document.getElementById('modDesig').value.trim();
            const manager_id = document.getElementById('modManager').value;
            const salary = document.getElementById('modSalary').value;
            const empType = document.getElementById('modEmpType').value;

            // Validations
            if(!fName || !empId || !joinDate || !desig || !uname || !email || !dept || !salary || !phone || !role) {
                showToast("Please fill all required (*) fields.", "error"); return;
            }
            if(editId === '' && !pwd) {
                showToast("Password is required for new employees.", "error"); return;
            }
            if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast("Invalid email address.", "error"); return; }
            if(!/^[0-9]{10}$/.test(phone)) { showToast("Phone number must be exactly 10 digits.", "error"); return; }
            if(pwd && pwd.length < 6) { showToast("Password must be at least 6 characters.", "error"); return; }
            if(pwd !== pwdConf) { showToast("Passwords do not match.", "error"); return; }

            const btn = document.getElementById('saveModalBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('edit_id', editId);
            formData.append('fname', fName); formData.append('lname', lName);
            formData.append('emp_id', empId); formData.append('join_date', joinDate);
            formData.append('uname', uname); formData.append('email', email);
            formData.append('pwd', pwd); formData.append('phone', phone);
            formData.append('role', role); 
            formData.append('dept', dept); formData.append('desig', desig);
            formData.append('manager_id', manager_id); formData.append('salary', salary);
            formData.append('emp_type', empType);
            formData.append('pan', document.getElementById('modPan').value.trim());
            formData.append('pf', document.getElementById('modPf').value.trim());
            formData.append('esi', document.getElementById('modEsi').value.trim());
            formData.append('bank_name', document.getElementById('modBankName').value.trim());
            formData.append('bank_acc', document.getElementById('modBankAcc').value.trim());
            formData.append('ifsc', document.getElementById('modIfsc').value.trim());
            
            const fileInput = document.getElementById('modProfileImg');
            if (fileInput.files.length > 0) formData.append('profile_img', fileInput.files[0]);

            fetch('employee_onboarding.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText; btn.disabled = false;
                if(data.status === 'success') {
                    showToast(`Employee data saved successfully`);
                    closeModal(); 
                    setTimeout(() => window.location.reload(), 1200); 
                } else showToast(`Error: ${data.message}`, "error");
            }).catch(err => { btn.innerHTML = originalText; btn.disabled = false; showToast("Network error.", "error"); });
        });

        // FILTERS LOGIC
        function applyFilters() {
            const searchVal = document.getElementById('filterSearch').value.toLowerCase();
            const dateVal = document.getElementById('filterDate').value;
            let visibleCount = 0;

            document.querySelectorAll('.onboarding-card').forEach(card => {
                const name = card.getAttribute('data-name');
                const empId = card.getAttribute('data-empid');
                const joinDate = card.getAttribute('data-joindate');

                let matchSearch = (name.includes(searchVal) || empId.includes(searchVal));
                let matchDate = (dateVal === "" || joinDate === dateVal);

                if (matchSearch && matchDate) {
                    card.classList.remove('d-none');
                    visibleCount++;
                } else {
                    card.classList.add('d-none');
                }
            });
            
            const emptyState = document.getElementById('emptyState');
            if(emptyState) {
                emptyState.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function clearFilters() {
            document.getElementById('filterSearch').value = '';
            document.getElementById('filterDate').value = '';
            applyFilters();
        }

        function updateStats() {
            const cards = document.querySelectorAll('.onboarding-card');
            document.getElementById('totalCount').textContent = cards.length;
            if(document.getElementById('emptyState')) document.getElementById('emptyState').classList.toggle('hidden', cards.length > 0);
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast'); document.getElementById('toastMsg').textContent = msg;
            toast.classList.remove('translate-y-24', 'opacity-0');
            toast.className = `fixed bottom-6 right-6 px-6 py-3.5 rounded-lg shadow-2xl transform transition-all duration-300 flex items-center gap-3 z-50 ${type === 'error' ? 'bg-red-600' : 'bg-teal-800'} text-white`;
            setTimeout(() => toast.classList.add('translate-y-24', 'opacity-0'), 3000);
        }
        updateStats();
    </script>
</body>
</html>