<?php
// productivity_monitor.php

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// HR அல்லது Admin மட்டும் பார்க்க அனுமதிக்கவும்
if (!isset($_SESSION['user_id'])) { 
    // header("Location: index.php"); 
    // exit(); 
}

// 2. MOCK DATA (உண்மையான டேட்டாபேஸ் இணைக்கும் வரை இதை பயன்படுத்தலாம்)
$tasks = [
    [
        "emp_name" => "Suresh Babu",
        "dept" => "IT Team",
        "title" => "Database Schema Design",
        "lead" => "Ragul Kumar",
        "deadline" => "2026-02-08",
        "status" => "Completed",
        "proof" => "Schema finalized. SQL file pushed to GitHub repository: repo/db_v1.sql"
    ],
    [
        "emp_name" => "Karthik",
        "dept" => "IT Team",
        "title" => "API Endpoints Creation",
        "lead" => "Ragul Kumar",
        "deadline" => "2026-02-05",
        "status" => "Overdue", // Late
        "proof" => ""
    ],
    [
        "emp_name" => "Anitha",
        "dept" => "Sales",
        "title" => "Client Pitch Deck",
        "lead" => "Priya",
        "deadline" => "2026-02-12",
        "status" => "In Progress",
        "proof" => ""
    ],
    [
        "emp_name" => "Ramesh",
        "dept" => "IT Team",
        "title" => "Frontend Login UI",
        "lead" => "Ragul Kumar",
        "deadline" => "2026-02-09",
        "status" => "Completed",
        "proof" => "Login screen UI completed with validation. Checked in generic/login.php"
    ],
    [
        "emp_name" => "Sarah",
        "dept" => "Marketing",
        "title" => "Social Media Calendar",
        "lead" => "Vikram",
        "deadline" => "2026-02-10",
        "status" => "Completed",
        "proof" => "February content calendar attached in Drive Link."
    ]
];

