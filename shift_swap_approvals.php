<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Fix Path: Pointing to current directory since file is in workack2.0/
$dbPath = __DIR__ . '/include/db_connect.php';
if (file_exists($dbPath)) { include_once($dbPath); } 
else { include_once('include/db_connect.php'); }

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); // Fix Path
    exit(); 
}

$user_id = $_SESSION['user_id'];
$message = "";

// 1. Get exact user role to determine level of access
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

// Role Classification
$is_tl = in_array($user_role, ['Team Lead', 'TL']);
$is_mgr = in_array($user_role, ['Manager', 'Project Manager', 'General Manager']);
$is_it_admin = ($user_role === 'IT Admin');
$is_hr_admin = in_array($user_role, ['HR', 'HR Executive', 'Admin', 'System Admin', 'CFO', 'CEO']);


// --- HANDLE APPROVAL / REJECTION BASED ON ROLE ---
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $req_id = intval($_GET['request_id']);
    $new_status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';

    $update_sql = "";
    if ($is_tl) {
        $update_sql = "UPDATE shift_swap_requests SET tl_approval = ?, tl_id = ? WHERE id = ?";
    } elseif ($is_mgr || $is_it_admin) {
        $update_sql = "UPDATE shift_swap_requests SET manager_approval = ?, manager_id = ? WHERE id = ?";
    } elseif ($is_hr_admin) {
        $update_sql = "UPDATE shift_swap_requests SET hr_approval = ?, hr_id = ? WHERE id = ?";
    }

    if (!empty($update_sql)) {
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sii", $new_status, $user_id, $req_id);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success border-0 shadow-sm font-bold'>Success: Request #$req_id marked as $new_status by you.</div>";
        } else {
            $message = "<div class='alert alert-danger font-bold'>Error: Could not update the request.</div>";
        }
        $stmt->close();
    }
}

// --- FETCH REQUESTS BASED ON HIERARCHY ---
// Joining with profiles to show employee names
$base_query = "SELECT r.*, p.full_name, p.designation, u.role as actual_role 
               FROM shift_swap_requests r 
               JOIN employee_profiles p ON r.user_id = p.user_id 
               JOIN users u ON r.user_id = u.id";

$sql_requests = "";

if ($is_tl) {
    // TL sees requests where they are the reporting manager
    $sql_requests = "$base_query WHERE p.reporting_to = $user_id AND r.user_id != $user_id AND u.role NOT IN ('CFO', 'Accounts', 'Accountant', 'IT Executive', 'IT Admin', 'HR Executive') ORDER BY (r.tl_approval = 'Pending') DESC, r.created_at DESC";
} elseif ($is_mgr) {
    // Manager sees requests already approved by TL, or direct reportees
    $sql_requests = "$base_query WHERE (r.tl_approval = 'Approved' OR p.manager_id = $user_id OR p.reporting_to = $user_id) AND r.user_id != $user_id AND u.role NOT IN ('CFO', 'Accounts', 'Accountant', 'IT Executive', 'IT Admin', 'HR Executive') ORDER BY (r.manager_approval = 'Pending') DESC, r.created_at DESC";
} elseif ($is_it_admin) {
    // IT Admin sees IT Executives
    $sql_requests = "$base_query WHERE u.role = 'IT Executive' AND r.user_id != $user_id ORDER BY (r.manager_approval = 'Pending') DESC, r.created_at DESC";
} elseif ($is_hr_admin) {
    // HR sees requests approved by Manager (or direct for special roles)
    $sql_requests = "$base_query WHERE (r.manager_approval = 'Approved' OR u.role IN ('IT Executive', 'CFO', 'Accounts', 'Accountant', 'IT Admin', 'HR Executive')) AND r.user_id != $user_id ORDER BY (r.hr_approval = 'Pending') DESC, r.created_at DESC";
} else {
    // Fallback security
    $sql_requests = "$base_query WHERE 1=0";
}

