<?php
// manager/my_team.php

$path_to_root = '../'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database Connection
require_once $path_to_root . 'include/db_connect.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $path_to_root . "index.php");
    exit();
}

$manager_id = $_SESSION['user_id'];

// Fetch all employees reporting to this manager
$sql = "SELECT p.*, u.role, u.username 
        FROM employee_profiles p 
        INNER JOIN users u ON p.user_id = u.id 
        WHERE p.manager_id = ? OR p.reporting_to = ?
        ORDER BY p.joining_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $manager_id, $manager_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team | Manager Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1b5a5a;
            --primary-light: #2d7a7a;
            --border: #e2e8f0;
            --text-muted: #64748b;
        }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; color: #1e293b; }
        
        #mainContent {
            margin-left: 95px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 12px; padding: 16px 24px; text-align: left; border-bottom: 1px solid var(--border); }
        td { padding: 16px 24px; vertical-align: middle; border-bottom: 1px solid var(--border); font-size: 14px; color: #334155; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f1f5f9; }

        .avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .role-badge { background: #f0fdfa; color: #0f766e; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid #ccfbf1; }
    </style>
</head>
<body>

    <?php 
    if(file_exists($path_to_root . '../sidebars.php')) include $path_to_root . '../sidebars.php';
    ?>

    <div id="mainContent">
        <?php 
        if(file_exists($path_to_root . '../header.php')) include $path_to_root . '../header.php';
        ?>

        <main class="p-8 w-full max-w-7xl mx-auto flex-1 mt-16">
            
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">My Team Overview</h1>
                    <p class="text-slate-500 mt-1.5">View and manage the employees and team leads assigned to you.</p>
                </div>
                <div class="bg-white px-5 py-2.5 rounded-lg shadow-sm border border-gray-200 text-sm font-medium text-gray-600">
                    Total Members: <span class="text-teal-700 font-bold text-lg ml-1"><?php echo $result->num_rows; ?></span>
                </div>
            </div>

            <div class="table-container">
                <?php if ($result && $result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Emp ID</th>
                                    <th>Designation / Role</th>
                                    <th>Contact Info</th>
                                    <th>Joining Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): 
                                    // Handle Image Path
                                    $img = !empty($row['profile_img']) ? $row['profile_img'] : "https://ui-avatars.com/api/?name=" . urlencode($row['full_name']) . "&background=random";
                                    if (!filter_var($img, FILTER_VALIDATE_URL)) { $img = '../' . $img; }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-4">
                                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Avatar" class="avatar">
                                                <div>
                                                    <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['department']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-medium text-slate-600 bg-slate-100 px-2 py-1 rounded text-xs border border-slate-200">
                                                <?php echo htmlspecialchars($row['emp_id_code']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="font-medium text-slate-800 mb-1"><?php echo htmlspecialchars($row['designation']); ?></div>
                                            <span class="role-badge"><?php echo htmlspecialchars($row['role']); ?></span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2 mb-1 text-slate-600">
                                                <i class="fas fa-envelope text-slate-400 w-4"></i> <?php echo htmlspecialchars($row['email']); ?>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-600">
                                                <i class="fas fa-phone text-slate-400 w-4"></i> <?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="font-medium text-slate-700">
                                            <?php echo !empty($row['joining_date']) ? date("d M Y", strtotime($row['joining_date'])) : 'N/A'; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <div class="text-slate-300 text-6xl mb-4"><i class="fas fa-users-slash"></i></div>
                        <h3 class="text-xl font-bold text-slate-700 mb-2">No Team Members Yet</h3>
                        <p class="text-slate-500">Currently, no employees or team leads have been assigned under your management.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

</body>
</html>