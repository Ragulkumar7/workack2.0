<?php
// attendance_tl.php (TEAM LEADER VIEW)

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// 2. DATABASE CONNECTION
require_once '../include/db_connect.php'; // Ensure path is correct

// 3. GET TL ID & FILTER DATE
$tl_user_id = $_SESSION['user_id']; // ID of logged-in TL (e.g., Frank is 19)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 4. FETCH DYNAMIC DATA
// COALESCE checks if the first value is NULL, and if it is, it uses the second value.
$query = "
    SELECT 
        u.id AS user_id, 
        COALESCE(ep.full_name, u.name, 'Unknown Employee') AS final_name, 
        COALESCE(ep.emp_id_code, u.employee_id, 'N/A') AS final_emp_id, 
        ep.designation AS role, 
        a.punch_in, 
        a.punch_out, 
        a.production_hours, 
        a.status 
    FROM users u
    JOIN employee_profiles ep ON u.id = ep.user_id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE ep.reporting_to = ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database Error: " . $conn->error); 
}

$stmt->bind_param("si", $selected_date, $tl_user_id);
$stmt->execute();
$result = $stmt->get_result();

$myTeam = [];
$present = 0; 
$late = 0; 
$absent = 0;

while ($row = $result->fetch_assoc()) {
    // Format times
    $in_time = $row['punch_in'] ? date("h:i A", strtotime($row['punch_in'])) : "-";
    $out_time = $row['punch_out'] ? date("h:i A", strtotime($row['punch_out'])) : ($row['punch_in'] ? "Working" : "-");
    
    // Format Production Hours
    $prod_hours = ($row['production_hours'] > 0) ? $row['production_hours'] . " Hrs" : "0 Hrs";

    // Determine Status
    $status = $row['status'] ?? 'Absent'; 
    
    if ($status === 'On Time' || $status === 'WFH') {
        $present++;
        $display_status = 'Present'; 
    } elseif ($status === 'Late') {
        $late++;
        $display_status = 'Late';
    } else {
        $absent++;
        $display_status = 'Absent';
    }

    $myTeam[] = [
        "id" => $row['user_id'],
        "name" => $row['final_name'],
        "emp_id" => $row['final_emp_id'],
        "role" => $row['role'] ?? 'Unassigned',
        "status" => $display_status,
        "in" => $in_time,
        "out" => $out_time,
        "prod" => $prod_hours
    ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - My Team Attendance</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-orange: #ff5e3a; --bg-gray: #f8f9fa; --border-color: #edf2f7; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 13px; color: #333; overflow-x: hidden; }
        
        #mainContent { 
            margin-left: 95px; 
            padding: 25px 35px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 20px; background: #fff; }
        
        /* Table Styling */
        .table thead th { background: #f9fafb; padding: 15px; border-bottom: 1px solid var(--border-color); color: #4a5568; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .avatar-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        /* Status Badges */
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .bg-present { background: #dcfce7; color: #166534; }
        .bg-late { background: #ffedd5; color: #c2410c; }
        .bg-absent { background: #fee2e2; color: #991b1b; }

        .modal-active { display: flex !important; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>

    <main id="mainContent">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0 text-dark">My Team Attendance</h4>
                </div>
                <p class="text-muted small mb-0">Overview of your reporting employees for <?php echo date("F j, Y", strtotime($selected_date)); ?></p>
            </div>
            <div class="d-flex gap-2">
                <input type="date" class="form-control form-control-sm" value="<?php echo $selected_date; ?>" style="width: 150px;" onchange="window.location.href='?date='+this.value">
                <button class="btn btn-light border btn-sm shadow-sm"><i class="fa-solid fa-download text-secondary"></i> Export</button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between border-l-4 border-l-blue-500">
                    <div>
                        <p class="text-muted small mb-1">Total Team</p>
                        <h3 class="fw-bold text-2xl"><?php echo count($myTeam); ?></h3>
                    </div>
                    <div class="bg-blue-50 text-blue-500 w-10 h-10 rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between border-l-4 border-l-emerald-500">
                    <div>
                        <p class="text-muted small mb-1">Present Today</p>
                        <h3 class="fw-bold text-2xl"><?php echo $present; ?></h3>
                    </div>
                    <div class="bg-emerald-50 text-emerald-500 w-10 h-10 rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between border-l-4 border-l-orange-500">
                    <div>
                        <p class="text-muted small mb-1">Late Arrivals</p>
                        <h3 class="fw-bold text-2xl"><?php echo $late; ?></h3>
                    </div>
                    <div class="bg-orange-50 text-orange-500 w-10 h-10 rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between border-l-4 border-l-red-500">
                    <div>
                        <p class="text-muted small mb-1">Absent</p>
                        <h3 class="fw-bold text-2xl"><?php echo $absent; ?></h3>
                    </div>
                    <div class="bg-red-50 text-red-500 w-10 h-10 rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Production</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($myTeam) > 0): ?>
                            <?php foreach($myTeam as $member): 
                                $statusClass = 'bg-present';
                                $icon = '<i class="fa-solid fa-check-circle me-1"></i>';
                                
                                if($member['status'] == 'Late') { $statusClass = 'bg-late'; $icon = '<i class="fa-solid fa-circle-exclamation me-1"></i>'; }
                                if($member['status'] == 'Absent') { $statusClass = 'bg-absent'; $icon = '<i class="fa-solid fa-times-circle me-1"></i>'; }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member['name']); ?>&background=random" class="avatar-img">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($member['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['emp_id']); ?> - <?php echo htmlspecialchars($member['role']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $icon . $member['status']; ?>
                                    </span>
                                </td>
                                <td class="text-slate-600"><?php echo $member['in']; ?></td>
                                <td class="text-slate-600"><?php echo $member['out']; ?></td>
                                <td>
                                    <?php if($member['prod'] != '0 Hrs'): ?>
                                        <span class="text-emerald-600 font-bold"><?php echo $member['prod']; ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-white border shadow-sm" onclick="openDetails('<?php echo htmlspecialchars($member['name']); ?>', '<?php echo $member['in']; ?>', '<?php echo $member['out']; ?>', '<?php echo $member['prod']; ?>')">
                                        <i class="fa-regular fa-eye text-secondary"></i> Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-users-slash text-3xl mb-3 text-slate-300 block"></i>
                                    No team members are assigned to you yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="detailModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-lg font-bold">Attendance Detail</h2>
                <button onclick="closeDetails()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 text-center">
                <div class="w-20 h-20 bg-slate-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <i class="fa-solid fa-user text-3xl text-slate-400"></i>
                </div>
                <h3 class="text-xl font-bold" id="modalName">Employee Name</h3>
                <p class="text-muted mb-6">Detailed report</p>
                
                <div class="grid grid-cols-3 gap-4 text-left">
                    <div class="bg-slate-50 p-3 rounded border">
                        <small class="text-muted">Punch In</small>
                        <div class="font-bold" id="modalIn">--:--</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded border">
                        <small class="text-muted">Punch Out</small>
                        <div class="font-bold" id="modalOut">--:--</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded border">
                        <small class="text-muted">Total Hours</small>
                        <div class="font-bold text-emerald-600" id="modalProd">0 Hrs</div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t flex justify-end">
                    <button class="btn btn-secondary btn-sm me-2" onclick="closeDetails()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('detailModal');

        function openDetails(name, punchIn, punchOut, prodHours) {
            document.getElementById('modalName').innerText = name;
            document.getElementById('modalIn').innerText = punchIn;
            document.getElementById('modalOut').innerText = punchOut;
            document.getElementById('modalProd').innerText = prodHours;
            
            modal.classList.add('modal-active');
            document.body.style.overflow = 'hidden';
        }

        function closeDetails() {
            modal.classList.remove('modal-active');
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = (e) => { if(e.target == modal) closeDetails(); }
    </script>
</body>
</html>