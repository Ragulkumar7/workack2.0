<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php';

// Handle Hike Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_hike'])) {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $old_sal = floatval($_POST['old_sal']);
    $new_sal = floatval($_POST['new_sal']);
    $hike_pct = floatval($_POST['hike_pct']);
    $score = floatval($_POST['score']);

    // Check if a pending request already exists
    $check = $conn->query("SELECT id FROM salary_hike_requests WHERE emp_id_code = '$emp_id' AND status = 'Pending'");
    if ($check->num_rows == 0) {
        $query = "INSERT INTO salary_hike_requests (emp_id_code, old_salary, hike_percent, new_salary, performance_score) 
                  VALUES ('$emp_id', '$old_sal', '$hike_pct', '$new_sal', '$score')";
        if($conn->query($query)) {
            $msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Hike request sent to Accounts successfully!</div>";
        }
    } else {
        $msg = "<div class='bg-orange-100 text-orange-700 p-3 rounded mb-4'>A pending request already exists for this employee.</div>";
    }
}

// Fetch Employees with Performance and Current Salary
$sql = "SELECT ep.emp_id_code, ep.full_name, ep.department, ep.designation, 
               COALESCE(eo.salary, 0) as current_salary, 
               COALESCE(per.total_score, 0) as performance_score 
        FROM employee_profiles ep 
        LEFT JOIN employee_onboarding eo ON ep.emp_id_code = eo.emp_id_code 
        LEFT JOIN employee_performance per ON ep.user_id = per.user_id 
        WHERE ep.status = 'Active'";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR | Performance Salary Hikes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); padding: 24px; min-height: 100vh; }
        .hidden-element { display: none; }
    </style>
</head>
<body>
    <?php include '../sidebars.php'; include '../header.php'; ?>

    <div id="mainContent">
        <div class="mb-6 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Performance-Based Salary Hikes</h1>
                <p class="text-sm text-gray-500 mt-1">Evaluate scores and send hike requests to Accounts</p>
            </div>
        </div>

        <?= $msg ?? '' ?>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-gray-100 text-xs uppercase text-gray-500 font-bold tracking-wider">
                            <th class="px-4 py-4">Emp ID</th>
                            <th class="px-4 py-4">Employee</th>
                            <th class="px-4 py-4">Score</th>
                            <th class="px-4 py-4">Current Salary</th>
                            <th class="px-4 py-4">Suggested Hike</th>
                            <th class="px-4 py-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php while($row = $result->fetch_assoc()): 
                            $score = floatval($row['performance_score']);
                            $current_sal = floatval($row['current_salary']);
                            
                            // Auto Calculate Hike based on Score
                            $hike_pct = 0;
                            if ($score >= 90) $hike_pct = 20;
                            elseif ($score >= 75) $hike_pct = 10;
                            elseif ($score >= 50) $hike_pct = 5;

                            $new_sal = $current_sal + ($current_sal * ($hike_pct / 100));
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-4 font-bold text-gray-600"><?= $row['emp_id_code'] ?></td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-gray-900"><?= $row['full_name'] ?></div>
                                <div class="text-xs text-gray-500"><?= $row['department'] ?></div>
                            </td>
                            <td class="px-4 py-4 font-bold <?= $score >= 75 ? 'text-green-600' : 'text-orange-500' ?>"><?= $score ?>%</td>
                            <td class="px-4 py-4 font-bold text-slate-700">₹<?= number_format($current_sal) ?></td>
                            <td class="px-4 py-4">
                                <?php if($hike_pct > 0): ?>
                                    <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">+<?= $hike_pct ?>%</span>
                                    <div class="text-xs text-slate-500 mt-1">New: ₹<?= number_format($new_sal) ?></div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">No Hike</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <?php if($hike_pct > 0 && $current_sal > 0): ?>
                                <button onclick="openModal('<?= $row['emp_id_code'] ?>', '<?= $row['full_name'] ?>', <?= $current_sal ?>, <?= $new_sal ?>, <?= $hike_pct ?>, <?= $score ?>)" 
                                        class="bg-teal-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-teal-800">
                                    Request Approval
                                </button>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">Not Eligible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="hikeModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden-element">
        <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 bg-slate-50 border-b flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800">Send Hike Request</h2>
                <button onclick="document.getElementById('hikeModal').classList.add('hidden-element')" class="text-gray-400 hover:text-red-500"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="request_hike" value="1">
                    <input type="hidden" name="emp_id" id="mod_emp_id">
                    <input type="hidden" name="score" id="mod_score">
                    
                    <p class="text-sm text-gray-600 mb-4">You are sending a salary revision request for <span id="mod_name" class="font-bold"></span> to the Accounts department.</p>
                    
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="text-xs font-bold text-gray-500">Current Salary</label>
                            <input type="text" name="old_sal" id="mod_old_sal" class="w-full mt-1 p-2 bg-gray-100 border rounded outline-none font-bold" readonly>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-1/3">
                                <label class="text-xs font-bold text-gray-500">Hike %</label>
                                <input type="text" name="hike_pct" id="mod_hike_pct" class="w-full mt-1 p-2 border rounded outline-none font-bold text-green-600">
                            </div>
                            <div class="w-2/3">
                                <label class="text-xs font-bold text-gray-500">Proposed New Salary</label>
                                <input type="text" name="new_sal" id="mod_new_sal" class="w-full mt-1 p-2 border rounded outline-none font-bold text-teal-700">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <button type="submit" class="bg-teal-700 text-white px-6 py-2 rounded-lg font-bold">Submit to Accounts</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(id, name, oldSal, newSal, pct, score) {
            document.getElementById('mod_emp_id').value = id;
            document.getElementById('mod_name').innerText = name;
            document.getElementById('mod_old_sal').value = oldSal;
            document.getElementById('mod_new_sal').value = newSal;
            document.getElementById('mod_hike_pct').value = pct;
            document.getElementById('mod_score').value = score;
            document.getElementById('hikeModal').classList.remove('hidden-element');
        }
    </script>
</body>
</html>