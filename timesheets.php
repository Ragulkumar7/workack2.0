<?php
// timesheets.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA
$timesheets = [
    ["id" => 1, "emp" => "Anthony Lewis", "date" => "14 Jan 2026", "project" => "Website Redesign", "assigned" => 40, "worked" => 32, "deadline" => "20 Jan 2026"],
    ["id" => 2, "emp" => "Brian Villalobos", "date" => "14 Jan 2026", "project" => "Mobile App Beta", "assigned" => 35, "worked" => 10, "deadline" => "18 Jan 2026"],
    ["id" => 3, "emp" => "Harvey Smith", "date" => "15 Jan 2026", "project" => "Dashboard UI", "assigned" => 20, "worked" => 15, "deadline" => "22 Jan 2026"],
    ["id" => 4, "emp" => "Stephan Peralt", "date" => "15 Jan 2026", "project" => "API Integration", "assigned" => 45, "worked" => 42, "deadline" => "25 Jan 2026"],
    ["id" => 5, "emp" => "Doglas Martini", "date" => "16 Jan 2026", "project" => "Testing Phase", "assigned" => 30, "worked" => 12, "deadline" => "19 Jan 2026"],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Timesheets</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-orange: #1b5a5a; --bg-gray: #f8f9fa; --border-color: #edf2f7; }
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
        .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .btn-orange { background: var(--primary-orange); color: white; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 600; transition: 0.2s; }
        .btn-orange:hover { background: #e04f2e; color: white; }

        .modal-active { display: flex !important; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
            <?php include 'header.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Timesheets</h4>
                <p class="text-muted small mb-0">Manage daily work logs and hours</p>
            </div>
            <div>
                <button class="btn btn-orange btn-sm shadow-sm d-flex align-items-center gap-2" onclick="openTimesheetModal()">
                    <i class="fa-solid fa-plus"></i> Add Today's Work
                </button>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Project</th>
                            <th class="text-center">Assigned Hours</th>
                            <th class="text-center">Worked Hours</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($timesheets as $row): 
                            // Determine progress color based on hours worked vs assigned
                            $percent = ($row['worked'] / $row['assigned']) * 100;
                            $progressColor = $percent > 90 ? 'bg-danger' : ($percent > 50 ? 'bg-success' : 'bg-warning');
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://i.pravatar.cc/150?img=<?php echo $row['id'] + 10; ?>" class="avatar-img">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $row['emp']; ?></div>
                                        <small class="text-muted">Web Designer</small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $row['date']; ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?php echo $row['project']; ?></span><br>
                                <small class="text-muted">Deadline: <?php echo $row['deadline']; ?></small>
                            </td>
                            <td class="text-center"><?php echo $row['assigned']; ?> Hrs</td>
                            <td class="text-center">
                                <span class="fw-bold"><?php echo $row['worked']; ?> Hrs</span>
                                <div class="progress mt-1" style="height: 4px; width: 60px; margin: 0 auto;">
                                    <div class="progress-bar <?php echo $progressColor; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light border me-1" title="Edit"><i class="fa-solid fa-pen text-secondary"></i></button>
                                <button class="btn btn-sm btn-light border text-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="timesheetModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-slate-800">Add Today's Work</h2>
                <button onclick="closeTimesheetModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <form class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Project <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select Project</option>
                        <option>Website Redesign</option>
                        <option>Mobile App Beta</option>
                        <option>Admin Dashboard</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Deadline <span class="text-red-500">*</span></label>
                    <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Total Hours <span class="text-red-500">*</span></label>
                        <input type="number" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none" placeholder="e.g. 40">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Remaining Hours <span class="text-red-500">*</span></label>
                        <input type="number" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none" placeholder="e.g. 12">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Date <span class="text-red-500">*</span></label>
                        <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Hours Worked <span class="text-red-500">*</span></label>
                        <input type="number" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none" placeholder="e.g. 8">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Description</label>
                    <textarea class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none min-h-[80px]" placeholder="Brief description of work done..."></textarea>
                </div>
                <div class="flex justify-end gap-3 border-t pt-4">
                    <button type="button" onclick="closeTimesheetModal()" class="px-6 py-2.5 rounded-lg border font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg bg-[#ff5e3a] text-white font-semibold hover:bg-orange-600 transition shadow-md">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const tsModal = document.getElementById('timesheetModal');

        function openTimesheetModal() { 
            tsModal.classList.add('modal-active'); 
            document.body.style.overflow = 'hidden'; 
        }

        function closeTimesheetModal() { 
            tsModal.classList.remove('modal-active'); 
            document.body.style.overflow = 'auto'; 
        }

        window.onclick = (e) => { 
            if (e.target == tsModal) closeTimesheetModal(); 
        }
    </script>
</body>
</html>