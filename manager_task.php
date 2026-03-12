<?php
// manager_task.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// 2. DB CONNECTION (Fixed Path)
require_once 'include/db_connect.php';

$current_user_id = $_SESSION['user_id'];

// 3. HANDLE FORM SUBMISSION (Add/Edit Project)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_project') {
        $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
        $leader_id = (int)$_POST['leader_id'];
        $deadline = mysqli_real_escape_string($conn, $_POST['deadline']);
        $priority = mysqli_real_escape_string($conn, $_POST['priority']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $start_date = date('Y-m-d');
        
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

        if ($edit_id > 0) {
            // Update Existing Project
            $update_sql = "UPDATE projects SET 
                            project_name = '$project_name', 
                            leader_id = $leader_id, 
                            deadline = '$deadline', 
                            priority = '$priority', 
                            description = '$description' 
                           WHERE id = $edit_id";
            mysqli_query($conn, $update_sql);
        } else {
            // Insert New Project
            $insert_sql = "INSERT INTO projects (project_name, leader_id, deadline, description, priority, created_by, start_date, status) 
                           VALUES ('$project_name', $leader_id, '$deadline', '$description', '$priority', $current_user_id, '$start_date', 'Active')";
            mysqli_query($conn, $insert_sql);
        }
        
        // Refresh page to prevent duplicate form submission
        header("Location: manager_task.php");
        exit();
    }
    
    // Handle Delete Project
    if (isset($_POST['action']) && $_POST['action'] === 'delete_project') {
        $delete_id = (int)$_POST['delete_id'];
        mysqli_query($conn, "DELETE FROM projects WHERE id = $delete_id");
        header("Location: manager_task.php");
        exit();
    }
}

// 4. FETCH ACTIVE TEAM LEADS FOR DROPDOWN
$team_leads = [];
$tl_query = "SELECT u.id, COALESCE(u.name, ep.full_name) as name 
             FROM users u 
             LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
             WHERE u.role = 'Team Lead' AND (u.name IS NOT NULL OR ep.full_name IS NOT NULL)";
$tl_result = mysqli_query($conn, $tl_query);
if ($tl_result) {
    $team_leads = mysqli_fetch_all($tl_result, MYSQLI_ASSOC);
}

// 5. FETCH ALL PROJECTS FOR THE TABLE
$projects = [];
$proj_query = "SELECT p.*, COALESCE(u.name, ep.full_name) as leader_name, ep.profile_img, ep.designation
               FROM projects p
               LEFT JOIN users u ON p.leader_id = u.id
               LEFT JOIN employee_profiles ep ON u.id = ep.user_id
               ORDER BY p.id DESC";
