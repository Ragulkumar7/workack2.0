<?php
// shiftswap.php (EMPLOYEE VIEW)

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA (Simulating logged-in user's history)
$myRequests = [
    ["id" => 1, "date" => "20 Feb 2026", "current" => "Morning (9AM-6PM)", "requested" => "Night (9PM-6AM)", "reason" => "Doctor appointment", "status" => "Pending"],
    ["id" => 2, "date" => "10 Feb 2026", "current" => "Night (9PM-6AM)", "requested" => "Morning (9AM-6PM)", "reason" => "Family function", "status" => "Approved"],
    ["id" => 3, "date" => "05 Feb 2026", "current" => "Morning (9AM-6PM)", "requested" => "Afternoon (2PM-11PM)", "reason" => "Car breakdown", "status" => "Rejected"],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - My Shift Swaps</title>
    
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
        
        .status-pill { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .bg-approved { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .bg-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

        .btn-orange { background: var(--primary-orange); color: white; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 600; transition: 0.2s; }
        .btn-orange:hover { background: #e04f2e; color: white; }

        .modal-active { display: flex !important; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-dark">My Shift Swap Requests</h4>
                <p class="text-muted small mb-0">Manage your shift preferences and requests</p>
            </div>
            <div>
                <button class="btn btn-orange btn-sm shadow-sm d-flex align-items-center gap-2" onclick="openModal()">
                    <i class="fa-solid fa-plus"></i> Request Swap
                </button>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Date Requested</th>
                            <th>Current Shift</th>
                            <th>Requested Shift</th>
                            <th>Reason</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($myRequests as $req): 
                            $statusClass = match($req['status']) {
                                'Approved' => 'bg-approved',
                                'Rejected' => 'bg-rejected',
                                default => 'bg-pending'
                            };
                            $icon = match($req['status']) {
                                'Approved' => '<i class="fa-solid fa-check"></i>',
                                'Rejected' => '<i class="fa-solid fa-circle-xmark"></i>',
                                default => '<i class="fa-regular fa-clock"></i>'
                            };
                        ?>
                        <tr>
                            <td class="text-slate-600 font-medium"><?php echo $req['date']; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $req['current']; ?></span></td>
                            <td><span class="badge bg-light text-primary border border-primary"><?php echo $req['requested']; ?></span></td>
                            <td><span class="text-muted small"><?php echo $req['reason']; ?></span></td>
                            <td class="text-end">
                                <span class="status-pill <?php echo $statusClass; ?>">
                                    <?php echo $icon . ' ' . $req['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="swapModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-xl font-bold text-slate-800">Request Shift Swap</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <form class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Date Required <span class="text-red-500">*</span></label>
                    <input type="date" class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Current Shift</label>
                    <input type="text" value="Morning (9AM - 6PM)" class="w-full border rounded-lg p-2.5 bg-gray-100 text-slate-500 outline-none" readonly>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Requested Shift <span class="text-red-500">*</span></label>
                    <select class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500">
                        <option>Select Shift</option>
                        <option>Afternoon (2PM - 11PM)</option>
                        <option>Night (9PM - 6AM)</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-2">Reason <span class="text-red-500">*</span></label>
                    <textarea class="w-full border rounded-lg p-2.5 bg-gray-50 outline-none focus:border-orange-500 min-h-[80px]" placeholder="e.g. Doctor's appointment..."></textarea>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button type="button" onclick="closeModal()" class="px-6 py-2.5 rounded-lg border font-semibold hover:bg-gray-50">Cancel</button>
                    <button type="button" class="px-6 py-2.5 rounded-lg bg-[#ff5e3a] text-white font-semibold hover:bg-orange-600 transition shadow-md">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('swapModal');

        function openModal() { 
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