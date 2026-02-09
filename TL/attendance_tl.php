<?php
// attendance_tl.php (TEAM LEADER VIEW)

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA (Simulating "My Team" - e.g., Web Development Team)
// In a real DB, you would query: SELECT * FROM attendance WHERE reporter_id = [Current_TL_ID]
$myTeam = [
    ["id" => 201, "name" => "Sarah Jenkins", "role" => "Frontend Dev", "status" => "Present", "in" => "09:02 AM", "out" => "06:05 PM", "prod" => "8.5 Hrs", "late" => "-"],
    ["id" => 202, "name" => "Mike Ross", "role" => "Backend Dev", "status" => "Late", "in" => "09:45 AM", "out" => "07:15 PM", "prod" => "8.0 Hrs", "late" => "45 Min"],
    ["id" => 203, "name" => "Rachel Zane", "role" => "UI Designer", "status" => "Present", "in" => "08:55 AM", "out" => "06:00 PM", "prod" => "9.0 Hrs", "late" => "-"],
    ["id" => 204, "name" => "Louis Litt", "role" => "QA Tester", "status" => "Absent", "in" => "-", "out" => "-", "prod" => "0 Hrs", "late" => "-"],
    ["id" => 205, "name" => "Donna Paulsen", "role" => "Scrum Master", "status" => "Present", "in" => "09:10 AM", "out" => "In Office", "prod" => "Running", "late" => "10 Min"],
];

// Stats Calculation
$present = 0; $late = 0; $absent = 0;
foreach($myTeam as $member) {
    if($member['status'] == 'Present') $present++;
    if($member['status'] == 'Late') $late++;
    if($member['status'] == 'Absent') $absent++;
}
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

    <main id="mainContent">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0 text-dark">My Team Attendance</h4>
                    <span class="badge bg-dark text-white rounded-pill px-3">Web Dev Dept</span>
                </div>
                <p class="text-muted small mb-0">Overview of your reporting employees today</p>
            </div>
            <div class="d-flex gap-2">
                <input type="date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" style="width: 150px;">
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
                            <th>Late By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($myTeam as $member): 
                            $statusClass = 'bg-present';
                            $icon = '<i class="fa-solid fa-check-circle me-1"></i>';
                            
                            if($member['status'] == 'Late') { $statusClass = 'bg-late'; $icon = '<i class="fa-solid fa-circle-exclamation me-1"></i>'; }
                            if($member['status'] == 'Absent') { $statusClass = 'bg-absent'; $icon = '<i class="fa-solid fa-times-circle me-1"></i>'; }
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://i.pravatar.cc/150?u=<?php echo $member['id']; ?>" class="avatar-img">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $member['name']; ?></div>
                                        <small class="text-muted"><?php echo $member['role']; ?></small>
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
                            <td>
                                <?php if($member['late'] != '-'): ?>
                                    <span class="text-danger fw-bold"><?php echo $member['late']; ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-white border shadow-sm" onclick="openDetails('<?php echo $member['name']; ?>')">
                                    <i class="fa-regular fa-eye text-secondary"></i> Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                <p class="text-muted mb-6">Detailed report for today</p>
                
                <div class="grid grid-cols-3 gap-4 text-left">
                    <div class="bg-slate-50 p-3 rounded border">
                        <small class="text-muted">Punch In</small>
                        <div class="font-bold">09:02 AM</div>
                        <div class="text-xs text-muted">IP: 192.168.1.1</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded border">
                        <small class="text-muted">Punch Out</small>
                        <div class="font-bold">06:05 PM</div>
                        <div class="text-xs text-muted">IP: 192.168.1.1</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded border">
                        <small class="text-muted">Avg. Hours</small>
                        <div class="font-bold text-emerald-600">8.5 Hrs</div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t flex justify-end">
                    <button class="btn btn-secondary btn-sm me-2" onclick="closeDetails()">Close</button>
                    <button class="btn btn-primary btn-sm">Download Report</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('detailModal');

        function openDetails(name) {
            document.getElementById('modalName').innerText = name;
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