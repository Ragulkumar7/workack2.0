<?php
require_once '../include/db_connect.php'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get current user role
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

// --- HANDLE APPROVE / REJECT ACTIONS (HR ONLY) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['req_id'])) {
    if ($user_role === 'HR' || $user_role === 'System Admin') {
        $req_id = intval($_POST['req_id']);
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            // 1. Update status to Approved
            $conn->query("UPDATE hiring_requests SET status = 'Approved' WHERE id = $req_id");
            
            // 2. Fetch job details for notification
            $job_res = $conn->query("SELECT job_title, department FROM hiring_requests WHERE id = $req_id");
            $job_data = $job_res->fetch_assoc();
            $job_title = $job_data['job_title'];
            $department = $job_data['department'];
            
            // 3. Notify all HR Executives
            $execs = $conn->query("SELECT id FROM users WHERE role = 'HR Executive'");
            $title = "New Job Assigned";
            $message = "Please start recruiting for $job_title ($department).";
            $type = "alert";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            while ($exec = $execs->fetch_assoc()) {
                $eid = $exec['id'];
                $notif_stmt->bind_param("isss", $eid, $title, $message, $type);
                $notif_stmt->execute();
            }
            $notif_stmt->close();
            
            header("Location: jobs.php?msg=approved");
            exit();
        } 
        elseif ($action === 'reject') {
            $conn->query("UPDATE hiring_requests SET status = 'Rejected' WHERE id = $req_id");
            header("Location: jobs.php?msg=rejected");
            exit();
        }
    }
}

// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hiring_requests_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Request ID', 'Job Title', 'Department', 'Requested By', 'Vacancies', 'Experience Required', 'Priority', 'Status', 'Date Requested'));
    
    // Role-based export condition
    $export_cond = "";
    if ($user_role === 'HR Executive') {
        $export_cond = "WHERE hr.status IN ('Approved', 'In Progress', 'Fulfilled')";
    }
    
    $export_sql = "SELECT hr.id, hr.job_title, hr.department, u.name as requested_by, hr.vacancy_count, hr.experience_required, hr.priority, hr.status, hr.created_at 
                   FROM hiring_requests hr 
                   LEFT JOIN users u ON hr.manager_id = u.id 
                   $export_cond ORDER BY hr.created_at DESC";
                   
    $export_result = mysqli_query($conn, $export_sql);
    if ($export_result && mysqli_num_rows($export_result) > 0) {
        while ($row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit();
}

include '../sidebars.php';
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Pipeline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .flatpickr-day.selected { background: #0f766e !important; border-color: #0f766e !important; }
    </style>
    
    <script>
        function updateFilter(param, value) {
            const url = new URL(window.location.href);
            if (value) url.searchParams.set(param, value);
            else url.searchParams.delete(param);
            window.location.href = url.href;
        }
    </script>
</head>
<body>

    <div class="lg:ml-20 p-6 md:p-10 transition-all duration-300 min-h-screen">
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i> Hiring request approved and pushed to HR Executives!
            </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'rejected'): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> Hiring request rejected.
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Recruitment Pipeline</h1>
                <nav class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                    <i class="fa-solid fa-house text-xs"></i>
                    <span>&rsaquo;</span> Recruitment <span>&rsaquo;</span> <span class="text-gray-400">Job Requests</span>
                </nav>
            </div>
            
            <div class="flex items-center gap-3 mt-4 md:mt-0">
                <button onclick="window.location.href='?export=csv'" class="bg-white border px-4 py-2 rounded-md text-gray-700 font-medium flex items-center gap-2 hover:bg-gray-50 transition">
                    <i class="fa-solid fa-file-export text-teal-600"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-8 flex flex-col lg:flex-row justify-between items-center gap-4">
            <h2 class="font-bold text-gray-700 px-2 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-teal-600"></i> Active Requests
            </h2>
            
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <div class="relative cursor-pointer flex-grow lg:flex-grow-0">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 z-10">
                        <i class="fa-regular fa-calendar"></i>
                    </span>
                    <input type="text" id="date-range" value="<?= $_GET['dates'] ?? '' ?>" placeholder="Select Date Range" class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-teal-500 w-full lg:w-56 bg-white cursor-pointer" readonly>
                </div>

                <select onchange="updateFilter('role', this.value)" class="border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white flex-grow lg:flex-grow-0">
                    <option value="">All Job Roles</option>
                    <?php 
                        $roles_q = mysqli_query($conn, "SELECT DISTINCT job_title FROM hiring_requests");
                        while($r = mysqli_fetch_assoc($roles_q)):
                            $selected = (isset($_GET['role']) && $_GET['role'] == $r['job_title']) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($r['job_title']) ?>" <?= $selected ?>><?= htmlspecialchars($r['job_title']) ?></option>
                    <?php endwhile; ?>
                </select>

                <div class="relative flex-grow lg:flex-grow-0" x-data="{ open: false }">
                    <?php 
                        $sort_text = "Sort By";
                        if(isset($_GET['sort'])) {
                            $sort_map = ['recent'=>'Recently Added', 'asc'=>'A-Z', 'desc'=>'Z-A', 'urgent'=>'High Priority'];
                            $sort_text = $sort_map[$_GET['sort']] ?? "Sort By";
                        }
                    ?>
                    <button @click="open = !open" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-600 focus:outline-none bg-white flex justify-between items-center lg:min-w-[160px]">
                        <span><?= $sort_text ?></span>
                        <i class="fa-solid fa-chevron-down text-xs ml-2 text-gray-400"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" style="display: none;" class="absolute right-0 mt-2 w-full lg:w-48 bg-white border border-gray-100 rounded-lg shadow-xl z-50 overflow-hidden">
                        <div class="py-1">
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'recent')" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-700">Recently Added</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'urgent')" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-700">High Priority</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'asc')" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-700">A - Z</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'desc')" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-700">Z - A</a>
                        </div>
                    </div>
                </div>
                
                <?php if(isset($_GET['role']) || isset($_GET['dates']) || isset($_GET['sort'])): ?>
                    <button onclick="window.location.href='jobs.php'" class="text-sm text-red-500 hover:text-red-700 font-medium px-2 py-2">Clear Filters</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php
            $conditions = [];
            
            // ROLE BASED VISIBILITY
            // HR Executive only sees Approved or In Progress tasks. HR sees all.
            if ($user_role === 'HR Executive') {
                $conditions[] = "hr.status IN ('Approved', 'In Progress', 'Fulfilled')";
            }

            // URL FILTERS
            if (!empty($_GET['role'])) {
                $role = mysqli_real_escape_string($conn, $_GET['role']);
                $conditions[] = "hr.job_title = '$role'";
            }

            if (!empty($_GET['dates'])) {
                $date_parts = explode(" to ", $_GET['dates']);
                if (count($date_parts) == 2) {
                    $start = date('Y-m-d', strtotime(trim($date_parts[0])));
                    $end = date('Y-m-d', strtotime(trim($date_parts[1])));
                    $conditions[] = "DATE(hr.created_at) BETWEEN '$start' AND '$end'";
                } else {
                    $single_date = date('Y-m-d', strtotime(trim($date_parts[0])));
                    $conditions[] = "DATE(hr.created_at) = '$single_date'";
                }
            }

            // SORTING
            $order_clause = " ORDER BY hr.created_at DESC";
            if (isset($_GET['sort'])) {
                if ($_GET['sort'] == 'asc') $order_clause = " ORDER BY hr.job_title ASC";
                if ($_GET['sort'] == 'desc') $order_clause = " ORDER BY hr.job_title DESC";
                if ($_GET['sort'] == 'recent') $order_clause = " ORDER BY hr.created_at DESC";
                if ($_GET['sort'] == 'urgent') $order_clause = " ORDER BY FIELD(hr.priority, 'High', 'Medium', 'Low'), hr.created_at DESC";
            }

            $where_clause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";
            
            // Main Query pulling from hiring_requests and users
            $sql = "SELECT hr.*, u.name as requested_by 
                    FROM hiring_requests hr 
                    LEFT JOIN users u ON hr.manager_id = u.id " 
                    . $where_clause . $order_clause;
                    
            $result = mysqli_query($conn, $sql); 
            
            if ($result && mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    
                    // Dynamic Department Icon
                    $dept = strtolower($row['department']);
                    $icon = 'fa-briefcase'; $icon_bg = 'bg-gray-100 text-gray-600';
                    if(strpos($dept, 'dev') !== false || strpos($dept, 'eng') !== false) { $icon = 'fa-code'; $icon_bg = 'bg-blue-100 text-blue-600'; }
                    elseif(strpos($dept, 'sale') !== false || strpos($dept, 'market') !== false) { $icon = 'fa-chart-line'; $icon_bg = 'bg-green-100 text-green-600'; }
                    elseif(strpos($dept, 'hr') !== false || strpos($dept, 'human') !== false) { $icon = 'fa-users'; $icon_bg = 'bg-purple-100 text-purple-600'; }
                    elseif(strpos($dept, 'acc') !== false || strpos($dept, 'fin') !== false) { $icon = 'fa-file-invoice-dollar'; $icon_bg = 'bg-yellow-100 text-yellow-600'; }

                    // Dynamic Status Badge
                    $status_bg = 'bg-gray-100 text-gray-600';
                    if ($row['status'] == 'Pending') $status_bg = 'bg-orange-100 text-orange-700';
                    if ($row['status'] == 'Approved') $status_bg = 'bg-teal-100 text-teal-700';
                    if ($row['status'] == 'Rejected') $status_bg = 'bg-red-100 text-red-700';
                    if ($row['status'] == 'In Progress') $status_bg = 'bg-blue-100 text-blue-700';
                    if ($row['status'] == 'Fulfilled') $status_bg = 'bg-emerald-100 text-emerald-700';
            ?>
                <div class="card bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col h-full">
                    <div class="flex items-center gap-4 bg-gray-50 rounded-xl p-4 mb-4">
                        <div class="w-12 h-12 <?= $icon_bg ?> rounded-xl flex items-center justify-center text-xl shadow-sm">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <div class="flex-grow">
                            <div class="flex justify-between items-start">
                                <h3 class="font-bold text-gray-800 text-lg leading-tight truncate pr-2" title="<?= htmlspecialchars($row['job_title']) ?>"><?= htmlspecialchars($row['job_title']) ?></h3>
                            </div>
                            <p class="text-xs text-gray-500 font-medium mt-1"><?= htmlspecialchars($row['department']) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-4 pb-4 border-b border-dashed border-gray-100 flex-grow">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Requested By</p>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-[10px] font-bold">
                                        <?= strtoupper(substr($row['requested_by'] ?? 'A', 0, 1)) ?>
                                    </div>
                                    <p class="text-sm text-gray-700 font-semibold truncate"><?= htmlspecialchars($row['requested_by'] ?? 'Unknown') ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Status</p>
                                <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-md <?= $status_bg ?> inline-block">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3 mb-5">
                        <div class="flex items-start gap-3 text-sm text-gray-600">
                            <i class="fa-regular fa-calendar-plus text-gray-400 mt-0.5"></i> 
                            <div>
                                <span class="font-medium text-gray-500 text-xs block">Posted On</span> 
                                <span class="font-semibold text-gray-700"><?= !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A' ?></span>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 text-sm text-gray-600">
                            <i class="fa-solid fa-users text-gray-400 mt-0.5"></i> 
                            <div>
                                <span class="font-medium text-gray-500 text-xs block">Openings</span> 
                                <span class="font-semibold text-gray-700"><?= $row['vacancy_count'] ?> Positions</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 text-sm text-gray-600">
                            <i class="fa-solid fa-briefcase text-gray-400 mt-0.5"></i>
                            <div>
                                <span class="font-medium text-gray-500 text-xs block">Experience</span> 
                                <span class="font-semibold text-gray-700"><?= htmlspecialchars($row['experience_required']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-2 mb-2">
                        <?php if($row['priority'] == 'High'): ?>
                            <span class="px-3 py-1 bg-red-50 text-red-600 border border-red-100 text-[10px] font-bold uppercase rounded-md shadow-sm flex items-center gap-1">
                                <i class="fa-solid fa-bolt"></i> Urgent
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (($user_role === 'HR' || $user_role === 'System Admin') && $row['status'] === 'Pending'): ?>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex gap-2">
                            <form method="POST" class="w-1/2">
                                <input type="hidden" name="req_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="action" value="approve" onclick="return confirm('Approve this request and alert HR Executives?')" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-2 rounded-lg text-xs font-bold transition shadow-sm">
                                    <i class="fa-solid fa-check mr-1"></i> Approve
                                </button>
                            </form>
                            <form method="POST" class="w-1/2">
                                <input type="hidden" name="req_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="action" value="reject" onclick="return confirm('Are you sure you want to reject this request?')" class="w-full bg-white border border-red-200 hover:bg-red-50 text-red-600 py-2 rounded-lg text-xs font-bold transition shadow-sm">
                                    <i class="fa-solid fa-xmark mr-1"></i> Reject
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            <?php 
                }
            } else {
                echo "<div class='col-span-full text-center py-16 bg-white rounded-xl border border-dashed border-gray-300'>
                        <div class='text-gray-300 mb-3'><i class='fa-solid fa-folder-open text-4xl'></i></div>
                        <h3 class='text-lg font-bold text-gray-700'>No Job Requests Found</h3>
                        <p class='text-sm text-gray-500 mt-1'>There are currently no active job requests matching your criteria.</p>
                      </div>";
            }
            ?>
        </div>
    </div>

    <script>
        flatpickr("#date-range", {
            mode: "range",
            dateFormat: "m/d/Y",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    updateFilter('dates', dateStr);
                }
            }
        });
    </script>
</body>
</html>