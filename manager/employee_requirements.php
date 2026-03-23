<?php
// -------------------------------------------------------------------------
// 1. SESSION & CONFIGURATION
// -------------------------------------------------------------------------
$path_to_root = '../'; // Assuming this file is in a subfolder like /manager/
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// FIX TIMEZONE
date_default_timezone_set('Asia/Kolkata');

// Database Connection
$db_path = $path_to_root . 'include/db_connect.php';
if(file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Error: db_connect.php not found. Please check paths.");
}

// Security Check - Ensure User is Logged In
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// =========================================================================
// 🚀 ENTERPRISE AUTO-PATCHER (Ensures columns exist before inserting)
// =========================================================================
$check_exp = $conn->query("SHOW COLUMNS FROM `hiring_requests` LIKE 'experience_required'");
if ($check_exp && $check_exp->num_rows == 0) {
    @$conn->query("ALTER TABLE `hiring_requests` ADD COLUMN `experience_required` VARCHAR(100) DEFAULT 'Fresher' AFTER `vacancy_count`");
}
$check_skills = $conn->query("SHOW COLUMNS FROM `hiring_requests` LIKE 'key_skills'");
if ($check_skills && $check_skills->num_rows == 0) {
    @$conn->query("ALTER TABLE `hiring_requests` ADD COLUMN `key_skills` TEXT DEFAULT NULL AFTER `experience_required`");
}
$check_desc = $conn->query("SHOW COLUMNS FROM `hiring_requests` LIKE 'job_description'");
if ($check_desc && $check_desc->num_rows == 0) {
    @$conn->query("ALTER TABLE `hiring_requests` ADD COLUMN `job_description` TEXT DEFAULT NULL AFTER `key_skills`");
}

// --- FETCH MANAGER'S DEPARTMENT DYNAMICALLY ---
$mgr_dept = "General"; // Fallback
$dept_query = "SELECT department FROM employee_profiles WHERE user_id = ?";
$dept_stmt = $conn->prepare($dept_query);
if ($dept_stmt) {
    $dept_stmt->bind_param("i", $current_user_id);
    $dept_stmt->execute();
    $dept_res = $dept_stmt->get_result();
    if ($dept_row = $dept_res->fetch_assoc()) {
        $mgr_dept = !empty($dept_row['department']) ? $dept_row['department'] : "General";
    }
    $dept_stmt->close();
}

