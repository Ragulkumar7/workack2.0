<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../include/db_connect.php';

// Ensure the table supports detailed breakdown
$conn->query("CREATE TABLE IF NOT EXISTS `salary_hike_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id_code` varchar(50) NOT NULL,
  `old_salary` decimal(10,2) NOT NULL,
  `hike_percent` decimal(5,2) NOT NULL,
  `new_salary` decimal(10,2) NOT NULL,
  `breakdown` text DEFAULT NULL,
  `performance_score` decimal(5,1) DEFAULT 0,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `requested_date` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
)");

$msg = '';

// --- FORM SUBMISSION LOGIC (Send to Accounts) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_salary'])) {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id_code']);
    
    // Earnings
    $basic = floatval($_POST['basic']);
    $da = floatval($_POST['da']);
    $hra = floatval($_POST['hra']);
    $conveyance = floatval($_POST['conveyance']);
    
    // Deductions
    $tds = floatval($_POST['tds']);
    $esi = floatval($_POST['esi']);
    $pf = floatval($_POST['pf']);
    $prof_tax = floatval($_POST['prof_tax']);
    
    $net_salary = ($basic + $da + $hra + $conveyance) - ($tds + $esi + $pf + $prof_tax);
    
    $breakdown = json_encode([
        'earnings' => ['basic' => $basic, 'da' => $da, 'hra' => $hra, 'conveyance' => $conveyance],
        'deductions' => ['tds' => $tds, 'esi' => $esi, 'pf' => $pf, 'prof_tax' => $prof_tax]
    ]);
    
    // Get Current Salary (Using COLLATE to prevent mix collation error)
    $old_sal_q = $conn->query("SELECT salary FROM employee_onboarding WHERE emp_id_code COLLATE utf8mb4_unicode_ci = '$emp_id' COLLATE utf8mb4_unicode_ci");
    $old_sal = ($old_sal_q && $old_sal_q->num_rows > 0) ? floatval($old_sal_q->fetch_assoc()['salary']) : 0;
    
    $hike_pct = ($old_sal > 0) ? (($net_salary - $old_sal) / $old_sal) * 100 : 0;

    // Check if pending request already exists
    $check_pending = $conn->query("SELECT id FROM salary_hike_requests WHERE emp_id_code COLLATE utf8mb4_unicode_ci = '$emp_id' COLLATE utf8mb4_unicode_ci AND status = 'Pending'");
    if($check_pending && $check_pending->num_rows > 0) {
        $msg = "<div class='bg-orange-100 text-orange-700 p-4 rounded-xl mb-4 font-semibold'>A pending request already exists for this employee. Wait for Accounts approval.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO salary_hike_requests (emp_id_code, old_salary, hike_percent, new_salary, breakdown, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("sddds", $emp_id, $old_sal, $hike_pct, $net_salary, $breakdown);
        if ($stmt->execute()) {
            $msg = "<div class='bg-emerald-100 text-emerald-800 p-4 rounded-xl mb-4 font-semibold'><i class='fa-solid fa-check-circle mr-2'></i> Salary package fixed and sent to Accounts team for approval!</div>";
        }
    }
}

// Fetch all employees for table (FIXED: Added COLLATE to force matching encoding)
$emp_query = "SELECT ep.emp_id_code, ep.full_name, ep.department, ep.email, ep.phone, ep.designation, ep.joining_date, COALESCE(eo.salary, 0) as current_salary
              FROM employee_profiles ep
              LEFT JOIN employee_onboarding eo ON ep.emp_id_code COLLATE utf8mb4_unicode_ci = eo.emp_id_code COLLATE utf8mb4_unicode_ci
              WHERE ep.status = 'Active'";
$emp_result = $conn->query($emp_query);
$emp_db_error = (!$emp_result) ? $conn->error : ""; 

