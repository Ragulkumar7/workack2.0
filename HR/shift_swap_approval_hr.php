<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../include/db_connect.php');

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

$hr_id = $_SESSION['user_id'];
$message = "";

// --- HANDLE HR FINAL APPROVAL ---
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $req_id = intval($_GET['request_id']);
    $new_status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';

    // HR updates hr_approval column AND the master 'status' column
    $update_sql = "UPDATE shift_swap_requests SET hr_approval = ?, hr_id = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("siii", $new_status, $hr_id, $new_status, $req_id);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-primary border-0 shadow-sm'>HR: Final status for Request #$req_id set to $new_status.</div>";
    }
}

// Fetch requests where Manager has APPROVED
$sql_requests = "SELECT r.*, p.full_name, p.designation 
                 FROM shift_swap_requests r 
                 JOIN employee_profiles p ON r.user_id = p.user_id 
                 WHERE r.manager_approval = 'Approved' 
                 ORDER BY (r.hr_approval = 'Pending') DESC, r.created_at DESC";
$result = $conn->query($sql_requests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final HR Approval - Shift Swap</title>
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
            <h1 class="text-2xl font-bold text-slate-800 mb-2">Final HR Approval Portal</h1>
            <p class="text-slate-500 mb-8">Authorizing shift swaps cleared by TLs and Managers.</p>
            <?php echo $message; ?>
            <div class="card overflow-hidden border-t-4 border-blue-500">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50">
                        <tr class="text-slate-400 text-[10px] uppercase font-bold">
                            <th class="px-6 py-4">Employee</th>
                            <th>Date</th>
                            <th>Current → New</th>
                            <th>HR Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 font-bold text-blue-700"><?php echo $row['full_name']; ?></td>
                            <td><?php echo date('d M Y', strtotime($row['request_date'])); ?></td>
                            <td><?php echo $row['current_shift']; ?> → <?php echo $row['requested_shift']; ?></td>
                            <td><span class="status-pill bg-<?php echo strtolower($row['hr_approval']); ?>"><?php echo $row['hr_approval']; ?></span></td>
                            <td class="text-center">
                                <?php if ($row['hr_approval'] == 'Pending'): ?>
                                    <div class="flex gap-2 justify-center">
                                        <a href="?action=approve&request_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-success">Final Approve</a>
                                        <a href="?action=reject&request_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger">Reject</a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300 font-bold uppercase text-[10px]">Archived</span>
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