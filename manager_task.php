<?php
// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
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
                        primary: '#1b5a5a', // Your Custom Color
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
    <?php include 'header.php'; ?>

    <div id="mainContent" class="p-8 min-h-screen">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Task Management</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                    <span class="hover:text-primary cursor-pointer">Manager</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-primary font-medium">Master Task Distribution</span>
                </nav>
            </div>
            <button onclick="prepareAddModal()" class="bg-primary hover:bg-primaryDark text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-teal-900/10 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Assign New Task
            </button>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-slate-700">Global Task Overview</h3>
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" placeholder="Search tasks..." class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary w-64 transition-colors">
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
                        <tr id="row-1" class="hover:bg-slate-50/80 transition-colors group">
                            <td class="px-6 py-4 font-semibold text-slate-700 t-title">Workack HRMS API Integration</td>
                            <td class="px-6 py-4 text-slate-600 flex items-center gap-2 t-person">
                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">R</div>
                                Ragul Kumar (TL)
                            </td>
                            <td class="px-6 py-4 t-cat">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-600 border border-blue-100">Team Lead</span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 t-date"><i class="fa-regular fa-calendar mr-1"></i> 15 Feb 2026</td>
                            <td class="px-6 py-4 t-priority">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-600 border border-red-100">High</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onclick="editTask('row-1')" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-primary hover:bg-teal-50 transition-all"><i class="fas fa-edit"></i></button>
                                    <button onclick="deleteTask('row-1')" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr id="row-2" class="hover:bg-slate-50/80 transition-colors group">
                            <td class="px-6 py-4 font-semibold text-slate-700 t-title">Monthly Payroll Review</td>
                            <td class="px-6 py-4 text-slate-600 flex items-center gap-2 t-person">
                                <div class="w-6 h-6 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xs font-bold">V</div>
                                Vasanth (HR)
                            </td>
                            <td class="px-6 py-4 t-cat">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-50 text-purple-600 border border-purple-100">HR Admin</span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 t-date"><i class="fa-regular fa-calendar mr-1"></i> 10 Feb 2026</td>
                            <td class="px-6 py-4 t-priority">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-orange-50 text-orange-600 border border-orange-100">Medium</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onclick="editTask('row-2')" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-primary hover:bg-teal-50 transition-all"><i class="fas fa-edit"></i></button>
                                    <button onclick="deleteTask('row-2')" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addMasterTaskModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl transform scale-95 transition-transform duration-300 overflow-hidden" id="modalPanel">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800" id="modalHeading">Assign Master Task</h3>
                <button onclick="closeModal('addMasterTaskModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="taskForm" class="p-6">
                <input type="hidden" id="editRowId">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Task Title <span class="text-red-500">*</span></label>
                        <input type="text" id="taskTitle" placeholder="e.g. Integrate Payment Gateway" required
                            class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all placeholder:text-gray-400">
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Department Level</label>
                            <div class="relative">
                                <select id="userRole" required class="w-full pl-4 pr-10 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all">
                                    <option value="">Select Level</option>
                                    <option value="tl">Team Lead (TL)</option>
                                    <option value="hr">Human Resource (HR)</option>
                                    <option value="emp">Direct Employee</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Assign To <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select id="assignedId" required class="w-full pl-4 pr-10 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all">
                                    <option value="">Choose Person</option>
                                    <option value="Ragul Kumar (TL)">Ragul Kumar</option>
                                    <option value="Vasanth (HR)">Vasanth</option>
                                    <option value="Suresh Babu (Employee)">Suresh Babu</option>
                                </select>
                                <i class="fa-solid fa-user absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Deadline</label>
                            <input type="date" id="deadline" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Priority</label>
                            <div class="flex gap-2">
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="prio" value="Low" class="peer sr-only">
                                    <div class="text-center py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-500 peer-checked:bg-green-50 peer-checked:text-green-600 peer-checked:border-green-200 transition-all">Low</div>
                                </label>
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="prio" value="Medium" class="peer sr-only" checked>
                                    <div class="text-center py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 peer-checked:border-orange-200 transition-all">Med</div>
                                </label>
                                <label class="cursor-pointer flex-1">
                                    <input type="radio" name="prio" value="High" class="peer sr-only">
                                    <div class="text-center py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-500 peer-checked:bg-red-50 peer-checked:text-red-600 peer-checked:border-red-200 transition-all">High</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Instructions</label>
                        <textarea id="description" rows="3" placeholder="Provide detailed instructions..." 
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
            // Small animation delay
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

        // Prepare Modal for Adding
        function prepareAddModal() {
            document.getElementById('taskForm').reset();
            document.getElementById('editRowId').value = "";
            document.getElementById('modalHeading').innerText = "Assign Master Task";
            openModal('addMasterTaskModal');
        }

        // EDIT Logic
        function editTask(rowId) {
            const title = document.querySelector(`#${rowId} .t-title`).innerText;
            // Simplified logic for demo (fetching raw text)
            const person = document.querySelector(`#${rowId} .t-person`).innerText.trim(); 
            
            document.getElementById('editRowId').value = rowId;
            document.getElementById('taskTitle').value = title;
            // Note: In real app, you match IDs, here we just match text for demo
            // document.getElementById('assignedId').value = person; 
            document.getElementById('modalHeading').innerText = "Edit Task";
            
            openModal('addMasterTaskModal');
        }

        // DELETE Logic
        function deleteTask(rowId) {
            if(confirm("Are you sure you want to delete this task?")) {
                const row = document.getElementById(rowId);
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
        }

        // Form Submit Simulation
        document.getElementById('taskForm').onsubmit = function(e) {
            e.preventDefault();
            const rowId = document.getElementById('editRowId').value;
            const title = document.getElementById('taskTitle').value;
            const person = document.getElementById('assignedId').value;
            
            if(rowId) {
                // Update Row Visuals
                document.querySelector(`#${rowId} .t-title`).innerText = title;
                // document.querySelector(`#${rowId} .t-person`).innerText = person;
                alert("Task updated successfully!");
            } else {
                alert("New task created successfully!");
            }
            closeModal('addMasterTaskModal');
        };

        // Close on Outside Click
        window.onclick = function(event) {
            const modal = document.getElementById('addMasterTaskModal');
            if (event.target === modal) { closeModal('addMasterTaskModal'); }
        }
    </script>
</body>
</html>