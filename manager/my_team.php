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

// Calculate Stats
 $total_members = $result->num_rows;
 $departments = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}
// Reset pointer for main loop
 $result->data_seek(0);
 $unique_depts = count(array_unique($departments));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team | Manager Portal</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary: #1b5a5a;
            --primary-dark: #144545;
            --primary-light: #e0f2f1;
            --bg-body: #f3f4f6;
        }
        body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; margin: 0; color: #1e293b; }
        
        /* Layout Adjustments for Sidebar */
        #mainContent {
            margin-left: 95px;
            width: calc(100% - 95px);
            min-height: 100vh;
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px;
            box-sizing: border-box;
        }
        #mainContent.main-shifted {
            margin-left: 315px;
            width: calc(100% - 315px);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Table Styles */
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th { background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; padding: 16px 20px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .custom-table td { padding: 18px 20px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 14px; transition: background 0.2s; }
        .custom-table tbody tr:hover td { background-color: #f8fafc; }
        
        /* Avatar Styles */
        .avatar-sm { width: 44px; height: 44px; border-radius: 12px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        
        /* Action Button */
        .btn-action {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-view {
            background: var(--primary-light);
            color: var(--primary);
        }
        .btn-view:hover {
            background: var(--primary);
            color: white;
        }

        /* Card Shadow */
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); }
        
        /* Modal Animation Styles */
        .modal-overlay { transition: opacity 0.3s ease-out; }
        .modal-content { transition: transform 0.3s ease-out, opacity 0.3s ease-out; }
    </style>
</head>
<body>

    <?php 
    if(file_exists($path_to_root . 'sidebars.php')) include $path_to_root . 'sidebars.php';
    ?>

    <div id="mainContent">
        <?php 
        if(file_exists($path_to_root . 'header.php')) include $path_to_root . 'header.php';
        ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">My Team Overview</h1>
                <p class="text-slate-500 mt-1 text-sm">Manage and monitor the employees and team leads assigned to you.</p>
            </div>
            <div class="flex gap-3">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Search by name, email..." 
                           class="bg-white border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent w-64 transition"
                           onkeyup="filterTable()">
                    <i class="fas fa-search absolute right-3 top-3.5 text-gray-400 text-sm"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 border border-gray-100 card-shadow flex items-center gap-5">
                <div class="w-14 h-14 rounded-xl bg-teal-50 flex items-center justify-center text-teal-600">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Total Members</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $total_members; ?></h3>
                </div>
            </div>
            
            <div class="bg-white rounded-xl p-6 border border-gray-100 card-shadow flex items-center gap-5">
                <div class="w-14 h-14 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                    <i class="fas fa-building text-2xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Departments</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $unique_depts; ?></h3>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-100 card-shadow flex items-center gap-5">
                <div class="w-14 h-14 rounded-xl bg-orange-50 flex items-center justify-center text-orange-600">
                    <i class="fas fa-user-tie text-2xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Direct Reports</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $total_members; ?></h3>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 card-shadow overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Team Directory</h3>
                <span class="text-xs font-semibold text-slate-500 bg-slate-50 px-3 py-1 rounded-full border border-slate-200">
                    Showing <?php echo $total_members; ?> results
                </span>
            </div>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="custom-table" id="teamTable">
                        <thead>
                            <tr>
                                <th>Employee Info</th>
                                <th>Emp ID</th>
                                <th>Designation & Role</th>
                                <th>Contact Info</th>
                                <th>Joining Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                // Handle Image Path
                                $img = !empty($row['profile_img']) ? $row['profile_img'] : "https://ui-avatars.com/api/?name=" . urlencode($row['full_name']) . "&background=random";
                                if (!filter_var($img, FILTER_VALIDATE_URL)) { $img = '../' . $img; }
                                
                                // --- PREPARE DATA FOR MODAL ---
                                $modal_data = json_encode([
                                    'name' => $row['full_name'],
                                    'emp_id' => $row['emp_id_code'],
                                    'designation' => $row['designation'],
                                    'role' => ucfirst($row['role']),
                                    'email' => $row['email'],
                                    'phone' => $row['phone'] ?? 'Not Available',
                                    'join_date' => !empty($row['joining_date']) ? date("d M Y", strtotime($row['joining_date'])) : 'N/A',
                                    'department' => $row['department'] ?? 'Unassigned',
                                    'img' => $img
                                ]);
                            ?>
                                <tr class="searchable-row">
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="Avatar" class="avatar-sm">
                                            <div>
                                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                <div class="text-xs text-slate-500 font-medium mt-0.5"><?php echo htmlspecialchars($row['department']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded text-xs font-mono border border-slate-200">
                                            <?php echo htmlspecialchars($row['emp_id_code']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-slate-700 mb-1"><?php echo htmlspecialchars($row['designation']); ?></div>
                                        <span class="bg-teal-50 text-teal-700 px-2.5 py-1 rounded text-xs font-semibold border border-teal-100">
                                            <?php echo htmlspecialchars(ucfirst($row['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2 text-slate-600 mb-1.5 text-sm">
                                            <i class="fas fa-envelope text-slate-300 w-4"></i> 
                                            <span><?php echo htmlspecialchars($row['email']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-600 text-sm">
                                            <i class="fas fa-phone text-slate-300 w-4"></i> 
                                            <span><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-slate-600 font-medium">
                                        <?php echo !empty($row['joining_date']) ? date("d M Y", strtotime($row['joining_date'])) : 'N/A'; ?>
                                    </td>
                                    <td class="text-right">
                                        <button onclick="openProfileModal(this)" data-emp='<?php echo htmlspecialchars($modal_data, ENT_QUOTES, 'UTF-8'); ?>' class="btn-action btn-view">
                                            <i class="fas fa-eye"></i> View Profile
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-20 px-10">
                    <div class="w-20 h-20 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-5">
                        <i class="fas fa-users text-4xl text-slate-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-700 mb-2">No Team Members Found</h3>
                    <p class="text-slate-500 max-w-sm mx-auto">Currently, no employees or team leads have been assigned under your management.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div id="profileModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-[1050] backdrop-blur-sm modal-overlay">
        <div id="modalBox" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 opacity-0 modal-content">
            
            <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-address-card text-teal-600"></i> Employee Profile
                </h3>
                <button onclick="closeProfileModal()" class="text-slate-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-full transition-colors outline-none">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="flex items-center gap-5 mb-6 pb-6 border-b border-slate-100">
                    <img id="modalImg" src="" alt="Profile" class="w-20 h-20 rounded-xl object-cover border-2 border-slate-100 shadow-sm">
                    <div>
                        <h4 id="modalName" class="text-2xl font-bold text-slate-800">Name</h4>
                        <span id="modalDept" class="mt-1 inline-block px-2.5 py-1 bg-teal-50 text-teal-700 text-xs font-bold rounded border border-teal-100">
                            Department
                        </span>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1"><i class="fas fa-id-badge mr-1"></i> Employee ID</p>
                            <p id="modalEmpId" class="font-bold text-slate-700 text-sm">EMP-000</p>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1"><i class="fas fa-calendar-alt mr-1"></i> Joining Date</p>
                            <p id="modalJoinDate" class="font-bold text-slate-700 text-sm">Date</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1"><i class="fas fa-briefcase mr-1"></i> Designation</p>
                            <p id="modalDesig" class="font-bold text-slate-700 text-sm">Designation</p>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1"><i class="fas fa-shield-alt mr-1"></i> Role</p>
                            <p id="modalRole" class="font-bold text-slate-700 text-sm">Role</p>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-3">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1"><i class="fas fa-envelope mr-1"></i> Email Address</p>
                            <p id="modalEmail" class="font-bold text-slate-700 text-sm">email@example.com</p>
                        </div>
                        <div class="pt-3 border-t border-slate-200/60">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1"><i class="fas fa-phone-alt mr-1"></i> Contact Number</p>
                            <p id="modalPhone" class="font-bold text-slate-700 text-sm">+91 0000000000</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                <button onclick="closeProfileModal()" class="px-5 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition-colors font-semibold text-sm shadow-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Icons (if using Lucide)
        if(window.lucide) lucide.createIcons();

        // Client-side Search Filter
        function filterTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toLowerCase();
            table = document.getElementById("teamTable");
            if(!table) return;
            tr = table.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                // Search in the first few columns (Name, Email, ID)
                var found = false;
                var tds = tr[i].getElementsByTagName("td");
                if (tds.length > 0) {
                    for(j=0; j<tds.length; j++){
                        if (tds[j]) {
                            txtValue = tds[j].textContent || tds[j].innerText;
                            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                                found = true;
                                break;
                            }
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        }

        // ==========================================
        // MODAL JAVASCRIPT LOGIC
        // ==========================================
        const modal = document.getElementById('profileModal');
        const modalBox = document.getElementById('modalBox');

        function openProfileModal(btn) {
            // Get data encoded in the button
            const data = JSON.parse(btn.getAttribute('data-emp'));
            
            // Populate modal fields
            document.getElementById('modalImg').src = data.img;
            document.getElementById('modalName').textContent = data.name;
            document.getElementById('modalDept').textContent = data.department;
            document.getElementById('modalEmpId').textContent = data.emp_id;
            document.getElementById('modalJoinDate').textContent = data.join_date;
            document.getElementById('modalDesig').textContent = data.designation;
            document.getElementById('modalRole').textContent = data.role;
            document.getElementById('modalEmail').textContent = data.email;
            document.getElementById('modalPhone').textContent = data.phone;

            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalBox.classList.remove('scale-95', 'opacity-0');
                modalBox.classList.add('scale-100', 'opacity-100');
            }, 10); // Small delay to allow CSS transition to work
        }

        function closeProfileModal() {
            // Hide modal with animation
            modalBox.classList.remove('scale-100', 'opacity-100');
            modalBox.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300); // Matches the CSS duration-300
        }

        // Close when clicking outside the modal box
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeProfileModal();
            }
        });
    </script>

</body>
</html>