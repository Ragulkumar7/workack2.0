<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

$manager_id = $_SESSION['user_id'];
$message = "";

// --- HANDLE MANAGER APPROVAL ---
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $req_id = intval($_GET['request_id']);
    $new_status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';

    $update_sql = "UPDATE shift_swap_requests SET manager_approval = ?, manager_id = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $new_status, $manager_id, $req_id);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm'>Manager: Request #$req_id marked as $new_status.</div>";
    }
}

// Fetch requests where TL has APPROVED
$sql_requests = "SELECT r.*, p.full_name, p.designation 
                 FROM shift_swap_requests r 
                 JOIN employee_profiles p ON r.user_id = p.user_id 
                 WHERE r.tl_approval = 'Approved' 
                 ORDER BY (r.manager_approval = 'Pending') DESC, r.created_at DESC";
$result = $conn->query($sql_requests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shift Swap Approvals - Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 13px; }
        #mainContent { margin-left: 95px; padding: 40px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #fff; }
        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .bg-pending { background: #fff7ed; color: #9a3412; }
        .bg-approved { background: #dcfce7; color: #166534; }
        .bg-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-slate-50">
    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>
    <main id="mainContent">
        <div class="w-full">
            <h1 class="text-2xl font-bold text-slate-800 mb-2">Manager Approval Queue</h1>
            <p class="text-slate-500 mb-8">Reviewing requests already cleared by Team Leads.</p>
            <?php echo $message; ?>
            <div class="card overflow-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50">
                        <tr class="text-slate-400 text-[10px] uppercase font-bold">
                            <th class="px-6 py-4">Employee</th>
                            <th>Date</th>
                            <th>Shift Swap</th>
                            <th>Manager Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 font-bold"><?php echo $row['full_name']; ?></td>
                            <td><?php echo date('d M Y', strtotime($row['request_date'])); ?></td>
                            <td><?php echo $row['current_shift']; ?> <i class="fa fa-arrow-right mx-1 text-orange-400"></i> <?php echo $row['requested_shift']; ?></td>
                            <td><span class="status-pill bg-<?php echo strtolower($row['manager_approval']); ?>"><?php echo $row['manager_approval']; ?></span></td>
                            <td class="text-center">
                                <?php if ($row['manager_approval'] == 'Pending'): ?>
                                    <a href="?action=approve&request_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success rounded-pill px-3">Approve</a>
                                    <a href="?action=reject&request_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger rounded-pill px-3">Reject</a>
                                <?php else: ?>
                                    <span class="text-slate-300 italic">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>