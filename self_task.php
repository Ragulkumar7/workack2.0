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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | My Tasks</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1b5a5a', // Your Custom Theme Color
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
        
        /* Modal Transitions */
        .modal { transition: opacity 0.25s ease; }
        .modal-content { transition: transform 0.25s ease; }
        body.modal-open { overflow: hidden; }
        
        /* Custom Scrollbar for columns */
        .task-col-scroll::-webkit-scrollbar { width: 4px; }
        .task-col-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <?php include('sidebars.php'); ?>
    <?php include 'header.php'; ?>

    <div id="mainContent" class="p-8 min-h-screen">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">My Tasks</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                    <span class="hover:text-primary cursor-pointer">Dashboard</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-primary font-medium">Personal Task Board</span>
                </nav>
            </div>
            <button onclick="openModal('addTaskModal')" class="bg-primary hover:bg-primaryDark text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-teal-900/10 transition-all flex items-center gap-2 transform active:scale-95">
                <i class="fas fa-plus"></i> New Task
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start h-[calc(100vh-180px)]">
            
            <div class="bg-slate-100/80 rounded-2xl p-4 h-full flex flex-col border border-slate-200/60" id="todo-col">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-slate-700 uppercase text-xs tracking-wider flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span> To Do
                    </h3>
                    <span class="bg-white text-slate-600 px-2.5 py-0.5 rounded-md text-xs font-bold border border-slate-200 shadow-sm" id="todo-count">1</span>
                </div>
                
                <div class="overflow-y-auto flex-1 task-col-scroll pr-1 space-y-3">
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-primary/50 transition-all group cursor-pointer" id="task-1">
                        <div class="flex justify-between items-start mb-2">
                            <span class="px-2 py-1 rounded text-[10px] font-bold bg-red-50 text-red-600 border border-red-100 uppercase tracking-wide">High</span>
                            <button class="text-gray-300 hover:text-primary"><i class="fa-solid fa-ellipsis"></i></button>
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm mb-1 leading-snug">Complete HRMS Dashboard UI</h4>
                        <p class="text-xs text-gray-500 line-clamp-2 mb-3">Finish the announcement view and self-task page integration for all user levels.</p>
                        
                        <div class="flex justify-between items-center pt-3 border-t border-gray-50">
                            <div class="text-[11px] text-gray-400 font-medium flex items-center gap-1.5">
                                <i class="far fa-calendar-alt text-primary"></i> 06 Feb 2026
                            </div>
                        </div>
                        
                        <div class="mt-3 pt-2 task-actions">
                            <button class="w-full py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-primary hover:text-white hover:border-primary transition-colors" onclick="updateTaskStatus('task-1', 'inprogress-col')">
                                Start Work
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-100/80 rounded-2xl p-4 h-full flex flex-col border border-slate-200/60" id="inprogress-col">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-blue-700 uppercase text-xs tracking-wider flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span> In Progress
                    </h3>
                    <span class="bg-white text-blue-600 px-2.5 py-0.5 rounded-md text-xs font-bold border border-blue-100 shadow-sm" id="inprogress-count">1</span>
                </div>
                
                <div class="overflow-y-auto flex-1 task-col-scroll pr-1 space-y-3">
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-primary/50 transition-all group cursor-pointer" id="task-2">
                        <div class="flex justify-between items-start mb-2">
                            <span class="px-2 py-1 rounded text-[10px] font-bold bg-orange-50 text-orange-600 border border-orange-100 uppercase tracking-wide">Medium</span>
                            <button class="text-gray-300 hover:text-primary"><i class="fa-solid fa-ellipsis"></i></button>
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm mb-1 leading-snug">Database Schema Setup</h4>
                        <p class="text-xs text-gray-500 line-clamp-2 mb-3">Create tables for candidates, roles, and system announcements.</p>
                        
                        <div class="flex justify-between items-center pt-3 border-t border-gray-50">
                            <div class="text-[11px] text-gray-400 font-medium flex items-center gap-1.5">
                                <i class="far fa-calendar-alt text-primary"></i> 07 Feb 2026
                            </div>
                        </div>

                        <div class="mt-3 pt-2 task-actions">
                            <button class="w-full py-1.5 rounded-lg border border-green-200 bg-green-50 text-xs font-semibold text-green-700 hover:bg-green-600 hover:text-white hover:border-green-600 transition-colors" onclick="updateTaskStatus('task-2', 'completed-col')">
                                Mark Finished
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-100/80 rounded-2xl p-4 h-full flex flex-col border border-slate-200/60" id="completed-col">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-green-700 uppercase text-xs tracking-wider flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span> Completed
                    </h3>
                    <span class="bg-white text-green-600 px-2.5 py-0.5 rounded-md text-xs font-bold border border-green-100 shadow-sm" id="completed-count">0</span>
                </div>
                
                <div class="overflow-y-auto flex-1 task-col-scroll pr-1 space-y-3">
                    </div>
            </div>

        </div>
    </div>

    <div id="addTaskModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300 overflow-hidden" id="modalPanel">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800">Create New Task</h3>
                <button onclick="closeModal('addTaskModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Task Title <span class="text-red-500">*</span></label>
                    <input type="text" placeholder="e.g., Update PHP Mailer" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Priority</label>
                        <select class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Due Date</label>
                        <input type="date" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Description</label>
                    <textarea rows="3" placeholder="Task details..." class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white transition-all resize-none"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('addTaskModal')" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
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
        
        function updateTaskStatus(taskId, targetColId) {
            const taskCard = document.getElementById(taskId);
            const targetCol = document.getElementById(targetColId).querySelector('.overflow-y-auto'); // Find the scroll container
            const actionContainer = taskCard.querySelector('.task-actions');

            // Move the card
            targetCol.appendChild(taskCard);

            // Update buttons based on the new column
            if (targetColId === 'inprogress-col') {
                actionContainer.innerHTML = `<button class="w-full py-1.5 rounded-lg border border-green-200 bg-green-50 text-xs font-semibold text-green-700 hover:bg-green-600 hover:text-white hover:border-green-600 transition-colors" onclick="updateTaskStatus('${taskId}', 'completed-col')">Mark Finished</button>`;
            } else if (targetColId === 'completed-col') {
                actionContainer.innerHTML = `<div class="text-center py-1.5 text-green-600 text-xs font-bold bg-green-50 rounded-lg border border-green-100"><i class="fas fa-check-circle mr-1"></i> Completed</div>`;
                taskCard.classList.add('opacity-75');
            }

            // Simple count update
            updateCounts();
        }

        function updateCounts() {
            document.getElementById('todo-count').innerText = document.getElementById('todo-col').querySelectorAll('.group').length;
            document.getElementById('inprogress-count').innerText = document.getElementById('inprogress-col').querySelectorAll('.group').length;
            document.getElementById('completed-count').innerText = document.getElementById('completed-col').querySelectorAll('.group').length;
        }

        window.onclick = function(event) { 
            const modal = document.getElementById('addTaskModal');
            if (event.target === modal) { closeModal('addTaskModal'); } 
        }
    </script>
</body>
</html>