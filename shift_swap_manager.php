<?php
// shift_swap_manager.php (MANAGER VIEW)

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. MOCK DATA (In a real app, this queries the database for ALL employees' requests)
$allRequests = [
    [
        "id" => 101, 
        "emp" => "Anthony Lewis", 
        "role" => "Web Designer", 
        "date" => "25 Feb 2026", 
        "current" => "Morning (9AM-6PM)", 
        "requested" => "Night (9PM-6AM)", 
        "reason" => "Family medical emergency", 
        "status" => "Pending"
    ],
    [
        "id" => 102, 
        "emp" => "Brian Villalobos", 
        "role" => "Developer", 
        "date" => "26 Feb 2026", 
        "current" => "Morning (9AM-6PM)", 
        "requested" => "Afternoon (2PM-11PM)", 
        "reason" => "Car breakdown, need time to fix", 
        "status" => "Pending"
    ],
    [
        "id" => 103, 
        "emp" => "Harvey Smith", 
        "role" => "Tester", 
        "date" => "24 Feb 2026", 
        "current" => "Night (9PM-6AM)", 
        "requested" => "Morning (9AM-6PM)", 
        "reason" => "Doctor appointment scheduled", 
        "status" => "Approved"
    ],
    [
        "id" => 104, 
        "emp" => "Stephan Peralt", 
        "role" => "Android Dev", 
        "date" => "28 Feb 2026", 
        "current" => "Morning (9AM-6PM)", 
        "requested" => "Night (9PM-6AM)", 
        "reason" => "Personal commitment", 
        "status" => "Rejected"
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Manager Shift Approvals</title>
    
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
        .avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        /* Status Badges */
        .status-pill { padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .bg-approved { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .bg-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

        /* Action Buttons */
        .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; border: 1px solid transparent; cursor: pointer; }
        .btn-approve { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .btn-approve:hover { background: #16a34a; color: white; border-color: #16a34a; }
        .btn-reject { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        .btn-reject:hover { background: #dc2626; color: white; border-color: #dc2626; }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('sidebars.php'); ?>

    <main id="mainContent">
            <?php include 'header.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Shift Swap Requests (Manager)</h4>
                <p class="text-muted small mb-0">Review pending shift changes from employees</p>
            </div>
            </div>

        <div class="card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Current Shift</th>
                            <th>Requested Shift</th>
                            <th style="width: 25%;">Reason</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allRequests as $req): 
                            // Determine Badge Color
                            $statusClass = match($req['status']) {
                                'Approved' => 'bg-approved',
                                'Rejected' => 'bg-rejected',
                                default => 'bg-pending'
                            };
                            // Determine Icon
                            $icon = match($req['status']) {
                                'Approved' => '<i class="fa-solid fa-check"></i>',
                                'Rejected' => '<i class="fa-solid fa-xmark"></i>',
                                default => '<i class="fa-regular fa-clock"></i>'
                            };
                        ?>
                        <tr id="row-<?php echo $req['id']; ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://i.pravatar.cc/150?u=<?php echo $req['id']; ?>" class="avatar-img">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $req['emp']; ?></div>
                                        <small class="text-muted"><?php echo $req['role']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-600 font-medium"><?php echo $req['date']; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $req['current']; ?></span></td>
                            <td><span class="badge bg-light text-primary border border-primary"><?php echo $req['requested']; ?></span></td>
                            <td>
                                <span class="d-block text-truncate text-muted small" style="max-width: 200px;" title="<?php echo $req['reason']; ?>">
                                    <?php echo $req['reason']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $statusClass; ?>">
                                    <?php echo $icon . ' ' . $req['status']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if($req['status'] === 'Pending'): ?>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn-action btn-approve" onclick="processRequest(<?php echo $req['id']; ?>, 'Approved')" title="Approve Request">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <button class="btn-action btn-reject" onclick="processRequest(<?php echo $req['id']; ?>, 'Rejected')" title="Reject Request">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small italic opacity-50">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function processRequest(id, action) {
            let confirmation = confirm(`Are you sure you want to ${action.toUpperCase()} this request?`);
            
            if (confirmation) {
                // In a real application, you would make an AJAX/Fetch call here to update the database
                alert(`Request has been ${action}.`);
                
                // Update UI to reflect change without reload
                const row = document.getElementById(`row-${id}`);
                const statusCell = row.querySelector('.status-pill');
                const actionCell = row.querySelector('td:last-child');

                if(action === 'Approved') {
                    statusCell.className = 'status-pill bg-approved';
                    statusCell.innerHTML = '<i class="fa-solid fa-check"></i> Approved';
                } else {
                    statusCell.className = 'status-pill bg-rejected';
                    statusCell.innerHTML = '<i class="fa-solid fa-xmark"></i> Rejected';
                }
                
                // Remove buttons and show "Processed"
                actionCell.innerHTML = '<span class="text-muted small italic opacity-50">Processed</span>';
            }
        }
    </script>
</body>
</html>