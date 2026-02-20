<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php'; 

// ---------------------------------------------------------
// FETCH MANAGERS BY DEPARTMENT FOR DYNAMIC DROPDOWN
// ---------------------------------------------------------
$dept_managers = [];
$mgr_sql = "SELECT u.id, COALESCE(p.full_name, u.name, u.username) as manager_name, p.department 
            FROM users u 
            JOIN employee_profiles p ON u.id = p.user_id 
            WHERE u.role IN ('Manager', 'Team Lead', 'System Admin', 'HR') 
            AND p.department IS NOT NULL";

$mgr_res = $conn->query($mgr_sql);
if ($mgr_res && $mgr_res->num_rows > 0) {
    while($row = $mgr_res->fetch_assoc()) {
        $dept = trim($row['department']);
        $name = htmlspecialchars($row['manager_name']);
        
        if (!isset($dept_managers[$dept])) {
            $dept_managers[$dept] = [];
        }
        $dept_managers[$dept][] = $name;
    }
}

// ---------------------------------------------------------
// BACKEND AJAX HANDLERS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. ADD NEW EMPLOYEE (TO ALL 3 TABLES)
    if ($action === 'add') {
        // Basic Info
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
        $manager = $_POST['manager'] ?? '';
        $salary = $_POST['salary'] ?? '';
        $emp_type = $_POST['emp_type'] ?? '';

        // Bank Details
        $pan = strtoupper(trim($_POST['pan'] ?? ''));
        $pf = trim($_POST['pf'] ?? '');
        $esi = trim($_POST['esi'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_acc = trim($_POST['bank_acc'] ?? '');
        $ifsc = strtoupper(trim($_POST['ifsc'] ?? ''));

        // Hash Password
        $pwd_hash = !empty($pwd) ? password_hash($pwd, PASSWORD_DEFAULT) : NULL;

        // Handle File Upload if exists
        $img = "https://ui-avatars.com/api/?name=" . urlencode($fullName) . "&background=random";
        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $fileExt = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
            $fileName = 'EMP_' . time() . '_' . rand(1000,9999) . '.' . $fileExt;
            $targetFilePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $targetFilePath)) {
                $img = 'uploads/profiles/' . $fileName; 
            }
        }

        // ========================================================
        // TRANSACTION TO INSERT INTO 3 TABLES SIMULTANEOUSLY
        // ========================================================
        $conn->begin_transaction();
        try {
            // STEP A: Get Manager's User ID
            $manager_id = NULL;
            if (!empty($manager)) {
                $stmt_mgr = $conn->prepare("SELECT user_id FROM employee_profiles WHERE full_name = ? LIMIT 1");
                $stmt_mgr->bind_param("s", $manager);
                $stmt_mgr->execute();
                $res_mgr = $stmt_mgr->get_result();
                if ($row_mgr = $res_mgr->fetch_assoc()) {
                    $manager_id = $row_mgr['user_id'];
                }
                $stmt_mgr->close();
            }

            // STEP B: Insert into `users` table (Now including Department)
            $stmt_user = $conn->prepare("INSERT INTO users (name, employee_id, department, username, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            
            // Map the selected role from frontend or default to Employee
            $assigned_role = 'Employee'; // Default
            
            $stmt_user->bind_param("ssssss", $fullName, $emp_id, $dept, $email, $pwd_hash, $assigned_role);
            if (!$stmt_user->execute()) throw new Exception("User Table Error: " . $stmt_user->error);
            $new_user_id = $stmt_user->insert_id;
            $stmt_user->close();

            // STEP C: Insert into `employee_profiles` table
            $bank_info_arr = [
                "bank_name" => $bank_name,
                "acc_no" => $bank_acc,
                "ifsc" => $ifsc,
                "pan" => $pan,
                "pf_no" => $pf,
                "esi_no" => $esi
            ];
            $bank_info_json = json_encode($bank_info_arr);

            $stmt_prof = $conn->prepare("INSERT INTO employee_profiles (user_id, full_name, designation, department, reporting_to, manager_id, emp_id_code, phone, joining_date, email, profile_img, bank_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_prof->bind_param("isssiissssss", $new_user_id, $fullName, $desig, $dept, $manager_id, $manager_id, $emp_id, $phone, $join_date, $email, $img, $bank_info_json);
            if (!$stmt_prof->execute()) throw new Exception("Profile Table Error: " . $stmt_prof->error);
            $stmt_prof->close();

            // STEP D: Insert into `employee_onboarding` table
            $sql_onb = "INSERT INTO employee_onboarding (
                        emp_id_code, first_name, last_name, email, phone, department, 
                        designation, manager_name, salary, employment_type, joining_date, 
                        username, password_hash, pan_no, pf_no, esi_no, bank_name, bank_acc_no, ifsc_code,
                        status, profile_img
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?)";
            
            $stmt_onb = $conn->prepare($sql_onb);
            $stmt_onb->bind_param("ssssssssssssssssssss", 
                $emp_id, $fname, $lname, $email, $phone, $dept, 
                $desig, $manager, $salary, $emp_type, $join_date, 
                $uname, $pwd_hash, $pan, $pf, $esi, $bank_name, $bank_acc, $ifsc,
                $img
            );
            if (!$stmt_onb->execute()) throw new Exception("Onboarding Table Error: " . $stmt_onb->error);
            $stmt_onb->close();

            // Commit all queries if successful
            $conn->commit();
            echo json_encode(['status' => 'success']);

        } catch (Exception $e) {
            // Rollback all queries if any single one fails (e.g. Duplicate Email)
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }

    // 2. UPDATE STATUS
    if ($action === 'update_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE employee_onboarding SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error']);
        exit();
    }

    // 3. DELETE RECORD
    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM employee_onboarding WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error']);
        exit();
    }
}

