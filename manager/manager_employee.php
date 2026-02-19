<?php 
// manager/team_overview.php

// 1. SESSION & INCLUDES
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

require_once '../include/db_connect.php';
$manager_id = $_SESSION['user_id'];

// 2. HANDLE FORM SUBMISSION (Create or Edit Team)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_team') {
    $team_name = mysqli_real_escape_string($conn, $_POST['team_name']);
    $tl_id = (int)$_POST['team_lead_id'];
    $members = $_POST['members'] ?? [];
    $old_tl_id = isset($_POST['old_tl_id']) ? (int)$_POST['old_tl_id'] : 0;

    // IF EDITING: First clear the old team associations 
    if ($old_tl_id > 0) {
        // Clear reporting_to for old members
        $stmt_clear = $conn->prepare("UPDATE employee_profiles SET department = NULL, reporting_to = NULL WHERE reporting_to = ?");
        $stmt_clear->bind_param("i", $old_tl_id);
        $stmt_clear->execute();
        $stmt_clear->close();

        // Clear old Team Lead's department
        $stmt_clear_tl = $conn->prepare("UPDATE employee_profiles SET department = NULL WHERE user_id = ?");
        $stmt_clear_tl->bind_param("i", $old_tl_id);
        $stmt_clear_tl->execute();
        $stmt_clear_tl->close();
    }

    // A. Update the chosen Team Lead's profile (Set department and manager)
    $stmt_tl = $conn->prepare("UPDATE employee_profiles SET department = ?, manager_id = ? WHERE user_id = ?");
    $stmt_tl->bind_param("sii", $team_name, $manager_id, $tl_id);
    $stmt_tl->execute();
    $stmt_tl->close();

    // B. Update the selected Members (Assign them to the TL and Department)
    if (!empty($members)) {
        foreach($members as $emp_id) {
            $emp_id = (int)$emp_id;
            $stmt_m = $conn->prepare("UPDATE employee_profiles SET department = ?, reporting_to = ?, manager_id = ? WHERE user_id = ?");
            $stmt_m->bind_param("siii", $team_name, $tl_id, $manager_id, $emp_id);
            $stmt_m->execute();
            $stmt_m->close();
        }
    }
    
    // Set success message based on action
    $msg = ($old_tl_id > 0) ? "Team '{$team_name}' updated successfully!" : "Team '{$team_name}' created successfully!";
    $_SESSION['toast'] = $msg;
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 3. FETCH DATA FOR THE MODAL
$available_tls = [];
$tl_res = mysqli_query($conn, "SELECT u.id, COALESCE(ep.full_name, u.name) as name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.role = 'Team Lead' AND (u.name IS NOT NULL OR ep.full_name IS NOT NULL)");
while($row = mysqli_fetch_assoc($tl_res)) { $available_tls[] = $row; }

$available_emps = [];
$emp_res = mysqli_query($conn, "SELECT u.id, COALESCE(ep.full_name, u.name) as name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.role = 'Employee' AND (u.name IS NOT NULL OR ep.full_name IS NOT NULL)");
while($row = mysqli_fetch_assoc($emp_res)) { $available_emps[] = $row; }

