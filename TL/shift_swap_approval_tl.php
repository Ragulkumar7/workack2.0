<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once('../include/db_connect.php');

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

$tl_id = $_SESSION['user_id'];
$message = "";

// --- HANDLE TEAM LEAD APPROVAL / REJECTION ---
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $req_id = intval($_GET['request_id']);
    $new_tl_status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';

    // Update ONLY the TL approval column and record the TL's ID
    $update_sql = "UPDATE shift_swap_requests SET tl_approval = ?, tl_id = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $new_tl_status, $tl_id, $req_id);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm font-bold'>Success: Request #$req_id marked as $new_tl_status by you.</div>";
    } else {
        $message = "<div class='alert alert-danger font-bold'>Error: Could not update the request.</div>";
    }
}

// Fetch all requests - joining with profiles to show employee names
// We show all requests so the TL can see the progress of their team's swaps
$sql_requests = "SELECT r.*, p.full_name, p.designation 
                 FROM shift_swap_requests r 
                 JOIN employee_profiles p ON r.user_id = p.user_id 
                 ORDER BY (r.tl_approval = 'Pending') DESC, r.created_at DESC";
$result = $conn->query($sql_requests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Swap Approvals - Team Lead</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 13px; }
        #mainContent { margin-left: 95px; padding: 40px; min-height: 100vh; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #fff; }
        .status-pill { padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .bg-pending { background: #fff7ed; color: #9a3412; }
        .bg-approved { background: #dcfce7; color: #166534; }
        .bg-rejected { background: #fee2e2; color: #991b1b; }
        @media (max-width: 768px) { #mainContent { margin-left: 0 !important; padding: 15px; } }
    </style>
</head>
<body class="bg-slate-50">

    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>

    <main id="mainContent">
        <div class="w-full">
            <div class="mb-8 flex justify-between items-end">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Shift Swap Approvals (Team Lead)</h1>
                    <p class="text-slate-500 text-sm">Review pending shift changes. Your approval will move the request to the Manager.</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Neoera Infotech</span>
                </div>
            </div>

            <?php echo $message; ?>

            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr class="text-slate-400 text-[10px] uppercase tracking-widest font-bold">
                                <th class="px-6 py-4">Employee</th>
                                <th>Swap Date</th>
                                <th>Shift Details</th>
                                <th>Reason</th>
                                <th>Your Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-bold">
                                                <?php echo substr($row['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 mb-0"><?php echo $row['full_name']; ?></p>
                                                <p class="text-[10px] text-slate-400"><?php echo $row['designation']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="font-semibold text-slate-600"><?php echo date('d M Y', strtotime($row['request_date'])); ?></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-medium text-slate-500"><?php echo $row['current_shift']; ?></span>
                                            <i class="fa fa-arrow-right text-[9px] text-orange-400"></i>
                                            <span class="text-[11px] font-bold text-orange-600"><?php echo $row['requested_shift']; ?></span>
                                        </div>
                                    </td>
                                    <td class="text-slate-500 italic text-[11px] max-w-xs truncate" title="<?php echo $row['reason']; ?>">
                                        <?php echo $row['reason']; ?>
                                    </td>
                                    <td>
                                        <span class="status-pill bg-<?php echo strtolower($row['tl_approval']); ?>">
                                            <?php echo $row['tl_approval']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['tl_approval'] == 'Pending'): ?>
                                            <div class="flex justify-center gap-2">
                                                <a href="?action=approve&request_id=<?php echo $row['id']; ?>" class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-all shadow-sm">
                                                    <i class="fa fa-check text-xs"></i>
                                                </a>
                                                <a href="?action=reject&request_id=<?php echo $row['id']; ?>" class="w-8 h-8 rounded-full bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                                    <i class="fa fa-times text-xs"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center">
                                                <i class="fa fa-check-circle text-emerald-500 text-lg"></i>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Submitted</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-12 text-slate-400 italic">No swap requests found for your team.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>