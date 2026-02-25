<?php 
// assign_tasks.php (Pure Interactive UI version with Targets)
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Sales Tasks & Targets | Workack</title>
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
        
        .header-actions { display: flex; gap: 15px; }
        
        .btn-primary { background: var(--theme-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.2); }
        
        .btn-secondary { background: white; color: var(--theme-color); border: 2px solid var(--theme-color); padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-secondary:hover { background: #f8fafc; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(27, 90, 90, 0.1); }

        /* Target Dashboard */
        .target-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .target-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); transition: transform 0.2s;}
        .target-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .tc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .tc-name { font-size: 15px; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 6px;}
        .tc-month { font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 20px; }
        
        .progress-container { width: 100%; background: #e2e8f0; border-radius: 10px; height: 8px; margin: 10px 0 15px; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 10px; transition: width 1s ease-in-out; }
        .progress-red { background: #ef4444; }
        .progress-orange { background: #f59e0b; }
        .progress-green { background: #10b981; }
        
        .tc-stats { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); font-weight: 600; }
        .tc-stats strong { color: var(--text-main); font-size: 14px; }
        .tc-message { font-size: 12px; margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-color); }
        .tc-message i { font-size: 14px; vertical-align: text-bottom; margin-right: 4px; }

        /* Filter Tabs */
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px;}
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
            .header-actions { width: 100%; display: grid; grid-template-columns: 1fr 1fr; }
            .header-actions button { justify-content: center; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="page-header">
        <div>
            <h2>Sales Dashboard & Tasks</h2>
            <p>Monitor executive targets and assign daily tasks.</p>
        </div>
        <div class="header-actions">
            <button class="btn-secondary" onclick="openTargetModal()">
                <i class="ph-bold ph-target"></i> Set Target
            </button>
            <button class="btn-primary" onclick="openModal()">
                <i class="ph-bold ph-plus-circle"></i> Create Task
            </button>
        </div>
    </div>

    <div class="target-grid">
        
        <div class="target-card">
            <div class="tc-header">
                <h4 class="tc-name"><i class="ph-bold ph-user-circle" style="color: var(--theme-color); font-size: 20px;"></i> Sam Executive</h4>
                <span class="tc-month">Feb 2026</span>
            </div>
            <div class="tc-stats">
                <span>Target: <strong>₹5,00,000</strong></span>
                <span>Achieved: <strong>105%</strong></span>
            </div>
            <div class="progress-container"><div class="progress-bar progress-green" style="width: 100%;"></div></div>
            <div class="tc-stats">
                <span>Revenue: <span style="color: #10b981;">₹5,25,000</span></span>
                <span>Clients Won: <strong style="color: #ef4444;">6 / 10</strong></span>
            </div>
            <div class="tc-message" style="color: #10b981; font-weight: 600;">
                <i class="ph-fill ph-check-circle"></i> Target Achieved! (Revenue hit)
            </div>
        </div>

        <div class="target-card">
            <div class="tc-header">
                <h4 class="tc-name"><i class="ph-bold ph-user-circle" style="color: var(--theme-color); font-size: 20px;"></i> Ravi Kumar</h4>
                <span class="tc-month">Feb 2026</span>
            </div>
            <div class="tc-stats">
                <span>Target: <strong>₹4,00,000</strong></span>
                <span>Achieved: <strong style="color: #ef4444;">45%</strong></span>
            </div>
            <div class="progress-container"><div class="progress-bar progress-red" style="width: 45%;"></div></div>
            <div class="tc-stats">
                <span>Revenue: ₹1,80,000</span>
                <span>Clients Won: <strong>4 / 8</strong></span>
            </div>
            <div class="tc-message" style="color: #ef4444; font-weight: 600;">
                <i class="ph-fill ph-warning-circle"></i> Target missed. Needs improvement.
            </div>
        </div>

        <div class="target-card">
            <div class="tc-header">
                <h4 class="tc-name"><i class="ph-bold ph-user-circle" style="color: var(--theme-color); font-size: 20px;"></i> Priya Sharma</h4>
                <span class="tc-month">Feb 2026</span>
            </div>
            <div class="tc-stats">
                <span>Target: <strong>₹3,50,000</strong></span>
                <span>Achieved: <strong style="color: #f59e0b;">80%</strong></span>
            </div>
            <div class="progress-container"><div class="progress-bar progress-orange" style="width: 80%;"></div></div>
            <div class="tc-stats">
                <span>Revenue: ₹2,80,000</span>
                <span>Clients Won: <strong>6 / 8</strong></span>
            </div>
            <div class="tc-message" style="color: #f59e0b; font-weight: 600;">
                <i class="ph-fill ph-trend-up"></i> On track to meet the target.
            </div>
        </div>
    </div>

    <div class="filter-tabs" id="tabContainer">
        <div class="tab active" data-filter="All">All Tasks (5)</div>
        <div class="tab" data-filter="Pending">Pending (2)</div>
        <div class="tab" data-filter="In Progress">In Progress (2)</div>
        <div class="tab" data-filter="Completed">Completed (1)</div>
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

        <div class="task-card" data-status="In Progress">
            <button class="btn-delete" title="Delete Task" onclick="this.closest('.task-card').remove();"><i class="ph-bold ph-trash"></i></button>
            <div class="task-header"><h4 class="task-title">Cold Calling Campaign</h4></div>
            <p class="task-desc">Call the 50 new leads generated from the recent digital marketing campaign on LinkedIn. Log all responses in the CRM.</p>
            <div class="task-meta">
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-user-circle meta-icon"></i> Sam Executive</div>
                    <span class="badge stat-In">In Progress</span>
                </div>
                <div class="meta-item">
                    <div class="meta-icon-group"><i class="ph-bold ph-calendar-blank meta-icon"></i> Due: 26 Feb 2026</div>
                    <span class="badge pri-Low">Low</span>
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

<div class="modal-overlay" id="targetModal">
    <div class="modal-content">
        <i class="ph-bold ph-x close-modal" onclick="closeTargetModal()"></i>
        <h3 style="margin-top: 0; color: var(--theme-color); font-size: 18px; margin-bottom: 20px;"><i class="ph-bold ph-target"></i> Set Monthly Target</h3>
        
        <form id="setTargetForm" onsubmit="event.preventDefault(); submitTarget();">
            <div class="form-group">
                <label>Select Executive *</label>
                <select required>
                    <option value="">-- Choose Executive --</option>
                    <option value="Sam Executive">Sam Executive (EMP-SE01)</option>
                    <option value="Ravi Kumar">Ravi Kumar (EMP-SE02)</option>
                    <option value="Priya Sharma">Priya Sharma (EMP-SE03)</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Target Month *</label>
                    <input type="month" required value="<?= date('Y-m') ?>">
                </div>
                <div class="form-group">
                    <label>Target Customers</label>
                    <input type="number" placeholder="E.g., 10" required>
                </div>
            </div>

            <div class="form-group">
                <label>Revenue Target (₹) *</label>
                <input type="number" placeholder="E.g., 500000" required>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px;" id="btnSubmitTarget">
                Save Target
            </button>
        </form>
    </div>
</div>

<script>
    // --- TASK MODAL LOGIC ---
    function openModal() { document.getElementById('taskModal').classList.add('active'); }
    function closeModal() { document.getElementById('taskModal').classList.remove('active'); document.getElementById('createTaskForm').reset(); }
    document.getElementById('taskModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

    // --- TARGET MODAL LOGIC ---
    function openTargetModal() { document.getElementById('targetModal').classList.add('active'); }
    function closeTargetModal() { document.getElementById('targetModal').classList.remove('active'); document.getElementById('setTargetForm').reset(); }
    document.getElementById('targetModal').addEventListener('click', function(e) { if (e.target === this) closeTargetModal(); });

    function submitTarget() {
        const btn = document.getElementById('btnSubmitTarget');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Saving...';
        btn.disabled = true;

        setTimeout(() => {
            alert('UI Mode: Monthly Target set successfully!');
            btn.innerHTML = originalText;
            btn.disabled = false;
            closeTargetModal();
        }, 600);
    }

    // --- SUBMIT DUMMY TASK (ADDS CARD TO SCREEN) ---
    function submitDummyTask() {
        const btn = document.getElementById('btnSubmitTask');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Processing...';
        btn.disabled = true;

        setTimeout(() => {
            const title = document.getElementById('taskTitle').value;
            const assignee = document.getElementById('taskAssignee').value;
            const date = document.getElementById('taskDate').value;
            const priority = document.getElementById('taskPriority').value;
            const desc = document.getElementById('taskDesc').value;

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

            document.getElementById('tasksContainer').insertAdjacentHTML('afterbegin', newCard);
            btn.innerHTML = originalText;
            btn.disabled = false;
            closeModal();
            document.querySelector('.tab[data-filter="All"]').click();
        }, 600);
    }

    // --- TAB FILTER LOGIC ---
    const tabs = document.querySelectorAll('.tab');
    const cards = document.getElementsByClassName('task-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const filterValue = this.getAttribute('data-filter');

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