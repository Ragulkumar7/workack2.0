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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-light: #f7f7f7;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #333333;
            --text-muted: #666666;
            --border-light: #e3e3e3;
        }

        /* BODY LAYOUT FIX: display:flex ஐ எடுத்துவிட்டு block ஆக வைக்கிறோம், அப்போதான் margin வேலை செய்யும் */
        body { 
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden; 
            display: block; 
        }
        
        /* --- SIDEBAR INTEGRATION CSS (OVERLAP FIX) --- */
        #mainContent { 
            margin-left: 95px; /* Sidebar-க்கு இடம் ஒதுக்குகிறோம் */
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
            box-sizing: border-box;
        }
        /* Sidebar விரியும் போது Content நகர (Shift ஆக) இது உதவும் */
        #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }
        /* ----------------------------------------------- */
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        /* Task Board Layout */
        .task-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; align-items: start; }
        .task-column { background: #f1f3f5; border-radius: 12px; padding: 20px; min-height: 80vh; border: 1px solid var(--border-light); }
        .column-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
        .column-header h3 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; }
        .task-count { background: var(--white); color: var(--primary-orange); padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid var(--border-light); }

        /* Task Cards */
        .task-card { background: var(--white); padding: 18px; border-radius: 10px; border: 1px solid var(--border-light); margin-bottom: 15px; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .task-card:hover { border-color: var(--primary-orange); transform: translateY(-2px); }

        .priority-label { font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 4px; margin-bottom: 12px; display: inline-block; }
        .high { background: #ffe5e5; color: #ff5b37; }
        .medium { background: #fff4e5; color: #ff9b44; }
        .low { background: #e5f9ed; color: #28c76f; }

        .task-title { font-size: 15px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark); }
        .task-desc { font-size: 12.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 15px; }
        
        .task-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #f9f9f9; }
        .task-info { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        .task-info i { margin-right: 5px; color: var(--primary-orange); }

        /* Action Buttons */
        .task-actions { display: flex; gap: 8px; margin-top: 15px; padding-top: 12px; border-top: 1px solid #f0f0f0; }
        .btn-status { flex: 1; padding: 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid var(--border-light); background: #fff; cursor: pointer; transition: 0.2s; }
        .btn-status:hover { background: #f9f9f9; border-color: var(--primary-orange); color: var(--primary-orange); }
        .btn-finish { background: #e5f9ed; color: #28c76f; border-color: #28c76f; }
        .btn-finish:hover { background: #28c76f; color: #fff; }

        .btn-add { background: var(--primary-orange); color: white; padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-add:hover { background: #e54e2d; box-shadow: 0 4px 10px rgba(255, 91, 55, 0.3); }

        /* Modal Styling */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 500px; }
        .input-group { margin-bottom: 15px; }
        label { display: block; font-size: 13px; margin-bottom: 5px; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border-light); border-radius: 6px; font-family: inherit; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        <div class="page-header">
            <div>
                <h1>My Tasks</h1>
                <div class="breadcrumb">Dashboard / HRM / My Tasks</div>
            </div>
            <button class="btn-add" onclick="openModal('addTaskModal')">
                <i class="fas fa-plus"></i> New Task
            </button>
        </div>

        <div class="task-board">
            <div class="task-column" id="todo-col">
                <div class="column-header">
                    <h3>To Do</h3>
                    <span class="task-count" id="todo-count">1</span>
                </div>
                
                <div class="task-card" id="task-1">
                    <span class="priority-label high">High Priority</span>
                    <div class="task-title">Complete HRMS Dashboard UI</div>
                    <p class="task-desc">Finish the announcement view and self-task page integration for all user levels.</p>
                    <div class="task-footer">
                        <div class="task-info"><i class="far fa-calendar-alt"></i> 06 Feb 2026</div>
                    </div>
                    <div class="task-actions">
                        <button class="btn-status" onclick="updateTaskStatus('task-1', 'inprogress-col')">Start Work</button>
                    </div>
                </div>
            </div>

            <div class="task-column" id="inprogress-col">
                <div class="column-header">
                    <h3>In Progress</h3>
                    <span class="task-count" id="inprogress-count">1</span>
                </div>
                
                <div class="task-card" id="task-2">
                    <span class="priority-label medium">Medium</span>
                    <div class="task-title">Database Schema Setup</div>
                    <p class="task-desc">Create tables for candidates, roles, and system announcements.</p>
                    <div class="task-footer">
                        <div class="task-info"><i class="far fa-calendar-alt"></i> 07 Feb 2026</div>
                    </div>
                    <div class="task-actions">
                        <button class="btn-status btn-finish" onclick="updateTaskStatus('task-2', 'completed-col')">Mark Finished</button>
                    </div>
                </div>
            </div>

            <div class="task-column" id="completed-col">
                <div class="column-header">
                    <h3>Completed</h3>
                    <span class="task-count" id="completed-count">0</span>
                </div>
            </div>
        </div>
    </div>

    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; font-size: 18px;">Create New Task</h3>
                <span style="cursor:pointer; font-size: 20px;" onclick="closeModal('addTaskModal')">&times;</span>
            </div>
            <form>
                <div class="input-group" style="margin-top:20px;">
                    <label>Task Title</label>
                    <input type="text" placeholder="e.g., Update PHP Mailer" required>
                </div>
                <div style="display: flex; gap: 15px; margin-top:15px;">
                    <div style="flex:1;">
                        <label>Priority</label>
                        <select>
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Due Date</label>
                        <input type="date" required>
                    </div>
                </div>
                <div style="margin-top:15px;">
                    <label>Description</label>
                    <textarea rows="4" placeholder="Task details..."></textarea>
                </div>
                <div style="text-align:right; margin-top:25px;">
                    <button type="button" style="background:#eee; border:none; padding:10px 20px; border-radius:6px; margin-right:10px; cursor:pointer;" onclick="closeModal('addTaskModal')">Cancel</button>
                    <button type="submit" class="btn-add">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function updateTaskStatus(taskId, targetColId) {
            const taskCard = document.getElementById(taskId);
            const targetCol = document.getElementById(targetColId);
            const actionContainer = taskCard.querySelector('.task-actions');

            // Move the card
            targetCol.appendChild(taskCard);

            // Update buttons based on the new column
            if (targetColId === 'inprogress-col') {
                actionContainer.innerHTML = `<button class="btn-status btn-finish" onclick="updateTaskStatus('${taskId}', 'completed-col')">Mark Finished</button>`;
            } else if (targetColId === 'completed-col') {
                actionContainer.innerHTML = `<span style="color:#28c76f; font-size:11px; font-weight:700;"><i class="fas fa-check-circle"></i> Work Finished</span>`;
                taskCard.style.opacity = '0.8';
            }

            // Simple count update
            updateCounts();
        }

        function updateCounts() {
            document.getElementById('todo-count').innerText = document.getElementById('todo-col').querySelectorAll('.task-card').length;
            document.getElementById('inprogress-count').innerText = document.getElementById('inprogress-col').querySelectorAll('.task-card').length;
            document.getElementById('completed-count').innerText = document.getElementById('completed-col').querySelectorAll('.task-card').length;
        }

        window.onclick = function(event) { if (event.target.className === 'modal') { closeModal(event.target.id); } }
    </script>
</body>
</html>