// -------------------------------------------------------------------------
// 2. HANDLE FORM SUBMISSION (WITH ANTI-DUPLICATE PRG)
// -------------------------------------------------------------------------
// Retrieve flash messages to show after redirect
$msg = "";
$msg_type = "";
if (isset($_SESSION['req_msg'])) {
    $msg = $_SESSION['req_msg'];
    $msg_type = $_SESSION['req_msg_type'];
    unset($_SESSION['req_msg']);
    unset($_SESSION['req_msg_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_requisition'])) {
    // Sanitize Inputs
    $job_title = mysqli_real_escape_string($conn, $_POST['job_title']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $vacancy_count = intval($_POST['vacancy_count']);
    $experience = mysqli_real_escape_string($conn, $_POST['experience']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $skills = mysqli_real_escape_string($conn, $_POST['skills']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Prevent identical duplicate submissions within a short timeframe (double clicks)
    $check_dup = $conn->prepare("SELECT id FROM hiring_requests WHERE manager_id = ? AND job_title = ? AND status = 'Pending' AND created_at >= NOW() - INTERVAL 5 MINUTE");
    $check_dup->bind_param("is", $current_user_id, $job_title);
    $check_dup->execute();
    if ($check_dup->get_result()->num_rows > 0) {
        $_SESSION['req_msg'] = "You have already submitted a request for this role recently.";
        $_SESSION['req_msg_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $check_dup->close();

    // 🚨 FIXED: Corrected 'skills_required' to 'key_skills' to match ATS board expectations
    $sql = "INSERT INTO hiring_requests (manager_id, job_title, department, vacancy_count, experience_required, key_skills, job_description, priority, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ississss", $current_user_id, $job_title, $department, $vacancy_count, $experience, $skills, $description, $priority);
    
    if ($stmt->execute()) {
        $_SESSION['req_msg'] = "Hiring request submitted successfully to HR!";
        $_SESSION['req_msg_type'] = "success";
    } else {
        $_SESSION['req_msg'] = "Error submitting request: " . $conn->error;
        $_SESSION['req_msg_type'] = "error";
    }
    $stmt->close();
    
    // Redirect to prevent form resubmission on page refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// -------------------------------------------------------------------------
// 3. FETCH RECENT REQUESTS (SMART GROUPING TO HIDE EXISTING DUPLICATES)
// -------------------------------------------------------------------------
// This query automatically collapses identical requests made on the same day, prioritizing the most recent status.
$history_sql = "
    SELECT t1.* FROM hiring_requests t1
    INNER JOIN (
        SELECT job_title, MAX(id) as max_id 
        FROM hiring_requests 
        WHERE manager_id = ? 
        GROUP BY job_title, DATE(created_at)
    ) t2 ON t1.id = t2.max_id
    ORDER BY t1.created_at DESC 
    LIMIT 5
";
$h_stmt = $conn->prepare($history_sql);
$h_stmt->bind_param("i", $current_user_id);
$h_stmt->execute();
$history_result = $h_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Requirements - Manager Portal</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; }
        
        /* Sidebar Adjustment */
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
        
        .card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; transition: all 0.2s; }
        .form-control:focus { border-color: #0d9488; outline: none; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
        .btn-primary { background-color: #0f766e; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; transition: background 0.2s; }
        .btn-primary:hover { background-color: #0d9488; }
        
        /* Status Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap;}
        .badge-Pending { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-Approved { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-Rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-In { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; } /* For 'In Progress' */
        .badge-Fulfilled { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    </style>
</head>
<body>

    <?php 
    if(file_exists($path_to_root . 'sidebars.php')) include $path_to_root . 'sidebars.php';
    ?>

    <main id="mainContent" class="main-content">
        <?php 
        if(file_exists($path_to_root . 'header.php')) include $path_to_root . 'header.php';
        ?>

        <main class="p-4 md:p-8 w-full">
            
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Employee Requirements</h1>
                    <p class="text-slate-500 text-sm mt-1">Submit job vacancies to HR for recruitment process.</p>
                </div>
                <div class="text-right">
                    <button onclick="document.getElementById('reqHistory').scrollIntoView({behavior: 'smooth'})" class="text-teal-700 font-semibold text-sm hover:underline">View History</button>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="mb-6 p-4 rounded-lg flex items-center gap-3 <?php echo ($msg_type == 'success') ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                    <i class="fa-solid <?php echo ($msg_type == 'success') ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                    <span class="font-medium"><?php echo $msg; ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <h2 class="text-lg font-bold text-slate-800 mb-6 border-b border-gray-100 pb-3">
                            <i class="fa-solid fa-briefcase text-teal-600 mr-2"></i> Create New Requisition
                        </h2>
                        
                        <form method="POST" action="">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Job Title / Position <span class="text-red-500">*</span></label>
                                    <input type="text" name="job_title" class="form-control" placeholder="e.g. Senior Java Developer" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Department <span class="text-red-500">*</span></label>
                                    <input type="text" name="department" class="form-control bg-slate-50 text-slate-600 font-semibold cursor-not-allowed" value="<?php echo htmlspecialchars($mgr_dept); ?>" readonly required title="Auto-fetched from your profile">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Vacancies <span class="text-red-500">*</span></label>
                                    <input type="number" name="vacancy_count" class="form-control" min="1" value="1" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Experience <span class="text-red-500">*</span></label>
                                    <select name="experience" class="form-control" required>
                                        <option value="Fresher">Fresher (0-1 Years)</option>
                                        <option value="Junior">Junior (1-3 Years)</option>
                                        <option value="Mid-Level">Mid-Level (3-5 Years)</option>
                                        <option value="Senior">Senior (5+ Years)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Priority <span class="text-red-500">*</span></label>
                                    <select name="priority" class="form-control" required>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High" class="text-red-600 font-bold">High (Urgent)</option>
                                        <option value="Low">Low</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Key Skills Required <span class="text-red-500">*</span></label>
                                <input type="text" name="skills" class="form-control" placeholder="e.g. Java, Spring Boot, MySQL, AWS" required>
                            </div>

                            <div class="mb-6">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Job Description & Responsibilities</label>
                                <textarea name="description" rows="5" class="form-control" placeholder="Describe the role details, responsibilities, and any specific requirements for the HR team..."></textarea>
                            </div>

                            <div class="flex justify-end border-t border-gray-100 pt-5">
                                <button type="submit" name="submit_requisition" class="btn-primary shadow-lg shadow-teal-500/30">
                                    <i class="fa-solid fa-paper-plane mr-2"></i> Submit Request to HR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="card p-6 bg-teal-800 text-white mb-6">
                        <h3 class="font-bold text-lg mb-2">Hiring Process</h3>
                        <ul class="text-sm text-teal-100 space-y-3 pl-4 list-disc">
                            <li>Manager submits requirement.</li>
                            <li>HR reviews and approves vacancy.</li>
                            <li>Job posted on portals.</li>
                            <li>HR conducts initial screening.</li>
                            <li>Manager conducts technical round.</li>
                        </ul>
                    </div>

                    <div class="card p-6" id="reqHistory">
                        <h3 class="font-bold text-slate-800 mb-4 text-sm uppercase">Recent Requests</h3>
                        <div class="space-y-4">
                            <?php if ($history_result && $history_result->num_rows > 0): ?>
                                <?php while($row = $history_result->fetch_assoc()): 
                                    $status_class_suffix = explode(' ', $row['status'])[0]; // e.g. "In Progress" -> "In"
                                ?>
                                    <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0">
                                                <p class="font-bold text-sm text-slate-700 truncate" title="<?php echo htmlspecialchars($row['job_title']); ?>"><?php echo htmlspecialchars($row['job_title']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo date("d M Y", strtotime($row['created_at'])); ?> • <?php echo $row['vacancy_count']; ?> Positions</p>
                                            </div>
                                            <span class="badge badge-<?php echo htmlspecialchars($status_class_suffix); ?> shrink-0"><?php echo htmlspecialchars($row['status']); ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 italic">No previous requests found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>