// Fetch all employees for Dropdown
$dropdown_query = "SELECT emp_id_code, full_name FROM employee_profiles WHERE status = 'Active'";
$dropdown_result = $conn->query($dropdown_query);
$dropdown_db_error = (!$dropdown_result) ? $conn->error : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Salary Dashboard | HR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }

        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: margin-left 0.3s ease, width 0.3s ease; padding: 24px; min-height: 100vh; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .bg-darkteal { background-color: #144d4d; }
        .hover-darkteal:hover { background-color: #115e59; }
        .text-darkteal { color: #144d4d; }
        
        .hidden-element { display: none; }
        
        th { background: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td { font-size: 0.875rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        tr:hover td { background: #f8fafc; }
        
        select, input { border-color: #e2e8f0; }
        select:focus, input:focus { border-color: #144d4d; outline: none; box-shadow: 0 0 0 3px rgba(20, 77, 77, 0.1); }
    </style>
</head>
<body class="text-gray-700">

    <?php include '../sidebars.php'; include '../header.php'; ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Employee Salary Packages</h1>
                <p class="text-sm text-gray-500 mt-1">Fix and manage salary structures for your workforce.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="toggleElement('salaryModal')" class="flex items-center gap-2 bg-darkteal hover-darkteal text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-lg">
                    <i class="fa-solid fa-calculator"></i> Fix & Request Package
                </button>
            </div>
        </div>

        <?= $msg ?>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                <h3 class="font-semibold text-gray-800">Employee Master Salary List</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="px-5 py-4 w-10"><input type="checkbox" class="rounded text-teal-600"></th>
                            <th class="px-4 py-4">Emp ID</th>
                            <th class="px-4 py-4">Name</th>
                            <th class="px-4 py-4">Phone</th>
                            <th class="px-4 py-4">Designation</th>
                            <th class="px-4 py-4">Joining Date</th>
                            <th class="px-4 py-4">Fixed Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($emp_db_error): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-red-500 bg-red-50 font-medium">
                                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> SQL Error: <?= htmlspecialchars($emp_db_error) ?>
                                </td>
                            </tr>
                        <?php elseif ($emp_result && $emp_result->num_rows > 0): ?>
                            <?php while($emp = $emp_result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-4"><input type="checkbox" class="rounded text-teal-600"></td>
                                <td class="px-4 py-4 font-medium text-gray-500"><?= htmlspecialchars($emp['emp_id_code'] ?? 'N/A') ?></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($emp['full_name'] ?? 'User') ?>&background=144d4d&color=fff" class="w-9 h-9 rounded-full">
                                        <div>
                                            <div class="font-bold text-gray-900"><?= htmlspecialchars($emp['full_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-gray-400 font-medium"><?= htmlspecialchars($emp['department'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-500"><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                                <td class="px-4 py-4">
                                    <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium">
                                        <?= htmlspecialchars($emp['designation'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-gray-500"><?= !empty($emp['joining_date']) ? date('d M Y', strtotime($emp['joining_date'])) : 'N/A' ?></td>
                                <td class="px-4 py-4 font-bold text-darkteal">
                                    <?= ($emp['current_salary'] > 0) ? '₹' . number_format($emp['current_salary']) : '<span class="text-orange-500 text-xs">Not Fixed</span>' ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-gray-500">
                                    No active employees found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="salaryModal" class="hidden-element fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleElement('salaryModal')"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden">
                <div class="px-6 py-5 border-b flex items-center justify-between bg-slate-50">
                    <h2 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-file-invoice-dollar text-darkteal mr-2"></i> Fix Package & Send to Accounts</h2>
                    <button type="button" onclick="toggleElement('salaryModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                        <i class="fa-solid fa-circle-xmark text-2xl"></i>
                    </button>
                </div>

                <form method="POST" class="p-6">
                    <input type="hidden" name="submit_salary" value="1">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 mb-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Employee</label>
                            <div class="relative">
                                <select name="emp_id_code" required class="w-full border border-gray-200 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-teal-500 outline-none appearance-none text-gray-700 font-medium">
                                    <option value="">-- Choose Employee --</option>
                                    <?php if ($dropdown_db_error): ?>
                                        <option value="" disabled>Error: <?= htmlspecialchars($dropdown_db_error) ?></option>
                                    <?php elseif ($dropdown_result && $dropdown_result->num_rows > 0): ?>
                                        <?php while($dd = $dropdown_result->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($dd['emp_id_code']) ?>"><?= htmlspecialchars($dd['full_name']) ?> (<?= htmlspecialchars($dd['emp_id_code']) ?>)</option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-gray-400 text-xs"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Calculated Net Salary (₹)</label>
                            <input type="text" id="net_salary" name="net_salary" class="w-full border border-gray-200 bg-emerald-50 text-emerald-700 font-bold rounded-xl px-4 py-3 outline-none" value="0.00" readonly>
                            <p class="text-xs text-gray-400 mt-1">Auto-calculated based on below inputs.</p>
                        </div>
                    </div>

                    <div class="mb-8 p-5 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-200 pb-2">
                            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-arrow-trend-up text-green-600 mr-2"></i> Earnings</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-4">
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Basic Salary</label><input type="number" id="basic" name="basic" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">DA Allowance</label><input type="number" id="da" name="da" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">HRA</label><input type="number" id="hra" name="hra" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Conveyance</label><input type="number" id="conveyance" name="conveyance" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                        </div>
                    </div>

                    <div class="mb-8 p-5 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-200 pb-2">
                            <h3 class="font-bold text-gray-800"><i class="fa-solid fa-arrow-trend-down text-red-500 mr-2"></i> Deductions</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-4">
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">TDS</label><input type="number" id="tds" name="tds" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">ESI</label><input type="number" id="esi" name="esi" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">PF</label><input type="number" id="pf" name="pf" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                            <div><label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Prof. Tax</label><input type="number" id="prof_tax" name="prof_tax" value="0" min="0" oninput="calculateNet()" class="w-full border border-gray-200 rounded-lg px-3 py-2 outline-none"></div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-100">
                        <button type="button" onclick="toggleElement('salaryModal')" class="px-6 py-2.5 bg-gray-100 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-8 py-2.5 bg-darkteal hover-darkteal text-white rounded-xl text-sm font-bold shadow-md transition-all flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Send to Accounts
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleElement(id) {
            const el = document.getElementById(id);
            el.classList.toggle('hidden-element');
            if (id === 'salaryModal') {
                document.body.style.overflow = el.classList.contains('hidden-element') ? 'auto' : 'hidden';
            }
        }

        function calculateNet() {
            let basic = parseFloat(document.getElementById('basic').value) || 0;
            let da = parseFloat(document.getElementById('da').value) || 0;
            let hra = parseFloat(document.getElementById('hra').value) || 0;
            let conv = parseFloat(document.getElementById('conveyance').value) || 0;
            
            let tds = parseFloat(document.getElementById('tds').value) || 0;
            let esi = parseFloat(document.getElementById('esi').value) || 0;
            let pf = parseFloat(document.getElementById('pf').value) || 0;
            let pt = parseFloat(document.getElementById('prof_tax').value) || 0;
            
            let earnings = basic + da + hra + conv;
            let deductions = tds + esi + pf + pt;
            let net = earnings - deductions;
            
            document.getElementById('net_salary').value = net.toFixed(2);
        }
    </script>
</body>
</html>