// 4. FETCH CURRENT TEAMS FOR THE CARDS
$departments = [];
$teams_res = mysqli_query($conn, "
    SELECT u.id as tl_id, 
           COALESCE(ep.full_name, u.name) as lead_name, 
           ep.designation, 
           ep.department, 
           ep.profile_img 
    FROM users u 
    JOIN employee_profiles ep ON u.id = ep.user_id 
    WHERE u.role = 'Team Lead' AND ep.full_name IS NOT NULL
");

while($tl = mysqli_fetch_assoc($teams_res)) {
    $current_tl_id = $tl['tl_id'];
    
    // Fetch members reporting to this specific TL
    $m_res = mysqli_query($conn, "SELECT user_id, full_name FROM employee_profiles WHERE reporting_to = $current_tl_id");
    $members = [];
    $member_ids = [];
    while($m = mysqli_fetch_assoc($m_res)) {
        if(!empty(trim($m['full_name']))) {
            $members[] = $m['full_name'];
            $member_ids[] = $m['user_id'];
        }
    }
    
    $departments[] = [
        "name" => !empty($tl['department']) ? $tl['department'] : 'Unassigned Team',
        "lead" => $tl['lead_name'],
        "lead_id" => $current_tl_id,
        "role" => !empty($tl['designation']) ? $tl['designation'] : 'Team Lead',
        "img" => !empty($tl['profile_img']) ? $tl['profile_img'] : 'default_user.png',
        "members" => $members,
        "member_ids" => $member_ids
    ];
}

// Handle Toast session
$toast_msg = '';
if (isset($_SESSION['toast'])) {
    $toast_msg = $_SESSION['toast'];
    unset($_SESSION['toast']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Overview | Workack</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1b5a5a',
                        primaryHover: '#144343',
                        bgLight: '#f8fafc',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        body { background-color: #f1f5f9; color: #1e293b; overflow-x: hidden;}

        /* Sidebar Layout Fix */
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 32px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Card Hover */
        .team-card { transition: all 0.3s ease; }
        .team-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }

        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Modal fixes */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; }
        
        .modal-box { 
            background: white; 
            width: 550px; 
            max-width: 100%; 
            border-radius: 12px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
            max-height: 90vh; 
            display: flex;
            flex-direction: column;
        }
        .modal-content-scroll {
            overflow-y: auto;
            padding: 24px;
        }

        /* Checkbox Style */
        .checkbox-wrapper input:checked + div { background-color: #f0fdfa; border-color: #1b5a5a; color: #1b5a5a; }
        .checkbox-wrapper input:checked + div .check-icon { display: block; }
        
        /* Toast */
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 12px; position: fixed; z-index: 10000; left: 50%; bottom: 30px; transform: translateX(-50%); opacity: 0; transition: opacity 0.5s, bottom 0.5s; }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.success { background-color: #1b5a5a; }

        /* Responsive Details */
        @media (max-width: 1024px) {
            #mainContent { margin-left: 0; width: 100%; padding: 20px; }
        }
    </style>
</head>
<body class="bg-slate-100">

    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>

    <main id="mainContent">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Manager Overview</h1>
                <p class="text-sm text-gray-500 mt-1">Manage team structures and resource allocation.</p>
            </div>
            <div class="flex gap-3 w-full md:w-auto">
                <button onclick="toggleFilter()" class="flex-1 md:flex-none justify-center bg-white border border-gray-200 px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm hover:shadow-md transition text-slate-600">
                    <i class="fa-solid fa-filter text-primary"></i> Filter
                </button>
                <button onclick="prepareAddModal()" class="flex-1 md:flex-none justify-center bg-primary hover:bg-primaryHover text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg shadow-teal-900/10 transition transform active:scale-95">
                    <i class="fa-solid fa-plus"></i> Add Team
                </button>
            </div>
        </div>

        <div id="filterBar" class="hidden mb-6 bg-white p-4 rounded-xl border border-gray-200 shadow-sm transition-all">
            <input type="text" id="searchInput" onkeyup="filterTeams()" placeholder="Search by Team Name or Lead..." class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
        </div>

        <div id="teamGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            
            <?php if (empty($departments)): ?>
                <div class="col-span-full bg-white p-10 rounded-2xl border border-gray-200 text-center text-gray-500 shadow-sm">
                    <i class="fa-solid fa-users-slash text-4xl text-gray-300 mb-3"></i>
                    <p>No teams have been structured yet. Click "Add Team" to begin.</p>
                </div>
            <?php else: ?>
                <?php foreach ($departments as $data): 
                    // Format Profile Image
                    $imgSource = $data['img'];
                    if(!$imgSource || $imgSource === 'default_user.png') {
                        $imgSource = "https://ui-avatars.com/api/?name=".urlencode($data['lead'])."&background=1b5a5a&color=fff";
                    } elseif (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
                        $imgSource = '../assets/profiles/' . $imgSource;
                    }
                    
                    // Securely encode team data for the edit function
                    $teamData = htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="team-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                        <div class="bg-primary p-6 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-white text-xl backdrop-blur-sm shrink-0">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h3 class="text-white font-bold text-lg leading-tight team-name truncate"><?php echo htmlspecialchars($data['name']); ?></h3>
                                <p class="text-teal-100/80 text-xs mt-1"><?php echo count($data['members']); ?> Members</p>
                            </div>
                        </div>

                        <div class="p-6 flex-grow flex flex-col">
                            <div class="mb-5">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Team Lead</label>
                                <div class="flex items-center mt-2 p-3 bg-slate-50 rounded-xl border border-gray-100 group cursor-pointer hover:border-primary/30 transition-colors">
                                    <img src="<?php echo $imgSource; ?>" class="w-10 h-10 rounded-full border-2 border-white shadow-sm object-cover">
                                    <div class="ml-3 overflow-hidden">
                                        <p class="font-bold text-slate-800 text-sm lead-name truncate"><?php echo htmlspecialchars($data['lead']); ?></p>
                                        <p class="text-xs text-primary font-medium truncate"><?php echo htmlspecialchars($data['role']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-grow">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Members</label>
                                <div class="mt-2 space-y-2 max-h-40 overflow-y-auto custom-scrollbar pr-1">
                                    <?php if(empty($data['members'])): ?>
                                        <p class="text-xs text-gray-400 italic">No members assigned yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($data['members'] as $member): ?>
                                            <div class="flex items-center p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                                <div class="w-2 h-2 rounded-full bg-emerald-400 mr-3 shrink-0"></div>
                                                <span class="text-slate-600 text-sm font-medium truncate"><?php echo htmlspecialchars($member); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-slate-50/50 border-t border-gray-100 flex justify-between items-center">
                            <span class="text-xs font-medium text-gray-500">Total Size: <span class="text-primary font-bold text-sm"><?php echo count($data['members']) + 1; ?></span></span>
                            <button onclick='editTeam(<?php echo $teamData; ?>)' class="text-xs font-bold text-primary hover:underline"><i class="fa-solid fa-pen-to-square mr-1"></i> Edit</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

    <div id="addTeamModal" class="modal-overlay">
        <div class="modal-box transform scale-95 transition-transform duration-300" id="modalPanel">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-12 shrink-0">
                <h3 class="font-bold text-lg text-slate-800" id="modalHeading">Create New Team</h3>
                <button onclick="closeModal('addTeamModal')" class="text-gray-400 hover:text-red-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm border border-gray-100">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-content-scroll">
                <form id="addTeamForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-5">
                    <input type="hidden" name="action" value="create_team">
                    <input type="hidden" name="old_tl_id" id="old_tl_id" value="0">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Team Name <span class="text-red-500">*</span></label>
                        <input type="text" name="team_name" id="team_name" required placeholder="e.g. QA & Testing" class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Select Team Lead <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="team_lead_id" id="team_lead_id" required class="w-full pl-4 pr-10 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                <option value="">Choose a Team Lead</option>
                                <?php foreach($available_tls as $tl): ?>
                                    <option value="<?php echo $tl['id']; ?>"><?php echo htmlspecialchars($tl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-user-tie absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Select Members (Employees)</label>
                        <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-2 custom-scrollbar space-y-1 bg-slate-50/30">
                            <?php if(empty($available_emps)): ?>
                                <p class="text-xs text-gray-400 italic p-2">No employees available.</p>
                            <?php else: ?>
                                <?php foreach($available_emps as $emp): ?>
                                    <label class="checkbox-wrapper flex cursor-pointer">
                                        <input type="checkbox" name="members[]" id="member_<?php echo $emp['id']; ?>" value="<?php echo $emp['id']; ?>" class="peer sr-only">
                                        <div class="w-full p-2.5 rounded-md border border-transparent hover:bg-slate-50 peer-checked:bg-teal-50 peer-checked:text-primary transition flex items-center bg-white shadow-sm mb-1">
                                            <div class="w-4 h-4 border border-gray-300 rounded mr-3 flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary shrink-0">
                                                <i class="fa-solid fa-check text-white text-[10px] hidden check-icon"></i>
                                            </div>
                                            <span class="text-sm font-medium"><?php echo htmlspecialchars($emp['name']); ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-6">
                        <button type="button" onclick="closeModal('addTeamModal')" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                        <button type="submit" id="submitBtnText" class="bg-primary hover:bg-primaryHover text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-teal-900/20 transition-all transform active:scale-95">Save & Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="toast"></div>

    <script>
        // --- Modal Logic ---
        function openModal(id) {
            const modal = document.getElementById(id);
            const panel = modal.querySelector('#modalPanel');
            modal.classList.add('active');
            setTimeout(() => { panel.classList.remove('scale-95'); panel.classList.add('scale-100'); }, 10);
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            const panel = modal.querySelector('#modalPanel');
            panel.classList.remove('scale-100'); panel.classList.add('scale-95');
            setTimeout(() => { modal.classList.remove('active'); }, 200);
        }

        // --- Prepare Modal for ADD ---
        function prepareAddModal() {
            document.getElementById('addTeamForm').reset();
            document.getElementById('old_tl_id').value = "0";
            document.getElementById('modalHeading').innerText = "Create New Team";
            document.getElementById('submitBtnText').innerText = "Save & Create";
            openModal('addTeamModal');
        }

        // --- Prepare Modal for EDIT ---
        function editTeam(teamData) {
            // Reset form first to clear checkboxes
            document.getElementById('addTeamForm').reset();

            // Set UI Texts
            document.getElementById('modalHeading').innerText = "Edit Team Details";
            document.getElementById('submitBtnText').innerText = "Update Team";
            
            // Populate values
            document.getElementById('old_tl_id').value = teamData.lead_id;
            document.getElementById('team_name').value = teamData.name;
            document.getElementById('team_lead_id').value = teamData.lead_id;
            
            // Check existing members
            if (teamData.member_ids && teamData.member_ids.length > 0) {
                teamData.member_ids.forEach(id => {
                    const checkbox = document.getElementById('member_' + id);
                    if(checkbox) checkbox.checked = true;
                });
            }
            
            openModal('addTeamModal');
        }

        // --- Filter Logic ---
        function toggleFilter() {
            const filterBar = document.getElementById('filterBar');
            filterBar.classList.toggle('hidden');
            if(!filterBar.classList.contains('hidden')) {
                document.getElementById('searchInput').focus();
            }
        }

        function filterTeams() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.team-card');

            cards.forEach(card => {
                const name = card.querySelector('.team-name').innerText.toLowerCase();
                const lead = card.querySelector('.lead-name').innerText.toLowerCase();
                
                if (name.includes(input) || lead.includes(input)) {
                    card.style.display = "flex";
                } else {
                    card.style.display = "none";
                }
            });
        }

        // --- Toast Notification Logic ---
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            toast.innerText = message;
            toast.className = "show " + type;
            setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3500);
        }

        // Trigger toast if session variable was set in PHP
        const toastMessage = "<?php echo $toast_msg; ?>";
        if (toastMessage) {
            showToast(toastMessage, "success");
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('addTeamModal');
            if (event.target === modal) { closeModal('addTeamModal'); }
        }
    </script>
</body>
</html>