// FETCH EXISTING RECORDS FOR DISPLAY
$sql = "SELECT * FROM employee_onboarding ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding | Workack HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1b5a5a;
            --primary-light: #2d7a7a;
            --primary-bg: #f0fdfa;
            --border: #e2e8f0;
            --text-muted: #64748b;
        }

        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
        
        main#content-wrapper {
            margin-left: 95px; padding-top: 80px; padding-bottom: 40px; min-height: 100vh; transition: margin-left 0.3s ease;
        }
        .sidebar-secondary.open ~ main#content-wrapper { margin-left: calc(95px + 220px); }

        .btn { padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; border: 1px solid var(--border); background: white; color: var(--text-muted); font-weight: 500; transition: all 0.2s; }
        .btn:hover { background: #f8fafc; }
        
        .btn-primary { background-color: var(--primary) !important; color: white !important; border-color: var(--primary) !important; }
        .btn-primary:hover { background-color: var(--primary-light) !important; border-color: var(--primary-light) !important; transform: translateY(-1px); }

        .onboarding-card { transition: all 0.3s ease; border-left: 4px solid transparent; }
        .onboarding-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); border-left: 4px solid var(--primary); }

        .d-none { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.12); outline: none; }
        .filter-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal-box { background: white; border-radius: 12px; width: 100%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
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
        .form-group .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; transition: all 0.2s; background: #fff; }
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
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Employee Onboarding</h2>
                <p class="text-gray-600 mt-1.5">Manage new hires, assign IDs, managers, and salary packages.</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-600 bg-white px-5 py-2.5 rounded-lg shadow-sm border">
                    <span id="currentDateDisplay"></span>
                </div>
                <button onclick="openModal()" class="btn-primary px-5 py-2.5 rounded-lg font-bold shadow-sm border flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Onboarding</p>
                    <h3 class="text-3xl font-bold text-gray-900 mt-2" id="totalCount">0</h3>
                </div>
                <div class="h-14 w-14 rounded-full bg-teal-50 flex items-center justify-center text-teal-700">
                    <i class="fas fa-users text-2xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">In Progress</p>
                    <h3 class="text-3xl font-bold text-orange-600 mt-2" id="inProgressCount">0</h3>
                </div>
                <div class="h-14 w-14 rounded-full bg-orange-50 flex items-center justify-center text-orange-600">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Completed</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2" id="completedCount">0</h3>
                </div>
                <div class="h-14 w-14 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 min-h-[700px]">
                <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gray-50 rounded-t-xl">
                    <h3 class="text-xl font-bold text-gray-900">Onboarding Pipeline</h3>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="filterPipeline('All', this)" class="filter-btn active px-4 py-2 text-sm font-semibold rounded-lg border transition-all">All</button>
                        <button onclick="filterPipeline('Pending', this)" class="filter-btn px-4 py-2 text-sm font-semibold rounded-lg border transition-all">Pending</button>
                        <button onclick="filterPipeline('In Progress', this)" class="filter-btn px-4 py-2 text-sm font-semibold rounded-lg border transition-all">In Progress</button>
                        <button onclick="filterPipeline('Completed', this)" class="filter-btn px-4 py-2 text-sm font-semibold rounded-lg border transition-all">Completed</button>
                    </div>
                </div>

                <div class="p-6 custom-scroll overflow-y-auto max-h-[800px]" id="onboardingList">
                    
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $statusColor = $row['status'] == 'Completed' ? 'bg-green-100 text-green-700 border-green-200' : 
                                            ($row['status'] == 'In Progress' ? 'bg-orange-100 text-orange-700 border-orange-200' : 'bg-gray-100 text-gray-600 border-gray-200');
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                            $salaryDisplay = $row['salary'] ? "₹" . number_format((float)$row['salary']) : "—";
                            
                            $profileImage = $row['profile_img'];
                            if ($profileImage && !filter_var($profileImage, FILTER_VALIDATE_URL)) {
                                $profileImage = '../' . $profileImage;
                            }
                        ?>
                        <div class="onboarding-card bg-white border border-gray-100 rounded-lg p-5 mb-5 flex flex-col sm:flex-row gap-5 items-start sm:items-center justify-between" data-id="<?= $row['id'] ?>" data-status="<?= $row['status'] ?>">
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <img src="<?= htmlspecialchars($profileImage) ?>" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200" alt="">
                                <div>
                                    <h4 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                        <?= $fullName ?> 
                                        <span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded border"><?= htmlspecialchars($row['emp_id_code']) ?></span>
                                    </h4>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium text-teal-700"><?= htmlspecialchars($row['designation']) ?></span> • <?= htmlspecialchars($row['department']) ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                                        <i class="fas fa-user-tie"></i> Mgr: <?= htmlspecialchars($row['manager_name'] ?? 'Pending Assignment') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 sm:gap-8 w-full sm:w-auto">
                                <div class="text-left sm:text-right">
                                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Start Date</div>
                                    <div class="text-sm font-medium text-gray-800"><?= date("M d, Y", strtotime($row['joining_date'])) ?></div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Salary</div>
                                    <div class="text-sm font-bold text-gray-900"><?= $salaryDisplay ?></div>
                                </div>
                                <span class="px-4 py-1.5 rounded-full text-xs font-bold status-badge <?= $statusColor ?>">
                                    <?= $row['status'] ?>
                                </span>
                                <div class="relative group">
                                    <button class="text-gray-500 hover:text-teal-700 p-2"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="hidden group-hover:block absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-xl z-20">
                                        <button onclick="updateStatus(this, 'In Progress', <?= $row['id'] ?>)" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-orange-50">
                                            <i class="fas fa-hourglass-half mr-2 text-orange-500"></i> In Progress
                                        </button>
                                        <button onclick="updateStatus(this, 'Completed', <?= $row['id'] ?>)" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50">
                                            <i class="fas fa-check mr-2 text-green-600"></i> Complete
                                        </button>
                                        <button onclick="deleteCard(this, <?= $row['id'] ?>)" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 border-t">
                                            <i class="fas fa-trash mr-2"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div id="emptyState" class="text-center py-16">
                            <div class="text-gray-300 text-6xl mb-4"><i class="fas fa-clipboard-list"></i></div>
                            <p class="text-gray-500 text-lg">No onboarding records found.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="employeeModal">
        <div class="modal-box">
            <div class="modal-header">
                <div style="display:flex; align-items:center;">
                    <h3 id="modalTitle">Add New Employee</h3>
                </div>
                <i class="fas fa-times" style="cursor:pointer; color:#94a3b8; font-size: 18px;" onclick="closeModal()"></i>
            </div>
            
            <div class="modal-tabs">
                <div class="tab-item active" onclick="switchTab(this, 'tab-basic')">Basic Information</div>
                <div class="tab-item" onclick="switchTab(this, 'tab-bank')">Bank Details</div>
            </div>
            
            <div class="modal-body custom-scroll">
                <form id="empFormDetailed" enctype="multipart/form-data">
                    
                    <div id="tab-basic" class="tab-content">
                        
                        <div class="img-upload-area">
                            <div class="preview-circle" id="imgPreview">
                                <i class="fas fa-image" style="font-size: 24px;"></i>
                            </div>
                            <div>
                                <h5 style="margin:0 0 5px; font-size:14px; font-weight:600;">Upload Profile Image</h5>
                                <p style="margin:0 0 10px; font-size:12px; color:#94a3b8;">Image should be below 4 MB (JPG, PNG)</p>
                                
                                <input type="file" id="modProfileImg" name="profile_img" accept="image/*" class="hidden" onchange="previewImage(this)">
                                <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="document.getElementById('modProfileImg').click()">
                                    Browse Photo
                                </button>
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
                                <label>Password <span>*</span></label>
                                <input type="password" class="form-control" id="modPwd" placeholder="Min. 6 characters" required>
                                <i class="fas fa-eye-slash password-toggle" onclick="togglePassword('modPwd', this)"></i>
                            </div>
                            <div class="form-group password-group">
                                <label>Confirm Password <span>*</span></label>
                                <input type="password" class="form-control" id="modPwdConfirm" placeholder="Re-enter password" required>
                                <i class="fas fa-eye-slash password-toggle" onclick="togglePassword('modPwdConfirm', this)"></i>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone Number <span>*</span></label>
                                <input type="text" class="form-control" id="modPhone" placeholder="10-digit number" maxlength="10" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Department <span>*</span></label>
                                <select class="form-control" id="modDept" onchange="updateModalManager()" required>
                                    <option value="">Select Department</option>
                                    <?php 
                                        $unique_depts = array_keys($dept_managers);
                                        foreach($unique_depts as $d) { echo "<option value=\"".htmlspecialchars($d)."\">".htmlspecialchars($d)."</option>"; }
                                        $defaults = ['Engineering Dept', 'Development Team', 'Sales', 'Human Resources', 'Design & Creative'];
                                        foreach($defaults as $def) { if(!in_array($def, $unique_depts)) { echo "<option value=\"$def\">$def</option>"; } }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Designation (Job Role) <span>*</span></label>
                                <input type="text" class="form-control" id="modDesig" placeholder="e.g. Senior Developer" required>
                            </div>

                            <div class="form-group">
                                <label>Reporting Manager</label>
                                <select class="form-control" id="modManager" disabled>
                                    <option value="">Select Department First</option>
                                </select>
                            </div>

                            <div class="form-group"><label>Annual Salary (₹) <span>*</span></label><input type="number" class="form-control" id="modSalary" placeholder="e.g. 1200000" min="0" required></div>
                            <div class="form-group">
                                <label>Employment Type <span>*</span></label>
                                <select class="form-control" id="modEmpType">
                                    <option value="Permanent">Permanent</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Intern">Intern</option>
                                    <option value="Freelance">Freelance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="tab-bank" class="tab-content" style="display:none;">
                        <h4 class="form-section-title">Legal Details</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>PAN Card No.</label>
                                <input type="text" class="form-control" id="modPan" placeholder="ABCDE1234F" style="text-transform: uppercase;" maxlength="10">
                            </div>
                            <div class="form-group"><label>PF Account No.</label><input type="text" class="form-control" id="modPf" placeholder="PF12345678"></div>
                            <div class="form-group"><label>ESI Number</label><input type="text" class="form-control" id="modEsi" placeholder="ESI987654"></div>
                        </div>

                        <h4 class="form-section-title">Bank Details</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Bank Name</label><input type="text" class="form-control" id="modBankName" placeholder="e.g. HDFC Bank"></div>
                            <div class="form-group"><label>Bank Account No.</label><input type="text" class="form-control" id="modBankAcc" placeholder="1234567890"></div>
                            <div class="form-group">
                                <label>IFSC Code</label>
                                <input type="text" class="form-control" id="modIfsc" placeholder="HDFC0001234" style="text-transform: uppercase;" maxlength="11">
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveModalBtn">Save Employee</button>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-6 right-6 bg-teal-800 text-white px-6 py-3.5 rounded-lg shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
        <i class="fas fa-check-circle text-lg"></i>
        <span id="toastMsg" class="font-medium">Action completed</span>
    </div>

    <script>
        document.getElementById('currentDateDisplay').textContent = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        const departmentManagers = <?php echo json_encode($dept_managers); ?>;

        // Image Preview Logic
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imgPreview');
                    preview.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updateModalManager() {
            const dept = document.getElementById('modDept').value;
            const mgrSelect = document.getElementById('modManager');
            mgrSelect.innerHTML = '<option value="" disabled selected>Select Manager</option>';
            if (dept && departmentManagers[dept] && departmentManagers[dept].length > 0) {
                mgrSelect.disabled = false;
                departmentManagers[dept].forEach(mgr => { mgrSelect.insertAdjacentHTML('beforeend', `<option value="${mgr}">${mgr}</option>`); });
            } else {
                mgrSelect.innerHTML = '<option value="" disabled selected>No Managers found in this Dept</option>';
                mgrSelect.disabled = true;
            }
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") { input.type = "text"; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } 
            else { input.type = "password"; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
        }

        function openModal() { document.getElementById('employeeModal').style.display = 'flex'; }
        
        function closeModal() { 
            document.getElementById('employeeModal').style.display = 'none'; 
            document.getElementById('empFormDetailed').reset();
            document.getElementById('imgPreview').innerHTML = '<i class="fas fa-image" style="font-size: 24px;"></i>'; // Reset image
            const mgrSelect = document.getElementById('modManager');
            mgrSelect.innerHTML = '<option value="" disabled selected>Select Department First</option>';
            mgrSelect.disabled = true;
            switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
        }

        function switchTab(btnElement, tabId) {
            document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            btnElement.classList.add('active');
            document.getElementById(tabId).style.display = 'block';
        }

        // SAVE LOGIC WITH VALIDATIONS
        document.getElementById('saveModalBtn').addEventListener('click', (e) => {
            e.preventDefault();
            
            // Collect Values
            const fName = document.getElementById('modFName').value.trim();
            const lName = document.getElementById('modLName').value.trim();
            const empId = document.getElementById('modEmpId').value.trim();
            const joinDate = document.getElementById('modJoinDate').value;
            const uname = document.getElementById('modUName').value.trim();
            const email = document.getElementById('modEmail').value.trim();
            const pwd = document.getElementById('modPwd').value;
            const pwdConf = document.getElementById('modPwdConfirm').value;
            const phone = document.getElementById('modPhone').value.trim();
            const dept = document.getElementById('modDept').value;
            const desig = document.getElementById('modDesig').value.trim();
            const manager = document.getElementById('modManager').value;
            const salary = document.getElementById('modSalary').value;
            const empType = document.getElementById('modEmpType').value;

            const pan = document.getElementById('modPan').value.trim().toUpperCase();
            const pf = document.getElementById('modPf').value.trim();
            const esi = document.getElementById('modEsi').value.trim();
            const bankName = document.getElementById('modBankName').value.trim();
            const bankAcc = document.getElementById('modBankAcc').value.trim();
            const ifsc = document.getElementById('modIfsc').value.trim().toUpperCase();

            const fileInput = document.getElementById('modProfileImg');

            // --- VALIDATIONS ---
            
            if(!fName || !empId || !joinDate || !desig || !uname || !email || !pwd || !dept || !salary || !phone) {
                showToast("Please fill all required (*) fields.", "error");
                switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(email)) {
                showToast("Please enter a valid email address.", "error");
                switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
                return;
            }

            const phoneRegex = /^[0-9]{10}$/;
            if(!phoneRegex.test(phone)) {
                showToast("Phone number must be exactly 10 digits.", "error");
                switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
                return;
            }

            if(pwd.length < 6) {
                showToast("Password must be at least 6 characters long.", "error");
                switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
                return;
            }
            if(pwd !== pwdConf) {
                showToast("Password and Confirm Password do not match.", "error");
                switchTab(document.querySelector('.tab-item:nth-child(1)'), 'tab-basic');
                return;
            }

            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            if(pan !== "" && !panRegex.test(pan)) {
                showToast("Invalid PAN format (e.g. ABCDE1234F).", "error");
                switchTab(document.querySelector('.tab-item:nth-child(2)'), 'tab-bank');
                return;
            }

            // --- PREPARE FORMDATA ---
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('fname', fName); formData.append('lname', lName);
            formData.append('emp_id', empId); formData.append('join_date', joinDate);
            formData.append('uname', uname); formData.append('email', email);
            formData.append('pwd', pwd); formData.append('phone', phone);
            formData.append('dept', dept); formData.append('desig', desig);
            formData.append('manager', manager); formData.append('salary', salary);
            formData.append('emp_type', empType); formData.append('pan', pan);
            formData.append('pf', pf); formData.append('esi', esi);
            formData.append('bank_name', bankName); formData.append('bank_acc', bankAcc);
            formData.append('ifsc', ifsc);
            
            if (fileInput.files.length > 0) {
                formData.append('profile_img', fileInput.files[0]);
            }

            // Send to Backend
            fetch('employee_onboarding.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast(`Employee ${fName} successfully added`);
                    closeModal();
                    setTimeout(() => window.location.reload(), 1000); 
                } else {
                    showToast(`Error: ${data.message}`, "error");
                }
            });
        });

        // UPDATE STATUS via AJAX
        function updateStatus(btn, status, id) {
            const formData = new FormData();
            formData.append('action', 'update_status'); formData.append('id', id); formData.append('status', status);

            fetch('employee_onboarding.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const card = btn.closest('.onboarding-card'); card.dataset.status = status;
                    const badge = card.querySelector('.status-badge'); badge.textContent = status;
                    badge.className = 'px-4 py-1.5 rounded-full text-xs font-bold border status-badge ';
                    if (status === 'Completed') { badge.classList.add('bg-green-100', 'text-green-700', 'border-green-200'); showToast('Onboarding marked as completed'); } 
                    else if (status === 'In Progress') { badge.classList.add('bg-orange-100', 'text-orange-700', 'border-orange-200'); showToast('Status updated to In Progress'); } 
                    else { badge.classList.add('bg-gray-100', 'text-gray-600', 'border-gray-200'); showToast('Status updated to Pending'); }
                    updateStats();
                } else { showToast('Failed to update status', 'error'); }
            });
        }

        // DELETE RECORD via AJAX
        function deleteCard(btn, id) {
            if (!confirm('Remove this onboarding record?')) return;
            const formData = new FormData(); formData.append('action', 'delete'); formData.append('id', id);

            fetch('employee_onboarding.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const card = btn.closest('.onboarding-card');
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(30px)';
                    setTimeout(() => { card.remove(); updateStats(); showToast('Record removed'); }, 300);
                } else { showToast('Failed to remove record', 'error'); }
            });
        }

        // UI FILTERS & UTILS
        function filterPipeline(status, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => { b.classList.remove('active', 'bg-teal-700', 'text-white', 'border-teal-700'); b.classList.add('text-gray-700', 'bg-white', 'border-gray-300'); });
            btn.classList.add('active', 'bg-teal-700', 'text-white', 'border-teal-700');
            document.querySelectorAll('.onboarding-card').forEach(card => { card.classList.toggle('d-none', status !== 'All' && card.dataset.status !== status); });
        }

        function updateStats() {
            const cards = document.querySelectorAll('.onboarding-card');
            const total = cards.length; let progress = 0, completed = 0;
            cards.forEach(c => { const s = c.dataset.status; if (s === 'Completed') completed++; else if (s === 'In Progress') progress++; });
            document.getElementById('totalCount').textContent = total; document.getElementById('inProgressCount').textContent = progress; document.getElementById('completedCount').textContent = completed;
            const emptyState = document.getElementById('emptyState'); if(emptyState) emptyState.classList.toggle('hidden', total > 0);
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast'); document.getElementById('toastMsg').textContent = msg;
            toast.classList.remove('translate-y-24', 'opacity-0');
            toast.className = `fixed bottom-6 right-6 px-6 py-3.5 rounded-lg shadow-2xl transform transition-all duration-300 flex items-center gap-3 z-50 ${type === 'error' ? 'bg-red-600' : 'bg-teal-800'} text-white`;
            setTimeout(() => toast.classList.add('translate-y-24', 'opacity-0'), 3000);
        }

        updateStats(); // Initial call
    </script>
</body>
</html>