$result = $conn->query($sql_requests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Swap Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 13px; }
<<<<<<< HEAD
        /* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT 
           ========================================================== */
        .main-content, #mainContent {
            
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            min-height: 100vh;
        }

        /* Desktop: Shifts content right when secondary sub-menu opens */
        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 215px; /* 95px + 220px */
            width: calc(100% - 215px);
        }

        /* Mobile & Tablet Adjustments */
        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 20px 15px 30px !important; /* Top padding clears the hamburger menu */
            }
            
            /* Prevent shifting on mobile (menu floats over content instead) */
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
=======
        #mainContent { margin-left: 95px; padding: 40px; min-height: 100vh; }
>>>>>>> 137345ac8b9688b1e901efc148117e3cb227d93d
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #fff; }
        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .bg-pending { background: #fff7ed; color: #9a3412; }
        .bg-approved { background: #dcfce7; color: #166534; }
        .bg-rejected { background: #fee2e2; color: #991b1b; }
        @media (max-width: 768px) { #mainContent { margin-left: 0 !important; padding: 15px; } }
    </style>
</head>
<body class="bg-slate-50">

<<<<<<< HEAD
<main id="mainContent" class="main-content">
=======
>>>>>>> 137345ac8b9688b1e901efc148117e3cb227d93d
    <?php include('sidebars.php'); ?> <?php include('header.php'); ?>   <main id="mainContent">
        <div class="w-full">
            <div class="mb-8 flex justify-between items-end">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Shift Swap Approvals (<?php echo htmlspecialchars($user_role); ?>)</h1>
                    <p class="text-slate-500 text-sm">Review pending shift changes. Your approval will move the request forward.</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Neoera Infotech</span>
                </div>
            </div>
<<<<<<< HEAD
            </main>
=======
>>>>>>> 137345ac8b9688b1e901efc148117e3cb227d93d

            <?php echo $message; ?>

            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr class="text-slate-400 text-[10px] uppercase tracking-widest font-bold">
                                <th class="px-6 py-4">Employee</th>
                                <th>Swap Date</th>
                                <th>Shift Details</th>
                                <th>Reason</th>
                                <th>Your Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): 
                                    // Determine WHICH status to display based on the logged-in user's role
                                    $my_status = 'Pending';
                                    if ($is_tl) { $my_status = $row['tl_approval'] ?? 'Pending'; }
                                    elseif ($is_mgr || $is_it_admin) { $my_status = $row['manager_approval'] ?? 'Pending'; }
                                    elseif ($is_hr_admin) { $my_status = $row['hr_approval'] ?? 'Pending'; }
                                ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-bold">
                                                <?php echo substr($row['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 mb-0"><?php echo $row['full_name']; ?></p>
                                                <p class="text-[10px] text-slate-400"><?php echo $row['designation']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="font-semibold text-slate-600"><?php echo date('d M Y', strtotime($row['request_date'])); ?></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-medium text-slate-500"><?php echo $row['current_shift']; ?></span>
                                            <i class="fa fa-arrow-right text-[9px] text-orange-400"></i>
                                            <span class="text-[11px] font-bold text-orange-600"><?php echo $row['requested_shift']; ?></span>
                                        </div>
                                    </td>
                                    <td class="text-slate-500 italic text-[11px] max-w-xs truncate" title="<?php echo $row['reason']; ?>">
                                        <?php echo $row['reason']; ?>
                                    </td>
                                    <td>
                                        <span class="status-pill bg-<?php echo strtolower($my_status); ?>">
                                            <?php echo $my_status; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($my_status == 'Pending'): ?>
                                            <div class="flex justify-center gap-2">
                                                <a href="?action=approve&request_id=<?php echo $row['id']; ?>" class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-all shadow-sm">
                                                    <i class="fa fa-check text-xs"></i>
                                                </a>
                                                <a href="?action=reject&request_id=<?php echo $row['id']; ?>" class="w-8 h-8 rounded-full bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                                    <i class="fa fa-times text-xs"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center">
                                                <i class="fa fa-check-circle text-emerald-500 text-lg"></i>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Submitted</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-12 text-slate-400 italic">No swap requests found for your queue.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>