$proj_result = mysqli_query($conn, $proj_query);
if ($proj_result) {
    $projects = mysqli_fetch_all($proj_result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workack HRMS | Manager Task Management</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1b5a5a', 
                        primaryDark: '#144343',
                        bgLight: '#f8fafc',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        /* Sidebar Layout Fix */
        #mainContent { 
            margin-left: 95px; 
            width: calc(100% - 95px); 
            transition: margin-left 0.3s ease, width 0.3s ease;
        }
        #mainContent.main-shifted { 
            margin-left: 315px; 
            width: calc(100% - 315px); 
        }
        
        /* Modal Animation */
        .modal { transition: opacity 0.25s ease; }
        .modal-content { transition: transform 0.25s ease; }
        body.modal-open { overflow: hidden; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-track { background: transparent; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent" class="p-8 min-h-screen">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Project Management</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                    <span class="hover:text-primary cursor-pointer">Manager</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-primary font-medium">Master Task Distribution</span>
                </nav>
            </div>
            <button onclick="prepareAddModal()" class="bg-primary hover:bg-primaryDark text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-teal-900/10 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Assign New Project
            </button>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-slate-700">Global Task Overview</h3>
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="searchInput" placeholder="Search tasks..." class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary w-64 transition-colors">
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="taskTable">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase text-gray-500 font-semibold border-b border-gray-100">
                            <th class="px-6 py-4">Task Title</th>
                            <th class="px-6 py-4">Assigned To</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Deadline</th>
                            <th class="px-6 py-4">Priority</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">No projects or tasks have been assigned yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($projects as $proj): 
                                // Determine priority color
                                $priorityColor = 'bg-orange-50 text-orange-600 border-orange-100'; // Default Medium
                                if ($proj['priority'] === 'High') $priorityColor = 'bg-red-50 text-red-600 border-red-100';
                                if ($proj['priority'] === 'Low') $priorityColor = 'bg-green-50 text-green-600 border-green-100';
                                
                                // Format Image safely
                                $imgSource = (!empty($proj['profile_img']) && $proj['profile_img'] !== 'default_user.png') ? $proj['profile_img'] : 'https://ui-avatars.com/api/?name='.urlencode($proj['leader_name']).'&background=random';
                                if (!str_starts_with($imgSource, 'http') && strpos($imgSource, 'assets/profiles/') === false) {
                                    $imgSource = 'assets/profiles/' . $imgSource;
                                }

                                // Package data securely for JS editing
                                $projData = htmlspecialchars(json_encode([
                                    'id' => $proj['id'],
                                    'title' => $proj['project_name'],
                                    'leader_id' => $proj['leader_id'],
                                    'deadline' => $proj['deadline'],
                                    'priority' => $proj['priority'],
                                    'description' => $proj['description']
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <td class="px-6 py-4 font-semibold text-slate-700 search-title"><?= htmlspecialchars($proj['project_name']) ?></td>
                                <td class="px-6 py-4 text-slate-600 flex items-center gap-3">
                                    <img src="<?= $imgSource ?>" class="w-8 h-8 rounded-full object-cover shadow-sm border border-gray-200">
                                    <?= htmlspecialchars($proj['leader_name'] ?? 'Unassigned') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-600 border border-blue-100">Team Lead</span>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><i class="fa-regular fa-calendar mr-1"></i> <?= date('d M Y', strtotime($proj['deadline'])) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $priorityColor ?>"><?= htmlspecialchars($proj['priority']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" onclick='editTask(<?= $projData ?>)' class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-primary hover:bg-teal-50 transition-all"><i class="fas fa-edit"></i></button>
                                        
                                        <button type="button" onclick="deleteTask(<?= $proj['id'] ?>)" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_project">
        <input type="hidden" name="delete_id" id="delete_id" value="">
    </form>

    <div id="addMasterTaskModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl transform scale-95 transition-transform duration-300 overflow-hidden" id="modalPanel">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800" id="modalHeading">Assign Master Task</h3>
                <button type="button" onclick="closeModal('addMasterTaskModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="taskForm" method="POST" action="manager_task.php" class="p-6">
                <input type="hidden" name="action" value="save_project">
                <input type="hidden" name="edit_id" id="editRowId" value="0">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Project / Task Title <span class="text-red-500">*</span></label>
                        <input type="text" name="project_name" id="taskTitle" placeholder="e.g. Integrate Payment Gateway" required
                            class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all placeholder:text-gray-400">
                    </div>

                    <div class="grid grid-cols-1 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Assign To (Team Lead) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select name="leader_id" id="assignedId" required class="w-full pl-4 pr-10 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all">
                                    <option value="">Choose Team Lead</option>
                                    <?php foreach ($team_leads as $tl): ?>
                                        <option value="<?= $tl['id'] ?>"><?= htmlspecialchars($tl['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-user absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Deadline</label>
                            <input type="date" name="deadline" id="deadline" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Priority</label>
                            <div class="flex gap-2">
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="priority" value="Low" id="prio_Low" class="peer sr-only">
                                    <div class="text-center py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-500 peer-checked:bg-green-50 peer-checked:text-green-600 peer-checked:border-green-200 transition-all">Low</div>
                                </label>
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="priority" value="Medium" id="prio_Medium" class="peer sr-only" checked>
                                    <div class="text-center py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 peer-checked:border-orange-200 transition-all">Med</div>
                                </label>
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="priority" value="High" id="prio_High" class="peer sr-only">
                                    <div class="text-center py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-500 peer-checked:bg-red-50 peer-checked:text-red-600 peer-checked:border-red-200 transition-all">High</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Instructions</label>
                        <textarea name="description" id="description" rows="3" placeholder="Provide detailed instructions..." 
                            class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-gray-50">
                    <button type="button" onclick="closeModal('addMasterTaskModal')" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button type="submit" class="bg-primary hover:bg-primaryDark text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-teal-900/20 transition-all transform active:scale-95">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { 
            const modal = document.getElementById(id);
            const panel = modal.querySelector('#modalPanel');
            modal.classList.remove('hidden');
            setTimeout(() => {
                panel.classList.remove('scale-95');
                panel.classList.add('scale-100');
            }, 10);
            document.body.classList.add('modal-open');
        }

        function closeModal(id) { 
            const modal = document.getElementById(id);
            const panel = modal.querySelector('#modalPanel');
            panel.classList.remove('scale-100');
            panel.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }, 200);
        }

        // Prepare Modal for Adding New Project
        function prepareAddModal() {
            document.getElementById('taskForm').reset();
            document.getElementById('editRowId').value = "0";
            document.getElementById('modalHeading').innerText = "Assign Master Task";
            // Reset priority UI
            document.getElementById('prio_Medium').checked = true;
            openModal('addMasterTaskModal');
        }

        // Prepare Modal for Editing Existing Project
        function editTask(data) {
            document.getElementById('editRowId').value = data.id;
            document.getElementById('taskTitle').value = data.title;
            document.getElementById('assignedId').value = data.leader_id;
            document.getElementById('deadline').value = data.deadline;
            document.getElementById('description').value = data.description;
            
            // Set priority radio button
            if (data.priority === 'Low') document.getElementById('prio_Low').checked = true;
            else if (data.priority === 'High') document.getElementById('prio_High').checked = true;
            else document.getElementById('prio_Medium').checked = true;

            document.getElementById('modalHeading').innerText = "Edit Assigned Task";
            openModal('addMasterTaskModal');
        }

        // Delete Project Logic
        function deleteTask(id) {
            if(confirm("Are you sure you want to delete this assigned task?")) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close Modal on Outside Click
        window.onclick = function(event) {
            const modal = document.getElementById('addMasterTaskModal');
            if (event.target === modal) { closeModal('addMasterTaskModal'); }
        }

        // Simple Search Filtering
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toUpperCase();
            let rows = document.querySelector("#taskTable tbody").rows;
            for (let i = 0; i < rows.length; i++) {
                let titleCell = rows[i].querySelector('.search-title');
                if (titleCell) {
                    let text = titleCell.textContent.toUpperCase();
                    rows[i].style.display = text.includes(filter) ? "" : "none";
                }
            }
        });
    </script>
</body>
</html>