<?php
// payslip_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. DATABASE CONNECTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// --- 2. HANDLE APPROVAL ACTIONS ---
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = mysqli_real_escape_string($conn, $_GET['req_id']);
    $status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';
    $reply = ($status == 'Approved') ? 'Your payslip has been generated and sent.' : 'Request rejected. Contact Accounts.';

    $updateSql = "UPDATE payslip_requests SET status = '$status', accounts_reply = '$reply' WHERE request_id = '$req_id'";
    mysqli_query($conn, $updateSql);
    header("Location: payslip_management.php?view=approvals&success=1");
    exit();
}

// --- 3. FETCH PENDING REQUESTS FROM DB ---
$sql_pending = "SELECT p.*, u.name as emp_name FROM payslip_requests p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.status = 'Pending' 
                ORDER BY p.requested_date DESC";
$res_pending = mysqli_query($conn, $sql_pending);

// --- 4. FETCH HISTORY FROM DB ---
$sql_history = "SELECT p.*, u.name as emp_name FROM payslip_requests p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.status IN ('Approved', 'Rejected') 
                ORDER BY p.requested_date DESC LIMIT 20";
$res_history = mysqli_query($conn, $sql_history);

$view = $_GET['view'] ?? 'approvals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Management | Accounts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); }
    </style>
</head>
<body class="text-slate-800">
    <?php include '../sidebars.php'; ?>
    <?php include '../header.php'; ?>

    <main id="mainContent" class="p-8 min-h-screen">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Payslip Request Management</h1>
                <p class="text-sm text-gray-500 mt-1">Review and process employee payslip requests.</p>
            </div>
            <div class="flex gap-2 bg-white p-1 rounded-xl shadow-sm border border-gray-100">
                <a href="?view=approvals" class="px-5 py-2 text-sm font-semibold rounded-lg transition <?php echo $view == 'approvals' ? 'bg-teal-600 text-white' : 'text-slate-600'; ?>">Pending Approvals</a>
                <a href="?view=history" class="px-5 py-2 text-sm font-semibold rounded-lg transition <?php echo $view == 'history' ? 'bg-teal-600 text-white' : 'text-slate-600'; ?>">Process History</a>
            </div>
        </div>

        <?php if($view == 'approvals'): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold border-b">
                    <tr><th class="p-5">Request ID</th><th class="p-5">Employee</th><th class="p-5">Period</th><th class="p-5">Priority</th><th class="p-5 text-right">Action</th></tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-50">
                    <?php while($row = mysqli_fetch_assoc($res_pending)): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-5 font-bold text-teal-600"><?php echo $row['request_id']; ?></td>
                        <td class="p-5 font-medium"><?php echo $row['emp_name']; ?></td>
                        <td class="p-5"><?php echo date('M d', strtotime($row['from_date'])); ?> - <?php echo date('M d, Y', strtotime($row['to_date'])); ?></td>
                        <td class="p-5"><span class="px-2 py-1 rounded-lg text-[10px] font-bold bg-orange-50 text-orange-600"><?php echo $row['priority']; ?></span></td>
                        <td class="p-5 text-right space-x-2">
                            <a href="?action=approve&req_id=<?php echo $row['request_id']; ?>" class="bg-teal-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-teal-700 transition shadow-sm"><i class="fa-solid fa-check mr-1"></i> Approve</a>
                            <a href="?action=reject&req_id=<?php echo $row['request_id']; ?>" class="bg-red-50 text-red-500 px-4 py-2 rounded-lg text-xs font-bold border border-red-100"><i class="fa-solid fa-times mr-1"></i> Reject</a>
                        </td>
                    </tr>
                    <?php endwhile; if(mysqli_num_rows($res_pending) == 0) echo "<tr><td colspan='5' class='p-10 text-center text-gray-400'>No pending requests.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if($view == 'history'): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-left text-sm divide-y divide-gray-50">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                    <tr><th class="p-5">ID</th><th class="p-5">Employee</th><th class="p-5">Status</th><th class="p-5">Date Processed</th><th class="p-5 text-right">Reply</th></tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($res_history)): 
                        $badge = ($row['status'] == 'Approved') ? 'bg-teal-100 text-teal-700' : 'bg-red-100 text-red-700';
                    ?>
                    <tr>
                        <td class="p-5 font-bold"><?php echo $row['request_id']; ?></td>
                        <td class="p-5 font-medium"><?php echo $row['emp_name']; ?></td>
                        <td class="p-5"><span class="<?php echo $badge; ?> px-3 py-1 rounded-full text-xs font-bold"><?php echo $row['status']; ?></span></td>
                        <td class="p-5 text-slate-500"><?php echo date('d M Y', strtotime($row['requested_date'])); ?></td>
                        <td class="p-5 text-right text-xs italic text-gray-400"><?php echo $row['accounts_reply']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>