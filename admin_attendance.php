<?php
// attendance_admin.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA FOR EXPORT (Since this is now a standalone admin page)
// In a real app, this would come from the database based on the table view.
$exportData = [];
for($i=1; $i<=6; $i++) {
    $exportData[] = [
        "employee" => "User $i",
        "status" => "Present",
        "checkin" => "09:00 AM",
        "checkout" => "06:".rand(10,59)." PM",
        "break" => "20 Min",
        "late" => "1$i Min",
        "production" => "8.55 Hrs"
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Attendance Admin</title>
    
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
        .table thead th { background: #f9fafb; padding: 15px; border-bottom: 1px solid var(--border-color); color: #4a5568; font-weight: 600; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
        
        .status-pill { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-present { background: #e6fffa; color: #38a169; }
        .bg-absent { background: #fff5f5; color: #e53e3e; }
        .prod-btn { color: white; border: none; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        
        .modal-active { display: flex !important; }

        /* Notification Toast Styling */
        #exportToast {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            z-index: 10000; display: none; background: #38a169; color: white;
            padding: 12px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-weight: 600; animation: fadeInOut 3s ease-in-out forwards;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -20px); }
            15% { opacity: 1; transform: translate(-50%, 0); }
            85% { opacity: 1; transform: translate(-50%, 0); }
            100% { opacity: 0; transform: translate(-50%, -20px); }
        }
    </style>
</head>
<body class="bg-slate-50">

    <div id="exportToast"><i class="fa-solid fa-circle-check mr-2"></i> Report Downloaded</div>

    <?php include('sidebars.php'); ?>

    <div id="reportModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b">
                <h2 class="text-2xl font-bold">Attendance Details</h2>
                <button onclick="closeModal()" class="bg-slate-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-slate-600 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-8">
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-slate-800" id="modalEmpName">User Name</h3>
                    <p class="text-slate-500 text-sm">Full Day Attendance Report</p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8 bg-slate-50 p-6 rounded-lg border border-slate-100">
                    <div><p class="text-slate-500 text-sm mb-1">Date</p><p class="font-bold text-lg" id="modalDate">15 Apr 2025</p></div>
                    <div><p class="text-slate-500 text-sm mb-1">Punch in at</p><p class="font-bold text-lg" id="modalPunchIn">09:00 AM</p></div>
                    <div><p class="text-slate-500 text-sm mb-1">Punch out at</p><p class="font-bold text-lg" id="modalPunchOut">06:45 PM</p></div>
                    <div><p class="text-slate-500 text-sm mb-1">Status</p><p class="font-bold text-lg" id="modalStatus">Present</p></div>
                </div>

                <div class="grid grid-cols-4 gap-4 mb-8">
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-slate-200"></span> Total Hours</p>
                        <p class="text-3xl font-bold text-slate-800">12h 36m</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Productive</p>
                        <p class="text-3xl font-bold text-slate-800">08h 36m</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Break</p>
                        <p class="text-3xl font-bold text-slate-800">22m 15s</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Overtime</p>
                        <p class="text-3xl font-bold text-slate-800">02h 15m</p>
                    </div>
                </div>

                <div class="h-10 w-full bg-slate-50 rounded-full flex overflow-hidden mb-4 border border-slate-100 p-1">
                    <div style="width: 15%"></div>
                    <div class="h-full bg-emerald-500 rounded-lg" style="width: 12%"></div>
                    <div class="h-full bg-amber-400 rounded-lg mx-1" style="width: 4%"></div>
                    <div class="h-full bg-emerald-500 rounded-lg" style="width: 20%"></div>
                    <div class="h-full bg-amber-400 rounded-lg mx-1" style="width: 10%"></div>
                    <div class="h-full bg-emerald-500 rounded-lg" style="width: 15%"></div>
                    <div class="h-full bg-amber-400 rounded-lg mx-1" style="width: 4%"></div>
                    <div class="h-full bg-blue-500 rounded-lg" style="width: 3%"></div>
                    <div class="h-full bg-blue-500 rounded-lg ml-1" style="width: 3%"></div>
                </div>
                
                <div class="flex justify-between text-[11px] text-slate-400 font-medium px-1 uppercase">
                    <span>06:00</span><span>08:00</span><span>10:00</span><span>12:00</span><span>02:00</span><span>04:00</span><span>06:00</span><span>08:00</span><span>10:00</span>
                </div>
            </div>
        </div>
    </div>

    <main id="mainContent">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h4 class="fw-bold mb-0 text-dark">Attendance Admin</h4></div>
            <div class="d-flex gap-2">
                <button class="btn btn-light border btn-sm" onclick="triggerExport()"><i class="fa-solid fa-download"></i> Export CSV</button>
            </div>
        </div>

        <div class="card mb-4 text-center">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="text-start">
                    <h6 class="fw-bold mb-0">Attendance Details Today</h6>
                    <small class="text-muted">Data from 800+ total employees</small>
                </div>
                <div class="small fw-bold">
                    Total Absenties today 
                    <img src="https://i.pravatar.cc/25?img=1" class="rounded-circle ms-1">
                    <span class="badge bg-danger rounded-circle">+1</span>
                </div>
            </div>
            <div class="row g-0">
                <div class="col border-end p-3">
                    <div class="text-muted small mb-1">Present</div>
                    <h4 class="mb-0 fw-bold">250</h4>
                    <span class="badge bg-success-subtle text-success mt-1">+1%</span>
                </div>
                <div class="col border-end p-3">
                    <div class="text-muted small mb-1">Late Login</div>
                    <h4 class="mb-0 fw-bold">45</h4>
                    <span class="badge bg-danger-subtle text-danger mt-1">-1%</span>
                </div>
                <div class="col border-end p-3">
                    <div class="text-muted small mb-1">Uninformed</div>
                    <h4 class="mb-0 fw-bold">15</h4>
                    <span class="badge bg-danger-subtle text-danger mt-1">-12%</span>
                </div>
                <div class="col border-end p-3">
                    <div class="text-muted small mb-1">Permission</div>
                    <h4 class="mb-0 fw-bold">03</h4>
                    <span class="badge bg-success-subtle text-success mt-1">+1%</span>
                </div>
                <div class="col p-3">
                    <div class="text-muted small mb-1">Absent</div>
                    <h4 class="mb-0 fw-bold">12</h4>
                    <span class="badge bg-danger-subtle text-danger mt-1">-19%</span>
                </div>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Break</th>
                            <th>Late</th>
                            <th>Production Hours</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($i=1; $i<=6; $i++): 
                            $checkOutTime = "06:" . rand(10,59) . " PM";
                            $lateMins = "1" . $i . " Min";
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://i.pravatar.cc/30?img=<?php echo $i+10; ?>" class="avatar-img">
                                    <div>
                                        <div class="fw-bold text-dark">User <?php echo $i; ?></div>
                                        <small class="text-muted">Developer</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="status-pill bg-present">● Present</span></td>
                            <td>09:00 AM</td>
                            <td><?php echo $checkOutTime; ?></td>
                            <td>20 Min</td>
                            <td><?php echo $lateMins; ?></td>
                            <td><span class="prod-btn bg-success">8.55 Hrs</span></td>
                            <td>
                                <button class="btn btn-sm btn-light text-primary border" 
                                        onclick="openModal({name: 'User <?php echo $i; ?>', date: '<?php echo date('d M Y'); ?>', in: '09:00 AM', out: '<?php echo $checkOutTime; ?>', status: 'Present'})">
                                    <i class="fa-solid fa-file-lines me-1"></i> Report
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        const modal = document.getElementById('reportModal');
        const toast = document.getElementById('exportToast');

        // CSV Export Logic
        function triggerExport() {
            // 1. Show Toast
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);

            // 2. Generate CSV from PHP Data
            let csv = [];
            csv.push("Employee,Status,Check In,Check Out,Break,Late,Production");
            
            const records = <?php echo json_encode($exportData); ?>;
            records.forEach(row => {
                csv.push(`${row.employee},${row.status},${row.checkin},${row.checkout},${row.break},${row.late},${row.production}`);
            });

            // 3. Download
            const csvString = csv.join("\n");
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "Admin_Attendance_Report.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Modal Logic
        function openModal(data) { 
            if(data) {
                document.getElementById('modalEmpName').innerText = data.name;
                document.getElementById('modalDate').innerText = data.date;
                document.getElementById('modalPunchIn').innerText = data.in || '-';
                document.getElementById('modalPunchOut').innerText = data.out || '-';
                document.getElementById('modalStatus').innerText = data.status;
            }
            modal.classList.add('modal-active'); 
            document.body.style.overflow = 'hidden'; 
        }

        function closeModal() { 
            modal.classList.remove('modal-active'); 
            document.body.style.overflow = 'auto'; 
        }

        window.onclick = (e) => { 
            if (e.target == modal) closeModal(); 
        }
    </script>
</body>
</html>