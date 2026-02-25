<?php 
// assign_tasks.php (Pure Interactive UI version)
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Sales Tasks | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --theme-color: #1b5a5a; 
            --bg-body: #f1f5f9; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e2e8f0; 
            --primary-sidebar-width: 95px; 
        }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); }
        .main-content { margin-left: var(--primary-sidebar-width); padding: 30px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; }
        
        /* Header & Button */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .page-header h2 { color: var(--theme-color); margin: 0; font-size: 24px; font-weight: 700; }
        .page-header p { margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted); }
        
        .btn-primary { background: var(--theme-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }

        /* Filter Tabs */
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px;}
        .tab { padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; background: white; border: 1px solid var(--border-color); color: var(--text-muted);}
        .tab.active { background: var(--theme-color); color: white; border-color: var(--theme-color); }
        .tab:hover:not(.active) { background: #f8fafc; }

        /* Task Cards */
        .tasks-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .task-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; position: relative; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; animation: fadeIn 0.4s ease forwards;}
        .task-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-color: #cbd5e1;}
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .task-title { font-size: 16px; font-weight: 700; color: var(--text-main); margin: 0; padding-right: 15px;}
        .task-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; flex-grow: 1;}
        
        .task-meta { display: flex; flex-direction: column; gap: 10px; padding-top: 15px; border-top: 1px dashed var(--border-color); }
        .meta-item { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #475569; font-weight: 600; }
        .meta-icon-group { display: flex; align-items: center; gap: 6px; color: var(--text-muted); font-weight: 500;}
        .meta-icon { color: var(--theme-color); font-size: 16px; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        
        /* Priority Badges */
        .pri-High { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .pri-Medium { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .pri-Low { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        /* Status Badges */
        .stat-Pending { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;}
        .stat-In { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;}
        .stat-Completed { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0;}

        .btn-delete { position: absolute; top: 20px; right: 20px; background: #fee2e2; border: none; color: #ef4444; cursor: pointer; transition: 0.2s; font-size: 16px; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; opacity: 0; }
        .task-card:hover .btn-delete { opacity: 1; }
        .btn-delete:hover { background: #ef4444; color: white; }

        /* MODAL STYLES */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(3px); opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 450px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); transform: translateY(-20px); transition: transform 0.3s ease; position: relative; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: #ef4444; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 13px; outline: none; box-sizing: border-box; background: #fff; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--theme-color); box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1); }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 15px; width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="page-header">
        <div>
            <h2>Task Assignment Hub</h2>
            <p>Assign, filter, and monitor tasks for the Sales Executive team.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">
            <i class="ph-bold ph-plus-circle"></i> Create New Task
        </button>
    </div>

    <div class="filter-tabs" id="tabContainer">
        <div class="tab active" data-filter="All">All Tasks</div>
        <div class="tab" data-filter="Pending">Pending</div>
        <div class="tab" data-filter="In Progress">In Progress</div>
        <div class="tab" data-filter="Completed">Completed</div>
    </div>

    <div class="tasks-container" id="tasksContainer">
        
        <div class="task-card" data-status="Pending">
            <button class="btn-delete" title="Delete Task" onclick="this.closest('.task-card').remove();"><i class="ph-bold ph-trash"></i></button>
            <div class="task-header"><h4 class="task-title">Pitch to Titan Jewels</h4></div>
            <p class="task-desc">Arrange a video call with the purchasing manager at Titan Jewels to pitch our new billing software.</p>
            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-user-circle meta-icon"></i> Sam Executive</div>
                    <span class="badge stat-Pending">Pending</span>
                </div>
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: 28 Feb 2026</div>
                    <span class="badge pri-High">High</span>
                </div>
            </div>
        </div>

        <div class="task-card" data-status="In Progress">
            <button class="btn-delete" title="Delete Task" onclick="this.closest('.task-card').remove();"><i class="ph-bold ph-trash"></i></button>
            <div class="task-header"><h4 class="task-title">Follow-up with Amazon India</h4></div>
            <p class="task-desc">Send the updated quotation document to the Amazon India procurement team. Follow up via phone call.</p>
            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-user-circle meta-icon"></i> Ravi Kumar</div>
                    <span class="badge stat-In">In Progress</span>
                </div>
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: 25 Feb 2026</div>
                    <span class="badge pri-Medium">Medium</span>
                </div>
            </div>
        </div>

        <div class="task-card" data-status="Pending">
            <button class="btn-delete" title="Delete Task" onclick="this.closest('.task-card').remove();"><i class="ph-bold ph-trash"></i></button>
            <div class="task-header"><h4 class="task-title">Prepare Monthly Sales Deck</h4></div>
            <p class="task-desc">Compile the data for the upcoming monthly review meeting. Focus on the conversion rates in the South Zone.</p>
            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-user-circle meta-icon"></i> Priya Sharma</div>
                    <span class="badge stat-Pending">Pending</span>
                </div>
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: 01 Mar 2026</div>
                    <span class="badge pri-Medium">Medium</span>
                </div>
            </div>
        </div>

        <div class="task-card" data-status="Completed" style="opacity: 0.7; background: #f8fafc;">
            <button class="btn-delete" title="Delete Task" onclick="this.closest('.task-card').remove();"><i class="ph-bold ph-trash"></i></button>
            <div class="task-header"><h4 class="task-title" style="text-decoration: line-through; color: #94a3b8;">Onboard Client 'Reliance'</h4></div>
            <p class="task-desc">Complete the paperwork and initial setup for the Reliance account. Ensure they have access to their dashboard.</p>
            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-user-circle meta-icon"></i> Ravi Kumar</div>
                    <span class="badge stat-Completed">Completed</span>
                </div>
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: 20 Feb 2026</div>
                    <span class="badge pri-High">High</span>
                </div>
            </div>
        </div>

    </div>
</main>

<div class="modal-overlay" id="taskModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="closeModal()"></i>
        <h3 style="margin-top: 0; color: var(--theme-color); font-size: 18px; margin-bottom: 20px;"><i class="ph-bold ph-paper-plane-right"></i> Assign New Task</h3>
        
        <form id="createTaskForm" onsubmit="event.preventDefault(); submitDummyTask();">
            <div class="form-group">
                <label>Task Title *</label>
                <input type="text" id="taskTitle" placeholder="E.g., Client Follow-up" required>
            </div>
            
            <div class="form-group">
                <label>Assign To (Executive) *</label>
                <select id="taskAssignee" required>
                    <option value="">-- Select Executive --</option>
                    <option value="Sam Executive">Sam Executive (EMP-SE01)</option>
                    <option value="Ravi Kumar">Ravi Kumar (EMP-SE02)</option>
                    <option value="Priya Sharma">Priya Sharma (EMP-SE03)</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Due Date *</label>
                    <input type="date" id="taskDate" required>
                </div>
                <div class="form-group">
                    <label>Priority Level</label>
                    <select id="taskPriority" required>
                        <option value="High">High Priority</option>
                        <option value="Medium" selected>Medium Priority</option>
                        <option value="Low">Low Priority</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description / Instructions</label>
                <textarea id="taskDesc" rows="3" placeholder="Type detailed instructions..."></textarea>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px;" id="btnSubmitTask">
                Create & Assign
            </button>
        </form>
    </div>
</div>

<script>
    // --- MODAL LOGIC ---
    function openModal() {
        document.getElementById('taskModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('taskModal').classList.remove('active');
        document.getElementById('createTaskForm').reset();
    }

    // Close modal when clicking outside
    document.getElementById('taskModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // --- SUBMIT DUMMY TASK (ADDS CARD TO SCREEN) ---
    function submitDummyTask() {
        const btn = document.getElementById('btnSubmitTask');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Processing...';
        btn.disabled = true;

        setTimeout(() => {
            // Get values
            const title = document.getElementById('taskTitle').value;
            const assignee = document.getElementById('taskAssignee').value;
            const date = document.getElementById('taskDate').value;
            const priority = document.getElementById('taskPriority').value;
            const desc = document.getElementById('taskDesc').value;

            // Generate HTML for new card
            const newCard = `
                <div class="task-card" data-status="Pending">
                    <button class="btn-delete" title="Delete Task" onclick="this.closest('.task-card').remove();"><i class="ph-bold ph-trash"></i></button>
                    <div class="task-header"><h4 class="task-title">${title}</h4></div>
                    <p class="task-desc">${desc || 'No specific description provided.'}</p>
                    <div class="task-meta">
                        <div class="meta-item">
                            <div class="meta-icon-group"><i class="ph-bold ph-user-circle meta-icon"></i> ${assignee}</div>
                            <span class="badge stat-Pending">Pending</span>
                        </div>
                        <div class="meta-item">
                            <div class="meta-icon-group"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: ${date}</div>
                            <span class="badge pri-${priority}">${priority}</span>
                        </div>
                    </div>
                </div>
            `;

            // Add to board
            document.getElementById('tasksContainer').insertAdjacentHTML('afterbegin', newCard);

            // Reset and close
            btn.innerHTML = originalText;
            btn.disabled = false;
            closeModal();

            // Auto-switch to "All" tab to ensure the new task is visible
            document.querySelector('.tab[data-filter="All"]').click();

        }, 600);
    }

    // --- TAB FILTER LOGIC ---
    const tabs = document.querySelectorAll('.tab');
    const cards = document.getElementsByClassName('task-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');

            const filterValue = this.getAttribute('data-filter');

            // Loop through cards and hide/show based on status
            Array.from(cards).forEach(card => {
                if (filterValue === 'All' || card.getAttribute('data-status') === filterValue) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
</script>

</body>
</html>