// Calculate Stats
$total = count($tasks);
$completed = count(array_filter($tasks, fn($t) => $t['status'] == 'Completed'));
$progress = count(array_filter($tasks, fn($t) => $t['status'] == 'In Progress'));
$overdue = count(array_filter($tasks, fn($t) => $t['status'] == 'Overdue'));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Productivity Monitor</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --bg-light: #f4f6f8;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-light: #e5e7eb;
        }

        body { 
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
        }

        /* --- SIDEBAR INTEGRATION --- */
        #mainContent { 
            margin-left: 95px; 
            padding: 30px; 
            width: calc(100% - 95px);
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        /* --- HEADER & STATS --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h1 { font-size: 22px; font-weight: 700; color: #111827; margin: 0; }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 20px; border-radius: 12px; border: 1px solid var(--border-light); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin: 0; color: #111827; }
        .stat-card p { margin: 5px 0 0; font-size: 13px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        
        .border-orange { border-bottom: 4px solid var(--primary-orange); }
        .border-green { border-bottom: 4px solid #10b981; }
        .border-blue { border-bottom: 4px solid #3b82f6; }
        .border-red { border-bottom: 4px solid #ef4444; }

        /* --- FILTERS --- */
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid var(--border-light); }
        .filter-bar span { font-size: 14px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .filter-select { padding: 8px 12px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 13px; color: var(--text-dark); outline: none; min-width: 150px; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary-orange); }

        /* --- TABLE --- */
        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .card-title { padding: 20px 25px; border-bottom: 1px solid var(--border-light); font-weight: 700; font-size: 16px; color: #374151; background: #f9fafb; }
        
        .task-table { width: 100%; border-collapse: collapse; }
        .task-table th { text-align: left; padding: 15px 25px; font-size: 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #f9fafb; font-weight: 600; text-transform: uppercase; }
        .task-table td { padding: 16px 25px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #4b5563; vertical-align: middle; }
        .task-table tr:last-child td { border-bottom: none; }
        .task-table tr:hover { background-color: #ffffec; }

        /* --- BADGES & BUTTONS --- */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .completed { background: #dcfce7; color: #166534; }
        .delayed { background: #fee2e2; color: #991b1b; }
        .progress-badge { background: #dbeafe; color: #1e40af; }

        .action-btn { background: var(--white); border: 1px solid var(--border-light); color: var(--text-dark); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .action-btn:hover { border-color: var(--primary-orange); color: var(--primary-orange); background: #fff5f2; }
        .action-btn.disabled { opacity: 0.5; cursor: not-allowed; background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }

        /* --- MODAL --- */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); align-items: center; justify-content: center; }
        .modal-content { background: white; width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: modalIn 0.2s ease-out; }
        @keyframes modalIn { from {opacity: 0; transform: scale(0.95);} to {opacity: 1; transform: scale(1);} }
        
        .modal-header { padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; color: #111827; }
        
        .modal-body { padding: 25px; }
        .proof-box { background: #f8fafc; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 8px; font-size: 14px; color: #334155; margin-top: 10px; line-height: 1.6; }
        
        .modal-footer { padding: 15px 20px; border-top: 1px solid #e5e7eb; text-align: right; }
        .close-btn { background: #f3f4f6; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; color: #374151; }
        .close-btn:hover { background: #e5e7eb; }
    </style>
</head>
<body>

    <?php include('../sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="page-header">
            <div>
                <h1>Productivity Monitor</h1>
                <p style="font-size:13px; color:#6b7280; margin-top:4px;">Track employee task completion and review work proofs.</p>
            </div>
            <div style="font-size:13px; font-weight:600; background:#fff; padding:8px 15px; border-radius:20px; border:1px solid #e5e7eb;">
                Today: <?php echo date('d M, Y'); ?>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card border-blue">
                <h3><?php echo $total; ?></h3>
                <p>Total Tasks</p>
            </div>
            <div class="stat-card border-green">
                <h3><?php echo $completed; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card border-orange">
                <h3><?php echo $progress; ?></h3>
                <p>In Progress</p>
            </div>
            <div class="stat-card border-red">
                <h3><?php echo $overdue; ?></h3>
                <p>Overdue</p>
            </div>
        </div>

        <div class="filter-bar">
            <span><i class="fas fa-filter text-orange-500"></i> Filter By:</span>
            <select class="filter-select" id="deptFilter" onchange="filterTable()">
                <option value="all">All Departments</option>
                <option value="IT Team">IT Team</option>
                <option value="Sales">Sales</option>
                <option value="Marketing">Marketing</option>
            </select>
            <select class="filter-select" id="statusFilter" onchange="filterTable()">
                <option value="all">All Status</option>
                <option value="Completed">Completed</option>
                <option value="In Progress">In Progress</option>
                <option value="Overdue">Overdue</option>
            </select>
        </div>

        <div class="content-card">
            <div class="card-title">Employee Performance Tracker</div>
            <div style="overflow-x: auto;">
                <table class="task-table" id="trackerTable">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Task Title</th>
                            <th>Team Lead</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th style="text-align: right;">Review Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tasks as $row): 
                            $statusClass = '';
                            if($row['status'] == 'Completed') $statusClass = 'completed';
                            elseif($row['status'] == 'Overdue') $statusClass = 'delayed';
                            else $statusClass = 'progress-badge';
                        ?>
                        <tr class="task-row" data-dept="<?php echo $row['dept']; ?>" data-status="<?php echo $row['status']; ?>">
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:30px; height:30px; background:#f3f4f6; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#4b5563;">
                                        <?php echo substr($row['emp_name'], 0, 1); ?>
                                    </div>
                                    <span style="font-weight:600; color:#1f2937;"><?php echo $row['emp_name']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $row['dept']; ?></td>
                            <td style="font-weight:500;"><?php echo $row['title']; ?></td>
                            <td><?php echo $row['lead']; ?></td>
                            <td><?php echo date('d M', strtotime($row['deadline'])); ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $row['status']; ?></span></td>
                            <td style="text-align: right;">
                                <?php if($row['status'] == 'Completed'): ?>
                                    <button class="action-btn" onclick="viewProof('<?php echo $row['emp_name']; ?>', '<?php echo addslashes($row['proof']); ?>')">
                                        <i class="far fa-eye"></i> View
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn disabled" disabled>
                                        <i class="far fa-clock"></i> Pending
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="proofViewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="proofEmpName">Work Proof</h3>
                <i data-lucide="x" style="cursor:pointer; color:#6b7280;" onclick="closeModal('proofViewModal')"></i>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: #6b7280; font-weight: 600; margin:0;">Submitted Evidence / Link:</p>
                <div class="proof-box" id="proofText">
                    </div>
            </div>
            <div class="modal-footer">
                <button class="close-btn" onclick="closeModal('proofViewModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // 1. Modal Logic
        function closeModal(id) { 
            const modal = document.getElementById(id);
            modal.style.display = 'none'; 
        }
        
        function viewProof(empName, proofContent) {
            document.getElementById('proofEmpName').innerText = "Proof: " + empName;
            document.getElementById('proofText').innerText = proofContent;
            
            const modal = document.getElementById('proofViewModal');
            modal.style.display = 'flex'; // Use flex to center
        }

        // Close modal on outside click
        window.onclick = function(event) { 
            const modal = document.getElementById('proofViewModal');
            if (event.target === modal) { 
                closeModal('proofViewModal'); 
            } 
        }

        // 2. Filter Logic
        function filterTable() {
            const dept = document.getElementById('deptFilter').value;
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.task-row');

            rows.forEach(row => {
                const rowDept = row.getAttribute('data-dept');
                const rowStatus = row.getAttribute('data-status');

                let show = true;

                if (dept !== 'all' && rowDept !== dept) show = false;
                if (status !== 'all' && rowStatus !== status) show = false;

                row.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>
</html>